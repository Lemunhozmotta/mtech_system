<?php
/* =========================================================
   M-TECH SYSTEM — LOGIN (backend)
   Recebe e-mail + senha via AJAX (fetch) e retorna JSON
   ========================================================= */

require_once 'conexao.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'sucesso' => false,
        'tipo' => 'erro',
        'erro' => 'Método não permitido.'
    ]);
    exit;
}

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

if (empty($email) || empty($senha)) {
    echo json_encode([
        'sucesso' => false,
        'tipo' => 'erro',
        'erro' => 'Preencha e-mail e senha.'
    ]);
    exit;
}

$conn = conectar();

$stmt = $conn->prepare("SELECT id_usuario, nome, email, senha, nivel, ativo FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

// ===== CASO 1: E-MAIL NÃO EXISTE → CLIENTE =====
if ($result->num_rows === 0) {
    echo json_encode([
        'sucesso' => false,
        'tipo' => 'cliente',  // ← JS troca pra tela de aviso
        'erro' => 'E-mail não cadastrado.'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

$usuario = $result->fetch_assoc();

// ===== CASO 2: USUÁRIO INATIVO =====
if ((int)$usuario['ativo'] !== 1) {
    echo json_encode([
        'sucesso' => false,
        'tipo' => 'erro',
        'erro' => 'Usuário inativo. Contate o administrador.'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

// ===== CASO 3: SENHA INCORRETA → MENSAGEM NA TELA DE LOGIN =====
if (!password_verify($senha, $usuario['senha'])) {
    echo json_encode([
        'sucesso' => false,
        'tipo' => 'senha',  // ← JS só mostra mensagem, NÃO troca de tela
        'erro' => 'Senha incorreta. Tente novamente.'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

// ===== LOGIN OK =====
$_SESSION['id_usuario'] = (int)$usuario['id_usuario'];
$_SESSION['nome'] = $usuario['nome'];
$_SESSION['email'] = $usuario['email'];
$_SESSION['nivel'] = (int)$usuario['nivel'];
$_SESSION['logado_em'] = time();

echo json_encode([
    'sucesso' => true,
    'nome' => $usuario['nome'],
    'nivel' => (int)$usuario['nivel'],
    'redirecionar' => 'admin/dashboard.php'
]);

$stmt->close();
$conn->close();
