<?php
/* =========================================================
   M-TECH SYSTEM — PAGAMENTOS (lista de orçamentos)
   Só níveis 1 e 2
   ========================================================= */

$titulo_pagina = 'Pagamentos';
require_once '_header.php';

$polling_ativo = true;

$usuarioLogado = usuarioLogado();
if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    redirecionar('dashboard.php?erro=sem_permissao');
}

$conn = conectar();

$busca = trim($_GET['busca'] ?? '');
$filtro = $_GET['filtro'] ?? 'aguardando';

$sql = "SELECT o.*,
               os.numero_os, os.status AS os_status,
               cl.nome AS cliente_nome,
               cr.marca, cr.modelo, cr.placa,
               (SELECT COUNT(*) FROM os_orcamento_pagamentos p
                WHERE p.id_orcamento = o.id_orcamento AND p.status = 'confirmado') AS pagos
        FROM os_orcamentos o
        INNER JOIN ordens_servico os ON os.id_os = o.id_os
        INNER JOIN clientes cl ON cl.id_cliente = os.id_cliente
        INNER JOIN carros cr ON cr.id_carro = os.id_carro
        WHERE 1 = 1";

$params = [];
$tipos = '';

if ($filtro === 'aguardando') {
    // Enviados (aguardando o cliente pagar) OU aprovados sem pagamento
    $sql .= " AND o.status IN ('enviado', 'aprovado')";
    $sql .= " AND NOT EXISTS (
                SELECT 1 FROM os_orcamento_pagamentos p
                WHERE p.id_orcamento = o.id_orcamento AND p.status = 'confirmado'
              )";
} elseif ($filtro === 'enviado') {
    $sql .= " AND o.status = 'enviado'";
} elseif ($filtro === 'aprovado') {
    $sql .= " AND o.status = 'aprovado' AND NOT EXISTS (
                SELECT 1 FROM os_orcamento_pagamentos p
                WHERE p.id_orcamento = o.id_orcamento AND p.status = 'confirmado'
              )";
} elseif ($filtro === 'pago') {
    $sql .= " AND EXISTS (
                SELECT 1 FROM os_orcamento_pagamentos p
                WHERE p.id_orcamento = o.id_orcamento AND p.status = 'confirmado'
              )";
} elseif ($filtro === 'recusado') {
    $sql .= " AND o.status = 'recusado'";
}

if ($busca !== '') {
    $sql .= " AND (o.numero_orcamento LIKE ? OR os.numero_os LIKE ?
                   OR cl.nome LIKE ? OR cr.placa LIKE ?)";
    $t = '%' . $busca . '%';
    $params = [$t, $t, $t, $t];
    $tipos = 'ssss';
}

$sql .= " ORDER BY o.id_orcamento DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$orcamentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Contadores
$res = $conn->query("SELECT COUNT(*) AS total FROM os_orcamentos WHERE status = 'aprovado'");
$total_aguardando = (int)$res->fetch_assoc()['total'];

$res = $conn->query("SELECT COUNT(*) AS total FROM os_orcamento_pagamentos WHERE status = 'confirmado'");
$total_pago = (int)$res->fetch_assoc()['total'];

$res = $conn->query("SELECT COALESCE(SUM(valor), 0) AS total FROM os_orcamento_pagamentos WHERE status = 'confirmado'");
$valor_total_pago = (float)$res->fetch_assoc()['total'];

$conn->close();

function nomeStatusPag($s)
{
    return [
        'rascunho'           => 'Rascunho',
        'aguardando_revisao' => 'Aguardando Revisão',
        'pronto_para_envio'  => 'Pronto pra Enviar',
        'enviado'            => 'Enviado (aguardando pagamento)',
        'aprovado'           => 'Aprovado (aguardando pagamento)',
        'aprovado_parcial'   => 'Aprovado Parcial',
        'recusado'           => 'Recusado',
        'expirado'           => 'Expirado',
    ][$s] ?? $s;
}
function classeStatusPag($s)
{
    return [
        'rascunho'           => 'admin-badge-info',
        'aguardando_revisao' => 'admin-badge-alerta',
        'pronto_para_envio'  => 'admin-badge-info',
        'enviado'            => 'admin-badge-info',
        'aprovado'           => 'admin-badge-alerta',
        'aprovado_parcial'   => 'admin-badge-alerta',
        'recusado'           => 'admin-badge-erro',
        'expirado'           => 'admin-badge-erro',
    ][$s] ?? 'admin-badge-info';
}
?>

<h1 class="admin-titulo-pagina">Pagamentos</h1>

<?php
$msg = $_GET['msg'] ?? '';
$mensagens = [
    'pago'     => ['texto' => 'Pagamento registrado! OS liberada pra peças.', 'tipo' => 'sucesso'],
    'erro'     => ['texto' => 'Ocorreu um erro.', 'tipo' => 'erro'],
    'erro_obrig' => ['texto' => 'Preencha os campos obrigatórios.', 'tipo' => 'erro'],
];
if (!empty($msg) && isset($mensagens[$msg])):
    $m = $mensagens[$msg];
    $icone = $m['tipo'] === 'sucesso' ? 'check-circle' : ($m['tipo'] === 'alerta' ? 'exclamation-circle' : 'times-circle');
?>
    <div class="admin-alerta admin-alerta-<?php echo $m['tipo']; ?>">
        <i class="fas fa-<?php echo $icone; ?>"></i>
        <?php echo $m['texto']; ?>
    </div>
<?php endif; ?>

<div class="admin-cards-resumo" style="grid-template-columns: repeat(3, 1fr);">
    <div class="admin-card-resumo amarelo">
        <div class="admin-card-resumo-icone"><i class="fas fa-hourglass-half"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $total_aguardando; ?></div>
        <div class="admin-card-resumo-titulo">Aguardando Pagamento</div>
    </div>
    <div class="admin-card-resumo verde">
        <div class="admin-card-resumo-icone"><i class="fas fa-check-circle"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $total_pago; ?></div>
        <div class="admin-card-resumo-titulo">Pagamentos Confirmados</div>
    </div>
    <div class="admin-card-resumo azul">
        <div class="admin-card-resumo-icone"><i class="fas fa-dollar-sign"></i></div>
        <div class="admin-card-resumo-valor" style="font-size:22px;">R$
            <?php echo number_format($valor_total_pago, 2, ',', '.'); ?></div>
        <div class="admin-card-resumo-titulo">Total Recebido</div>
    </div>
</div>

<div class="admin-bloco">
    <form method="GET" class="admin-barra-acoes" id="formFiltrosPag">
        <div class="admin-busca">
            <i class="fas fa-search"></i>
            <input type="text" name="busca" placeholder="Buscar por nº orçamento, OS, cliente ou placa..."
                value="<?php echo limpar($busca); ?>">
        </div>

        <select name="filtro" class="admin-select-filtro" id="filtroPag">
            <option value="aguardando" <?php echo $filtro === 'aguardando' ? 'selected' : ''; ?>>Aguardando Pagamento
            </option>
            <option value="enviado" <?php echo $filtro === 'enviado' ? 'selected' : ''; ?>>Enviados (não pagos)</option>
            <option value="aprovado" <?php echo $filtro === 'aprovado' ? 'selected' : ''; ?>>Aprovados (não pagos)
            </option>
            <option value="pago" <?php echo $filtro === 'pago' ? 'selected' : ''; ?>>Já Pagos</option>
            <option value="recusado" <?php echo $filtro === 'recusado' ? 'selected' : ''; ?>>Recusados</option>
            <option value="todos" <?php echo $filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
        </select>

        <button type="submit" class="admin-btn admin-btn-secundario"><i class="fas fa-search"></i> Buscar</button>
    </form>
</div>

<div class="admin-bloco">
    <div class="admin-bloco-titulo">
        <span><i class="fas fa-dollar-sign"></i> Orçamentos</span>
        <span><?php echo count($orcamentos); ?> registro(s)</span>
    </div>

    <?php if (empty($orcamentos)): ?>
        <div class="admin-vazio">
            <i class="fas fa-file-invoice-dollar"></i>
            <p>Nenhum orçamento encontrado.</p>
            <small>Quando um orçamento for aprovado, ele aparece aqui pra registro do pagamento.</small>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="admin-tabela">
                <thead>
                    <tr>
                        <th>Nº Orçamento</th>
                        <th>OS</th>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th style="text-align:right;">Valor</th>
                        <th>Status</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orcamentos as $o): ?>
                        <tr>
                            <td>
                                <strong style="font-family: monospace; letter-spacing: 1px;">
                                    <?php echo limpar($o['numero_orcamento']); ?>
                                </strong>
                            </td>
                            <td>
                                <a href="ordens_ver.php?id=<?php echo (int)$o['id_os']; ?>"
                                    style="color:var(--mtech-yellow); font-family:monospace;">
                                    <?php echo limpar($o['numero_os']); ?>
                                </a>
                            </td>
                            <td><?php echo limpar($o['cliente_nome']); ?></td>
                            <td>
                                <strong><?php echo limpar($o['marca']); ?></strong>
                                <?php echo limpar($o['modelo']); ?>
                                <br><small
                                    style="color:var(--mtech-text-muted); font-family:monospace;"><?php echo limpar(strtoupper($o['placa'])); ?></small>
                            </td>
                            <td style="text-align:right; font-weight:600;">
                                R$ <?php echo number_format((float)$o['valor_total'], 2, ',', '.'); ?>
                            </td>
                            <td>
                                <?php if ((int)$o['pagos'] > 0): ?>
                                    <span class="admin-badge admin-badge-sucesso"><i class="fas fa-check"></i> Pago</span>
                                <?php else: ?>
                                    <span class="admin-badge <?php echo classeStatusPag($o['status']); ?>">
                                        <?php echo nomeStatusPag($o['status']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right; white-space:nowrap;">
                                <a href="pagamentos_ver.php?id=<?php echo (int)$o['id_orcamento']; ?>" class="admin-btn-acao"
                                    title="Registrar Pagamento">
                                    <i class="fas fa-dollar-sign"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    (function() {
        const sel = document.getElementById('filtroPag');
        const form = document.getElementById('formFiltrosPag');
        if (sel && form) sel.addEventListener('change', () => form.submit());
    })();
</script>

<?php require_once '_footer.php'; ?>