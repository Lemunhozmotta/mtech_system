<?php
/* =========================================================
   M-TECH SYSTEM — ORÇAMENTOS (ações)
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
$id_os = (int)($_POST['id_os'] ?? 0);

$conn = conectar();

function pegarOuCriarOrcamento($conn, $id_os, $idUsuario)
{
    $stmt = $conn->prepare("
        SELECT id_orcamento FROM os_orcamentos
        WHERE id_os = ? AND status NOT IN ('arquivado', 'cancelado')
        ORDER BY id_orcamento DESC LIMIT 1
    ");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $orc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($orc) return (int)$orc['id_orcamento'];

    $numero_orcamento = '';
    $stmt = $conn->prepare("SELECT numero_os FROM ordens_servico WHERE id_os = ? LIMIT 1");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) $numero_orcamento = $row['numero_os'];

    $stmt = $conn->prepare("
        INSERT INTO os_orcamentos
        (id_os, numero_orcamento, status, adendo_status, id_usuario_criou)
        VALUES (?, ?, 'gerado_enviado', 'nenhum', ?)
    ");
    $stmt->bind_param('isi', $id_os, $numero_orcamento, $idUsuario);
    $stmt->execute();
    $id = $conn->insert_id;
    $stmt->close();
    return $id;
}

// ===== SALVAR ITENS =====
if ($acao === 'salvar_itens') {

    $valor_mao_obra = (float)($_POST['valor_mao_obra'] ?? 0);
    $valor_desconto = (float)($_POST['valor_desconto'] ?? 0);
    $validade_dias  = (int)($_POST['validade_dias'] ?? 7);
    $observacoes    = trim($_POST['observacoes'] ?? '');

    if ($validade_dias < 1) $validade_dias = 7;
    $obs_val = $observacoes !== '' ? $observacoes : null;

    if ($id_orcamento > 0) {
        $stmt = $conn->prepare("
            UPDATE os_orcamentos
            SET valor_mao_obra = ?, valor_desconto = ?, validade_dias = ?, observacoes = ?
            WHERE id_orcamento = ?
        ");
        $stmt->bind_param('ddisi', $valor_mao_obra, $valor_desconto, $validade_dias, $obs_val, $id_orcamento);
        $stmt->execute();
        $stmt->close();
    } else if ($id_os > 0) {
        $id_orcamento = pegarOuCriarOrcamento($conn, $id_os, $idUsuario);
        $stmt = $conn->prepare("
            UPDATE os_orcamentos
            SET valor_mao_obra = ?, valor_desconto = ?, validade_dias = ?, observacoes = ?
            WHERE id_orcamento = ?
        ");
        $stmt->bind_param('ddisi', $valor_mao_obra, $valor_desconto, $validade_dias, $obs_val, $id_orcamento);
        $stmt->execute();
        $stmt->close();
    }

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

    recalcularOrcamento($conn, $id_orcamento);

    $conn->close();
    header('Location: orcamentos_ver.php?id_orcamento=' . $id_orcamento . '&msg=editado');
    exit;
}

// ===== ADICIONAR PEÇA =====
if ($acao === 'item_add_peca') {
    $descricao = trim($_POST['descricao'] ?? '');
    $quantidade = (float)($_POST['quantidade'] ?? 1);
    $valor_unitario = (float)($_POST['valor_unitario'] ?? 0);
    $id_estoque = (int)($_POST['id_estoque'] ?? 0);

    if ($descricao === '' || $quantidade <= 0 || $id_orcamento <= 0) {
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

// ===== ADICIONAR SERVIÇO =====
if ($acao === 'item_add_servico') {
    $descricao = trim($_POST['descricao'] ?? '');
    $quantidade = (float)($_POST['quantidade'] ?? 1);
    $valor_unitario = (float)($_POST['valor_unitario'] ?? 0);
    $id_servico = (int)($_POST['id_servico'] ?? 0);

    if ($descricao === '' || $quantidade <= 0 || $id_orcamento <= 0) {
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

// ===== EXCLUIR ITEM =====
if ($acao === 'item_excluir') {
    $id_item = (int)($_POST['id_item'] ?? 0);

    if ($id_item <= 0 || $id_orcamento <= 0) {
        $conn->close();
        header('Location: orcamentos_ver.php?id_orcamento=' . $id_orcamento . '&msg=erro');
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM os_orcamento_itens WHERE id_item = ? AND id_orcamento = ?");
    $stmt->bind_param('ii', $id_item, $id_orcamento);
    $stmt->execute();
    $stmt->close();

    recalcularOrcamento($conn, $id_orcamento);
    $conn->close();
    header('Location: orcamentos_ver.php?id_orcamento=' . $id_orcamento . '&msg=item_del');
    exit;
}

// ===== ENVIAR =====
if ($acao === 'enviar') {

    if ($id_orcamento <= 0 && $id_os > 0) {
        $id_orcamento = pegarOuCriarOrcamento($conn, $id_os, $idUsuario);
    }

    if ($id_orcamento <= 0) {
        $conn->close();
        header('Location: orcamentos.php?msg=erro');
        exit;
    }

    $stmt = $conn->prepare("SELECT id_os, status, adendo_status FROM os_orcamentos WHERE id_orcamento = ? LIMIT 1");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $o = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$o) {
        $conn->close();
        header('Location: orcamentos.php?msg=erro');
        exit;
    }

    $id_os_real = (int)$o['id_os'];
    $token = bin2hex(random_bytes(32));

    $status_original = $o['status'];

    if (in_array($status_original, ['gerado_enviado', 'aprovado', 'aguardando_pagamento', 'pagamento_efetuado'])) {
        // É adendo
        $stmt = $conn->prepare("
            UPDATE os_orcamentos
            SET adendo_status = 'enviado', token_publico = ?, id_usuario_enviou = ?, data_envio = NOW()
            WHERE id_orcamento = ?
        ");
        $stmt->bind_param('sii', $token, $idUsuario, $id_orcamento);
        $stmt->execute();
        $stmt->close();
    } else {
        // Orçamento principal
        $stmt = $conn->prepare("
            UPDATE os_orcamentos
            SET status = 'gerado_enviado', token_publico = ?, id_usuario_enviou = ?, data_envio = NOW()
            WHERE id_orcamento = ?
        ");
        $stmt->bind_param('sii', $token, $idUsuario, $id_orcamento);
        $stmt->execute();
        $stmt->close();

        $conn->query("UPDATE ordens_servico SET status = 'aguardando_aprovacao' WHERE id_os = {$id_os_real} AND status IN ('aberta', 'em_andamento', 'em_execucao')");
    }

    $conn->close();
    header('Location: orcamentos_ver.php?id_orcamento=' . $id_orcamento . '&msg=enviado');
    exit;
}

// ===== APROVAR MANUAL =====
if ($acao === 'aprovar_manual') {
    if ($id_orcamento <= 0) {
        $conn->close();
        header('Location: orcamentos.php?msg=erro');
        exit;
    }

    $stmt = $conn->prepare("SELECT id_os FROM os_orcamentos WHERE id_orcamento = ? LIMIT 1");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $o = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$o) {
        $conn->close();
        header('Location: orcamentos.php?msg=erro');
        exit;
    }

    $id_os_real = (int)$o['id_os'];

    $stmt = $conn->prepare("UPDATE os_orcamentos SET status = 'aprovado' WHERE id_orcamento = ?");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $stmt->close();

    $conn->query("UPDATE ordens_servico SET status = 'aguardando_peca' WHERE id_os = {$id_os_real} AND status IN ('aguardando_aprovacao', 'em_andamento', 'em_execucao')");

    $conn->close();
    header('Location: orcamentos_ver.php?id_orcamento=' . $id_orcamento . '&msg=aprovado_manual');
    exit;
}

// ===== APROVAR ADENDO =====
if ($acao === 'aprovar_adendo') {
    if ($id_orcamento <= 0) {
        $conn->close();
        header('Location: orcamentos.php?msg=erro');
        exit;
    }

    $stmt = $conn->prepare("UPDATE os_orcamentos SET adendo_status = 'aprovado' WHERE id_orcamento = ?");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $stmt->close();

    recalcularOrcamento($conn, $id_orcamento);

    $conn->close();
    header('Location: orcamentos_ver.php?id_orcamento=' . $id_orcamento . '&msg=adendo_aprovado');
    exit;
}

// ===== REPROVAR ADENDO =====
if ($acao === 'reprovar_adendo') {
    if ($id_orcamento <= 0) {
        $conn->close();
        header('Location: orcamentos.php?msg=erro');
        exit;
    }

    $stmt = $conn->prepare("UPDATE os_orcamentos SET adendo_status = 'reprovado' WHERE id_orcamento = ?");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $stmt->close();

    $conn->close();
    header('Location: orcamentos_ver.php?id_orcamento=' . $id_orcamento . '&msg=adendo_reprovado');
    exit;
}

$conn->close();
header('Location: orcamentos.php');
exit;
