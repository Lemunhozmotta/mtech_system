<?php
/* =========================================================
   M-TECH SYSTEM — ORÇAMENTOS (lista)
   Fluxo novo:
   - Mostra OS aguardando revisão (sem orçamento)
   - E orçamentos já enviados/aprovados
   ========================================================= */

$titulo_pagina = 'Orçamentos';
require_once '_header.php';

$polling_ativo = true;

$usuarioLogado = usuarioLogado();
if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    redirecionar('dashboard.php?erro=sem_permissao');
}

$conn = conectar();

$busca  = trim($_GET['busca'] ?? '');
$filtro = $_GET['filtro'] ?? 'ativos';

// ===== BUSCA: OS aguardando revisão (sem orçamento ainda) + orçamentos =====
$sql = "
    SELECT
        'os_nova' AS tipo,
        os.id_os,
        os.numero_os,
        os.status AS os_status,
        os.data_abertura,
        NULL AS id_orcamento,
        NULL AS numero_orcamento,
        NULL AS valor_total,
        NULL AS status_orc,
        NULL AS adendo_status,
        cl.nome AS cliente_nome,
        cr.marca, cr.modelo, cr.placa,
        (SELECT COUNT(*) FROM os_solicitacoes_peca sp WHERE sp.id_os = os.id_os AND sp.status = 'pendente') AS qtd_solic_pendentes
    FROM ordens_servico os
    INNER JOIN clientes cl ON cl.id_cliente = os.id_cliente
    INNER JOIN carros cr ON cr.id_carro = os.id_carro
    WHERE os.status = 'aguardando_aprovacao'
      AND NOT EXISTS (
          SELECT 1 FROM os_orcamentos o
          WHERE o.id_os = os.id_os
            AND o.status NOT IN ('arquivado', 'cancelado')
      )
";

// Depois concatena os orçamentos existentes
$sql .= "
    UNION ALL

    SELECT
        'orcamento' AS tipo,
        o.id_os,
        os.numero_os,
        os.status AS os_status,
        os.data_abertura,
        o.id_orcamento,
        o.numero_orcamento,
        o.valor_total,
        o.status AS status_orc,
        o.adendo_status,
        cl.nome AS cliente_nome,
        cr.marca, cr.modelo, cr.placa,
        0 AS qtd_solic_pendentes
    FROM os_orcamentos o
    INNER JOIN ordens_servico os ON os.id_os = o.id_os
    INNER JOIN clientes cl ON cl.id_cliente = os.id_cliente
    INNER JOIN carros cr ON cr.id_carro = os.id_carro
    WHERE o.status NOT IN ('arquivado')
";

$params = [];
$tipos = '';

if ($busca !== '') {
    $sql = "SELECT * FROM ({$sql}) AS resultados
            WHERE numero_os LIKE ? OR numero_orcamento LIKE ?
               OR cliente_nome LIKE ? OR placa LIKE ?";
    $t = '%' . $busca . '%';
    $params = [$t, $t, $t, $t];
    $tipos = 'ssss';
} else {
    $sql = "SELECT * FROM ({$sql}) AS resultados";
}

$sql .= " ORDER BY id_os DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== CONTADORES =====
// OS aguardando revisão
$res = $conn->query("
    SELECT COUNT(*) AS total FROM ordens_servico os
    WHERE os.status = 'aguardando_aprovacao'
      AND NOT EXISTS (
          SELECT 1 FROM os_orcamentos o
          WHERE o.id_os = os.id_os
            AND o.status NOT IN ('arquivado', 'cancelado')
      )
");
$total_aguardando_revisao = (int)$res->fetch_assoc()['total'];

// Orçamentos enviados
$res = $conn->query("SELECT COUNT(*) AS total FROM os_orcamentos WHERE status = 'gerado_enviado'");
$total_enviados = (int)$res->fetch_assoc()['total'];

// Orçamentos aprovados
$res = $conn->query("SELECT COUNT(*) AS total FROM os_orcamentos WHERE status = 'aprovado'");
$total_aprovados = (int)$res->fetch_assoc()['total'];

// Orçamentos cancelados
$res = $conn->query("SELECT COUNT(*) AS total FROM os_orcamentos WHERE status = 'cancelado'");
$total_cancelados = (int)$res->fetch_assoc()['total'];

$conn->close();
?>

<h1 class="admin-titulo-pagina">Orçamentos</h1>

<?php
$msg = $_GET['msg'] ?? '';
$mensagens = [
    'enviado'           => ['texto' => 'Orçamento enviado ao cliente!', 'tipo' => 'sucesso'],
    'editado'           => ['texto' => 'Orçamento atualizado!', 'tipo' => 'sucesso'],
    'item_add'          => ['texto' => 'Item adicionado.', 'tipo' => 'sucesso'],
    'item_del'          => ['texto' => 'Item removido.', 'tipo' => 'alerta'],
    'aprovado_manual'   => ['texto' => 'Orçamento aprovado manualmente!', 'tipo' => 'sucesso'],
    'adendo_aprovado'   => ['texto' => 'Adendo aprovado!', 'tipo' => 'sucesso'],
    'adendo_reprovado'  => ['texto' => 'Adendo reprovado.', 'tipo' => 'alerta'],
    'erro'              => ['texto' => 'Ocorreu um erro.', 'tipo' => 'erro'],
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

<!-- ===== CONTADORES ===== -->
<div class="admin-cards-resumo" style="grid-template-columns: repeat(4, 1fr);">
    <div class="admin-card-resumo amarelo">
        <div class="admin-card-resumo-icone"><i class="fas fa-hourglass-half"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $total_aguardando_revisao; ?></div>
        <div class="admin-card-resumo-titulo">Aguardando Revisão</div>
    </div>
    <div class="admin-card-resumo azul">
        <div class="admin-card-resumo-icone"><i class="fas fa-paper-plane"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $total_enviados; ?></div>
        <div class="admin-card-resumo-titulo">Enviados ao Cliente</div>
    </div>
    <div class="admin-card-resumo verde">
        <div class="admin-card-resumo-icone"><i class="fas fa-check-circle"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $total_aprovados; ?></div>
        <div class="admin-card-resumo-titulo">Aprovados</div>
    </div>
    <div class="admin-card-resumo" style="border-left-color: #666;">
        <div class="admin-card-resumo-icone"><i class="fas fa-ban"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $total_cancelados; ?></div>
        <div class="admin-card-resumo-titulo">Cancelados</div>
    </div>
</div>

<!-- ===== FILTROS ===== -->
<div class="admin-bloco">
    <form method="GET" class="admin-barra-acoes" id="formFiltrosOrc">
        <div class="admin-busca">
            <i class="fas fa-search"></i>
            <input type="text" name="busca" placeholder="Buscar por OS, orçamento, cliente ou placa..."
                value="<?php echo limpar($busca); ?>">
        </div>
        <select name="filtro" class="admin-select-filtro" id="filtroOrc">
            <option value="ativos" <?php echo $filtro === 'ativos' ? 'selected' : ''; ?>>Todos</option>
            <option value="aguardando" <?php echo $filtro === 'aguardando' ? 'selected' : ''; ?>>Aguardando Revisão
            </option>
            <option value="enviado" <?php echo $filtro === 'enviado' ? 'selected' : ''; ?>>Enviados</option>
            <option value="aprovado" <?php echo $filtro === 'aprovado' ? 'selected' : ''; ?>>Aprovados</option>
            <option value="cancelado" <?php echo $filtro === 'cancelado' ? 'selected' : ''; ?>>Cancelados</option>
        </select>
        <button type="submit" class="admin-btn admin-btn-secundario"><i class="fas fa-search"></i> Buscar</button>
    </form>
</div>

<!-- ===== LISTA ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo">
        <span><i class="fas fa-file-invoice-dollar"></i> Lista</span>
        <span><?php echo count($resultados); ?> registro(s)</span>
    </div>

    <?php if (empty($resultados)): ?>
        <div class="admin-vazio">
            <i class="fas fa-file-invoice"></i>
            <p>Nenhum orçamento ou solicitação pendente.</p>
            <small>Quando o mecânico solicitar peças, aparece aqui pra revisão.</small>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="admin-tabela">
                <thead>
                    <tr>
                        <th>OS</th>
                        <th>Orçamento</th>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th style="text-align:right;">Valor</th>
                        <th>Status</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados as $r): ?>
                        <tr>
                            <td>
                                <strong style="font-family: monospace; letter-spacing: 1px;">
                                    <?php echo limpar($r['numero_os']); ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($r['tipo'] === 'orcamento'): ?>
                                    <strong style="font-family: monospace;"><?php echo limpar($r['numero_orcamento']); ?></strong>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-alerta">
                                        <i class="fas fa-plus-circle"></i> A gerar
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo limpar($r['cliente_nome']); ?></td>
                            <td>
                                <strong><?php echo limpar($r['marca']); ?></strong> <?php echo limpar($r['modelo']); ?>
                                <br><small style="color:var(--mtech-text-muted); font-family:monospace;">
                                    <?php echo limpar(strtoupper($r['placa'])); ?>
                                </small>
                            </td>
                            <td style="text-align:right; font-weight:600;">
                                <?php if ($r['valor_total'] !== null): ?>
                                    R$ <?php echo number_format((float)$r['valor_total'], 2, ',', '.'); ?>
                                <?php else: ?>
                                    <span style="color:var(--mtech-text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['tipo'] === 'os_nova'): ?>
                                    <span class="admin-badge admin-badge-alerta">
                                        <i class="fas fa-hourglass-half"></i> Aguardando Revisão
                                    </span>
                                    <?php if ((int)$r['qtd_solic_pendentes'] > 0): ?>
                                        <br><small style="color:var(--mtech-text-muted);">
                                            <?php echo (int)$r['qtd_solic_pendentes']; ?> peça(s) pedida(s)
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="admin-badge <?php echo classeStatusOrc($r['status_orc']); ?>">
                                        <?php echo nomeStatusOrc($r['status_orc']); ?>
                                    </span>
                                    <?php if (!empty($r['adendo_status']) && $r['adendo_status'] !== 'nenhum'): ?>
                                        <br><span class="admin-badge <?php echo classeStatusAdendo($r['adendo_status']); ?>"
                                            style="font-size:9px; margin-top:4px;">
                                            <?php echo nomeStatusAdendo($r['adendo_status']); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right; white-space:nowrap;">
                                <?php if ($r['tipo'] === 'os_nova'): ?>
                                    <a href="orcamentos_ver.php?id_os=<?php echo (int)$r['id_os']; ?>" class="admin-btn-acao"
                                        title="Revisar e criar orçamento">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="orcamentos_ver.php?id_orcamento=<?php echo (int)$r['id_orcamento']; ?>"
                                        class="admin-btn-acao" title="Ver / Editar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once '_footer.php'; ?>