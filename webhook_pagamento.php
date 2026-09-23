<?php
/* =========================================================
   M-TECH SYSTEM — WEBHOOK DE PAGAMENTO
   Recebe confirmação de pagamento (simulado ou gateway real)

   Hoje: chamado pelo simulador_banco.php
   Futuro: chamado pelo Mercado Pago / Asaas / etc
   ========================================================= */

require_once 'sistema/conexao.php';

header('Content-Type: application/json; charset=utf-8');

// ===== VALIDAÇÃO BÁSICA =====
$senha = $_POST['senha'] ?? $_GET['senha'] ?? '';
if ($senha !== 'mtech-simulador-2026') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'erro' => 'Senha inválida']);
    exit;
}

// ===== RECEBE OS DADOS =====
$token = trim($_POST['token'] ?? $_GET['token'] ?? '');
$status = trim($_POST['status'] ?? $_GET['status'] ?? '');
$forma = trim($_POST['forma'] ?? $_GET['forma'] ?? 'pix');
$valor = (float)($_POST['valor'] ?? $_GET['valor'] ?? 0);
$transacao_id = trim($_POST['transacao_id'] ?? $_GET['transacao_id'] ?? '');

if ($token === '' || !in_array($status, ['aprovado', 'recusado'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Dados incompletos']);
    exit;
}

$conn = conectar();

// ===== BUSCA O ORÇAMENTO PELO TOKEN =====
$stmt = $conn->prepare("
    SELECT id_orcamento, id_os, status, valor_total
    FROM os_orcamentos
    WHERE token_publico = ?
    LIMIT 1
");
$stmt->bind_param('s', $token);
$stmt->execute();
$o = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$o) {
    $conn->close();
    http_response_code(404);
    echo json_encode(['ok' => false, 'erro' => 'Orçamento não encontrado']);
    exit;
}

// ===== VERIFICA SE JÁ NÃO FOI PAGO =====
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total FROM os_orcamento_pagamentos
    WHERE id_orcamento = ? AND status = 'confirmado'
");
$stmt->bind_param('i', $o['id_orcamento']);
$stmt->execute();
$ja_pago = (int)$stmt->get_result()->fetch_assoc()['total'] > 0;
$stmt->close();

if ($ja_pago) {
    $conn->close();
    echo json_encode(['ok' => true, 'msg' => 'Já estava confirmado']);
    exit;
}

// Se valor veio 0, usa o total do orçamento
if ($valor <= 0) {
    $valor = (float)$o['valor_total'];
}

// =========================================================
// APROVADO
// =========================================================
if ($status === 'aprovado') {

    // Registra o pagamento
    $obs = 'Pagamento via ' . $forma . ($transacao_id !== '' ? ' — Transação: ' . $transacao_id : '');
    $stmt = $conn->prepare("
        INSERT INTO os_orcamento_pagamentos
        (id_orcamento, forma, valor, parcelas, status, data_pagamento, observacoes, id_usuario_registrou)
        VALUES (?, ?, ?, 1, 'confirmado', NOW(), ?, 1)
    ");
    $stmt->bind_param('isds', $o['id_orcamento'], $forma, $valor, $obs);
    $stmt->execute();
    $stmt->close();

    // Aprova o orçamento
    $stmt = $conn->prepare("UPDATE os_orcamentos SET status = 'aprovado' WHERE id_orcamento = ?");
    $stmt->bind_param('i', $o['id_orcamento']);
    $stmt->execute();
    $stmt->close();

    // OS vai pra aguardando_peca
    $id_os = (int)$o['id_os'];
    $conn->query("UPDATE ordens_servico SET status = 'aguardando_peca'
                  WHERE id_os = {$id_os} AND status NOT IN ('concluida','cancelada')");

    $conn->close();
    echo json_encode([
        'ok' => true,
        'msg' => 'Pagamento aprovado e orçamento liberado',
        'id_orcamento' => $o['id_orcamento'],
        'id_os' => $id_os
    ]);
    exit;
}

// =========================================================
// RECUSADO
// =========================================================
if ($status === 'recusado') {

    // Volta o orçamento pra revisão
    $stmt = $conn->prepare("UPDATE os_orcamentos SET status = 'aguardando_revisao' WHERE id_orcamento = ?");
    $stmt->bind_param('i', $o['id_orcamento']);
    $stmt->execute();
    $stmt->close();

    // OS volta pra aguardando_aprovacao
    $id_os = (int)$o['id_os'];
    $conn->query("UPDATE ordens_servico SET status = 'aguardando_aprovacao'
                  WHERE id_os = {$id_os} AND status NOT IN ('concluida','cancelada')");

    $conn->close();
    echo json_encode([
        'ok' => true,
        'msg' => 'Pagamento recusado. Orçamento voltou pra revisão.',
        'id_orcamento' => $o['id_orcamento'],
        'id_os' => $id_os
    ]);
    exit;
}

$conn->close();
http_response_code(400);
echo json_encode(['ok' => false, 'erro' => 'Status inválido']);
