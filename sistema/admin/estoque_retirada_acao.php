<?php
/* =========================================================
   M-TECH SYSTEM — CONFIRMAR RETIRADA DA PEÇA DO ESTOQUE
   - Chamado quando o responsável confirma a retirada física
   - Dá baixa no estoque (movimentação tipo 'retirada_os')
   - Marca a solicitação como 'entregue'
   - Bloqueia se a quantidade for insuficiente
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
$nivel = (int)$usuarioLogado['nivel'];
$idUsuario = (int)$usuarioLogado['id_usuario'];

if (!in_array($nivel, [1, 2, 3])) {
    header('Location: estoque.php?msg=sem_permissao');
    exit;
}

$id_solicitacao = (int)($_POST['id_solicitacao'] ?? 0);
$id_estoque = (int)($_POST['id_estoque'] ?? 0);
$quantidade = (float)($_POST['quantidade'] ?? 0);
$id_os = (int)($_POST['id_os'] ?? 0);

if ($id_solicitacao <= 0 || $id_estoque <= 0 || $quantidade <= 0) {
    header('Location: estoque_pendentes.php?msg=erro');
    exit;
}

$conn = conectar();

// ===== BUSCA A SOLICITAÇÃO =====
$stmt = $conn->prepare("SELECT * FROM os_solicitacoes_peca WHERE id_solicitacao = ? AND status = 'aprovada_estoque' LIMIT 1");
$stmt->bind_param('i', $id_solicitacao);
$stmt->execute();
$sol = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sol) {
    $conn->close();
    header('Location: estoque_pendentes.php?msg=erro');
    exit;
}

// ===== BUSCA O ITEM DO ESTOQUE =====
$stmt = $conn->prepare("SELECT quantidade, nome FROM estoque WHERE id_estoque = ? LIMIT 1");
$stmt->bind_param('i', $id_estoque);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    $conn->close();
    header('Location: estoque_pendentes.php?msg=erro');
    exit;
}

$qtd_estoque = (int)$item['quantidade'];
$qtd_retirada = (int)$quantidade;

// ===== BLOQUEIA NEGATIVO =====
if ($qtd_retirada > $qtd_estoque) {
    $conn->close();
    header('Location: estoque_pendentes.php?msg=erro_negativo');
    exit;
}

$qtd_posterior = $qtd_estoque - $qtd_retirada;

$conn->begin_transaction();

try {
    // 1) Baixa no estoque
    $stmt = $conn->prepare("UPDATE estoque SET quantidade = ? WHERE id_estoque = ?");
    $stmt->bind_param('ii', $qtd_posterior, $id_estoque);
    $stmt->execute();
    $stmt->close();

    // 2) Movimentação
    $motivo = 'Retirada para OS ' . $id_os . ' — ' . $item['nome'];
    $stmt = $conn->prepare("
        INSERT INTO estoque_movimentacoes
        (id_estoque, tipo, quantidade, quantidade_anterior, quantidade_posterior, motivo, id_os, id_solicitacao_peca, id_usuario)
        VALUES (?, 'retirada_os', ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'idiisiii',
        $id_estoque,
        $qtd_retirada,
        $qtd_estoque,
        $qtd_posterior,
        $motivo,
        $id_os,
        $id_solicitacao,
        $idUsuario
    );
    $stmt->execute();
    $stmt->close();

    // 3) Marca a solicitação como entregue
    $stmt = $conn->prepare("
        UPDATE os_solicitacoes_peca
        SET status = 'entregue',
            quantidade_atendida = ?,
            data_entrega = NOW(),
            id_usuario_entregou = ?
        WHERE id_solicitacao = ?
    ");
    $stmt->bind_param('dii', $qtd_retirada, $idUsuario, $id_solicitacao);
    $stmt->execute();
    $stmt->close();

    // 4) Verifica se ainda tem pendências na OS. Se não, volta pra em_andamento
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM os_solicitacoes_peca
        WHERE id_os = ? AND status IN ('pendente','aprovada_estoque','aprovada_compra')
    ");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $pendentes = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    if ($pendentes === 0) {
        $conn->query("UPDATE ordens_servico SET status = 'em_andamento' WHERE id_os = {$id_os} AND status = 'aguardando_peca'");
    }

    $conn->commit();
    $conn->close();
    header('Location: estoque_pendentes.php?msg=retirada_confirmada');
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    header('Location: estoque_pendentes.php?msg=erro');
}
exit;
