<?php
/* =========================================================
   M-TECH SYSTEM — NEGAR SOLICITAÇÃO DE PEÇA (individual)
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    header('Location: ordens.php?msg=sem_permissao');
    exit;
}

$id_solicitacao = (int)($_POST['id_solicitacao'] ?? 0);
$id_os = (int)($_POST['id_os'] ?? 0);
$motivo = trim($_POST['motivo'] ?? '');
$idUsuario = (int)$usuarioLogado['id_usuario'];

if ($id_solicitacao <= 0 || $motivo === '') {
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro_obrig');
    exit;
}

$conn = conectar();

$stmt = $conn->prepare("
    UPDATE os_solicitacoes_peca
    SET status = 'negada',
        motivo_recusa = ?,
        id_usuario_fechou = ?,
        data_fechamento = NOW()
    WHERE id_solicitacao = ? AND id_os = ? AND status = 'pendente'
");
$stmt->bind_param('siii', $motivo, $idUsuario, $id_solicitacao, $id_os);
$stmt->execute();
$stmt->close();

$conn->close();
header('Location: ordens_ver.php?id=' . $id_os . '&msg=negada');
exit;
