<?php
/* =========================================================
   M-TECH SYSTEM — BUSCAR SERVIÇOS (autocomplete)
   Retorna serviços do catálogo para adicionar ao orçamento
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

$sql = "SELECT id_servico, nome, descricao, tempo_estimado_min, valor_hora, valor_fixo
        FROM servicos
        WHERE ativo = 1";
$params = [];
$tipos = '';

if ($termo !== '') {
    $sql .= " AND (nome LIKE ? OR descricao LIKE ?)";
    $t = '%' . $termo . '%';
    $params = [$t, $t];
    $tipos = 'ss';
}

$sql .= " ORDER BY nome ASC LIMIT 20";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$servicos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$resultado = [];
foreach ($servicos as $s) {
    $resultado[] = [
        'id_servico'         => (int)$s['id_servico'],
        'nome'               => $s['nome'],
        'descricao'          => $s['descricao'] ?? '',
        'tempo_estimado_min' => $s['tempo_estimado_min'] !== null ? (int)$s['tempo_estimado_min'] : null,
        'valor_hora'         => $s['valor_hora'] !== null ? (float)$s['valor_hora'] : null,
        'valor_fixo'         => $s['valor_fixo'] !== null ? (float)$s['valor_fixo'] : null,
    ];
}

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
