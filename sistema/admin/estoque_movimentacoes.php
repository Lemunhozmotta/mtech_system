<?php
/* =========================================================
   M-TECH SYSTEM — ESTOQUE (histórico de movimentações)
   ========================================================= */

$titulo_pagina = 'Movimentações do Estoque';
require_once '_header.php';

$usuarioLogado = usuarioLogado();
$conn = conectar();

$id_item = (int)($_GET['id'] ?? 0);
$item = null;

if ($id_item > 0) {
    $stmt = $conn->prepare("SELECT * FROM estoque WHERE id_estoque = ? LIMIT 1");
    $stmt->bind_param('i', $id_item);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$filtro_tipo = $_GET['tipo'] ?? '';
$busca = trim($_GET['busca'] ?? '');

$sql = "SELECT m.*,
               e.nome AS item_nome, e.codigo AS item_codigo,
               u.nome AS usuario_nome,
               os.numero_os
        FROM estoque_movimentacoes m
        INNER JOIN estoque e ON e.id_estoque = m.id_estoque
        INNER JOIN usuarios u ON u.id_usuario = m.id_usuario
        LEFT JOIN ordens_servico os ON os.id_os = m.id_os
        WHERE 1 = 1";

$params = [];
$tipos = '';

if ($id_item > 0) {
    $sql .= " AND m.id_estoque = ?";
    $params[] = $id_item;
    $tipos .= 'i';
}

if ($filtro_tipo !== '') {
    $sql .= " AND m.tipo = ?";
    $params[] = $filtro_tipo;
    $tipos .= 's';
}

if ($busca !== '') {
    $sql .= " AND (e.nome LIKE ? OR m.motivo LIKE ? OR u.nome LIKE ?)";
    $t = '%' . $busca . '%';
    $params = array_merge($params, [$t, $t, $t]);
    $tipos .= 'sss';
}

$sql .= " ORDER BY m.id_movimentacao DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$movs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

function nomeTipoMov($t)
{
    return [
        'entrada'     => 'Entrada',
        'saida'       => 'Saída',
        'ajuste'      => 'Ajuste',
        'retirada_os' => 'Retirada (OS)',
        'devolucao'   => 'Devolução',
    ][$t] ?? $t;
}
function classeTipoMov($t)
{
    return [
        'entrada'     => 'admin-badge-sucesso',
        'saida'       => 'admin-badge-erro',
        'ajuste'      => 'admin-badge-info',
        'retirada_os' => 'admin-badge-alerta',
        'devolucao'   => 'admin-badge-info',
    ][$t] ?? 'admin-badge-info';
}
?>

<div class="admin-topo-pagina">
    <h1 class="admin-titulo-pagina">
        <a href="estoque.php" class="admin-voltar" title="Voltar"><i class="fas fa-arrow-left"></i></a>
        Movimentações
        <?php if ($item): ?>
            — <?php echo limpar($item['nome']); ?>
        <?php endif; ?>
    </h1>
</div>

<?php if ($item): ?>
    <div class="admin-bloco">
        <div class="admin-bloco-titulo"><span><i class="fas fa-box"></i> Item</span></div>
        <div class="admin-form-grid">
            <div class="admin-form-campo"><label>Nome</label>
                <input type="text" value="<?php echo limpar($item['nome']); ?>" disabled>
            </div>
            <div class="admin-form-campo"><label>Código</label>
                <input type="text" value="<?php echo limpar($item['codigo'] ?? '—'); ?>" disabled>
            </div>
            <div class="admin-form-campo"><label>Quantidade atual</label>
                <input type="text" value="<?php echo number_format((int)$item['quantidade'], 0, ',', '.'); ?>" disabled>
            </div>
            <div class="admin-form-campo"><label>Localização</label>
                <input type="text" value="<?php echo limpar($item['localizacao'] ?? '—'); ?>" disabled>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="admin-bloco">
    <form method="GET" class="admin-barra-acoes" id="formFiltrosMov">
        <?php if ($id_item > 0): ?>
            <input type="hidden" name="id" value="<?php echo $id_item; ?>">
        <?php endif; ?>

        <div class="admin-busca">
            <i class="fas fa-search"></i>
            <input type="text" name="busca" placeholder="Buscar por item, motivo ou usuário..."
                value="<?php echo limpar($busca); ?>">
        </div>

        <select name="tipo" class="admin-select-filtro" id="filtroTipoMov">
            <option value="">Todos os tipos</option>
            <option value="entrada" <?php echo $filtro_tipo === 'entrada' ? 'selected' : ''; ?>>Entrada</option>
            <option value="saida" <?php echo $filtro_tipo === 'saida' ? 'selected' : ''; ?>>Saída</option>
            <option value="ajuste" <?php echo $filtro_tipo === 'ajuste' ? 'selected' : ''; ?>>Ajuste</option>
            <option value="retirada_os" <?php echo $filtro_tipo === 'retirada_os' ? 'selected' : ''; ?>>Retirada (OS)
            </option>
            <option value="devolucao" <?php echo $filtro_tipo === 'devolucao' ? 'selected' : ''; ?>>Devolução</option>
        </select>

        <button type="submit" class="admin-btn admin-btn-secundario"><i class="fas fa-search"></i> Filtrar</button>
    </form>
</div>

<div class="admin-bloco">
    <div class="admin-bloco-titulo">
        <span><i class="fas fa-history"></i> Histórico</span>
        <span><?php echo count($movs); ?> registro(s)</span>
    </div>

    <?php if (empty($movs)): ?>
        <div class="admin-vazio">
            <i class="fas fa-inbox"></i>
            <p>Nenhuma movimentação registrada.</p>
            <small>Ajustes e retiradas aparecerão aqui.</small>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="admin-tabela">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <?php if (!$item): ?><th>Item</th><?php endif; ?>
                        <th style="text-align:center;">Qtd</th>
                        <th style="text-align:center;">Antes</th>
                        <th style="text-align:center;">Depois</th>
                        <th>Motivo</th>
                        <th>OS</th>
                        <th>Usuário</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movs as $m): ?>
                        <tr>
                            <td>
                                <?php echo date('d/m/Y', strtotime($m['criado_em'])); ?>
                                <br><small
                                    style="color:var(--mtech-text-muted);"><?php echo date('H:i', strtotime($m['criado_em'])); ?></small>
                            </td>
                            <td><span
                                    class="admin-badge <?php echo classeTipoMov($m['tipo']); ?>"><?php echo nomeTipoMov($m['tipo']); ?></span>
                            </td>
                            <?php if (!$item): ?>
                                <td>
                                    <strong><?php echo limpar($m['item_nome']); ?></strong>
                                    <?php if ($m['item_codigo']): ?>
                                        <br><small
                                            style="color:var(--mtech-text-muted); font-family:monospace;"><?php echo limpar($m['item_codigo']); ?></small>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td style="text-align:center; font-weight:600;">
                                <?php echo number_format((float)$m['quantidade'], 0, ',', '.'); ?>
                            </td>
                            <td style="text-align:center; color:var(--mtech-text-muted);">
                                <?php echo number_format((float)$m['quantidade_anterior'], 0, ',', '.'); ?>
                            </td>
                            <td style="text-align:center; font-weight:600; color:var(--mtech-yellow);">
                                <?php echo number_format((float)$m['quantidade_posterior'], 0, ',', '.'); ?>
                            </td>
                            <td><?php echo limpar($m['motivo'] ?? '—'); ?></td>
                            <td>
                                <?php if ($m['numero_os']): ?>
                                    <a href="ordens_ver.php?id=<?php echo (int)$m['id_os']; ?>" style="color:var(--mtech-yellow);">
                                        <?php echo limpar($m['numero_os']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--mtech-text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo limpar($m['usuario_nome']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    (function() {
        const sel = document.getElementById('filtroTipoMov');
        const form = document.getElementById('formFiltrosMov');
        if (sel && form) sel.addEventListener('change', () => form.submit());
    })();
</script>

<?php require_once '_footer.php'; ?>