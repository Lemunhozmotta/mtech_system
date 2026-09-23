<?php
/* =========================================================
   M-TECH SYSTEM — ORÇAMENTOS (lista)
   Só níveis 1 e 2
   ========================================================= */

$titulo_pagina = 'Orçamentos';
require_once '_header.php';

$polling_ativo = true;

$usuarioLogado = usuarioLogado();
if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    redirecionar('dashboard.php?erro=sem_permissao');
}

$conn = conectar();

$busca = trim($_GET['busca'] ?? '');
$filtro = $_GET['filtro'] ?? 'ativos';

$sql = "SELECT o.*,
               os.numero_os, os.status AS os_status,
               cl.nome AS cliente_nome,
               cr.marca, cr.modelo, cr.placa
        FROM os_orcamentos o
        INNER JOIN ordens_servico os ON os.id_os = o.id_os
        INNER JOIN clientes cl ON cl.id_cliente = os.id_cliente
        INNER JOIN carros cr ON cr.id_carro = os.id_carro
        WHERE 1 = 1";

$params = [];
$tipos = '';

if ($filtro === 'ativos') {
    $sql .= " AND o.status IN ('aguardando_revisao','pronto_para_envio','enviado')";
} elseif ($filtro === 'aguardando_revisao') {
    $sql .= " AND o.status = 'aguardando_revisao'";
} elseif ($filtro === 'enviado') {
    $sql .= " AND o.status = 'enviado'";
} elseif ($filtro === 'aprovado') {
    $sql .= " AND o.status = 'aprovado'";
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
$cont = ['aguardando_revisao' => 0, 'enviado' => 0, 'aprovado' => 0, 'recusado' => 0];
$res = $conn->query("SELECT status, COUNT(*) AS total FROM os_orcamentos GROUP BY status");
while ($r = $res->fetch_assoc()) $cont[$r['status']] = (int)$r['total'];

$conn->close();

function nomeStatusOrc($s)
{
    return [
        'rascunho'           => 'Rascunho',
        'aguardando_revisao' => 'Aguardando Revisão',
        'pronto_para_envio'  => 'Pronto pra Enviar',
        'enviado'            => 'Enviado ao Cliente',
        'aprovado'           => 'Aprovado',
        'aprovado_parcial'   => 'Aprovado Parcial',
        'recusado'           => 'Recusado',
        'expirado'           => 'Expirado',
    ][$s] ?? $s;
}
function classeStatusOrc($s)
{
    return [
        'rascunho'           => 'admin-badge-info',
        'aguardando_revisao' => 'admin-badge-alerta',
        'pronto_para_envio'  => 'admin-badge-info',
        'enviado'            => 'admin-badge-info',
        'aprovado'           => 'admin-badge-sucesso',
        'aprovado_parcial'   => 'admin-badge-alerta',
        'recusado'           => 'admin-badge-erro',
        'expirado'           => 'admin-badge-erro',
    ][$s] ?? 'admin-badge-info';
}
?>

<h1 class="admin-titulo-pagina">Orçamentos</h1>

<?php
$msg = $_GET['msg'] ?? '';
$mensagens = [
    'enviado'     => ['texto' => 'Orçamento enviado ao cliente!', 'tipo' => 'sucesso'],
    'aprovado'    => ['texto' => 'Orçamento aprovado! Peças liberadas.', 'tipo' => 'sucesso'],
    'recusado'    => ['texto' => 'Orçamento recusado. OS será cancelada.', 'tipo' => 'alerta'],
    'pago'        => ['texto' => 'Pagamento registrado! OS agora aguarda peças.', 'tipo' => 'sucesso'],
    'editado'     => ['texto' => 'Orçamento atualizado!', 'tipo' => 'sucesso'],
    'erro'        => ['texto' => 'Ocorreu um erro.', 'tipo' => 'erro'],
    'erro_obrig'  => ['texto' => 'Preencha todos os campos obrigatórios.', 'tipo' => 'erro'],
    'sem_permissao' => ['texto' => 'Você não tem permissão.', 'tipo' => 'erro'],
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

<div class="admin-cards-resumo">
    <div class="admin-card-resumo amarelo">
        <div class="admin-card-resumo-icone"><i class="fas fa-hourglass-half"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $cont['aguardando_revisao']; ?></div>
        <div class="admin-card-resumo-titulo">Aguardando Revisão</div>
    </div>
    <div class="admin-card-resumo azul">
        <div class="admin-card-resumo-icone"><i class="fas fa-paper-plane"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $cont['enviado']; ?></div>
        <div class="admin-card-resumo-titulo">Enviados ao Cliente</div>
    </div>
    <div class="admin-card-resumo verde">
        <div class="admin-card-resumo-icone"><i class="fas fa-check-circle"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $cont['aprovado']; ?></div>
        <div class="admin-card-resumo-titulo">Aprovados</div>
    </div>
    <div class="admin-card-resumo" style="border-left-color: #666;">
        <div class="admin-card-resumo-icone"><i class="fas fa-ban"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $cont['recusado']; ?></div>
        <div class="admin-card-resumo-titulo">Recusados</div>
    </div>
</div>

<div class="admin-bloco">
    <form method="GET" class="admin-barra-acoes" id="formFiltrosOrc">
        <div class="admin-busca">
            <i class="fas fa-search"></i>
            <input type="text" name="busca" placeholder="Buscar por nº orçamento, OS, cliente ou placa..."
                value="<?php echo limpar($busca); ?>">
        </div>

        <select name="filtro" class="admin-select-filtro" id="filtroOrc">
            <option value="ativos" <?php echo $filtro === 'ativos' ? 'selected' : ''; ?>>Ativos</option>
            <option value="aguardando_revisao" <?php echo $filtro === 'aguardando_revisao' ? 'selected' : ''; ?>>
                Aguardando Revisão</option>
            <option value="enviado" <?php echo $filtro === 'enviado' ? 'selected' : ''; ?>>Enviados</option>
            <option value="aprovado" <?php echo $filtro === 'aprovado' ? 'selected' : ''; ?>>Aprovados</option>
            <option value="recusado" <?php echo $filtro === 'recusado' ? 'selected' : ''; ?>>Recusados</option>
            <option value="todos" <?php echo $filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
        </select>

        <button type="submit" class="admin-btn admin-btn-secundario"><i class="fas fa-search"></i> Buscar</button>
    </form>
</div>

<div class="admin-bloco">
    <div class="admin-bloco-titulo">
        <span><i class="fas fa-file-invoice-dollar"></i> Lista de Orçamentos</span>
        <span><?php echo count($orcamentos); ?> registro(s)</span>
    </div>

    <?php if (empty($orcamentos)): ?>
        <div class="admin-vazio">
            <i class="fas fa-file-invoice"></i>
            <p>Nenhum orçamento.</p>
            <small>Quando o mecânico solicitar peças, um orçamento é criado automaticamente.</small>
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
                                <?php if ((int)$o['versao'] > 1): ?>
                                    <span class="admin-badge admin-badge-info"
                                        style="font-size:10px; margin-left:4px;">v<?php echo (int)$o['versao']; ?></span>
                                <?php endif; ?>
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
                                <span class="admin-badge <?php echo classeStatusOrc($o['status']); ?>">
                                    <?php echo nomeStatusOrc($o['status']); ?>
                                </span>
                            </td>
                            <td style="text-align:right; white-space:nowrap;">
                                <a href="orcamentos_ver.php?id=<?php echo (int)$o['id_orcamento']; ?>" class="admin-btn-acao"
                                    title="Ver / Revisar">
                                    <i class="fas fa-eye"></i>
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
        const sel = document.getElementById('filtroOrc');
        const form = document.getElementById('filtrosOrc');
        const formReal = document.getElementById('formFiltrosOrc');
        if (sel && formReal) sel.addEventListener('change', () => formReal.submit());
    })();
</script>

<?php require_once '_footer.php'; ?>