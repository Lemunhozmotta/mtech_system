<?php
/* =========================================================
   M-TECH SYSTEM — CARROS (buscar marcas via AJAX)
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
if (!$usuarioLogado) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';
$conn = conectar();

if ($termo === '') {
    // Sem termo: retorna TODAS as marcas
    $sql = "SELECT id_marca, nome FROM marcas ORDER BY nome ASC";
    $stmt = $conn->prepare($sql);
} else {
    // Com termo: filtra por começa com
    $sql = "SELECT id_marca, nome FROM marcas WHERE nome LIKE CONCAT(?, '%') ORDER BY nome ASC LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $termo);
}

$stmt->execute();
$result = $stmt->get_result();
$marcas = [];
while ($row = $result->fetch_assoc()) {
    $marcas[] = [
        'id' => (int)$row['id_marca'],
        'nome' => $row['nome']
    ];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode($marcas);
