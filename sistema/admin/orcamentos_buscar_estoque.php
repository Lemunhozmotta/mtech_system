<?php
/* =========================================================
   M-TECH SYSTEM — BUSCAR ITENS DO ESTOQUE (autocomplete)
   Retorna peças do estoque para adicionar ao orçamento
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$termo = trim($_GET['termo'] ?? '');

$conn = conectar();

$sql = "SELECT id_estoque, nome, codigo, codigo_barras, categoria, marca,
               quantidade, valor_venda, valor_custo
        FROM estoque
        WHERE ativo = 1";
$params = [];
$tipos = '';

if ($termo !== '') {
    $sql .= " AND (nome LIKE ? OR codigo LIKE ? OR codigo_barras LIKE ?
                   OR categoria LIKE ? OR marca LIKE ?)";
    $t = '%' . $termo . '%';
    $params = [$t, $t, $t, $t, $t];
    $tipos = 'sssss';
}

$sql .= " ORDER BY nome ASC LIMIT 20";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$resultado = [];
foreach ($itens as $it) {
    $resultado[] = [
        'id_estoque'   => (int)$it['id_estoque'],
        'nome'         => $it['nome'],
        'codigo'       => $it['codigo'] ?? '',
        'codigo_barras' => $it['codigo_barras'] ?? '',
        'categoria'    => $it['categoria'] ?? '',
        'marca'        => $it['marca'] ?? '',
        'quantidade'   => (int)$it['quantidade'],
        'valor_venda'  => (float)$it['valor_venda'],
        'valor_custo'  => (float)$it['valor_custo'],
    ];
}

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
