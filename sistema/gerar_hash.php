<?php
/* =========================================================
   GERADOR DE HASH DE SENHA — USO ÚNICO
   Rode uma vez, copie o hash, depois APAGUE este arquivo.
   ========================================================= */

$senha = '123';
$hash = password_hash($senha, PASSWORD_DEFAULT);

echo "<h2>Senha: <code>$senha</code></h2>";
echo "<h3>Hash gerado:</h3>";
echo "<textarea style='width:100%; height:80px; font-family:monospace;'>$hash</textarea>";
echo "<hr>";
echo "<p><strong>Próximo passo:</strong> copie o hash acima, cole na query abaixo e rode no MySQL:</p>";
echo "<textarea style='width:100%; height:100px; font-family:monospace;'>UPDATE usuarios SET senha = '$hash';</textarea>";
