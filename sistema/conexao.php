<?php
/* =========================================================
   M-TECH SYSTEM — CONEXÃO E FUNÇÕES AUXILIARES
   ========================================================= */

// ===== CONFIGURAÇÕES DO BANCO =====
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mtech_system');

// ===== INICIA SESSÃO (pra login) =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== TIMEZONE =====
date_default_timezone_set('America/Sao_Paulo');

/* =========================================================
   FUNÇÃO: conectar()
   Retorna a conexão com o banco (mysqli)
   ========================================================= */
function conectar()
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        die('Erro ao conectar no banco: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

/* =========================================================
   FUNÇÃO: verificarLogin()
   Verifica se o usuário está logado. Se não, redireciona pro login.
   ========================================================= */
function verificarLogin()
{
    if (!isset($_SESSION['id_usuario'])) {
        // Calcula o caminho relativo pro login
        $caminho = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2);
        header('Location: ' . $caminho . 'login.php');
        exit;
    }
}

/* =========================================================
   FUNÇÃO: verificarNivel()
   Verifica se o usuário tem o nível necessário.
   Uso: verificarNivel(1) — só admin
        verificarNivel(1, 2) — admin ou financeiro
   ========================================================= */
function verificarNivel(...$niveis_permitidos)
{
    verificarLogin();

    if (!in_array($_SESSION['nivel'], $niveis_permitidos)) {
        header('Location: dashboard.php?erro=sem_permissao');
        exit;
    }
}

/* =========================================================
   FUNÇÃO: redirecionar()
   Atalho pra redirecionar
   ========================================================= */
function redirecionar($url)
{
    header('Location: ' . $url);
    exit;
}

/* =========================================================
   FUNÇÃO: limpar()
   Limpa strings pra prevenir XSS
   ========================================================= */
function limpar($texto)
{
    return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   FUNÇÃO: nomeNivel()
   Retorna o nome do nível de acesso
   ========================================================= */
function nomeNivel($nivel)
{
    $niveis = [
        1 => 'Administrador',
        2 => 'Financeiro',
        3 => 'Mecânico',
        4 => 'Atendente',
        5 => 'Recursos Humanos'
    ];
    return $niveis[$nivel] ?? 'Desconhecido';
}

/* =========================================================
   FUNÇÃO: usuarioLogado()
   Retorna os dados do usuário logado (array) ou null
   ========================================================= */
function usuarioLogado()
{
    if (!isset($_SESSION['id_usuario'])) {
        return null;
    }

    return [
        'id_usuario' => $_SESSION['id_usuario'],
        'nome' => $_SESSION['nome'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'nivel' => $_SESSION['nivel'] ?? 4
    ];
}
