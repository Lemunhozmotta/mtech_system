<?php
/* =========================================================
   M-TECH SYSTEM — BUSCAR CATEGORIAS DE ESTOQUE
   Retorna as categorias padrão + as já cadastradas no banco
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

header('Content-Type: application/json; charset=utf-8');

$termo = trim($_GET['termo'] ?? '');

// Categorias padrão do sistema
$categorias_padrao = [
    'Filtros',
    'Freios',
    'Motor',
    'Ignição',
    'Suspensão',
    'Elétrica',
    'Arrefecimento',
    'Transmissão',
    'Lubrificantes',
    'Acessórios',
    'Pneus',
    'Funilaria',
    'Ar Condicionado',
    'Injeção Eletrônica',
    'Escape',
    'Direção',
    'Iluminação',
    'Vedação',
    'Parafusos e Fixadores',
    'Ferramentas',
];

// Busca categorias já cadastradas no banco
$conn = conectar();
$res = $conn->query("SELECT DISTINCT categoria FROM estoque
                     WHERE categoria IS NOT NULL AND categoria <> ''
                     ORDER BY categoria ASC");
$categorias_banco = [];
while ($r = $res->fetch_assoc()) {
    $categorias_banco[] = $r['categoria'];
}
$conn->close();

// Junta as duas listas sem duplicar
$todas = array_unique(array_merge($categorias_padrao, $categorias_banco));
sort($todas);

// Filtra pelo termo digitado (se houver)
$filtradas = [];
if ($termo !== '') {
    $termo_norm = mb_strtolower($termo, 'UTF-8');
    foreach ($todas as $cat) {
        if (mb_strpos(mb_strtolower($cat, 'UTF-8'), $termo_norm) !== false) {
            $filtradas[] = ['nome' => $cat];
        }
    }
} else {
    foreach ($todas as $cat) {
        $filtradas[] = ['nome' => $cat];
    }
}

// Limita a 15 resultados
$filtradas = array_slice($filtradas, 0, 15);

echo json_encode($filtradas, JSON_UNESCAPED_UNICODE);
