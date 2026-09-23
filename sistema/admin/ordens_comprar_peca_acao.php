<?php
/* =========================================================
   M-TECH SYSTEM — MANDAR PEÇA PRA COMPRA
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
$idUsuario = (int)$usuarioLogado['id_usuario'];

if ($id_solicitacao <= 0) {
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
    exit;
}

$conn = conectar();

$stmt = $conn->prepare("SELECT * FROM os_solicitacoes_peca WHERE id_solicitacao = ? AND status = 'pendente' LIMIT 1");
$stmt->bind_param('i', $id_solicitacao);
$stmt->execute();
$sp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sp) {
    $conn->close();
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
    exit;
}

$nome_peca = $sp['nome_peca'];
$quantidade = (float)$sp['quantidade'];

// Cria a compra
$stmt = $conn->prepare("
    INSERT INTO compras_solicitacoes
    (id_os, id_solicitacao_peca, id_usuario_solicitou, id_usuario_aprovou,
     nome_peca, quantidade, status, data_aprovacao)
    VALUES (?, ?, ?, ?, ?, ?, 'aprovada', NOW())
");
$stmt->bind_param('iiiisd', $id_os, $id_solicitacao, $idUsuario, $idUsuario, $nome_peca, $quantidade);
$stmt->execute();
$id_compra = $conn->insert_id;
$stmt->close();

$stmt = $conn->prepare("
    UPDATE os_solicitacoes_peca
    SET status = 'aprovada_compra',
        origem = 'compra',
        id_compra = ?,
        id_usuario_aprovou = ?,
        data_aprovacao = NOW()
    WHERE id_solicitacao = ?
");
$stmt->bind_param('iii', $id_compra, $idUsuario, $id_solicitacao);
$stmt->execute();
$stmt->close();

$conn->close();
header('Location: ordens_ver.php?id=' . $id_os . '&msg=compra_aprovada');
exit;
