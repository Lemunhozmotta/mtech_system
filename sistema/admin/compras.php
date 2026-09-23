<?php
/* =========================================================
   M-TECH SYSTEM — COMPRAS (lista)
   ========================================================= */

$titulo_pagina = 'Compras';
require_once '_header.php';

$polling_ativo = true;

$usuarioLogado = usuarioLogado();
$conn = conectar();

$podeGerenciar = in_array($usuarioLogado['nivel'], [1, 2]);
$podeVerValores = in_array($usuarioLogado['nivel'], [1, 2]);

$busca = trim($_GET['busca'] ?? '');
$filtro = $_GET['filtro'] ?? 'ativas';

$sql = "SELECT c.*,
               os.numero_os,
               u_sol.nome AS solicitou_nome,
               u_apr.nome AS aprovou_nome
        FROM compras_solicitacoes c
        LEFT JOIN ordens_servico os ON os.id_os = c.id_os
        INNER JOIN usuarios u_sol ON u_sol.id_usuario = c.id_usuario_solicitou
        LEFT JOIN usuarios u_apr ON u_apr.id_usuario = c.id_usuario_aprovou
        WHERE 1 = 1";

$params = [];
$tipos = '';

if ($filtro === 'ativas') {
    $sql .= " AND c.status IN ('aprovada','comprada')";
} elseif ($filtro === 'aprovada') {
    $sql .= " AND c.status = 'aprovada'";
} elseif ($filtro === 'comprada') {
    $sql .= " AND c.status = 'comprada'";
} elseif ($filtro === 'recebida') {
    $sql .= " AND c.status = 'recebida'";
} elseif ($filtro === 'negada') {
    $sql .= " AND c.status = 'negada'";
} elseif ($filtro === 'cancelada') {
    $sql .= " AND c.status = 'cancelada'";
}

if ($busca !== '') {
    $sql .= " AND (c.nome_peca LIKE ? OR c.fornecedor LIKE ?
                   OR os.numero_os LIKE ? OR u_sol.nome LIKE ?)";
    $t = '%' . $busca . '%';
    $params = [$t, $t, $t, $t];
    $tipos = 'ssss';
}

$sql .= " ORDER BY c.id_compra DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$compras = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== CONTADORES =====
$cont = ['aprovada' => 0, 'comprada' => 0, 'recebida' => 0, 'negada' => 0, 'cancelada' => 0];
$res = $conn->query("SELECT status, COUNT(*) AS total FROM compras_solicitacoes GROUP BY status");
while ($r = $res->fetch_assoc()) $cont[$r['status']] = (int)$r['total'];

$conn->close();

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
?>

<h1 class="admin-titulo-pagina">Compras</h1>

<?php
$msg = $_GET['msg'] ?? '';
$mensagens = [
    'comprada'      => ['texto' => 'Compra marcada como realizada!', 'tipo' => 'sucesso'],
    'recebida'      => ['texto' => 'Peça recebida! Entrada registrada no estoque.', 'tipo' => 'sucesso'],
    'negada'        => ['texto' => 'Compra negada.', 'tipo' => 'alerta'],
    'cancelada'     => ['texto' => 'Compra cancelada.', 'tipo' => 'alerta'],
    'editada'       => ['texto' => 'Compra atualizada!', 'tipo' => 'sucesso'],
    'erro'          => ['texto' => 'Ocorreu um erro.', 'tipo' => 'erro'],
    'erro_obrig'    => ['texto' => 'Preencha todos os campos obrigatórios.', 'tipo' => 'erro'],
    'sem_permissao' => ['texto' => 'Você não tem permissão para essa ação.', 'tipo' => 'erro'],
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

<div class="admin-cards-resumo" style="grid-template-columns: repeat(4, 1fr);">
    <div class="admin-card-resumo amarelo">
        <div class="admin-card-resumo-icone"><i class="fas fa-hourglass-half"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $cont['aprovada']; ?></div>
        <div class="admin-card-resumo-titulo">Aprovadas (a comprar)</div>
    </div>
    <div class="admin-card-resumo azul">
        <div class="admin-card-resumo-icone"><i class="fas fa-shopping-cart"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $cont['comprada']; ?></div>
        <div class="admin-card-resumo-titulo">Compradas (a receber)</div>
    </div>
    <div class="admin-card-resumo verde">
        <div class="admin-card-resumo-icone"><i class="fas fa-check-circle"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $cont['recebida']; ?></div>
        <div class="admin-card-resumo-titulo">Recebidas</div>
    </div>
    <div class="admin-card-resumo" style="border-left-color: #666;">
        <div class="admin-card-resumo-icone"><i class="fas fa-ban"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $cont['negada'] + $cont['cancelada']; ?></div>
        <div class="admin-card-resumo-titulo">Negadas / Canceladas</div>
    </div>
</div>

<div class="admin-bloco">
    <form method="GET" class="admin-barra-acoes" id="formFiltrosCompras">
        <div class="admin-busca">
            <i class="fas fa-search"></i>
            <input type="text" name="busca" placeholder="Buscar por peça, fornecedor, OS ou solicitante..."
                value="<?php echo limpar($busca); ?>">
        </div>

        <select name="filtro" class="admin-select-filtro" id="filtroCompras">
            <option value="ativas" <?php echo $filtro === 'ativas' ? 'selected' : ''; ?>>Ativas (a comprar + compradas)
            </option>
            <option value="aprovada" <?php echo $filtro === 'aprovada' ? 'selected' : ''; ?>>Aprovadas (a comprar)
            </option>
            <option value="comprada" <?php echo $filtro === 'comprada' ? 'selected' : ''; ?>>Compradas (a receber)
            </option>
            <option value="recebida" <?php echo $filtro === 'recebida' ? 'selected' : ''; ?>>Recebidas</option>
            <option value="negada" <?php echo $filtro === 'negada' ? 'selected' : ''; ?>>Negadas</option>
            <option value="cancelada" <?php echo $filtro === 'cancelada' ? 'selected' : ''; ?>>Canceladas</option>
            <option value="todas" <?php echo $filtro === 'todas' ? 'selected' : ''; ?>>Todas</option>
        </select>

        <button type="submit" class="admin-btn admin-btn-secundario"><i class="fas fa-search"></i> Filtrar</button>
    </form>
</div>

<div class="admin-bloco">
    <div class="admin-bloco-titulo">
        <span><i class="fas fa-shopping-cart"></i> Solicitações de Compra</span>
        <span><?php echo count($compras); ?> registro(s)</span>
    </div>

    <?php if (empty($compras)): ?>
        <div class="admin-vazio">
            <i class="fas fa-shopping-cart"></i>
            <p>Nenhuma solicitação de compra.</p>
            <small>Quando o admin aprovar uma peça sem estoque, ela aparece aqui.</small>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="admin-tabela">
                <thead>
                    <tr>
                        <th>Peça</th>
                        <th style="text-align:center;">Qtd</th>
                        <th>Fornecedor</th>
                        <?php if ($podeVerValores): ?>
                            <th style="text-align:right;">Valor total</th>
                        <?php endif; ?>
                        <th>OS</th>
                        <th>Solicitou</th>
                        <th>Status</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compras as $c): ?>
                        <tr>
                            <td><strong><?php echo limpar($c['nome_peca']); ?></strong></td>
                            <td style="text-align:center;"><?php echo number_format((float)$c['quantidade'], 0, ',', '.'); ?>
                            </td>
                            <td><?php echo $c['fornecedor'] ? limpar($c['fornecedor']) : '<span style="color:var(--mtech-text-muted);">—</span>'; ?>
                            </td>
                            <?php if ($podeVerValores): ?>
                                <td style="text-align:right;">
                                    <?php echo $c['valor_total'] !== null ? 'R$ ' . number_format((float)$c['valor_total'], 2, ',', '.') : '<span style="color:var(--mtech-text-muted);">—</span>'; ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <?php if ($c['numero_os']): ?>
                                    <a href="ordens_ver.php?id=<?php echo (int)$c['id_os']; ?>"
                                        style="color:var(--mtech-yellow); font-family:monospace;">
                                        <?php echo limpar($c['numero_os']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--mtech-text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo limpar($c['solicitou_nome']); ?></td>
                            <td><span
                                    class="admin-badge <?php echo classeStatusCompra($c['status']); ?>"><?php echo nomeStatusCompra($c['status']); ?></span>
                            </td>
                            <td style="text-align:right; white-space:nowrap;">
                                <a href="compras_ver.php?id=<?php echo (int)$c['id_compra']; ?>" class="admin-btn-acao"
                                    title="Ver">
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
        const sel = document.getElementById('filtroCompras');
        const form = document.getElementById('formFiltrosCompras');
        if (sel && form) sel.addEventListener('change', () => form.submit());
    })();
</script>

<?php require_once '_footer.php'; ?>