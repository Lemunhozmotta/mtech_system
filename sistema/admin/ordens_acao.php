<?php
/* =========================================================
   M-TECH SYSTEM — ORDENS DE SERVIÇO (ações)
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
$conn = conectar();

$nivel = (int)$usuarioLogado['nivel'];
$idUsuario = (int)$usuarioLogado['id_usuario'];

// =========================================================
// CADASTRAR
// =========================================================
if ($acao === 'cadastrar') {

    if (!in_array($nivel, [1, 2, 3, 4])) {
        $conn->close();
        header('Location: ordens.php?msg=sem_permissao');
        exit;
    }

    $id_cliente = (int)($_POST['id_cliente'] ?? 0);
    $id_carro = (int)($_POST['id_carro'] ?? 0);
    $id_mecanico = (int)($_POST['id_mecanico'] ?? 0);
    $data_previsao = trim($_POST['data_previsao'] ?? '');
    $descricao_problema = trim($_POST['descricao_problema'] ?? '');

    if ($id_cliente <= 0 || $id_carro <= 0 || $descricao_problema === '') {
        $conn->close();
        header('Location: ordens_novo.php?msg=erro_obrig');
        exit;
    }

    // ===== DEFINE O MECÂNICO RESPONSÁVEL =====
    if ($nivel === 3) {
        $id_mecanico_val = $idUsuario;
    } else {
        $id_mecanico_val = $id_mecanico > 0 ? $id_mecanico : 0;
    }

    $ano = date('Y');
    $res = $conn->query("SELECT MAX(CAST(SUBSTRING(numero_os, 6) AS UNSIGNED)) AS ultimo
                         FROM ordens_servico WHERE numero_os LIKE '{$ano}-%'");
    $row = $res->fetch_assoc();
    $proximo = ((int)($row['ultimo'] ?? 0)) + 1;
    $numero_os = $ano . '-' . str_pad($proximo, 4, '0', STR_PAD_LEFT);

    $data_previsao_val = $data_previsao !== '' ? $data_previsao . ' 00:00:00' : null;

    $stmt = $conn->prepare("
        INSERT INTO ordens_servico
        (numero_os, id_cliente, id_carro, id_mecanico, id_usuario_abertura,
         data_previsao, descricao_problema, status)
        VALUES (?, ?, ?, NULLIF(?, 0), ?, ?, ?, 'aberta')
    ");
    $stmt->bind_param(
        'siiiiss',
        $numero_os,
        $id_cliente,
        $id_carro,
        $id_mecanico_val,
        $idUsuario,
        $data_previsao_val,
        $descricao_problema
    );

    if ($stmt->execute()) {
        $novo_id = $conn->insert_id;
        $stmt->close();
        $conn->close();
        header('Location: ordens_ver.php?id=' . $novo_id . '&msg=cadastrada');
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header('Location: ordens_novo.php?msg=erro');
        exit;
    }
}

// =========================================================
// EDITAR
// =========================================================
if ($acao === 'editar') {

    $id_os = (int)($_POST['id_os'] ?? 0);
    $id_mecanico = (int)($_POST['id_mecanico'] ?? 0);
    $data_previsao = trim($_POST['data_previsao'] ?? '');
    $descricao_problema = trim($_POST['descricao_problema'] ?? '');
    $diagnostico = trim($_POST['diagnostico'] ?? '');
    $solucao = trim($_POST['solucao'] ?? '');

    if ($id_os <= 0 || $descricao_problema === '') {
        $conn->close();
        header('Location: ordens.php?msg=erro_obrig');
        exit;
    }

    $stmt = $conn->prepare("SELECT id_usuario_abertura, status, id_mecanico FROM ordens_servico WHERE id_os = ? LIMIT 1");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $os_atual = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$os_atual) {
        $conn->close();
        header('Location: ordens.php?msg=erro');
        exit;
    }

    if (in_array($os_atual['status'], ['concluida', 'cancelada'])) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
        exit;
    }

    $abriuOS = ((int)$os_atual['id_usuario_abertura'] === $idUsuario);
    $estaApontado = estaApontadoNaOS($conn, $id_os, $idUsuario);

    $podeEditar = ($nivel === 1) || $abriuOS || ($nivel === 3 && $estaApontado);

    if (!$podeEditar) {
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
        exit;
    }

    $id_mecanico_val = $id_mecanico > 0 ? $id_mecanico : 0;
    $data_previsao_val = $data_previsao !== '' ? $data_previsao . ' 00:00:00' : null;
    $diagnostico_val = $diagnostico !== '' ? $diagnostico : null;
    $solucao_val = $solucao !== '' ? $solucao : null;

    if ($nivel === 3 && $estaApontado) {
        $stmt = $conn->prepare("
            UPDATE ordens_servico
            SET data_previsao = ?, descricao_problema = ?,
                diagnostico = ?, solucao = ?
            WHERE id_os = ?
        ");
        $stmt->bind_param('ssssi', $data_previsao_val, $descricao_problema, $diagnostico_val, $solucao_val, $id_os);
    } else {
        $stmt = $conn->prepare("
            UPDATE ordens_servico
            SET id_mecanico = NULLIF(?, 0),
                data_previsao = ?, descricao_problema = ?,
                diagnostico = ?, solucao = ?
            WHERE id_os = ?
        ");
        $stmt->bind_param('issssi', $id_mecanico_val, $data_previsao_val, $descricao_problema, $diagnostico_val, $solucao_val, $id_os);
    }

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=editada');
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
        exit;
    }
}

$conn->close();
header('Location: ordens.php');
exit;