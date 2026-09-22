<?php
require_once 'conexao.php';

$email = 'renato@mtech.com';
$senha = '123';

$conn = conectar();
$stmt = $conn->prepare("SELECT nome, senha FROM usuarios WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "❌ Usuário NÃO encontrado no banco.<br>";
    exit;
}

$user = $result->fetch_assoc();

echo "<strong>Usuário:</strong> " . $user['nome'] . "<br>";
echo "<strong>Hash no banco:</strong> " . $user['senha'] . "<br>";
echo "<strong>Senha digitada:</strong> " . $senha . "<br>";
echo "<hr>";

if (password_verify($senha, $user['senha'])) {
    echo "✅ <strong>Senha CORRETA!</strong> O login DEVERIA funcionar.<br>";
} else {
    echo "❌ <strong>Senha INCORRETA!</strong> O hash no banco não corresponde a '123'.<br>";
    echo "<br>👉 <strong>Solução:</strong> Rode o <code>gerar_hash.php</code> de novo e atualize o hash no banco.";
}
