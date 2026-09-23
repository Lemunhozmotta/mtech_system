<?php
/* =========================================================
   M-TECH SYSTEM — REQUISITAR PEÇA DO ESTOQUE
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    header('Location: ordens.php?msg=sem_permissao');
    exit;
}

$id_solicitacao = (int)($_POST['id_solicitacao'] ?? 0);
$id_estoque = (int)($_POST['id_estoque'] ?? 0);
$id_os = (int)($_POST['id_os'] ?? 0);
$idUsuario = (int)$usuarioLogado['id_usuario'];

if ($id_solicitacao <= 0 || $id_estoque <= 0) {
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
    exit;
}

$conn = conectar();

// Verifica estoque suficiente
$stmt = $conn->prepare("SELECT quantidade FROM estoque WHERE id_estoque = ? LIMIT 1");
$stmt->bind_param('i', $id_estoque);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT quantidade FROM os_solicitacoes_peca WHERE id_solicitacao = ? AND status = 'pendente' LIMIT 1");
$stmt->bind_param('i', $id_solicitacao);
$stmt->execute();
$sol = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item || !$sol || (int)$item['quantidade'] < (int)$sol['quantidade']) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=estoque_insuficiente');
    exit;
}

$stmt = $conn->prepare("
    UPDATE os_solicitacoes_peca
    SET status = 'aprovada_estoque',
        origem = 'estoque',
        id_estoque = ?,
        id_usuario_aprovou = ?,
        data_aprovacao = NOW()
    WHERE id_solicitacao = ?
");
$stmt->bind_param('iii', $id_estoque, $idUsuario, $id_solicitacao);
$stmt->execute();
$stmt->close();

$conn->close();
header('Location: ordens_ver.php?id=' . $id_os . '&msg=requisitada');
exit;
