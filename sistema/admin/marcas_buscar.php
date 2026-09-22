<?php
/* =========================================================
   M-TECH SYSTEM — MARCAS (buscar modelos via AJAX)
   Retorna JSON com a marca + modelos
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

// ===== SÓ NÍVEIS 1 E 2 =====
$usuarioLogado = usuarioLogado();
if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'sem_permissao']);
    exit;
}

// ===== PEGA O ID DA MARCA =====
$id_marca = (int)($_GET['id_marca'] ?? 0);

if ($id_marca <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'id_invalido']);
    exit;
}

$conn = conectar();

// ===== BUSCA A MARCA =====
$stmt = $conn->prepare("SELECT id_marca, nome FROM marcas WHERE id_marca = ? LIMIT 1");
$stmt->bind_param('i', $id_marca);
$stmt->execute();
$marca = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$marca) {
    $conn->close();
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'marca_nao_encontrada']);
    exit;
}

// ===== BUSCA OS MODELOS =====
$stmt = $conn->prepare("
    SELECT mo.id_modelo, mo.nome,
           (SELECT COUNT(*) FROM carros WHERE id_modelo = mo.id_modelo) AS qtd_carros
    FROM modelos mo
    WHERE mo.id_marca = ?
    ORDER BY mo.nome ASC
");
$stmt->bind_param('i', $id_marca);
$stmt->execute();
$modelos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// ===== RETORNA JSON =====
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'marca' => [
        'id_marca' => (int)$marca['id_marca'],
        'nome' => $marca['nome']
    ],
    'modelos' => array_map(function ($m) {
        return [
            'id_modelo' => (int)$m['id_modelo'],
            'nome' => $m['nome'],
            'qtd_carros' => (int)$m['qtd_carros']
        ];
    }, $modelos)
]);
