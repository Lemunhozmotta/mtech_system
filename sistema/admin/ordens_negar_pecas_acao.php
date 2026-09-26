<?php
/* =========================================================
   M-TECH SYSTEM — NEGAR PEÇAS EM LOTE
   - Níveis 1 e 2
   - Marca todas como 'negada' com o mesmo motivo
   - Atualiza status da OS via atualizarStatusOSPorSolicitacoes()
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
$nivel = (int)$usuarioLogado['nivel'];
$idUsuario = (int)$usuarioLogado['id_usuario'];

if (!in_array($nivel, [1, 2])) {
    header('Location: ordens.php?msg=sem_permissao');
    exit;
}

$id_os = (int)($_POST['id_os'] ?? 0);
$ids = $_POST['ids'] ?? [];
$motivo = trim($_POST['motivo'] ?? '');

if ($id_os <= 0 || empty($ids) || !is_array($ids) || $motivo === '') {
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro_obrig');
    exit;
}

$conn = conectar();

foreach ($ids as $id_solic) {
    $id_solic = (int)$id_solic;
    if ($id_solic <= 0) continue;

    $stmt = $conn->prepare("
        UPDATE os_solicitacoes_peca
        SET status = 'negada',
            motivo_recusa = ?,
            id_usuario_fechou = ?,
            data_fechamento = NOW()
        WHERE id_solicitacao = ? AND id_os = ? AND status = 'pendente'
    ");
    $stmt->bind_param('siii', $motivo, $idUsuario, $id_solic, $id_os);
    $stmt->execute();
    $stmt->close();
}

// ===== ATUALIZA STATUS DA OS =====
atualizarStatusOSPorSolicitacoes($conn, $id_os);

$conn->close();
header('Location: ordens_ver.php?id=' . $id_os . '&msg=negadas');
exit;
