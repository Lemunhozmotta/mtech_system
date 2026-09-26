<?php
/* =========================================================
   M-TECH SYSTEM — COMPRAS (ações)
   Ações: comprar, receber, negar, cancelar
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
$nivel = (int)$usuarioLogado['nivel'];
$idUsuario = (int)$usuarioLogado['id_usuario'];

$acao = $_POST['acao'] ?? '';

if (!in_array($nivel, [1, 2])) {
    header('Location: compras.php?msg=sem_permissao');
    exit;
}

$conn = conectar();

// =========================================================
// COMPRAR
// =========================================================
if ($acao === 'comprar') {

    $id_compra = (int)($_POST['id_compra'] ?? 0);
    $fornecedor = trim($_POST['fornecedor'] ?? '');
    $valor_unitario = isset($_POST['valor_unitario']) ? (float)$_POST['valor_unitario'] : null;
    $observacoes = trim($_POST['observacoes'] ?? '');

    if ($id_compra <= 0 || $fornecedor === '') {
        $conn->close();
        header('Location: compras_ver.php?id=' . $id_compra . '&msg=erro_obrig');
        exit;
    }

    // Verifica estado atual
    $stmt = $conn->prepare("SELECT status, quantidade FROM compras_solicitacoes WHERE id_compra = ? LIMIT 1");
    $stmt->bind_param('i', $id_compra);
    $stmt->execute();
    $compra = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$compra || $compra['status'] !== 'aprovada') {
        $conn->close();
        header('Location: compras_ver.php?id=' . $id_compra . '&msg=erro');
        exit;
    }

    $valor_total = null;
    if ($valor_unitario !== null) {
        $valor_total = $valor_unitario * (float)$compra['quantidade'];
    }

    $obs_val = $observacoes !== '' ? $observacoes : null;

    $stmt = $conn->prepare("
        UPDATE compras_solicitacoes
        SET status = 'comprada',
            fornecedor = ?,
            valor_unitario = ?,
            valor_total = ?,
            observacoes = ?,
            data_compra = NOW(),
            id_usuario_comprou = ?
        WHERE id_compra = ?
    ");
    $stmt->bind_param('sddssi', $fornecedor, $valor_unitario, $valor_total, $obs_val, $idUsuario, $id_compra);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: compras_ver.php?id=' . $id_compra . '&msg=comprada');
    } else {
        $stmt->close();
        $conn->close();
        header('Location: compras_ver.php?id=' . $id_compra . '&msg=erro');
    }
    exit;
}

// =========================================================
// RECEBER (peça chegou → entra no estoque)
// =========================================================
if ($acao === 'receber') {

    $id_compra = (int)($_POST['id_compra'] ?? 0);
    $id_solicitacao = (int)($_POST['id_solicitacao'] ?? 0);

    if ($id_compra <= 0) {
        $conn->close();
        header('Location: compras.php?msg=erro');
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM compras_solicitacoes WHERE id_compra = ? LIMIT 1");
    $stmt->bind_param('i', $id_compra);
    $stmt->execute();
    $compra = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$compra || $compra['status'] !== 'comprada') {
        $conn->close();
        header('Location: compras_ver.php?id=' . $id_compra . '&msg=erro');
        exit;
    }

    $quantidade_recebida = (int)$compra['quantidade'];
    $id_estoque = null;

    $conn->begin_transaction();

    try {
        // ===== 1) ACHA OU CRIA O ITEM NO ESTOQUE =====
        $stmt = $conn->prepare("SELECT id_estoque, quantidade FROM estoque WHERE nome = ? LIMIT 1");
        $stmt->bind_param('s', $compra['nome_peca']);
        $stmt->execute();
        $item_existente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($item_existente) {
            // Já existe → soma
            $id_estoque = (int)$item_existente['id_estoque'];
            $qtd_anterior = (int)$item_existente['quantidade'];
            $qtd_posterior = $qtd_anterior + $quantidade_recebida;

            $stmt = $conn->prepare("UPDATE estoque SET quantidade = ? WHERE id_estoque = ?");
            $stmt->bind_param('ii', $qtd_posterior, $id_estoque);
            $stmt->execute();
            $stmt->close();
        } else {
            // Não existe → cria novo item
            // Não existe → cria novo item
            $nome = $compra['nome_peca'];
            $custo = (float)($compra['valor_unitario'] ?? 0);
            // Margem padrão de 80% sobre o custo (custo × 1.8). Ajustável depois em Estoque → Editar.
            $venda_sugerida = round($custo * 1.8, 2);
            $stmt = $conn->prepare("
                INSERT INTO estoque (nome, quantidade, quantidade_minima, valor_custo, valor_venda, ativo)
                VALUES (?, ?, 0, ?, ?, 1)
            ");
            $stmt->bind_param('sidd', $nome, $quantidade_recebida, $custo, $venda_sugerida);
            $stmt->execute();
            $id_estoque = $conn->insert_id;
            $stmt->close();

            $qtd_anterior = 0;
            $qtd_posterior = $quantidade_recebida;
        }

        // ===== 2) MOVIMENTAÇÃO =====
        $motivo = 'Compra #' . $id_compra . ' recebida';
        if ($compra['fornecedor']) {
            $motivo .= ' — ' . $compra['fornecedor'];
        }
        $id_os_val = $compra['id_os'] ? (int)$compra['id_os'] : null;
        $id_solic_val = $compra['id_solicitacao_peca'] ? (int)$compra['id_solicitacao_peca'] : null;

        $stmt = $conn->prepare("
            INSERT INTO estoque_movimentacoes
            (id_estoque, tipo, quantidade, quantidade_anterior, quantidade_posterior, motivo, id_os, id_solicitacao_peca, id_usuario)
            VALUES (?, 'entrada', ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'idiisiii',
            $id_estoque,
            $quantidade_recebida,
            $qtd_anterior,
            $qtd_posterior,
            $motivo,
            $id_os_val,
            $id_solic_val,
            $idUsuario
        );
        $stmt->execute();
        $stmt->close();

        // ===== 3) ATUALIZA A SOLICITAÇÃO DO MECÂNICO =====
        if ($id_solicitacao > 0) {
            $stmt = $conn->prepare("
                UPDATE os_solicitacoes_peca
                SET status = 'entregue',
                    quantidade_atendida = ?,
                    data_entrega = NOW(),
                    id_usuario_entregou = ?,
                    id_estoque = ?
                WHERE id_solicitacao = ?
            ");
            $stmt->bind_param('diii', $quantidade_recebida, $idUsuario, $id_estoque, $id_solicitacao);
            $stmt->execute();
            $stmt->close();
        }

        // ===== 4) MARCA A COMPRA COMO RECEBIDA =====
        $stmt = $conn->prepare("
            UPDATE compras_solicitacoes
            SET status = 'recebida',
                data_recebimento = NOW(),
                id_usuario_recebeu = ?
            WHERE id_compra = ?
        ");
        $stmt->bind_param('ii', $idUsuario, $id_compra);
        $stmt->execute();
        $stmt->close();

        // ===== 5) SE A OS NÃO TEM MAIS PENDÊNCIA, VOLTA PRA EM_ANDAMENTO =====
        $id_os = (int)($compra['id_os'] ?? 0);
        if ($id_os > 0) {
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
        }

        $conn->commit();
        $conn->close();
        header('Location: compras_ver.php?id=' . $id_compra . '&msg=recebida');
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        header('Location: compras_ver.php?id=' . $id_compra . '&msg=erro');
    }
    exit;
}

// =========================================================
// NEGAR
// =========================================================
if ($acao === 'negar') {

    $id_compra = (int)($_POST['id_compra'] ?? 0);

    if ($id_compra <= 0) {
        $conn->close();
        header('Location: compras.php?msg=erro');
        exit;
    }

    $stmt = $conn->prepare("SELECT status, id_solicitacao_peca, id_os FROM compras_solicitacoes WHERE id_compra = ? LIMIT 1");
    $stmt->bind_param('i', $id_compra);
    $stmt->execute();
    $compra = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$compra || !in_array($compra['status'], ['aprovada', 'comprada'])) {
        $conn->close();
        header('Location: compras_ver.php?id=' . $id_compra . '&msg=erro');
        exit;
    }

    $stmt = $conn->prepare("UPDATE compras_solicitacoes SET status = 'negada', data_compra = NULL WHERE id_compra = ?");
    $stmt->bind_param('i', $id_compra);
    $stmt->execute();
    $stmt->close();

    // Marca a solicitação do mecânico como negada
    if ($compra['id_solicitacao_peca']) {
        $id_sol = (int)$compra['id_solicitacao_peca'];
        $conn->query("UPDATE os_solicitacoes_peca SET status = 'negada' WHERE id_solicitacao = {$id_sol}");
    }

    // Se a OS não tem mais pendência, volta pra em_andamento
    if ($compra['id_os']) {
        $id_os = (int)$compra['id_os'];
        $res = $conn->query("SELECT COUNT(*) AS total FROM os_solicitacoes_peca WHERE id_os = {$id_os} AND status IN ('pendente','aprovada_estoque','aprovada_compra')");
        $pendentes = (int)$res->fetch_assoc()['total'];
        if ($pendentes === 0) {
            $conn->query("UPDATE ordens_servico SET status = 'em_andamento' WHERE id_os = {$id_os} AND status = 'aguardando_peca'");
        }
    }

    $conn->close();
    header('Location: compras_ver.php?id=' . $id_compra . '&msg=negada');
    exit;
}

$conn->close();
header('Location: compras.php');
exit;