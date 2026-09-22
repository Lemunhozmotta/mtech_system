<?php
/* =========================================================
   M-TECH SYSTEM — MARCAS E MODELOS (ações)
   Processa: cadastrar_marca, editar_marca,
             cadastrar_modelo, editar_modelo
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

// ===== SÓ NÍVEIS 1 E 2 =====
$usuarioLogado = usuarioLogado();
if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    header('Location: dashboard.php?msg=sem_permissao');
    exit;
}

$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
$conn = conectar();

// =========================================================
// AÇÃO: CADASTRAR MARCA
// =========================================================
if ($acao === 'cadastrar_marca') {

    $nome = trim($_POST['nome'] ?? '');

    if (empty($nome)) {
        $conn->close();
        header('Location: marcas.php?msg=erro_nome');
        exit;
    }

    $stmt = $conn->prepare("SELECT id_marca FROM marcas WHERE nome = ? LIMIT 1");
    $stmt->bind_param('s', $nome);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $conn->close();
        header('Location: marcas.php?msg=erro_duplicado');
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO marcas (nome) VALUES (?)");
    $stmt->bind_param('s', $nome);

    if ($stmt->execute()) {
        $novo_id = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        header('Location: marcas.php?id_marca=' . $novo_id . '&msg=marca_cadastrada');
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header('Location: marcas.php?msg=erro');
        exit;
    }
}

// =========================================================
// AÇÃO: EDITAR MARCA
// =========================================================
if ($acao === 'editar_marca') {

    $id = (int)($_POST['id_marca'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');

    if ($id <= 0 || empty($nome)) {
        $conn->close();
        header('Location: marcas.php?msg=erro_nome');
        exit;
    }

    $stmt = $conn->prepare("SELECT id_marca FROM marcas WHERE nome = ? AND id_marca <> ? LIMIT 1");
    $stmt->bind_param('si', $nome, $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $conn->close();
        header('Location: marcas.php?id_marca=' . $id . '&msg=erro_duplicado');
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE marcas SET nome = ? WHERE id_marca = ?");
    $stmt->bind_param('si', $nome, $id);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: marcas.php?id_marca=' . $id . '&msg=marca_editada');
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header('Location: marcas.php?id_marca=' . $id . '&msg=erro');
        exit;
    }
}

// =========================================================
// AÇÃO: CADASTRAR MODELO
// =========================================================
if ($acao === 'cadastrar_modelo') {

    $id_marca = (int)($_POST['id_marca'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');

    if ($id_marca <= 0 || empty($nome)) {
        $conn->close();
        header('Location: marcas.php?msg=erro_nome');
        exit;
    }

    $stmt = $conn->prepare("SELECT id_marca FROM marcas WHERE id_marca = ? LIMIT 1");
    $stmt->bind_param('i', $id_marca);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $stmt->close();
        $conn->close();
        header('Location: marcas.php?msg=erro_marca');
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT id_modelo FROM modelos WHERE id_marca = ? AND nome = ? LIMIT 1");
    $stmt->bind_param('is', $id_marca, $nome);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $conn->close();
        header('Location: marcas.php?id_marca=' . $id_marca . '&msg=erro_duplicado');
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO modelos (id_marca, nome) VALUES (?, ?)");
    $stmt->bind_param('is', $id_marca, $nome);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: marcas.php?id_marca=' . $id_marca . '&msg=modelo_cadastrado');
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header('Location: marcas.php?id_marca=' . $id_marca . '&msg=erro');
        exit;
    }
}

// =========================================================
// AÇÃO: EDITAR MODELO
// =========================================================
if ($acao === 'editar_modelo') {

    $id = (int)($_POST['id_modelo'] ?? 0);
    $id_marca = (int)($_POST['id_marca'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');

    if ($id <= 0 || $id_marca <= 0 || empty($nome)) {
        $conn->close();
        header('Location: marcas.php?msg=erro_nome');
        exit;
    }

    $stmt = $conn->prepare("SELECT id_modelo FROM modelos WHERE id_marca = ? AND nome = ? AND id_modelo <> ? LIMIT 1");
    $stmt->bind_param('isi', $id_marca, $nome, $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $conn->close();
        header('Location: marcas.php?id_marca=' . $id_marca . '&msg=erro_duplicado');
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE modelos SET nome = ? WHERE id_modelo = ?");
    $stmt->bind_param('si', $nome, $id);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: marcas.php?id_marca=' . $id_marca . '&msg=modelo_editado');
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header('Location: marcas.php?id_marca=' . $id_marca . '&msg=erro');
        exit;
    }
}

// =========================================================
// AÇÃO DESCONHECIDA
// =========================================================
$conn->close();
header('Location: marcas.php');
exit;
