<?php
/* =========================================================
   M-TECH SYSTEM — ESTOQUE (ações)
   Ações: cadastrar, editar, desativar, reativar, ajustar
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
$nivel = (int)$usuarioLogado['nivel'];
$idUsuario = (int)$usuarioLogado['id_usuario'];

$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
$id = (int)($_POST['id_estoque'] ?? $_GET['id'] ?? 0);

$conn = conectar();

// ===== SÓ NÍVEIS 1 E 2 =====
if (!in_array($nivel, [1, 2])) {
    $conn->close();
    header('Location: estoque.php?msg=sem_permissao');
    exit;
}

// =========================================================
// CADASTRAR
// =========================================================
if ($acao === 'cadastrar') {

    $nome = trim($_POST['nome'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');
    $codigo_barras = trim($_POST['codigo_barras'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $quantidade = (int)($_POST['quantidade'] ?? 0);
    $quantidade_minima = (int)($_POST['quantidade_minima'] ?? 0);
    $valor_custo = (float)($_POST['valor_custo'] ?? 0);
    $valor_venda = (float)($_POST['valor_venda'] ?? 0);
    $localizacao = trim($_POST['localizacao'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if ($nome === '') {
        $conn->close();
        header('Location: estoque_novo.php?msg=erro_obrig');
        exit;
    }

    $codigo_val = $codigo !== '' ? $codigo : null;
    $codigo_barras_val = $codigo_barras !== '' ? $codigo_barras : null;
    $categoria_val = $categoria !== '' ? $categoria : null;
    $marca_val = $marca !== '' ? $marca : null;
    $localizacao_val = $localizacao !== '' ? $localizacao : null;
    $observacoes_val = $observacoes !== '' ? $observacoes : null;

    $stmt = $conn->prepare("
        INSERT INTO estoque
        (nome, codigo, codigo_barras, categoria, marca, quantidade, quantidade_minima,
         valor_custo, valor_venda, localizacao, observacoes, ativo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->bind_param(
        'sssssiiddss',
        $nome,
        $codigo_val,
        $codigo_barras_val,
        $categoria_val,
        $marca_val,
        $quantidade,
        $quantidade_minima,
        $valor_custo,
        $valor_venda,
        $localizacao_val,
        $observacoes_val
    );

    if (!$stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: estoque_novo.php?msg=erro');
        exit;
    }

    $id_novo = $conn->insert_id;
    $stmt->close();

    // Registra movimentação inicial (se quantidade > 0)
    if ($quantidade > 0) {
        $stmt = $conn->prepare("
            INSERT INTO estoque_movimentacoes
            (id_estoque, tipo, quantidade, quantidade_anterior, quantidade_posterior, motivo, id_usuario)
            VALUES (?, 'entrada', ?, 0, ?, 'Cadastro inicial do item', ?)
        ");
        $stmt->bind_param('idis', $id_novo, $quantidade, $quantidade, $idUsuario);
        $stmt->execute();
        $stmt->close();
    }

    $conn->close();
    header('Location: estoque.php?msg=cadastrado');
    exit;
}

// =========================================================
// EDITAR
// =========================================================
if ($acao === 'editar') {

    if ($id <= 0) {
        $conn->close();
        header('Location: estoque.php?msg=erro');
        exit;
    }

    $nome = trim($_POST['nome'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');
    $codigo_barras = trim($_POST['codigo_barras'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $quantidade_minima = (int)($_POST['quantidade_minima'] ?? 0);
    $valor_custo = (float)($_POST['valor_custo'] ?? 0);
    $valor_venda = (float)($_POST['valor_venda'] ?? 0);
    $localizacao = trim($_POST['localizacao'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if ($nome === '') {
        $conn->close();
        header('Location: estoque_editar.php?id=' . $id . '&msg=erro_obrig');
        exit;
    }

    $codigo_val = $codigo !== '' ? $codigo : null;
    $codigo_barras_val = $codigo_barras !== '' ? $codigo_barras : null;
    $categoria_val = $categoria !== '' ? $categoria : null;
    $marca_val = $marca !== '' ? $marca : null;
    $localizacao_val = $localizacao !== '' ? $localizacao : null;
    $observacoes_val = $observacoes !== '' ? $observacoes : null;

    $stmt = $conn->prepare("
        UPDATE estoque SET
            nome = ?, codigo = ?, codigo_barras = ?, categoria = ?, marca = ?,
            quantidade_minima = ?, valor_custo = ?, valor_venda = ?,
            localizacao = ?, observacoes = ?
        WHERE id_estoque = ?
    ");
    $stmt->bind_param(
        'sssssiidssi',
        $nome,
        $codigo_val,
        $codigo_barras_val,
        $categoria_val,
        $marca_val,
        $quantidade_minima,
        $valor_custo,
        $valor_venda,
        $localizacao_val,
        $observacoes_val,
        $id
    );

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: estoque.php?msg=editado');
    } else {
        $stmt->close();
        $conn->close();
        header('Location: estoque_editar.php?id=' . $id . '&msg=erro');
    }
    exit;
}

// =========================================================
// AJUSTAR QUANTIDADE
// =========================================================
if ($acao === 'ajustar') {

    if ($id <= 0) {
        $conn->close();
        header('Location: estoque.php?msg=erro');
        exit;
    }

    $tipo_ajuste = $_POST['tipo_ajuste'] ?? '';
    $quantidade_ajuste = (float)($_POST['quantidade_ajuste'] ?? 0);
    $nova_quantidade = (float)($_POST['nova_quantidade'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? '');

    if (!in_array($tipo_ajuste, ['entrada', 'saida', 'ajuste', 'devolucao']) || $motivo === '') {
        $conn->close();
        header('Location: estoque_editar.php?id=' . $id . '&msg=erro_obrig');
        exit;
    }

    // ===== BUSCA ESTADO ATUAL =====
    $stmt = $conn->prepare("SELECT quantidade FROM estoque WHERE id_estoque = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $conn->close();
        header('Location: estoque.php?msg=erro');
        exit;
    }

    $qtd_anterior = (int)$row['quantidade'];
    $tipo_mov = 'ajuste';
    $qtd_movimentada = 0;
    $qtd_posterior = $qtd_anterior;

    if ($tipo_ajuste === 'entrada') {
        if ($quantidade_ajuste <= 0) {
            $conn->close();
            header('Location: estoque_editar.php?id=' . $id . '&msg=erro_obrig');
            exit;
        }
        $qtd_posterior = $qtd_anterior + (int)$quantidade_ajuste;
        $qtd_movimentada = (int)$quantidade_ajuste;
        $tipo_mov = 'entrada';
    } elseif ($tipo_ajuste === 'saida') {
        if ($quantidade_ajuste <= 0) {
            $conn->close();
            header('Location: estoque_editar.php?id=' . $id . '&msg=erro_obrig');
            exit;
        }
        if ($quantidade_ajuste > $qtd_anterior) {
            // ===== BLOQUEIA NEGATIVO =====
            $conn->close();
            header('Location: estoque_editar.php?id=' . $id . '&msg=erro_negativo');
            exit;
        }
        $qtd_posterior = $qtd_anterior - (int)$quantidade_ajuste;
        $qtd_movimentada = (int)$quantidade_ajuste;
        $tipo_mov = 'saida';
    } elseif ($tipo_ajuste === 'devolucao') {
        if ($quantidade_ajuste <= 0) {
            $conn->close();
            header('Location: estoque_editar.php?id=' . $id . '&msg=erro_obrig');
            exit;
        }
        $qtd_posterior = $qtd_anterior + (int)$quantidade_ajuste;
        $qtd_movimentada = (int)$quantidade_ajuste;
        $tipo_mov = 'devolucao';
    } elseif ($tipo_ajuste === 'ajuste') {
        if ($nova_quantidade < 0) {
            $conn->close();
            header('Location: estoque_editar.php?id=' . $id . '&msg=erro_obrig');
            exit;
        }
        $qtd_posterior = (int)$nova_quantidade;
        $qtd_movimentada = abs($qtd_posterior - $qtd_anterior);
        $tipo_mov = 'ajuste';
    }

    // ===== ATUALIZA ESTOQUE =====
    $stmt = $conn->prepare("UPDATE estoque SET quantidade = ? WHERE id_estoque = ?");
    $stmt->bind_param('ii', $qtd_posterior, $id);
    $stmt->execute();
    $stmt->close();

    // ===== REGISTRA MOVIMENTAÇÃO =====
    $motivo_val = $motivo;
    $stmt = $conn->prepare("
        INSERT INTO estoque_movimentacoes
        (id_estoque, tipo, quantidade, quantidade_anterior, quantidade_posterior, motivo, id_usuario)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'isdiisi',
        $id,
        $tipo_mov,
        $qtd_movimentada,
        $qtd_anterior,
        $qtd_posterior,
        $motivo_val,
        $idUsuario
    );
    $stmt->execute();
    $stmt->close();

    $conn->close();
    header('Location: estoque.php?msg=ajustado');
    exit;
}

// =========================================================
// DESATIVAR
// =========================================================
if ($acao === 'desativar') {
    if ($id <= 0) {
        $conn->close();
        header('Location: estoque.php?msg=erro');
        exit;
    }

    $stmt = $conn->prepare("UPDATE estoque SET ativo = 0 WHERE id_estoque = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header('Location: estoque.php?msg=desativado');
    exit;
}

// =========================================================
// REATIVAR
// =========================================================
if ($acao === 'reativar') {
    if ($id <= 0) {
        $conn->close();
        header('Location: estoque.php?msg=erro');
        exit;
    }

    $stmt = $conn->prepare("UPDATE estoque SET ativo = 1 WHERE id_estoque = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header('Location: estoque.php?msg=reativado');
    exit;
}

$conn->close();
header('Location: estoque.php');
exit;
