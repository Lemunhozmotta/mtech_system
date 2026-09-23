<?php
/* =========================================================
   M-TECH SYSTEM — ENDPOINT DE POLLING (auto-refresh global)
   Retorna uma "assinatura" do estado do sistema.
   Se mudar, o cliente recarrega a página.
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

header('Content-Type: application/json; charset=utf-8');

$conn = conectar();

$partes = [];

// ===== CLIENTES =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(SUM(ativo),0) AS ativos,
           COALESCE(MAX(id_cliente),0) AS mx,
           COALESCE(SUM(CRC32(CONCAT_WS('|',
               COALESCE(nome,''),
               COALESCE(telefone,''),
               COALESCE(email,''),
               COALESCE(ativo,0)
           ))),0) AS soma
    FROM clientes
");
$row = $r->fetch_assoc();
$partes[] = 'clientes:' . implode('|', [
    $row['c'],
    $row['ativos'],
    $row['mx'],
    $row['soma']
]);

// ===== CARROS =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(SUM(ativo),0) AS ativos,
           COALESCE(MAX(id_carro),0) AS mx,
           COALESCE(SUM(CRC32(CONCAT_WS('|',
               COALESCE(marca,''),
               COALESCE(modelo,''),
               COALESCE(placa,''),
               COALESCE(km_atual,0),
               COALESCE(ativo,0)
           ))),0) AS soma
    FROM carros
");
$row = $r->fetch_assoc();
$partes[] = 'carros:' . implode('|', [
    $row['c'],
    $row['ativos'],
    $row['mx'],
    $row['soma']
]);

// ===== MARCAS E MODELOS =====
$r = $conn->query("SELECT COUNT(*) AS c, COALESCE(MAX(id_marca),0) AS mx FROM marcas");
$row = $r->fetch_assoc();
$partes[] = 'marcas:' . $row['c'] . ':' . $row['mx'];

$r = $conn->query("SELECT COUNT(*) AS c, COALESCE(MAX(id_modelo),0) AS mx FROM modelos");
$row = $r->fetch_assoc();
$partes[] = 'modelos:' . $row['c'] . ':' . $row['mx'];

// ===== ORDENS DE SERVIÇO =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(MAX(id_os),0) AS mx,
           SUM(status = 'aberta') AS abertas,
           SUM(status = 'em_andamento') AS em_and,
           SUM(status = 'aguardando_aprovacao') AS ag_aprov,
           SUM(status = 'orcamento_enviado') AS orc_env,
           SUM(status = 'aprovado') AS aprov,
           SUM(status = 'aguardando_pagamento') AS ag_pag,
           SUM(status = 'aguardando_peca') AS ag_peca,
           SUM(status = 'concluida') AS concl,
           SUM(status = 'cancelada') AS canc,
           COALESCE(SUM(CRC32(CONCAT_WS('|',
               COALESCE(status,''),
               COALESCE(descricao_problema,''),
               COALESCE(diagnostico,''),
               COALESCE(solucao,''),
               COALESCE(data_conclusao,'')
           ))),0) AS soma
    FROM ordens_servico
");
$row = $r->fetch_assoc();
$partes[] = 'os:' . implode('|', [
    $row['c'],
    $row['mx'],
    $row['abertas'] ?? 0,
    $row['em_and'] ?? 0,
    $row['ag_aprov'] ?? 0,
    $row['orc_env'] ?? 0,
    $row['aprov'] ?? 0,
    $row['ag_pag'] ?? 0,
    $row['ag_peca'] ?? 0,
    $row['concl'] ?? 0,
    $row['canc'] ?? 0,
    $row['soma']
]);

// ===== APONTAMENTOS =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(MAX(id_apontamento),0) AS mx,
           SUM(data_desapontamento IS NULL) AS abertos,
           COALESCE(SUM(CRC32(CONCAT_WS('|',
               id_os,
               id_usuario,
               COALESCE(data_apontamento,''),
               COALESCE(data_desapontamento,'')
           ))),0) AS soma
    FROM os_apontamentos
");
$row = $r->fetch_assoc();
$partes[] = 'apontamentos:' . implode('|', [
    $row['c'],
    $row['mx'],
    $row['abertos'] ?? 0,
    $row['soma']
]);

// ===== SOLICITAÇÕES DE PEÇA =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(MAX(id_solicitacao),0) AS mx,
           SUM(status = 'pendente') AS pend,
           SUM(status = 'aprovada_estoque') AS ap_est,
           SUM(status = 'aprovada_compra') AS ap_com,
           SUM(status = 'entregue') AS ent,
           SUM(status = 'negada') AS neg,
           COALESCE(SUM(CRC32(CONCAT_WS('|',
               COALESCE(status,''),
               COALESCE(nome_peca,''),
               COALESCE(quantidade,0),
               COALESCE(origem,''),
               COALESCE(motivo_recusa,'')
           ))),0) AS soma
    FROM os_solicitacoes_peca
");
$row = $r->fetch_assoc();
$partes[] = 'solicitacoes:' . implode('|', [
    $row['c'],
    $row['mx'],
    $row['pend'] ?? 0,
    $row['ap_est'] ?? 0,
    $row['ap_com'] ?? 0,
    $row['ent'] ?? 0,
    $row['neg'] ?? 0,
    $row['soma']
]);

// ===== COMPRAS =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(MAX(id_compra),0) AS mx,
           SUM(status = 'aprovada') AS aprov,
           SUM(status = 'comprada') AS comp,
           SUM(status = 'recebida') AS receb,
           SUM(status = 'negada') AS neg,
           SUM(status = 'cancelada') AS canc,
           COALESCE(SUM(CRC32(CONCAT_WS('|',
               COALESCE(status,''),
               COALESCE(nome_peca,''),
               COALESCE(fornecedor,''),
               COALESCE(valor_total,0),
               COALESCE(data_recebimento,'')
           ))),0) AS soma
    FROM compras_solicitacoes
");
$row = $r->fetch_assoc();
$partes[] = 'compras:' . implode('|', [
    $row['c'],
    $row['mx'],
    $row['aprov'] ?? 0,
    $row['comp'] ?? 0,
    $row['receb'] ?? 0,
    $row['neg'] ?? 0,
    $row['canc'] ?? 0,
    $row['soma']
]);

// ===== ESTOQUE =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(MAX(id_estoque),0) AS mx,
           COALESCE(SUM(quantidade),0) AS total_qtd,
           COALESCE(SUM(ativo),0) AS ativos,
           COALESCE(SUM(CRC32(CONCAT_WS('|',
               COALESCE(nome,''),
               COALESCE(codigo,''),
               COALESCE(quantidade,0),
               COALESCE(valor_custo,0),
               COALESCE(valor_venda,0),
               COALESCE(ativo,0)
           ))),0) AS soma
    FROM estoque
");
$row = $r->fetch_assoc();
$partes[] = 'estoque:' . implode('|', [
    $row['c'],
    $row['mx'],
    $row['total_qtd'],
    $row['ativos'],
    $row['soma']
]);

// ===== MOVIMENTAÇÕES DE ESTOQUE =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(MAX(id_movimentacao),0) AS mx
    FROM estoque_movimentacoes
");
$row = $r->fetch_assoc();
$partes[] = 'movimentacoes:' . $row['c'] . ':' . $row['mx'];

// ===== ORÇAMENTOS =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(MAX(id_orcamento),0) AS mx,
           SUM(status = 'aguardando_revisao') AS aguard_rev,
           SUM(status = 'enviado') AS enviados,
           SUM(status = 'aprovado') AS aprovados,
           SUM(status = 'recusado') AS recusados,
           COALESCE(SUM(CRC32(CONCAT_WS('|',
               COALESCE(status,''),
               COALESCE(numero_orcamento,''),
               COALESCE(valor_total,0),
               COALESCE(data_envio,'')
           ))),0) AS soma
    FROM os_orcamentos
");
$row = $r->fetch_assoc();
$partes[] = 'orcamentos:' . implode('|', [
    $row['c'],
    $row['mx'],
    $row['aguard_rev'] ?? 0,
    $row['enviados'] ?? 0,
    $row['aprovados'] ?? 0,
    $row['recusados'] ?? 0,
    $row['soma']
]);

// ===== PAGAMENTOS DE ORÇAMENTO =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(MAX(id_pagamento),0) AS mx,
           SUM(status = 'confirmado') AS confirmados,
           SUM(status = 'pendente') AS pendentes
    FROM os_orcamento_pagamentos
");
$row = $r->fetch_assoc();
$partes[] = 'pagamentos_orc:' . implode('|', [
    $row['c'],
    $row['mx'],
    $row['confirmados'] ?? 0,
    $row['pendentes'] ?? 0
]);

// ===== USUÁRIOS =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(SUM(ativo),0) AS ativos,
           COALESCE(MAX(id_usuario),0) AS mx
    FROM usuarios
");
$row = $r->fetch_assoc();
$partes[] = 'usuarios:' . $row['c'] . ':' . $row['ativos'] . ':' . $row['mx'];

// ===== AGENDAMENTOS =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(MAX(id_agendamento),0) AS mx,
           SUM(status = 'pendente') AS pend,
           SUM(status = 'confirmado') AS conf,
           SUM(status = 'concluido') AS conc,
           SUM(status = 'cancelado') AS canc
    FROM agendamentos
");
$row = $r->fetch_assoc();
$partes[] = 'agendamentos:' . implode('|', [
    $row['c'],
    $row['mx'],
    $row['pend'] ?? 0,
    $row['conf'] ?? 0,
    $row['conc'] ?? 0,
    $row['canc'] ?? 0
]);

// ===== GUINCHO =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(MAX(id_chamado),0) AS mx,
           SUM(status = 'aberto') AS ab,
           SUM(status = 'em_rota') AS er,
           SUM(status = 'concluido') AS co,
           SUM(status = 'cancelado') AS ca
    FROM guincho
");
$row = $r->fetch_assoc();
$partes[] = 'guincho:' . implode('|', [
    $row['c'],
    $row['mx'],
    $row['ab'] ?? 0,
    $row['er'] ?? 0,
    $row['co'] ?? 0,
    $row['ca'] ?? 0
]);

// ===== PAGAMENTOS (tabela antiga) =====
$r = $conn->query("
    SELECT COUNT(*) AS c, COALESCE(MAX(id_pagamento),0) AS mx,
           SUM(status = 'pendente') AS pend,
           SUM(status = 'pago') AS pag,
           SUM(status = 'atrasado') AS atr,
           SUM(status = 'cancelado') AS canc
    FROM pagamentos
");
$row = $r->fetch_assoc();
$partes[] = 'pagamentos_legado:' . implode('|', [
    $row['c'],
    $row['mx'],
    $row['pend'] ?? 0,
    $row['pag'] ?? 0,
    $row['atr'] ?? 0,
    $row['canc'] ?? 0
]);

// ===== GERA HASH FINAL =====
$conn->close();

$assinatura = implode('###', $partes);
$hash = md5($assinatura);

echo json_encode([
    'ok'    => true,
    'hash'  => $hash,
    'ts'    => time(),
]);
