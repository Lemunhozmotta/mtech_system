<?php
/* =========================================================
   M-TECH SYSTEM — LOGOUT
   Destrói a sessão e volta pro site institucional
   ========================================================= */

session_start();

// Limpa todas as variáveis de sessão
$_SESSION = array();

// Destrói o cookie da sessão (se existir)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destrói a sessão
session_destroy();

// Redireciona pro site institucional (caminho absoluto)
header('Location: /mtech_system/index.html');
exit;
