<?php
/* =========================================================
   M-TECH SYSTEM — CARROS (buscar modelos via AJAX)
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
if (!$usuarioLogado) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$id_marca = (int)($_GET['id_marca'] ?? 0);
$termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';

if ($id_marca <= 0) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$conn = conectar();

if ($termo === '') {
    // Sem termo: TODOS os modelos da marca
    $sql = "SELECT id_modelo, nome FROM modelos WHERE id_marca = ? ORDER BY nome ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_marca);
} else {
    // Com termo: filtra por começa com
    $sql = "SELECT id_modelo, nome FROM modelos WHERE id_marca = ? AND nome LIKE CONCAT(?, '%') ORDER BY nome ASC LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $id_marca, $termo);
}

$stmt->execute();
$result = $stmt->get_result();
$modelos = [];
while ($row = $result->fetch_assoc()) {
    $modelos[] = [
        'id' => (int)$row['id_modelo'],
        'nome' => $row['nome']
    ];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode($modelos);
