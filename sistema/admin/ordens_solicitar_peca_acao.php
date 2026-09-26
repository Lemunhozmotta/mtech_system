<?php
/* =========================================================
   M-TECH SYSTEM — SOLICITAR PEÇAS (carrinho)
   Fluxo novo:
   - Só mecânico apontado pode solicitar
   - Valor vem do estoque quando id_estoque é informado
   - Se OS já tem orçamento → gera ADENDO
   - Se não tem → cria orçamento
   - NÃO muda status da OS (o mecânico desaponta quando quiser)
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
$id_os = (int)($_POST['id_os'] ?? 0);
$pecas = $_POST['pecas'] ?? [];
$conn = conectar();

if ($id_os <= 0 || empty($pecas) || !is_array($pecas)) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
    exit;
}

$nivel = (int)$usuarioLogado['nivel'];
$idUsuario = (int)$usuarioLogado['id_usuario'];

// ===== SÓ MECÂNICO =====
if ($nivel !== 3) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
    exit;
}

// ===== PRECISA ESTAR APONTADO =====
if (!estaApontadoNaOS($conn, $id_os, $idUsuario)) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=nao_apontado');
    exit;
}

// ===== BUSCA A OS =====
$stmt = $conn->prepare("SELECT numero_os, status FROM ordens_servico WHERE id_os = ? LIMIT 1");
$stmt->bind_param('i', $id_os);
$stmt->execute();
$os = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$os || in_array($os['status'], ['concluida', 'cancelada', 'pronta', 'aguardando_retirada'])) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
    exit;
}

// =========================================================
// INSERE AS PEÇAS
// =========================================================
$stmt = $conn->prepare("
    INSERT INTO os_solicitacoes_peca
    (id_os, id_usuario_solicitou, nome_peca, quantidade, observacoes, id_estoque)
    VALUES (?, ?, ?, ?, ?, ?)
");

$total_inseridas = 0;
$ids_solicitacoes = [];

foreach ($pecas as $p) {
    $nome = trim($p['nome'] ?? '');
    $qtd = (float)($p['qtd'] ?? 0);
    $obs = trim($p['obs'] ?? '');
    $id_estoque = (int)($p['id_estoque'] ?? 0);

    if ($nome === '' || $qtd <= 0) continue;

    $obs_val = $obs !== '' ? $obs : null;
    $id_estoque_val = $id_estoque > 0 ? $id_estoque : null;

    $stmt->bind_param('iisdsi', $id_os, $idUsuario, $nome, $qtd, $obs_val, $id_estoque_val);

    if ($stmt->execute()) {
        $total_inseridas++;
        $ids_solicitacoes[] = $conn->insert_id;
    }
}
$stmt->close();

if ($total_inseridas === 0) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
    exit;
}

// =========================================================
// VERIFICA SE JÁ EXISTE ORÇAMENTO NESSA OS
// =========================================================
$stmt = $conn->prepare("SELECT id_orcamento, status, adendo_status FROM os_orcamentos
                        WHERE id_os = ?
                          AND status NOT IN ('arquivado', 'cancelado')
                        ORDER BY id_orcamento DESC LIMIT 1");
$stmt->bind_param('i', $id_os);
$stmt->execute();
$orc_existente = $stmt->get_result()->fetch_assoc();
$stmt->close();

$eh_adendo = false;
$id_orcamento = 0;

if ($orc_existente) {
    // ===== JÁ EXISTE → ADENDO =====
    $eh_adendo = true;
    $id_orcamento = (int)$orc_existente['id_orcamento'];

    $stmt = $conn->prepare("UPDATE os_orcamentos SET adendo_status = 'enviado' WHERE id_orcamento = ?");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $stmt->close();
} else {
    // ===== NÃO EXISTE → CRIA ORÇAMENTO =====
    $numero_orcamento = $os['numero_os'];

    $stmt = $conn->prepare("
        INSERT INTO os_orcamentos
        (id_os, numero_orcamento, status, adendo_status, id_usuario_criou)
        VALUES (?, ?, 'aguardando_revisao', 'nenhum', ?)
    ");
    $stmt->bind_param('isi', $id_os, $numero_orcamento, $idUsuario);
    $stmt->execute();
    $id_orcamento = $conn->insert_id;
    $stmt->close();
}

// =========================================================
// ADICIONA AS PEÇAS COMO ITENS DO ORÇAMENTO
// =========================================================
foreach ($ids_solicitacoes as $id_solic) {

    $stmt = $conn->prepare("
        SELECT nome_peca, quantidade, id_estoque FROM os_solicitacoes_peca
        WHERE id_solicitacao = ? LIMIT 1
    ");
    $stmt->bind_param('i', $id_solic);
    $stmt->execute();
    $sp = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sp) continue;

    // ===== VALOR SUGERIDO =====
    $valor_unitario = 0;
    if ($sp['id_estoque']) {
        $stmt = $conn->prepare("SELECT valor_venda FROM estoque WHERE id_estoque = ? LIMIT 1");
        $stmt->bind_param('i', $sp['id_estoque']);
        $stmt->execute();
        $est = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($est) $valor_unitario = (float)$est['valor_venda'];
    }

    $qtd = (float)$sp['quantidade'];
    $valor_total = $qtd * $valor_unitario;

    $stmt = $conn->prepare("
        INSERT INTO os_orcamento_itens
        (id_orcamento, tipo, descricao, id_estoque, id_solicitacao_peca, quantidade, valor_unitario, valor_total)
        VALUES (?, 'peca', ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('isiiidd', $id_orcamento, $sp['nome_peca'], $sp['id_estoque'], $id_solic, $qtd, $valor_unitario, $valor_total);
    $stmt->execute();
    $stmt->close();
}

// =========================================================
// RECALCULA ORÇAMENTO
// =========================================================
recalcularOrcamento($conn, $id_orcamento);

// =========================================================
// NÃO MUDA STATUS DA OS
// (o mecânico desaponta quando quiser, e aí sim muda)
// =========================================================

// =========================================================
// LIMPA O CARRINHO DA SESSÃO
// =========================================================
$_SESSION['estoque_carrinho'] = [];

$conn->close();

if ($eh_adendo) {
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=adendo_gerado');
} else {
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=peca_solicitada');
}
exit;
