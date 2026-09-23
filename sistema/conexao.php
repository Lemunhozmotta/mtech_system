<?php
// ===== DEBUG TEMPORÁRIO =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/* =========================================================
   M-TECH SYSTEM — CONEXÃO E FUNÇÕES AUXILIARES
   ========================================================= */

// ===== ANTI-CACHE =====
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ===== CONFIGURAÇÕES DO BANCO =====
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mtech_system');

// ===== INICIA SESSÃO =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== TIMEZONE =====
date_default_timezone_set('America/Sao_Paulo');

/* =========================================================
   FUNÇÃO: conectar()
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
   ========================================================= */
function verificarLogin()
{
    if (!isset($_SESSION['id_usuario'])) {
        $caminho = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2);
        header('Location: ' . $caminho . 'login.php');
        exit;
    }
}

/* =========================================================
   FUNÇÃO: verificarNivel()
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
   ========================================================= */
function redirecionar($url)
{
    header('Location: ' . $url);
    exit;
}

/* =========================================================
   FUNÇÃO: limpar()
   ========================================================= */
function limpar($texto)
{
    return htmlspecialchars(trim($texto ?? ''), ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   FUNÇÃO: nomeNivel()
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

/* =========================================================
   FUNÇÃO: estaApontadoNaOS($conn, $id_os, $id_usuario)
   Retorna true se o usuário está apontado (sem desapontamento) nessa OS
   ========================================================= */
function estaApontadoNaOS($conn, $id_os, $id_usuario)
{
    $stmt = $conn->prepare("SELECT id_apontamento FROM os_apontamentos
                            WHERE id_os = ? AND id_usuario = ? AND data_desapontamento IS NULL
                            LIMIT 1");
    $stmt->bind_param('ii', $id_os, $id_usuario);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$r;
}

/* =========================================================
   FUNÇÃO: temSolicitacaoPendente($conn, $id_os)
   ========================================================= */
function temSolicitacaoPendente($conn, $id_os)
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM os_solicitacoes_peca
                            WHERE id_os = ? AND status = 'pendente'");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ((int)$r['total']) > 0;
}
