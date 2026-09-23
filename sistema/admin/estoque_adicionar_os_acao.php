<?php
/* =========================================================
   M-TECH SYSTEM — ADICIONAR PEÇAS DO ESTOQUE À SESSÃO DA OS
   Fica no estoque, só guarda o carrinho.
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
if ((int)$usuarioLogado['nivel'] !== 3) {
    header('Location: estoque.php?msg=sem_permissao');
    exit;
}

$ids_estoque = $_POST['ids_estoque'] ?? [];
$id_os = (int)($_POST['id_os'] ?? 0);

if (empty($ids_estoque) || !is_array($ids_estoque) || $id_os <= 0) {
    header('Location: estoque.php?msg=erro_obrig');
    exit;
}

$conn = conectar();

// Verifica se a OS existe e se o mecânico está apontado nela
$idU = (int)$usuarioLogado['id_usuario'];
$stmt = $conn->prepare("
    SELECT os.id_os FROM ordens_servico os
    INNER JOIN os_apontamentos a ON a.id_os = os.id_os
    WHERE os.id_os = ? AND a.id_usuario = ? AND a.data_desapontamento IS NULL
    LIMIT 1
");
$stmt->bind_param('ii', $id_os, $idU);
$stmt->execute();
$valida = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$valida) {
    $conn->close();
    header('Location: estoque.php?msg=nao_apontado');
    exit;
}

// Guarda a OS destino na sessão
$_SESSION['estoque_os_destino'] = $id_os;

// Adiciona os IDs à sessão (sem duplicar)
if (!isset($_SESSION['estoque_carrinho'])) {
    $_SESSION['estoque_carrinho'] = [];
}

foreach ($ids_estoque as $id_e) {
    $id_e = (int)$id_e;
    if ($id_e > 0 && !in_array($id_e, $_SESSION['estoque_carrinho'])) {
        $_SESSION['estoque_carrinho'][] = $id_e;
    }
}

$conn->close();

// FICA NO ESTOQUE
header('Location: estoque.php?msg=carrinho_add');
exit;
