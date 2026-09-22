<?php
/* =========================================================
   M-TECH SYSTEM — ORDENS DE SERVIÇO (ações)
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
$conn = conectar();

// =========================================================
// CADASTRAR
// =========================================================
if ($acao === 'cadastrar') {

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

    // ===== GERA NÚMERO: 2026-0001 =====
    $ano = date('Y');
    $res = $conn->query("SELECT MAX(CAST(SUBSTRING(numero_os, 6) AS UNSIGNED)) AS ultimo
                         FROM ordens_servico
                         WHERE numero_os LIKE '{$ano}-%'");
    $row = $res->fetch_assoc();
    $proximo = ((int)($row['ultimo'] ?? 0)) + 1;
    $numero_os = $ano . '-' . str_pad($proximo, 4, '0', STR_PAD_LEFT);

    $id_mecanico_val = $id_mecanico > 0 ? $id_mecanico : null;
    $data_previsao_val = $data_previsao !== '' ? $data_previsao . ' 00:00:00' : null;
    $id_usuario_abertura = (int)$usuarioLogado['id_usuario'];

    $stmt = $conn->prepare("
        INSERT INTO ordens_servico
        (numero_os, id_cliente, id_carro, id_mecanico, id_usuario_abertura,
         data_previsao, descricao_problema, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'aberta')
    ");
    $stmt->bind_param(
        'siiiiss',
        $numero_os,
        $id_cliente,
        $id_carro,
        $id_mecanico_val,
        $id_usuario_abertura,
        $data_previsao_val,
        $descricao_problema
    );

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: ordens.php?msg=cadastrada');
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header('Location: ordens_novo.php?msg=erro');
        exit;
    }
}

// =========================================================
// AÇÃO DESCONHECIDA
// =========================================================
$conn->close();
header('Location: ordens.php');
exit;
