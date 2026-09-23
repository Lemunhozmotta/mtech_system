<?php
/* =========================================================
   M-TECH SYSTEM — LIMPAR CARRINHO DO ESTOQUE
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$_SESSION['estoque_carrinho'] = [];
$_SESSION['estoque_os_destino'] = null;

header('Location: estoque.php?msg=carrinho_limp');
exit;
