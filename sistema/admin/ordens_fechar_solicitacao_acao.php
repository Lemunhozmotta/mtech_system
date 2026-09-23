<?php
/* =========================================================
   M-TECH SYSTEM — FECHAR SOLICITAÇÃO DE PEÇA
   - Níveis 1 e 2
   - Ao fechar (comprada ou recusada): status volta para em_andamento
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
$id_solicitacao = (int)($_POST['id_solicitacao'] ?? 0);
$id_os = (int)($_POST['id_os'] ?? 0);
$resultado = $_POST['resultado'] ?? '';
$motivo_recusa = trim($_POST['motivo_recusa'] ?? '');
$conn = conectar();

$nivel = (int)$usuarioLogado['nivel'];
$idUsuario = (int)$usuarioLogado['id_usuario'];

if (!in_array($nivel, [1, 2])) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
    exit;
}

if ($id_solicitacao <= 0 || !in_array($resultado, ['comprada', 'recusada'])) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
    exit;
}

// ===== VERIFICA A SOLICITAÇÃO =====
$stmt = $conn->prepare("SELECT id_os, status FROM os_solicitacoes_peca WHERE id_solicitacao = ? LIMIT 1");
$stmt->bind_param('i', $id_solicitacao);
$stmt->execute();
$sol = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sol || $sol['status'] !== 'pendente') {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
    exit;
}

$id_os_real = (int)$sol['id_os'];

if ($resultado === 'recusada' && $motivo_recusa === '') {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os_real . '&msg=erro_obrig');
    exit;
}

// ===== ATUALIZA A SOLICITAÇÃO =====
$motivo_val = $resultado === 'recusada' ? $motivo_recusa : null;
$stmt = $conn->prepare("UPDATE os_solicitacoes_peca
                        SET status = ?, data_fechamento = NOW(),
                            id_usuario_fechou = ?, motivo_recusa = ?
                        WHERE id_solicitacao = ?");
$stmt->bind_param('sisi', $resultado, $idUsuario, $motivo_val, $id_solicitacao);
$stmt->execute();
$stmt->close();

// ===== VERIFICA SE AINDA TEM OUTRAS PENDENTES =====
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM os_solicitacoes_peca
                        WHERE id_os = ? AND status = 'pendente'");
$stmt->bind_param('i', $id_os_real);
$stmt->execute();
$pendentes = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// ===== SE NÃO TEM MAIS PENDENTE, VOLTA STATUS PARA EM_ANDAMENTO =====
if ($pendentes === 0) {
    $conn->query("UPDATE ordens_servico SET status = 'em_andamento' WHERE id_os = {$id_os_real} AND status = 'aguardando_peca'");
}

$conn->close();
header('Location: ordens_ver.php?id=' . $id_os_real . '&msg=solicitacao_fechada');
exit;
