<?php
/* =========================================================
   M-TECH SYSTEM — ORDENS DE SERVIÇO (mudar status)
   - Concluir: níveis 1 e 3 (e mecânico precisa estar apontado)
   - Cancelar: níveis 1 e 2
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

if (!$os) {
    $conn->close();
    header('Location: ordens.php?msg=erro');
    exit;
}

if (in_array($os['status'], ['concluida', 'cancelada'])) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
    exit;
}

// =========================================================
// CONCLUIR
// =========================================================
if ($acao === 'concluir') {

    if (!in_array($nivel, [1, 3])) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
        exit;
    }

    // Se for mecânico, precisa estar apontado nesta OS
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

    // Conclui + desaponta qualquer um que esteja apontado
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

    // Desaponta quem estiver apontado
    $conn->query("UPDATE os_apontamentos
                  SET data_desapontamento = NOW(), id_usuario_desapontou = {$idUsuario}
                  WHERE id_os = {$id_os} AND data_desapontamento IS NULL");

    $stmt = $conn->prepare("UPDATE ordens_servico SET status = 'cancelada' WHERE id_os = ?");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $stmt->close();

    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=cancelada');
    exit;
}

$conn->close();
header('Location: ordens_ver.php?id=' . $id_os);
exit;
