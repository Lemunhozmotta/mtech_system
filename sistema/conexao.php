<?php
/* =========================================================
   M-TECH SYSTEM — CONEXÃO E FUNÇÕES AUXILIARES
   VERSÃO FINAL — base pra todos os módulos
   ========================================================= */

// ===== MODO DEBUG (trocar pra false em produção) =====
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
}

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
   CONEXÃO
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
   AUTENTICAÇÃO
   ========================================================= */
function verificarLogin()
{
    if (!isset($_SESSION['id_usuario'])) {
        $caminho = str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2);
        header('Location: ' . $caminho . 'login.php');
        exit;
    }
}

function verificarNivel(...$niveis_permitidos)
{
    verificarLogin();
    if (!in_array($_SESSION['nivel'], $niveis_permitidos)) {
        header('Location: dashboard.php?erro=sem_permissao');
        exit;
    }
}

function usuarioLogado()
{
    if (!isset($_SESSION['id_usuario'])) {
        return null;
    }
    return [
        'id_usuario' => $_SESSION['id_usuario'],
        'nome'       => $_SESSION['nome'] ?? '',
        'email'      => $_SESSION['email'] ?? '',
        'nivel'      => $_SESSION['nivel'] ?? 4
    ];
}

/* =========================================================
   ATALHOS DE PERMISSÃO
   ========================================================= */
function usuarioEhAdmin()
{
    $u = usuarioLogado();
    return $u && (int)$u['nivel'] === 1;
}
function usuarioEhFinanceiro()
{
    $u = usuarioLogado();
    return $u && (int)$u['nivel'] === 2;
}
function usuarioEhMecanico()
{
    $u = usuarioLogado();
    return $u && (int)$u['nivel'] === 3;
}
function usuarioEhAtendente()
{
    $u = usuarioLogado();
    return $u && (int)$u['nivel'] === 4;
}
function usuarioPodeVerValores()
{
    $u = usuarioLogado();
    return $u && in_array((int)$u['nivel'], [1, 2]);
}
function usuarioPodeAprovar()
{
    $u = usuarioLogado();
    return $u && in_array((int)$u['nivel'], [1, 2]);
}

/* =========================================================
   UTILITÁRIOS
   ========================================================= */
function redirecionar($url)
{
    header('Location: ' . $url);
    exit;
}

function limpar($texto)
{
    return htmlspecialchars(trim($texto ?? ''), ENT_QUOTES, 'UTF-8');
}

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

function formatarMoeda($valor)
{
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function formatarMinutos($min)
{
    $min = (int)$min;
    if ($min < 60) return $min . ' min';
    $h = floor($min / 60);
    $m = $min % 60;
    return $h . 'h' . ($m > 0 ? ' ' . $m . 'min' : '');
}

/* =========================================================
   VERIFICAÇÕES DE OS
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

/* =========================================================
   OS — GERAR PRÓXIMO NÚMERO
   ========================================================= */
function proximoNumeroOS($conn)
{
    $ano = date('Y');
    $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(numero_os, 6) AS UNSIGNED)) AS ultimo
                            FROM ordens_servico WHERE numero_os LIKE ?");
    $like = $ano . '-%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $proximo = ((int)($row['ultimo'] ?? 0)) + 1;
    return $ano . '-' . str_pad($proximo, 4, '0', STR_PAD_LEFT);
}

/* =========================================================
   STATUS DA OS
   ========================================================= */
function nomeStatusOS($s)
{
    return [
        'aberta'                 => 'Aberta',
        'em_andamento'           => 'Em Andamento',
        'aguardando_aprovacao'   => 'Aguardando Aprovação',
        'aprovado'               => 'Aprovada',
        'aguardando_peca'        => 'Aguardando Peça',
        'em_execucao'            => 'Em Execução',
        'pronta'                 => 'Pronta',
        'aguardando_pagamento'   => 'Aguardando Pagamento',
        'aguardando_retirada'    => 'Aguardando Retirada',
        'concluida'              => 'Concluída',
        'cancelada'              => 'Cancelada',
    ][$s] ?? $s;
}

function classeStatusOS($s)
{
    return [
        'aberta'                 => 'admin-badge-info',
        'em_andamento'           => 'admin-badge-alerta',
        'aguardando_aprovacao'   => 'admin-badge-alerta',
        'aprovado'               => 'admin-badge-sucesso',
        'aguardando_peca'        => 'admin-badge-alerta',
        'em_execucao'            => 'admin-badge-info',
        'pronta'                 => 'admin-badge-sucesso',
        'aguardando_pagamento'   => 'admin-badge-erro',
        'aguardando_retirada'    => 'admin-badge-sucesso',
        'concluida'              => 'admin-badge-sucesso',
        'cancelada'              => 'admin-badge-erro',
    ][$s] ?? 'admin-badge-info';
}

/* =========================================================
   STATUS DO ORÇAMENTO
   ========================================================= */
function nomeStatusOrc($s)
{
    return [
        'rascunho'            => 'Rascunho',
        'aguardando_revisao'  => 'Aguardando Revisão',
        'pronto_para_envio'   => 'Pronto pra Enviar',
        'enviado'             => 'Enviado ao Cliente',
        'aprovado'            => 'Aprovado',
        'aprovado_parcial'    => 'Aprovado Parcial',
        'recusado'            => 'Recusado',
        'expirado'            => 'Expirado',
        'adendo_gerado'       => 'Adendo Gerado',
        'adendo_enviado'      => 'Adendo Enviado',
        'adendo_aprovado'     => 'Adendo Aprovado',
        'adendo_reprovado'    => 'Adendo Reprovado',
        'aguardando_pagamento' => 'Aguardando Pagamento',
        'arquivado'           => 'Arquivado',
    ][$s] ?? $s;
}

function classeStatusOrc($s)
{
    return [
        'rascunho'            => 'admin-badge-info',
        'aguardando_revisao'  => 'admin-badge-alerta',
        'pronto_para_envio'   => 'admin-badge-info',
        'enviado'             => 'admin-badge-info',
        'aprovado'            => 'admin-badge-sucesso',
        'aprovado_parcial'    => 'admin-badge-alerta',
        'recusado'            => 'admin-badge-erro',
        'expirado'            => 'admin-badge-erro',
        'adendo_gerado'       => 'admin-badge-alerta',
        'adendo_enviado'      => 'admin-badge-info',
        'adendo_aprovado'     => 'admin-badge-sucesso',
        'adendo_reprovado'    => 'admin-badge-erro',
        'aguardando_pagamento' => 'admin-badge-erro',
        'arquivado'           => 'admin-badge-info',
    ][$s] ?? 'admin-badge-info';
}

/* =========================================================
   ALIASES DE COMPATIBILIDADE
   (telas antigas que chamam esses nomes)
   ========================================================= */
function nomeStatus($s)
{
    return nomeStatusOS($s);
}
function classeStatus($s)
{
    return classeStatusOS($s);
}
function nomeStatusDash($s)
{
    return nomeStatusOS($s);
}
function classeStatusDash($s)
{
    return classeStatusOS($s);
}
function nomeStatusPag($s)
{
    return nomeStatusOrc($s);
}
function classeStatusPag($s)
{
    return classeStatusOrc($s);
}

function nomeStatusCompra($s)
{
    return [
        'aguardando_aprovacao' => 'Aguardando Aprovação',
        'aprovada'             => 'Aprovada',
        'comprada'             => 'Comprada',
        'recebida'             => 'Recebida',
        'negada'               => 'Negada',
        'cancelada'            => 'Cancelada',
    ][$s] ?? $s;
}

function classeStatusCompra($s)
{
    return [
        'aguardando_aprovacao' => 'admin-badge-info',
        'aprovada'             => 'admin-badge-alerta',
        'comprada'             => 'admin-badge-info',
        'recebida'             => 'admin-badge-sucesso',
        'negada'               => 'admin-badge-erro',
        'cancelada'            => 'admin-badge-erro',
    ][$s] ?? 'admin-badge-info';
}

function nomeStatusC($s)
{
    return nomeStatusCompra($s);
}
function classeStatusC($s)
{
    return classeStatusCompra($s);
}

/* =========================================================
   ORÇAMENTO — SALDO DEVEDOR
   ========================================================= */
function calcularSaldoOrcamento($conn, $id_orcamento)
{
    $stmt = $conn->prepare("SELECT valor_total FROM os_orcamentos WHERE id_orcamento = ? LIMIT 1");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $o = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$o) {
        return ['total' => 0, 'pago' => 0, 'saldo' => 0, 'quitado' => false];
    }

    $total = (float)$o['valor_total'];

    $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) AS pago
                            FROM os_orcamento_pagamentos
                            WHERE id_orcamento = ? AND status = 'confirmado'");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $pago = (float)$stmt->get_result()->fetch_assoc()['pago'];
    $stmt->close();

    $saldo = $total - $pago;
    if ($saldo < 0) $saldo = 0;

    return [
        'total'    => $total,
        'pago'     => $pago,
        'saldo'    => $saldo,
        'quitado'  => ($saldo <= 0.009),
    ];
}

/* =========================================================
   ORÇAMENTO — RECALCULAR VALORES
   ========================================================= */
function recalcularOrcamento($conn, $id_orcamento)
{
    $stmt = $conn->prepare("SELECT valor_mao_obra, valor_desconto FROM os_orcamentos WHERE id_orcamento = ? LIMIT 1");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $o = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$o) return;

    $mao_obra = (float)$o['valor_mao_obra'];
    $desconto = (float)$o['valor_desconto'];

    $stmt = $conn->prepare("SELECT COALESCE(SUM(valor_total), 0) AS total FROM os_orcamento_itens
                            WHERE id_orcamento = ? AND tipo = 'peca'");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $pecas = (float)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(valor_total), 0) AS total FROM os_orcamento_itens
                            WHERE id_orcamento = ? AND tipo = 'servico'");
    $stmt->bind_param('i', $id_orcamento);
    $stmt->execute();
    $servicos = (float)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $mao_obra_total = $mao_obra + $servicos;
    $total = ($pecas + $mao_obra_total) - $desconto;
    if ($total < 0) $total = 0;

    $stmt = $conn->prepare("
        UPDATE os_orcamentos
        SET valor_pecas = ?,
            valor_mao_obra = ?,
            valor_total = ?
        WHERE id_orcamento = ?
    ");
    $stmt->bind_param('dddi', $pecas, $mao_obra_total, $total, $id_orcamento);
    $stmt->execute();
    $stmt->close();
}

/* =========================================================
   OS — ATUALIZAR STATUS BASEADO NAS SOLICITAÇÕES
   ========================================================= */
function atualizarStatusOSPorSolicitacoes($conn, $id_os)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS nao_resolvidas
        FROM os_solicitacoes_peca
        WHERE id_os = ? AND status IN ('pendente','aprovada_estoque','aprovada_compra')
    ");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $nao_resolvidas = (int)$stmt->get_result()->fetch_assoc()['nao_resolvidas'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT status FROM ordens_servico WHERE id_os = ? LIMIT 1");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $os_atual = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$os_atual) return;

    $status_finais = ['concluida', 'cancelada', 'pronta', 'aguardando_pagamento', 'aguardando_retirada'];
    if (in_array($os_atual['status'], $status_finais)) return;

    if ($nao_resolvidas > 0) {
        $conn->query("UPDATE ordens_servico SET status = 'aguardando_peca' WHERE id_os = {$id_os}");
    } else {
        $conn->query("UPDATE ordens_servico SET status = 'em_andamento' WHERE id_os = {$id_os}");
    }
}

/* =========================================================
   OS — DEFINIR PRÓXIMO STATUS APÓS APONTAMENTO/DESAPONTAMENTO
   ========================================================= */
function decidirStatusAposDesapontar($conn, $id_os)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS nao_resolvidas
        FROM os_solicitacoes_peca
        WHERE id_os = ? AND status IN ('pendente','aprovada_estoque','aprovada_compra')
    ");
    $stmt->bind_param('i', $id_os);
    $stmt->execute();
    $nao_resolvidas = (int)$stmt->get_result()->fetch_assoc()['nao_resolvidas'];
    $stmt->close();

    if ($nao_resolvidas > 0) {
        return 'aguardando_aprovacao';
    }
    return 'em_andamento';
}

/* =========================================================
   UNIDADE DE MEDIDA
   ========================================================= */
function nomeUnidade($u)
{
    return [
        'un' => 'Unidade',
        'kg' => 'Quilograma',
        'g'  => 'Grama',
        'L'  => 'Litro',
        'mL' => 'Mililitro',
        'm'  => 'Metro',
        'cx' => 'Caixa',
    ][$u] ?? 'Unidade';
}

function abreviacaoUnidade($u)
{
    return [
        'un' => 'un',
        'kg' => 'kg',
        'g'  => 'g',
        'L'  => 'L',
        'mL' => 'mL',
        'm'  => 'm',
        'cx' => 'cx',
    ][$u] ?? 'un';
}

function listaUnidades()
{
    return [
        'un' => 'Unidade',
        'kg' => 'Quilograma',
        'g'  => 'Grama',
        'L'  => 'Litro',
        'mL' => 'Mililitro',
        'm'  => 'Metro',
        'cx' => 'Caixa',
    ];
}
