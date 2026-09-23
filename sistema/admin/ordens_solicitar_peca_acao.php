<?php
/* =========================================================
   M-TECH SYSTEM — SOLICITAR PEÇAS (carrinho)
   - Só o mecânico apontado pode solicitar
   - Recebe ARRAY de peças
   - NÃO desaponta, NÃO muda status
   - Quem desaponta é o próprio mecânico depois
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

if ($nivel !== 3) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
    exit;
}

if (!estaApontadoNaOS($conn, $id_os, $idUsuario)) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=nao_apontado');
    exit;
}

// Busca OS
$stmt = $conn->prepare("SELECT numero_os, status FROM ordens_servico WHERE id_os = ? LIMIT 1");
$stmt->bind_param('i', $id_os);
$stmt->execute();
$os = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$os || in_array($os['status'], ['concluida', 'cancelada'])) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
    exit;
}

// ===== INSERE AS PEÇAS =====
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

// ===== CRIA ORÇAMENTO AUTOMÁTICO =====
if ($total_inseridas > 0) {

    // Verifica se já existe orçamento pra essa OS
    $stmt = $conn->prepare("SELECT id_orcamento FROM os_orcamentos WHERE id_os = ? LIMIT 1");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $orc_existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$orc_existente) {
        // Cria novo orçamento
        $numero_orcamento = $os['numero_os']; // mesmo número da OS

        $stmt = $conn->prepare("
            INSERT INTO os_orcamentos
            (id_os, numero_orcamento, status, id_usuario_criou)
            VALUES (?, ?, 'aguardando_revisao', ?)
        ");
        $stmt->bind_param('isi', $id_os, $numero_orcamento, $idUsuario);
        $stmt->execute();
        $id_orcamento = $conn->insert_id;
        $stmt->close();
    } else {
        $id_orcamento = (int)$orc_existente['id_orcamento'];
    }

    // Adiciona as peças como itens do orçamento
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

        // Valor sugerido: se tem no estoque, pega o valor_venda
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

    // Atualiza valores do orçamento
    $stmt = $conn->prepare("
        UPDATE os_orcamentos
        SET valor_pecas = (
                SELECT COALESCE(SUM(valor_total), 0)
                FROM os_orcamento_itens
                WHERE id_orcamento = ? AND tipo = 'peca'
            ),
            valor_total = (
                SELECT COALESCE(SUM(valor_total), 0)
                FROM os_orcamento_itens
                WHERE id_orcamento = ?
            )
        WHERE id_orcamento = ?
    ");
    $stmt->bind_param('iii', $id_orcamento, $id_orcamento, $id_orcamento);
    $stmt->execute();
    $stmt->close();

    // Atualiza status da OS pra aguardando_aprovacao
    $conn->query("UPDATE ordens_servico SET status = 'aguardando_aprovacao' WHERE id_os = {$id_os}");
}

// Limpa o carrinho da sessão
$_SESSION['estoque_carrinho'] = [];

$conn->close();

if ($total_inseridas > 0) {
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=peca_solicitada');
} else {
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
}
exit;
