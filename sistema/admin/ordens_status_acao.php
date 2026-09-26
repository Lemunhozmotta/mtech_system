<?php
/* =========================================================
   M-TECH SYSTEM — ORDENS DE SERVIÇO (mudar status)
   Ações do fluxo novo:
   - pecas_chegaram     → aguardando_peca → em_andamento
   - marcar_pronta      → em_execucao/pronta → pronta
   - cliente_retirou    → aguardando_retirada → concluida
   - concluir           → em_execucao/em_andamento → concluida (atalho)
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
function mudarStatusSimples($conn, $id_os, $novo_status, $msg)
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
// PEÇAS CHEGARAM (aguardando_peca → em_andamento)
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

    // Muda pra em_andamento — mecânico se aponta depois pra virar em_execucao
    mudarStatusSimples($conn, $id_os, 'em_andamento', 'pecas_chegaram');
}

// =========================================================
// MARCAR COMO PRONTA (em_execucao → pronta → decide retirada/pagamento)
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
    $stmt = $conn->prepare("SELECT id_orcamento FROM os_orcamentos
                            WHERE id_os = ?
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
            // Ainda tem saldo → fica em pronta
            $conn->close();
            header('Location: ordens_ver.php?id=' . $id_os . '&msg=pronta');
            exit;
        }
    }

    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=pronta');
    exit;
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

    // Arquiva o orçamento
    $conn->query("UPDATE os_orcamentos SET status = 'arquivado' WHERE id_os = {$id_os}");

    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=concluida');
    exit;
}

// =========================================================
// CONCLUIR (atalho — em_andamento/em_execucao/pronta → concluida)
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

    $conn->query("UPDATE os_orcamentos SET status = 'arquivado' WHERE id_os = {$id_os}");

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
    if (in_array($status_atual, ['pronta', 'aguardando_retirada'])) {
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

    // Cancela o orçamento também
    $conn->query("UPDATE os_orcamentos SET status = 'cancelado' WHERE id_os = {$id_os}");

    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=cancelada');
    exit;
}

$conn->close();
header('Location: ordens_ver.php?id=' . $id_os);
exit;
