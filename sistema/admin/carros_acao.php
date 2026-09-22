<?php
/* =========================================================
   M-TECH SYSTEM — CARROS (ações)
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
$id = (int)($_POST['id_carro'] ?? $_GET['id'] ?? 0);

$conn = conectar();

function normalizarPlaca($placa)
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa));
}

// =========================================================
// CADASTRAR
// =========================================================
if ($acao === 'cadastrar') {

    $id_cliente = (int)($_POST['id_cliente'] ?? 0);
    $id_marca = (int)($_POST['id_marca'] ?? 0);
    $id_modelo = (int)($_POST['id_modelo'] ?? 0);
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $ano = trim($_POST['ano'] ?? '');
    $placa = trim($_POST['placa'] ?? '');
    $cor = trim($_POST['cor'] ?? '');
    $km_atual = trim($_POST['km_atual'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if ($id_cliente <= 0 || empty($marca) || empty($modelo) || empty($placa)) {
        $conn->close();
        header('Location: carros_novo.php?msg=erro_obrig');
        exit;
    }

    // Cliente existe e ativo?
    $stmt = $conn->prepare("SELECT id_cliente FROM clientes WHERE id_cliente = ? AND ativo = 1 LIMIT 1");
    $stmt->bind_param('i', $id_cliente);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $stmt->close();
        $conn->close();
        header('Location: carros_novo.php?msg=erro_cliente');
        exit;
    }
    $stmt->close();

    // Se id_marca não veio, tenta achar pelo nome
    if ($id_marca <= 0 && !empty($marca)) {
        $stmt = $conn->prepare("SELECT id_marca FROM marcas WHERE nome = ? LIMIT 1");
        $stmt->bind_param('s', $marca);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) $id_marca = (int)$r['id_marca'];
    }

    // Se id_modelo não veio, tenta achar pelo nome
    if ($id_modelo <= 0 && $id_marca > 0 && !empty($modelo)) {
        $stmt = $conn->prepare("SELECT id_modelo FROM modelos WHERE id_marca = ? AND nome = ? LIMIT 1");
        $stmt->bind_param('is', $id_marca, $modelo);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) $id_modelo = (int)$r['id_modelo'];
    }

    // Placa duplicada?
    $placa_norm = normalizarPlaca($placa);
    $stmt = $conn->prepare("SELECT id_carro FROM carros WHERE REPLACE(REPLACE(UPPER(placa), '-', ''), ' ', '') = ? LIMIT 1");
    $stmt->bind_param('s', $placa_norm);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $conn->close();
        header('Location: carros_novo.php?msg=erro_placa');
        exit;
    }
    $stmt->close();

    $id_marca_val = $id_marca > 0 ? $id_marca : null;
    $id_modelo_val = $id_modelo > 0 ? $id_modelo : null;
    $ano = $ano ?: null;
    $cor = $cor ?: null;
    $km_atual = ($km_atual !== '' && is_numeric($km_atual)) ? (int)$km_atual : null;
    $observacoes = $observacoes ?: null;

    $stmt = $conn->prepare("
        INSERT INTO carros
        (id_cliente, id_marca, id_modelo, marca, modelo, ano, placa, cor, km_atual, observacoes, ativo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $stmt->bind_param(
        'iiisssssis',
        $id_cliente,
        $id_marca_val,
        $id_modelo_val,
        $marca,
        $modelo,
        $ano,
        $placa_norm,
        $cor,
        $km_atual,
        $observacoes
    );

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: carros.php?msg=cadastrado');
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header('Location: carros.php?msg=erro');
        exit;
    }
}

// =========================================================
// EDITAR
// =========================================================
if ($acao === 'editar') {

    if ($id <= 0) {
        $conn->close();
        header('Location: carros.php?msg=erro');
        exit;
    }

    $id_cliente = (int)($_POST['id_cliente'] ?? 0);
    $id_marca = (int)($_POST['id_marca'] ?? 0);
    $id_modelo = (int)($_POST['id_modelo'] ?? 0);
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $ano = trim($_POST['ano'] ?? '');
    $placa = trim($_POST['placa'] ?? '');
    $cor = trim($_POST['cor'] ?? '');
    $km_atual = trim($_POST['km_atual'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if ($id_cliente <= 0 || empty($marca) || empty($modelo) || empty($placa)) {
        $conn->close();
        header('Location: carros_editar.php?id=' . $id . '&msg=erro_obrig');
        exit;
    }

    $stmt = $conn->prepare("SELECT id_cliente FROM clientes WHERE id_cliente = ? AND ativo = 1 LIMIT 1");
    $stmt->bind_param('i', $id_cliente);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $stmt->close();
        $conn->close();
        header('Location: carros_editar.php?id=' . $id . '&msg=erro_cliente');
        exit;
    }
    $stmt->close();

    if ($id_marca <= 0 && !empty($marca)) {
        $stmt = $conn->prepare("SELECT id_marca FROM marcas WHERE nome = ? LIMIT 1");
        $stmt->bind_param('s', $marca);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) $id_marca = (int)$r['id_marca'];
    }

    if ($id_modelo <= 0 && $id_marca > 0 && !empty($modelo)) {
        $stmt = $conn->prepare("SELECT id_modelo FROM modelos WHERE id_marca = ? AND nome = ? LIMIT 1");
        $stmt->bind_param('is', $id_marca, $modelo);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) $id_modelo = (int)$r['id_modelo'];
    }

    $placa_norm = normalizarPlaca($placa);
    $stmt = $conn->prepare("
        SELECT id_carro FROM carros
        WHERE REPLACE(REPLACE(UPPER(placa), '-', ''), ' ', '') = ? AND id_carro <> ?
        LIMIT 1
    ");
    $stmt->bind_param('si', $placa_norm, $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $conn->close();
        header('Location: carros_editar.php?id=' . $id . '&msg=erro_placa');
        exit;
    }
    $stmt->close();

    $id_marca_val = $id_marca > 0 ? $id_marca : null;
    $id_modelo_val = $id_modelo > 0 ? $id_modelo : null;
    $ano = $ano ?: null;
    $cor = $cor ?: null;
    $km_atual = ($km_atual !== '' && is_numeric($km_atual)) ? (int)$km_atual : null;
    $observacoes = $observacoes ?: null;

    $stmt = $conn->prepare("
        UPDATE carros SET
            id_cliente = ?, id_marca = ?, id_modelo = ?,
            marca = ?, modelo = ?, ano = ?, placa = ?,
            cor = ?, km_atual = ?, observacoes = ?
        WHERE id_carro = ?
    ");

    $stmt->bind_param(
        'iiisssssisi',
        $id_cliente,
        $id_marca_val,
        $id_modelo_val,
        $marca,
        $modelo,
        $ano,
        $placa_norm,
        $cor,
        $km_atual,
        $observacoes,
        $id
    );

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: carros.php?msg=editado');
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header('Location: carros.php?msg=erro');
        exit;
    }
}

// =========================================================
// DESATIVAR
// =========================================================
if ($acao === 'desativar') {

    $usuarioLogado = usuarioLogado();
    if (!in_array($usuarioLogado['nivel'], [1, 2])) {
        $conn->close();
        header('Location: carros.php?msg=sem_permissao');
        exit;
    }

    if ($id <= 0) {
        $conn->close();
        header('Location: carros.php?msg=erro');
        exit;
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total FROM ordens_servico
        WHERE id_carro = ? AND status IN ('aberta', 'em_andamento', 'aguardando_peca')
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int)$res['total'] > 0) {
        $conn->close();
        header('Location: carros.php?msg=tem_os_aberta');
        exit;
    }

    $stmt = $conn->prepare("UPDATE carros SET ativo = 0 WHERE id_carro = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header('Location: carros.php?msg=desativado');
    exit;
}

// =========================================================
// REATIVAR
// =========================================================
if ($acao === 'reativar') {

    $usuarioLogado = usuarioLogado();
    if (!in_array($usuarioLogado['nivel'], [1, 2])) {
        $conn->close();
        header('Location: carros.php?msg=sem_permissao');
        exit;
    }

    if ($id <= 0) {
        $conn->close();
        header('Location: carros.php?msg=erro');
        exit;
    }

    $stmt = $conn->prepare("UPDATE carros SET ativo = 1 WHERE id_carro = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header('Location: carros.php?msg=reativado');
    exit;
}

// =========================================================
// AÇÃO DESCONHECIDA
// =========================================================
$conn->close();
header('Location: carros.php');
exit;
