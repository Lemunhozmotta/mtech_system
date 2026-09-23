<?php
/* =========================================================
   M-TECH SYSTEM — APROVAR PEÇAS EM LOTE
   - Níveis 1 e 2
   - Classifica automaticamente: estoque OU compra
   - Se estoque: marca 'aprovada_estoque'
   - Se compra: cria compras_solicitacoes + marca 'aprovada_compra'
   - Atualiza status da OS conforme regra nova
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
$nivel = (int)$usuarioLogado['nivel'];
$idUsuario = (int)$usuarioLogado['id_usuario'];

if (!in_array($nivel, [1, 2])) {
    header('Location: ordens.php?msg=sem_permissao');
    exit;
}

$id_os = (int)($_POST['id_os'] ?? 0);
$ids = $_POST['ids'] ?? [];

if ($id_os <= 0 || empty($ids) || !is_array($ids)) {
    header('Location: ordens_ver.php?id=' . $id_os . '&msg=erro');
    exit;
}

$conn = conectar();

$aprovadas = 0;

foreach ($ids as $id_solic) {
    $id_solic = (int)$id_solic;
    if ($id_solic <= 0) continue;

    // Busca a solicitação
    $stmt = $conn->prepare("SELECT * FROM os_solicitacoes_peca
                            WHERE id_solicitacao = ? AND id_os = ? AND status = 'pendente' LIMIT 1");
    $stmt->bind_param('ii', $id_solic, $id_os);
    $stmt->execute();
    $sp = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sp) continue;

    $nome_peca = $sp['nome_peca'];
    $quantidade = (float)$sp['quantidade'];

    // ===== BUSCA NO ESTOQUE (nome exato, case-insensitive) =====
    $stmt = $conn->prepare("SELECT id_estoque, quantidade FROM estoque
                            WHERE LOWER(nome) = LOWER(?) AND ativo = 1 LIMIT 1");
    $stmt->bind_param('s', $nome_peca);
    $stmt->execute();
    $item_estoque = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($item_estoque && (int)$item_estoque['quantidade'] >= (int)$quantidade) {
        // ===== TEM EM ESTOQUE =====
        $id_estoque = (int)$item_estoque['id_estoque'];

        $stmt = $conn->prepare("
            UPDATE os_solicitacoes_peca
            SET status = 'aprovada_estoque',
                origem = 'estoque',
                id_estoque = ?,
                id_usuario_aprovou = ?,
                data_aprovacao = NOW()
            WHERE id_solicitacao = ?
        ");
        $stmt->bind_param('iii', $id_estoque, $idUsuario, $id_solic);
        $stmt->execute();
        $stmt->close();
    } else {
        // ===== NÃO TEM → CRIA COMPRA =====
        // Primeiro: cria a compra
        $stmt = $conn->prepare("
            INSERT INTO compras_solicitacoes
            (id_os, id_solicitacao_peca, id_usuario_solicitou, id_usuario_aprovou,
             nome_peca, quantidade, status, data_aprovacao)
            VALUES (?, ?, ?, ?, ?, ?, 'aprovada', NOW())
        ");
        $stmt->bind_param(
            'iiiisd',
            $id_os,
            $id_solic,
            $idUsuario,
            $idUsuario,
            $nome_peca,
            $quantidade
        );
        $stmt->execute();
        $id_compra = $conn->insert_id;
        $stmt->close();

        // Agora atualiza a solicitação
        $stmt = $conn->prepare("
            UPDATE os_solicitacoes_peca
            SET status = 'aprovada_compra',
                origem = 'compra',
                id_compra = ?,
                id_usuario_aprovou = ?,
                data_aprovacao = NOW()
            WHERE id_solicitacao = ?
        ");
        $stmt->bind_param('iii', $id_compra, $idUsuario, $id_solic);
        $stmt->execute();
        $stmt->close();
    }

    $aprovadas++;
}

// ===== ATUALIZA STATUS DA OS =====
atualizarStatusOSPorSolicitacoes($conn, $id_os);

$conn->close();
header('Location: ordens_ver.php?id=' . $id_os . '&msg=aprovadas');
exit;

// =========================================================
// FUNÇÃO: atualiza status da OS conforme as solicitações
// Regra:
//  - Se tem 'pendente' → OS fica 'em_andamento'
//  - Se NÃO tem pendente E tem 'aprovada_estoque'/'aprovada_compra' → 'aguardando_peca'
//  - Se todas resolvidas (entregue/negada) → 'em_andamento'
// =========================================================
function atualizarStatusOSPorSolicitacoes($conn, $id_os)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS nao_resolvidas
        FROM os_solicitacoes_peca
        WHERE id_os = ? AND status IN ('pendente','aprovada_estoque','aprovada_compra')
    ");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $nao_resolvidas = (int)$stmt->get_result()->fetch_assoc()['nao_resolvidas'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT status FROM ordens_servico WHERE id_os = ? LIMIT 1");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $os_atual = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$os_atual) return;
    if (in_array($os_atual['status'], ['concluida', 'cancelada'])) return;

    if ($nao_resolvidas > 0) {
        $conn->query("UPDATE ordens_servico SET status = 'aguardando_peca' WHERE id_os = {$id_os}");
    } else {
        $conn->query("UPDATE ordens_servico SET status = 'em_andamento' WHERE id_os = {$id_os}");
    }
}
