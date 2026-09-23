<?php
/* =========================================================
   M-TECH SYSTEM — ORÇAMENTOS (ações)
   Só ações de edição e envio. Pagamento é em pagamentos_acao.php
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
$nivel = (int)$usuarioLogado['nivel'];
$idUsuario = (int)$usuarioLogado['id_usuario'];

if (!in_array($nivel, [1, 2])) {
    header('Location: dashboard.php?erro=sem_permissao');
    exit;
}

$acao = $_POST['acao'] ?? '';
$id_orcamento = (int)($_POST['id_orcamento'] ?? 0);

$conn = conectar();

// =========================================================
// HELPER: recalcular valores
// =========================================================
function recalcularOrcamento($conn, $id_orcamento)
{
    $stmt = $conn->prepare("SELECT valor_mao_obra, valor_desconto FROM os_orcamentos WHERE id_orcamento = ? LIMIT 1");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $o = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$o) return;

    $mao_obra = (float)$o['valor_mao_obra'];
    $desconto = (float)$o['valor_desconto'];

    // Soma itens (peças + serviços que foram adicionados como item)
    $stmt = $conn->prepare("SELECT COALESCE(SUM(valor_total), 0) AS total FROM os_orcamento_itens
                            WHERE id_orcamento = ? AND tipo = 'peca'");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $pecas = (float)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(valor_total), 0) AS total FROM os_orcamento_itens
                            WHERE id_orcamento = ? AND tipo = 'servico'");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $servicos = (float)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $mao_obra_total = $mao_obra + $servicos;
    $total = ($pecas + $mao_obra_total) - $desconto;
    if ($total < 0) $total = 0;

    $stmt = $conn->prepare("
        UPDATE os_orcamentos
        SET valor_pecas = ?,
            valor_mao_obra = ?,
            valor_total = ?
        WHERE id_orcamento = ?
    ");
    $stmt->bind_param('dddi', $pecas, $mao_obra_total, $total, $id_orcamento);
    $stmt->execute();
    $stmt->close();
}

// =========================================================
// SALVAR ITENS (edição inline + mão de obra + desconto + obs)
// =========================================================
if ($acao === 'salvar_itens') {

    // 1) Atualiza mão de obra, desconto, obs, validade
    $valor_mao_obra = (float)($_POST['valor_mao_obra'] ?? 0);
    $valor_desconto = (float)($_POST['valor_desconto'] ?? 0);
    $validade_dias = (int)($_POST['validade_dias'] ?? 7);
    $observacoes = trim($_POST['observacoes'] ?? '');

    if ($validade_dias < 1) $validade_dias = 7;

    $obs_val = $observacoes !== '' ? $observacoes : null;

    $stmt = $conn->prepare("
        UPDATE os_orcamentos
        SET valor_mao_obra = ?,
            valor_desconto = ?,
            validade_dias = ?,
            observacoes = ?
        WHERE id_orcamento = ?
    ");
    $stmt->bind_param('ddisi', $valor_mao_obra, $valor_desconto, $validade_dias, $obs_val, $id_orcamento);
    $stmt->execute();
    $stmt->close();

    // 2) Atualiza cada item
    $itens = $_POST['itens'] ?? [];
    if (is_array($itens)) {
        $stmt = $conn->prepare("
            UPDATE os_orcamento_itens
            SET descricao = ?, quantidade = ?, valor_unitario = ?, valor_total = ?
            WHERE id_item = ? AND id_orcamento = ?
        ");

        foreach ($itens as $id_item => $dados) {
            $id_item = (int)$id_item;
            $descricao = trim($dados['descricao'] ?? '');
            $qtd = (float)($dados['quantidade'] ?? 0);
            $valor_unit = (float)($dados['valor_unitario'] ?? 0);

            if ($id_item <= 0 || $descricao === '' || $qtd <= 0) continue;

            $valor_total = $qtd * $valor_unit;

            $stmt->bind_param('siddii', $descricao, $qtd, $valor_unit, $valor_total, $id_item, $id_orcamento);
            $stmt->execute();
        }

        $stmt->close();
    }

    // 3) Recalcula
    recalcularOrcamento($conn, $id_orcamento);

    $conn->close();
    header('Location: orcamentos_ver.php?id=' . $id_orcamento . '&msg=editado');
    exit;
}

// =========================================================
// ADICIONAR ITEM: PEÇA (via AJAX)
// =========================================================
if ($acao === 'item_add_peca') {

    $descricao = trim($_POST['descricao'] ?? '');
    $quantidade = (float)($_POST['quantidade'] ?? 1);
    $valor_unitario = (float)($_POST['valor_unitario'] ?? 0);
    $id_estoque = (int)($_POST['id_estoque'] ?? 0);

    if ($descricao === '' || $quantidade <= 0) {
        http_response_code(400);
        echo 'erro';
        exit;
    }

    $valor_total = $quantidade * $valor_unitario;
    $id_estoque_val = $id_estoque > 0 ? $id_estoque : null;

    $stmt = $conn->prepare("
        INSERT INTO os_orcamento_itens
        (id_orcamento, tipo, descricao, id_estoque, quantidade, valor_unitario, valor_total)
        VALUES (?, 'peca', ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('isiddd', $id_orcamento, $descricao, $id_estoque_val, $quantidade, $valor_unitario, $valor_total);
    $stmt->execute();
    $stmt->close();

    recalcularOrcamento($conn, $id_orcamento);

    $conn->close();
    echo 'ok';
    exit;
}

// =========================================================
// ADICIONAR ITEM: SERVIÇO (via AJAX)
// =========================================================
if ($acao === 'item_add_servico') {

    $descricao = trim($_POST['descricao'] ?? '');
    $quantidade = (float)($_POST['quantidade'] ?? 1);
    $valor_unitario = (float)($_POST['valor_unitario'] ?? 0);
    $id_servico = (int)($_POST['id_servico'] ?? 0);

    if ($descricao === '' || $quantidade <= 0) {
        http_response_code(400);
        echo 'erro';
        exit;
    }

    $valor_total = $quantidade * $valor_unitario;
    $id_servico_val = $id_servico > 0 ? $id_servico : null;

    $stmt = $conn->prepare("
        INSERT INTO os_orcamento_itens
        (id_orcamento, tipo, descricao, id_servico, quantidade, valor_unitario, valor_total)
        VALUES (?, 'servico', ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('isiddd', $id_orcamento, $descricao, $id_servico_val, $quantidade, $valor_unitario, $valor_total);
    $stmt->execute();
    $stmt->close();

    recalcularOrcamento($conn, $id_orcamento);

    $conn->close();
    echo 'ok';
    exit;
}

// =========================================================
// EXCLUIR ITEM
// =========================================================
if ($acao === 'item_excluir') {

    $id_item = (int)($_POST['id_item'] ?? 0);

    if ($id_item <= 0) {
        $conn->close();
        header('Location: orcamentos_ver.php?id=' . $id_orcamento . '&msg=erro');
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM os_orcamento_itens WHERE id_item = ? AND id_orcamento = ?");
    $stmt->bind_param('ii', $id_item, $id_orcamento);
    $stmt->execute();
    $stmt->close();

    recalcularOrcamento($conn, $id_orcamento);

    $conn->close();
    header('Location: orcamentos_ver.php?id=' . $id_orcamento . '&msg=item_del');
    exit;
}

// =========================================================
// ENVIAR AO CLIENTE (gera novo token sempre)
// =========================================================
if ($acao === 'enviar') {

    // Busca orçamento
    $stmt = $conn->prepare("SELECT id_os, status FROM os_orcamentos WHERE id_orcamento = ? LIMIT 1");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $o = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$o) {
        $conn->close();
        header('Location: orcamentos_ver.php?id=' . $id_orcamento . '&msg=erro');
        exit;
    }

    // Gera SEMPRE um novo token (link antigo morre)
    $token = bin2hex(random_bytes(32));

    // Se já tinha sido enviado antes, incrementa versão
    $nova_versao = 1;
    if ($o['status'] === 'enviado') {
        $stmt = $conn->prepare("SELECT versao FROM os_orcamentos WHERE id_orcamento = ? LIMIT 1");
        $stmt->bind_param('i', $id_orcamento);
        $stmt->execute();
        $v = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $nova_versao = (int)$v['versao'] + 1;
    }

    $stmt = $conn->prepare("
        UPDATE os_orcamentos
        SET status = 'enviado',
            token_publico = ?,
            versao = ?,
            data_envio = NOW(),
            id_usuario_enviou = ?
        WHERE id_orcamento = ?
    ");
    $stmt->bind_param('siii', $token, $nova_versao, $idUsuario, $id_orcamento);
    $stmt->execute();
    $stmt->close();

    // Atualiza status da OS
    $conn->query("UPDATE ordens_servico SET status = 'orcamento_enviado' WHERE id_os = " . (int)$o['id_os'] . " AND status IN ('aguardando_aprovacao','em_andamento','orcamento_enviado')");

    $conn->close();
    header('Location: orcamentos_ver.php?id=' . $id_orcamento . '&msg=enviado');
    exit;
}

$conn->close();
header('Location: orcamentos.php');
exit;
