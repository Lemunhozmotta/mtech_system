<?php
/* =========================================================
   M-TECH SYSTEM — ORDENS DE SERVIÇO (mudar status)
   Ações de transição do fluxo novo:
   - iniciar_separacao  → aprovado → aguardando_peca
   - pecas_chegaram     → aguardando_peca → em_execucao
   - marcar_pronta      → em_execucao → pronta (ou aguardando_pagamento/retirada)
   - aguardando_retirada → pronta → aguardando_retirada
   - cliente_retirou    → aguardando_retirada → concluida
   - concluir           → em_execucao → concluida (atalho legado)
   - cancelar           → qualquer (antes de pronta) → cancelada
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
$acao = $_POST['acao'] ?? '';
$id_os = (int)($_POST['id_os'] ?? 0);
$conn = conectar();

if ($id_os <= 0) {
    $conn->close();
    header('Location: ordens.php?msg=erro');
    exit;
}

$nivel = (int)$usuarioLogado['nivel'];
$idUsuario = (int)$usuarioLogado['id_usuario'];

// ===== BUSCA A OS =====
$stmt = $conn->prepare("SELECT status, numero_os FROM ordens_servico WHERE id_os = ? LIMIT 1");
$stmt->bind_param('i', $id_os);
$stmt->execute();
$os = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$os) {
    $conn->close();
    header('Location: ordens.php?msg=erro');
    exit;
}

$status_atual = $os['status'];

// ===== STATUS FINAIS NÃO MUDAM =====
if (in_array($status_atual, ['concluida', 'cancelada'])) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
    exit;
}

// =========================================================
// HELPER: mudar status e redirecionar
// =========================================================
function mudarStatus($conn, $id_os, $novo_status, $msg)
{
    $stmt = $conn->prepare("UPDATE ordens_servico SET status = ? WHERE id_os = ?");
    $stmt->bind_param('si', $novo_status, $id_os);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=' . $msg);
    exit;
}

// =========================================================
// INICIAR SEPARAÇÃO (aprovado → aguardando_peca)
// =========================================================
if ($acao === 'iniciar_separacao') {

    if (!in_array($nivel, [1, 2])) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
        exit;
    }

    if ($status_atual !== 'aprovado') {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
        exit;
    }

    mudarStatus($conn, $id_os, 'aguardando_peca', 'separacao_iniciada');
}

// =========================================================
// PEÇAS CHEGARAM (aguardando_peca → em_execucao)
// =========================================================
if ($acao === 'pecas_chegaram') {

    if (!in_array($nivel, [1, 2])) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
        exit;
    }

    if ($status_atual !== 'aguardando_peca') {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
        exit;
    }

    mudarStatus($conn, $id_os, 'em_execucao', 'pecas_chegaram');
}

// =========================================================
// MARCAR COMO PRONTA (em_execucao → pronta → decide próximo)
// =========================================================
if ($acao === 'marcar_pronta') {

    if (!in_array($nivel, [1, 3])) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
        exit;
    }

    if ($status_atual !== 'em_execucao') {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
        exit;
    }

    // Desaponta quem estiver apontado
    $conn->query("UPDATE os_apontamentos
                  SET data_desapontamento = NOW(), id_usuario_desapontou = {$idUsuario}
                  WHERE id_os = {$id_os} AND data_desapontamento IS NULL");

    // Marca como pronta
    $stmt = $conn->prepare("UPDATE ordens_servico SET status = 'pronta' WHERE id_os = ?");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $stmt->close();

    // ===== DECIDE O PRÓXIMO STATUS BASEADO NO PAGAMENTO =====
    // Pega o orçamento principal da OS
    $stmt = $conn->prepare("SELECT id_orcamento FROM os_orcamentos
                            WHERE id_os = ? AND id_orcamento_pai IS NULL
                            ORDER BY id_orcamento DESC LIMIT 1");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $orc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($orc) {
        $saldo = calcularSaldoOrcamento($conn, (int)$orc['id_orcamento']);

        if ($saldo['quitado'] && $saldo['total'] > 0) {
            // Já pagou tudo → aguardando_retirada
            $conn->query("UPDATE ordens_servico SET status = 'aguardando_retirada' WHERE id_os = {$id_os}");
            $conn->close();
            header('Location: ordens_ver.php?id=' . $id_os . '&msg=pronta_retirada');
            exit;
        } else {
            // Ainda tem saldo → aguardando_pagamento
            $conn->query("UPDATE ordens_servico SET status = 'aguardando_pagamento' WHERE id_os = {$id_os}");
            $conn->close();
            header('Location: ordens_ver.php?id=' . $id_os . '&msg=pronta_pagamento');
            exit;
        }
    }

    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=pronta');
    exit;
}

// =========================================================
// AGUARDANDO RETIRADA (pronta → aguardando_retirada)
// =========================================================
if ($acao === 'aguardando_retirada') {

    if (!in_array($nivel, [1, 2])) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
        exit;
    }

    if ($status_atual !== 'pronta') {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
        exit;
    }

    mudarStatus($conn, $id_os, 'aguardando_retirada', 'aguardando_retirada');
}

// =========================================================
// CLIENTE RETIROU (aguardando_retirada → concluida)
// =========================================================
if ($acao === 'cliente_retirou') {

    if (!in_array($nivel, [1, 2])) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
        exit;
    }

    if (!in_array($status_atual, ['aguardando_retirada', 'pronta'])) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
        exit;
    }

    $stmt = $conn->prepare("UPDATE ordens_servico SET status = 'concluida', data_conclusao = NOW() WHERE id_os = ?");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=concluida');
    exit;
}

// =========================================================
// CONCLUIR (atalho legado — em_execucao/pronta → concluida)
// =========================================================
if ($acao === 'concluir') {

    if (!in_array($nivel, [1, 3])) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
        exit;
    }

    // Se for mecânico, precisa estar apontado
    if ($nivel === 3) {
        $stmt = $conn->prepare("SELECT id_apontamento FROM os_apontamentos
                                WHERE id_os = ? AND id_usuario = ? AND data_desapontamento IS NULL LIMIT 1");
        $stmt->bind_param('ii', $id_os, $idUsuario);
        $stmt->execute();
        $ap = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ap) {
            $conn->close();
            header('Location: ordens_ver.php?id=' . $id_os . '&msg=nao_apontado');
            exit;
        }
    }

    $conn->query("UPDATE os_apontamentos
                  SET data_desapontamento = NOW(), id_usuario_desapontou = {$idUsuario}
                  WHERE id_os = {$id_os} AND data_desapontamento IS NULL");

    $stmt = $conn->prepare("UPDATE ordens_servico SET status = 'concluida', data_conclusao = NOW() WHERE id_os = ?");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $stmt->close();

    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=concluida');
    exit;
}

// =========================================================
// CANCELAR
// =========================================================
if ($acao === 'cancelar') {

    if (!in_array($nivel, [1, 2])) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
        exit;
    }

    // Não pode cancelar depois de pronta
    if (in_array($status_atual, ['pronta', 'aguardando_pagamento', 'aguardando_retirada'])) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
        exit;
    }

    $motivo = trim($_POST['motivo_cancelamento'] ?? '');

    $conn->query("UPDATE os_apontamentos
                  SET data_desapontamento = NOW(), id_usuario_desapontou = {$idUsuario}
                  WHERE id_os = {$id_os} AND data_desapontamento IS NULL");

    if ($motivo !== '') {
        $stmt = $conn->prepare("UPDATE ordens_servico SET status = 'cancelada', motivo_cancelamento = ? WHERE id_os = ?");
        $stmt->bind_param('si', $motivo, $id_os);
    } else {
        $stmt = $conn->prepare("UPDATE ordens_servico SET status = 'cancelada' WHERE id_os = ?");
        $stmt->bind_param('i', $id_os);
    }
    $stmt->execute();
    $stmt->close();

    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=cancelada');
    exit;
}

$conn->close();
header('Location: ordens_ver.php?id=' . $id_os);
exit;
