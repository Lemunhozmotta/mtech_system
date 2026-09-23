<?php
/* =========================================================
   M-TECH SYSTEM — APONTAR / DESAPONTAR MECÂNICO
   Regras:
   - Mecânico (3): só se aponta em si mesmo
   - Admin (1) e Financeiro (2): apontam qualquer mecânico
   - 1 mecânico só pode estar em 1 OS por vez
   - Apontar muda status pra 'em_andamento' (se estava 'aberta')
   - Desapontar: se tem solicitação não resolvida → 'aguardando_aprovacao'
                 senão → 'em_andamento'
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
$stmt = $conn->prepare("SELECT status FROM ordens_servico WHERE id_os = ? LIMIT 1");
$stmt->bind_param('i', $id_os);
$stmt->execute();
$os = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$os || in_array($os['status'], ['concluida', 'cancelada'])) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
    exit;
}

// =========================================================
// APONTAR
// =========================================================
if ($acao === 'apontar') {

    if ($nivel === 3) {
        $id_mecanico = $idUsuario;
    } elseif (in_array($nivel, [1, 2])) {
        $id_mecanico = (int)($_POST['id_mecanico'] ?? 0);
        if ($id_mecanico <= 0) {
            $conn->close();
            header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
            exit;
        }
    } else {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
        exit;
    }

    // Já tem alguém apontado nesta OS?
    $stmt = $conn->prepare("SELECT id_apontamento FROM os_apontamentos
                            WHERE id_os = ? AND data_desapontamento IS NULL LIMIT 1");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=ja_apontado_nesta');
        exit;
    }
    $stmt->close();

    // Mecânico já está apontado em outra OS?
    $stmt = $conn->prepare("SELECT id_os FROM os_apontamentos
                            WHERE id_usuario = ? AND data_desapontamento IS NULL LIMIT 1");
    $stmt->bind_param('i', $id_mecanico);
    $stmt->execute();
    $outra = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($outra) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=ja_apontado_outra');
        exit;
    }

    // Insere o apontamento
    $stmt = $conn->prepare("INSERT INTO os_apontamentos (id_os, id_usuario, apontado_por)
                            VALUES (?, ?, ?)");
    $stmt->bind_param('iii', $id_os, $id_mecanico, $idUsuario);

    if (!$stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
        exit;
    }
    $stmt->close();

    // Status → em_andamento
    if ($os['status'] === 'aberta' || $os['status'] === 'aguardando_peca' || $os['status'] === 'aguardando_aprovacao') {
        $conn->query("UPDATE ordens_servico SET status = 'em_andamento' WHERE id_os = {$id_os}");
    }

    // Atualiza id_mecanico da OS
    $conn->query("UPDATE ordens_servico SET id_mecanico = {$id_mecanico} WHERE id_os = {$id_os}");

    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=apontado');
    exit;
}

// =========================================================
// DESAPONTAR
// =========================================================
if ($acao === 'desapontar') {

    if ($nivel === 3) {
        $stmt = $conn->prepare("UPDATE os_apontamentos
                                SET data_desapontamento = NOW(), id_usuario_desapontou = ?
                                WHERE id_os = ? AND id_usuario = ? AND data_desapontamento IS NULL");
        $stmt->bind_param('iii', $idUsuario, $id_os, $idUsuario);
    } elseif (in_array($nivel, [1, 2])) {
        $stmt = $conn->prepare("UPDATE os_apontamentos
                                SET data_desapontamento = NOW(), id_usuario_desapontou = ?
                                WHERE id_os = ? AND data_desapontamento IS NULL");
        $stmt->bind_param('ii', $idUsuario, $id_os);
    } else {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
        exit;
    }

    $stmt->execute();
    $afetadas = $stmt->affected_rows;
    $stmt->close();

    if ($afetadas === 0) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=nao_apontado');
        exit;
    }

    // ===== CONTA SOLICITAÇÕES NÃO RESOLVIDAS =====
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total FROM os_solicitacoes_peca
        WHERE id_os = ? AND status IN ('pendente','aprovada_estoque','aprovada_compra')
    ");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $nao_resolvidas = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // ===== APLICA O STATUS =====
    if ($nao_resolvidas > 0) {
        // Tem peça pedida/aprovada → OS aguarda aprovação do orçamento
        $conn->query("UPDATE ordens_servico SET status = 'aguardando_aprovacao' WHERE id_os = {$id_os}");
    } else {
        $conn->query("UPDATE ordens_servico SET status = 'em_andamento' WHERE id_os = {$id_os}");
    }

    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=desapontado');
    exit;
}

$conn->close();
header('Location: ordens_ver.php?id=' . $id_os);
exit;
