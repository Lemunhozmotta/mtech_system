<?php
/* =========================================================
   M-TECH SYSTEM — PAGAMENTOS (ações)
   Só níveis 1 e 2
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
// REGISTRAR PAGAMENTO
// =========================================================
if ($acao === 'registrar') {

    $forma = $_POST['forma'] ?? '';
    $valor = (float)($_POST['valor'] ?? 0);
    $parcelas = (int)($_POST['parcelas'] ?? 1);
    $data_vencimento = trim($_POST['data_vencimento'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    $formas_validas = ['pix', 'dinheiro', 'credito', 'debito', 'transferencia', 'boleto'];

    if (!in_array($forma, $formas_validas) || $valor <= 0) {
        $conn->close();
        header('Location: pagamentos_ver.php?id=' . $id_orcamento . '&msg=erro_obrig');
        exit;
    }

    // Busca orçamento
    $stmt = $conn->prepare("SELECT id_os, status FROM os_orcamentos WHERE id_orcamento = ? LIMIT 1");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $o = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$o) {
        $conn->close();
        header('Location: pagamentos.php?msg=erro');
        exit;
    }

    // Verifica se já tem pagamento confirmado
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM os_orcamento_pagamentos
                            WHERE id_orcamento = ? AND status = 'confirmado'");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $ja_pago = (int)$stmt->get_result()->fetch_assoc()['total'] > 0;
    $stmt->close();

    if ($ja_pago) {
        $conn->close();
        header('Location: pagamentos_ver.php?id=' . $id_orcamento . '&msg=erro');
        exit;
    }

    // Define status do pagamento
    // Boleto: fica pendente até compensar (mas admin pode confirmar na hora se quiser)
    $status_pag = 'confirmado';
    if ($forma === 'boleto' && $data_vencimento !== '') {
        // Se o vencimento tá no futuro, marca como pendente
        $hoje = date('Y-m-d');
        if ($data_vencimento > $hoje) {
            $status_pag = 'pendente';
        }
    }

    // Grava observações extras (parcelas, vencimento)
    $obs_extra = [];
    if ($parcelas > 1) $obs_extra[] = "Parcelado em {$parcelas}x";
    if ($data_vencimento !== '') $obs_extra[] = "Vencimento: " . date('d/m/Y', strtotime($data_vencimento));
    if ($observacoes !== '') $obs_extra[] = $observacoes;

    $obs_final = implode(' | ', $obs_extra);
    $obs_val = $obs_final !== '' ? $obs_final : null;

    // Insere pagamento
    $stmt = $conn->prepare("
        INSERT INTO os_orcamento_pagamentos
        (id_orcamento, forma, valor, parcelas, status, data_pagamento, observacoes, id_usuario_registrou, origem)
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, 'manual')
    ");
    $stmt->bind_param('isdissi', $id_orcamento, $forma, $valor, $parcelas, $status_pag, $obs_val, $idUsuario);
    $stmt->bind_param('isdissi', $id_orcamento, $forma, $valor, $parcelas, $status_pag, $obs_val, $idUsuario);
    $stmt->execute();
    $stmt->close();

    // Se pagamento confirmado, aprova o orçamento e libera a OS
    if ($status_pag === 'confirmado') {
        $stmt = $conn->prepare("UPDATE os_orcamentos SET status = 'aprovado' WHERE id_orcamento = ?");
        $stmt->bind_param('i', $id_orcamento);
        $stmt->execute();
        $stmt->close();

        // OS vai pra aguardando_peca
        $id_os = (int)$o['id_os'];
        $conn->query("UPDATE ordens_servico SET status = 'aguardando_peca' WHERE id_os = {$id_os} AND status NOT IN ('concluida','cancelada')");
    }

    $conn->close();
    header('Location: pagamentos.php?msg=pago');
    exit;
}

$conn->close();
header('Location: pagamentos.php');
exit;