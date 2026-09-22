<?php
/* =========================================================
   M-TECH SYSTEM — CLIENTES (ações)
   Processa: cadastrar, editar, desativar, reativar
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

// ===== PEGA A AÇÃO =====
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
$id = (int)($_POST['id_cliente'] ?? $_GET['id'] ?? 0);

// ===== CONECTA AO BANCO =====
$conn = conectar();

// =========================================================
// AÇÃO: CADASTRAR
// =========================================================
if ($acao === 'cadastrar') {

    // ===== PEGA E LIMPA OS DADOS =====
    $nome = trim($_POST['nome'] ?? '');
    $cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $logradouro = trim($_POST['logradouro'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    // ===== VALIDAÇÃO BÁSICA =====
    if (empty($nome)) {
        $conn->close();
        header('Location: clientes.php?msg=erro_nome');
        exit;
    }

    // ===== INSERE NO BANCO =====
    $stmt = $conn->prepare("
        INSERT INTO clientes
        (nome, cpf_cnpj, data_nascimento, telefone, whatsapp, email,
         cep, logradouro, numero, complemento, bairro, cidade, estado,
         observacoes, ativo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    // Trata campos vazios como NULL
    $cpf_cnpj = $cpf_cnpj ?: null;
    $data_nascimento = $data_nascimento ?: null;
    $telefone = $telefone ?: null;
    $whatsapp = $whatsapp ?: null;
    $email = $email ?: null;
    $cep = $cep ?: null;
    $logradouro = $logradouro ?: null;
    $numero = $numero ?: null;
    $complemento = $complemento ?: null;
    $bairro = $bairro ?: null;
    $cidade = $cidade ?: null;
    $estado = $estado ?: null;
    $observacoes = $observacoes ?: null;

    $stmt->bind_param(
        'ssssssssssssss',
        $nome,
        $cpf_cnpj,
        $data_nascimento,
        $telefone,
        $whatsapp,
        $email,
        $cep,
        $logradouro,
        $numero,
        $complemento,
        $bairro,
        $cidade,
        $estado,
        $observacoes
    );

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: clientes.php?msg=cadastrado');
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header('Location: clientes.php?msg=erro');
        exit;
    }
}

// =========================================================
// AÇÃO: EDITAR
// =========================================================
if ($acao === 'editar') {

    if ($id <= 0) {
        $conn->close();
        header('Location: clientes.php?msg=erro');
        exit;
    }

    // ===== PEGA E LIMPA OS DADOS =====
    $nome = trim($_POST['nome'] ?? '');
    $cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $logradouro = trim($_POST['logradouro'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro = trim($_POST['bairro'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if (empty($nome)) {
        $conn->close();
        header('Location: clientes_editar.php?id=' . $id . '&msg=erro_nome');
        exit;
    }

    // Trata vazios
    $cpf_cnpj = $cpf_cnpj ?: null;
    $data_nascimento = $data_nascimento ?: null;
    $telefone = $telefone ?: null;
    $whatsapp = $whatsapp ?: null;
    $email = $email ?: null;
    $cep = $cep ?: null;
    $logradouro = $logradouro ?: null;
    $numero = $numero ?: null;
    $complemento = $complemento ?: null;
    $bairro = $bairro ?: null;
    $cidade = $cidade ?: null;
    $estado = $estado ?: null;
    $observacoes = $observacoes ?: null;

    // ===== ATUALIZA NO BANCO =====
    $stmt = $conn->prepare("
        UPDATE clientes SET
            nome = ?, cpf_cnpj = ?, data_nascimento = ?, telefone = ?, whatsapp = ?,
            email = ?, cep = ?, logradouro = ?, numero = ?, complemento = ?,
            bairro = ?, cidade = ?, estado = ?, observacoes = ?
        WHERE id_cliente = ?
    ");

    $stmt->bind_param(
        'ssssssssssssssi',
        $nome,
        $cpf_cnpj,
        $data_nascimento,
        $telefone,
        $whatsapp,
        $email,
        $cep,
        $logradouro,
        $numero,
        $complemento,
        $bairro,
        $cidade,
        $estado,
        $observacoes,
        $id
    );

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: clientes.php?msg=editado');
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header('Location: clientes.php?msg=erro');
        exit;
    }
}

// =========================================================
// AÇÃO: DESATIVAR
// =========================================================
if ($acao === 'desativar') {

    // ===== VERIFICA PERMISSÃO (nível 1 ou 2) =====
    $usuarioLogado = usuarioLogado();
    if (!in_array($usuarioLogado['nivel'], [1, 2])) {
        $conn->close();
        header('Location: clientes.php?msg=sem_permissao');
        exit;
    }

    if ($id <= 0) {
        $conn->close();
        header('Location: clientes.php?msg=erro');
        exit;
    }

    // ===== VERIFICA PENDÊNCIAS =====
    // OS em aberto ou em andamento?
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total FROM ordens_servico
        WHERE id_cliente = ? AND status IN ('aberta', 'em_andamento', 'aguardando_peca')
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int)$res['total'] > 0) {
        $conn->close();
        header('Location: clientes.php?msg=tem_os_aberta');
        exit;
    }

    // ===== DESATIVA =====
    $stmt = $conn->prepare("UPDATE clientes SET ativo = 0 WHERE id_cliente = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header('Location: clientes.php?msg=desativado');
    exit;
}

// =========================================================
// AÇÃO: REATIVAR
// =========================================================
if ($acao === 'reativar') {

    // ===== VERIFICA PERMISSÃO (nível 1 ou 2) =====
    $usuarioLogado = usuarioLogado();
    if (!in_array($usuarioLogado['nivel'], [1, 2])) {
        $conn->close();
        header('Location: clientes.php?msg=sem_permissao');
        exit;
    }

    if ($id <= 0) {
        $conn->close();
        header('Location: clientes.php?msg=erro');
        exit;
    }

    $stmt = $conn->prepare("UPDATE clientes SET ativo = 1 WHERE id_cliente = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header('Location: clientes.php?msg=reativado');
    exit;
}

// =========================================================
// AÇÃO DESCONHECIDA — volta pra lista
// =========================================================
$conn->close();
header('Location: clientes.php');
exit;
