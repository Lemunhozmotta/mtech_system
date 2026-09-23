<?php
/* =========================================================
   M-TECH SYSTEM — ESTOQUE (lista)
   ========================================================= */

$titulo_pagina = 'Estoque';
require_once '_header.php';

$polling_ativo = true;

$usuarioLogado = usuarioLogado();
$conn = conectar();

$podeGerenciar = in_array($usuarioLogado['nivel'], [1, 2]);
$podeVerValores = in_array($usuarioLogado['nivel'], [1, 2]);
$souMecanico = ($usuarioLogado['nivel'] === 3);

$busca = trim($_GET['busca'] ?? '');
$filtro = $_GET['filtro'] ?? 'ativos';
$categoria_filtro = trim($_GET['categoria'] ?? '');

// Carrinho da sessão
if (!isset($_SESSION['estoque_carrinho'])) {
    $_SESSION['estoque_carrinho'] = [];
}
$carrinho = $_SESSION['estoque_carrinho'];

$sql = "SELECT e.* FROM estoque e WHERE 1 = 1";
$params = [];
$tipos = '';

if ($filtro === 'ativos') {
    $sql .= " AND e.ativo = 1";
} elseif ($filtro === 'inativos') {
    $sql .= " AND e.ativo = 0";
} elseif ($filtro === 'baixo') {
    $sql .= " AND e.ativo = 1 AND e.quantidade > 0 AND e.quantidade <= e.quantidade_minima";
} elseif ($filtro === 'zerado') {
    $sql .= " AND e.ativo = 1 AND e.quantidade = 0";
}

if ($categoria_filtro !== '') {
    $sql .= " AND e.categoria = ?";
    $params[] = $categoria_filtro;
    $tipos .= 's';
}

if ($busca !== '') {
    $sql .= " AND (e.nome LIKE ? OR e.codigo LIKE ? OR e.codigo_barras LIKE ?
                   OR e.categoria LIKE ? OR e.marca LIKE ?)";
    $t = '%' . $busca . '%';
    $params = array_merge($params, [$t, $t, $t, $t, $t]);
    $tipos .= 'sssss';
}

$sql .= " ORDER BY e.nome ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Contadores
$res = $conn->query("
    SELECT
        SUM(ativo = 1) AS total,
        SUM(ativo = 1 AND quantidade > 0 AND quantidade <= quantidade_minima) AS baixo,
        SUM(ativo = 1 AND quantidade = 0) AS zerado
    FROM estoque
");
$cont = $res->fetch_assoc();
$cont['total'] = (int)($cont['total'] ?? 0);
$cont['baixo'] = (int)($cont['baixo'] ?? 0);
$cont['zerado'] = (int)($cont['zerado'] ?? 0);

$categorias = [];
$res = $conn->query("SELECT DISTINCT categoria FROM estoque WHERE categoria IS NOT NULL AND categoria <> '' ORDER BY categoria ASC");
while ($r = $res->fetch_assoc()) $categorias[] = $r['categoria'];

$res = $conn->query("SELECT COUNT(*) AS total FROM os_solicitacoes_peca WHERE status = 'aprovada_estoque'");
$retiradas_pendentes = (int)$res->fetch_assoc()['total'];

$conn->close();
?>

<h1 class="admin-titulo-pagina">Estoque</h1>

<?php
$msg = $_GET['msg'] ?? '';
$mensagens = [
    'cadastrado'    => ['texto' => 'Item cadastrado no estoque!', 'tipo' => 'sucesso'],
    'editado'       => ['texto' => 'Item atualizado!', 'tipo' => 'sucesso'],
    'desativado'    => ['texto' => 'Item desativado. Continua no histórico.', 'tipo' => 'alerta'],
    'reativado'     => ['texto' => 'Item reativado!', 'tipo' => 'sucesso'],
    'ajustado'      => ['texto' => 'Quantidade ajustada e movimentação registrada.', 'tipo' => 'sucesso'],
    'carrinho_add'  => ['texto' => 'Peça(s) adicionada(s) à OS. Volte na OS pra solicitar.', 'tipo' => 'sucesso'],
    'carrinho_limp' => ['texto' => 'Seleção limpa.', 'tipo' => 'alerta'],
    'erro'          => ['texto' => 'Ocorreu um erro.', 'tipo' => 'erro'],
    'erro_obrig'    => ['texto' => 'Preencha todos os campos obrigatórios.', 'tipo' => 'erro'],
    'erro_negativo' => ['texto' => 'Quantidade insuficiente em estoque. Operação bloqueada.', 'tipo' => 'erro'],
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

<?php if ($retiradas_pendentes > 0 && $podeGerenciar): ?>
    <div class="admin-alerta admin-alerta-alerta">
        <i class="fas fa-exclamation-triangle"></i>
        Há <strong><?php echo $retiradas_pendentes; ?></strong> retirada(s) de peça aguardando confirmação.
        <a href="estoque_pendentes.php"
            style="color: var(--mtech-yellow); text-decoration: underline; margin-left: 8px;">Ver agora →</a>
    </div>
<?php endif; ?>

<div class="admin-cards-resumo">
    <div class="admin-card-resumo azul">
        <div class="admin-card-resumo-icone"><i class="fas fa-boxes"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $cont['total']; ?></div>
        <div class="admin-card-resumo-titulo">Itens ativos</div>
    </div>
    <div class="admin-card-resumo amarelo">
        <div class="admin-card-resumo-icone"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $cont['baixo']; ?></div>
        <div class="admin-card-resumo-titulo">Estoque baixo</div>
    </div>
    <div class="admin-card-resumo">
        <div class="admin-card-resumo-icone"><i class="fas fa-times-circle"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $cont['zerado']; ?></div>
        <div class="admin-card-resumo-titulo">Zerado</div>
    </div>
    <?php if ($podeGerenciar): ?>
        <div class="admin-card-resumo verde">
            <div class="admin-card-resumo-icone"><i class="fas fa-clipboard-check"></i></div>
            <div class="admin-card-resumo-valor"><?php echo $retiradas_pendentes; ?></div>
            <div class="admin-card-resumo-titulo">Retiradas pendentes</div>
        </div>
    <?php endif; ?>
</div>

<div class="admin-bloco">
    <form method="GET" class="admin-barra-acoes" id="formFiltrosEstoque">
        <div class="admin-busca">
            <i class="fas fa-search"></i>
            <input type="text" name="busca" placeholder="Buscar por nome, código, código de barras, marca..."
                value="<?php echo limpar($busca); ?>">
        </div>

        <select name="categoria" class="admin-select-filtro" id="filtroCategoria">
            <option value="">Todas as categorias</option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?php echo limpar($cat); ?>" <?php echo $categoria_filtro === $cat ? 'selected' : ''; ?>>
                    <?php echo limpar($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="filtro" class="admin-select-filtro" id="filtroStatus">
            <option value="ativos" <?php echo $filtro === 'ativos' ? 'selected' : ''; ?>>Apenas ativos</option>
            <option value="baixo" <?php echo $filtro === 'baixo' ? 'selected' : ''; ?>>Estoque baixo</option>
            <option value="zerado" <?php echo $filtro === 'zerado' ? 'selected' : ''; ?>>Zerados</option>
            <option value="inativos" <?php echo $filtro === 'inativos' ? 'selected' : ''; ?>>Inativos</option>
            <option value="todos" <?php echo $filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
        </select>

        <button type="submit" class="admin-btn admin-btn-secundario"><i class="fas fa-search"></i> Filtrar</button>

        <?php if ($podeGerenciar): ?>
            <a href="estoque_novo.php" class="admin-btn admin-btn-novo"><i class="fas fa-plus"></i> Novo Item</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($souMecanico && !empty($carrinho)): ?>
    <div class="admin-alerta admin-alerta-alerta" style="justify-content: space-between;">
        <div>
            <i class="fas fa-shopping-basket"></i>
            Você tem <strong><?php echo count($carrinho); ?></strong> peça(s) pré-selecionada(s) pra adicionar a uma OS.
        </div>
        <a href="estoque_limpar_carrinho_acao.php" class="admin-btn admin-btn-secundario"
            style="font-size:12px; padding:6px 12px;">
            <i class="fas fa-times"></i> Limpar seleção
        </a>
    </div>
<?php endif; ?>

<?php if ($souMecanico && !empty($itens)): ?>
    <form action="estoque_adicionar_os_acao.php" method="POST" id="formAdicionarOS">
    <?php endif; ?>

    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-boxes"></i> Itens em estoque</span>
            <span><?php echo count($itens); ?> item(ns)</span>
        </div>

        <?php if ($souMecanico && !empty($itens)): ?>
            <div
                style="margin-bottom: 15px; padding: 12px; background: rgba(235, 175, 0, 0.08); border-left: 3px solid var(--mtech-yellow); border-radius: 6px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
                    <input type="checkbox" id="selecionarTodasEstoque" onchange="toggleTodasEstoque(this)"> Selecionar todos
                </label>
                <span style="color:var(--mtech-text-muted); font-size:13px;">
                    <i class="fas fa-info-circle"></i>
                    Marque as peças, escolha o número da OS e clique em "Adicionar à OS".
                </span>
                <div style="margin-left:auto; display:flex; gap:10px; align-items:center;">
                    <label style="font-size:13px; color: var(--mtech-text-muted);">OS:</label>
                    <select name="id_os" required
                        style="padding:8px 12px; background:var(--mtech-bg); color:var(--mtech-text); border:1px solid var(--mtech-border); border-radius:6px; font-family:Poppins; font-size:13px;">
                        <option value="">— Selecione —</option>
                        <?php
                        // Mostra só as OS em que ele está apontado (ou abertas/em_andamento)
                        $conn_list = conectar();
                        $idU = (int)$usuarioLogado['id_usuario'];
                        $res_os = $conn_list->query("
                        SELECT os.id_os, os.numero_os, os.status
                        FROM ordens_servico os
                        INNER JOIN os_apontamentos a ON a.id_os = os.id_os
                        WHERE a.id_usuario = {$idU}
                          AND a.data_desapontamento IS NULL
                          AND os.status IN ('em_andamento','aguardando_peca','aberta')
                        ORDER BY os.id_os DESC
                    ");
                        while ($o = $res_os->fetch_assoc()):
                        ?>
                            <option value="<?php echo (int)$o['id_os']; ?>">
                                OS <?php echo limpar($o['numero_os']); ?> — <?php echo $o['status']; ?>
                            </option>
                        <?php endwhile;
                        $conn_list->close(); ?>
                    </select>
                    <button type="submit" class="admin-btn">
                        <i class="fas fa-cart-plus"></i> Adicionar à OS
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($itens)): ?>
            <div class="admin-vazio">
                <i class="fas fa-box-open"></i>
                <p>Nenhum item encontrado.</p>
                <small><?php echo $busca !== '' ? 'Tente outra busca.' : 'Clique em "Novo Item" pra começar.'; ?></small>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="admin-tabela">
                    <thead>
                        <tr>
                            <?php if ($souMecanico): ?><th style="width:40px;"></th><?php endif; ?>
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Marca</th>
                            <th style="text-align:center;">Qtd</th>
                            <th style="text-align:center;">Mín.</th>
                            <?php if ($podeVerValores): ?>
                                <th style="text-align:right;">Custo</th>
                                <th style="text-align:right;">Venda</th>
                            <?php endif; ?>
                            <th>Local</th>
                            <th>Status</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $it): ?>
                            <?php
                            $qtd = (int)$it['quantidade'];
                            $min = (int)$it['quantidade_minima'];
                            if ($qtd <= 0) $classe_qtd = 'admin-badge-erro';
                            elseif ($qtd <= $min) $classe_qtd = 'admin-badge-alerta';
                            else $classe_qtd = 'admin-badge-sucesso';

                            $no_carrinho = in_array((int)$it['id_estoque'], array_map('intval', $carrinho));
                            ?>
                            <tr>
                                <?php if ($souMecanico): ?>
                                    <td>
                                        <input type="checkbox" name="ids_estoque[]" value="<?php echo (int)$it['id_estoque']; ?>"
                                            class="check-item-estoque" <?php echo $no_carrinho ? 'checked' : ''; ?>>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($it['codigo']): ?>
                                        <small style="font-family:monospace;"><?php echo limpar($it['codigo']); ?></small>
                                    <?php else: ?>
                                        <span style="color:var(--mtech-text-muted);">—</span>
                                    <?php endif; ?>
                                    <?php if ($it['codigo_barras']): ?>
                                        <br><small style="color:var(--mtech-text-muted); font-size:10px;">🏷
                                            <?php echo limpar($it['codigo_barras']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo limpar($it['nome']); ?></strong></td>
                                <td><?php echo $it['categoria'] ? limpar($it['categoria']) : '<span style="color:var(--mtech-text-muted);">—</span>'; ?>
                                </td>
                                <td><?php echo $it['marca'] ? limpar($it['marca']) : '<span style="color:var(--mtech-text-muted);">—</span>'; ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="admin-badge <?php echo $classe_qtd; ?>" style="font-size:13px;">
                                        <?php echo number_format($qtd, 0, ',', '.'); ?>
                                    </span>
                                </td>
                                <td style="text-align:center; color:var(--mtech-text-muted);">
                                    <?php echo number_format($min, 0, ',', '.'); ?>
                                </td>
                                <?php if ($podeVerValores): ?>
                                    <td style="text-align:right;">R$
                                        <?php echo number_format((float)$it['valor_custo'], 2, ',', '.'); ?></td>
                                    <td style="text-align:right;">R$
                                        <?php echo number_format((float)$it['valor_venda'], 2, ',', '.'); ?></td>
                                <?php endif; ?>
                                <td><?php echo $it['localizacao'] ? limpar($it['localizacao']) : '<span style="color:var(--mtech-text-muted);">—</span>'; ?>
                                </td>
                                <td>
                                    <?php if ($it['ativo']): ?>
                                        <span class="admin-badge admin-badge-sucesso">Ativo</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge-erro">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <a href="estoque_movimentacoes.php?id=<?php echo (int)$it['id_estoque']; ?>"
                                        class="admin-btn-acao" title="Movimentações">
                                        <i class="fas fa-history"></i>
                                    </a>
                                    <?php if ($podeGerenciar): ?>
                                        <a href="estoque_editar.php?id=<?php echo (int)$it['id_estoque']; ?>" class="admin-btn-acao"
                                            title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($it['ativo']): ?>
                                            <a href="estoque_acao.php?acao=desativar&id=<?php echo (int)$it['id_estoque']; ?>"
                                                class="admin-btn-acao admin-btn-acao-erro" title="Desativar"
                                                onclick="return confirm('Desativar este item? Ele não será apagado.');">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="estoque_acao.php?acao=reativar&id=<?php echo (int)$it['id_estoque']; ?>"
                                                class="admin-btn-acao admin-btn-acao-sucesso" title="Reativar">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($souMecanico && !empty($itens)): ?>
    </form>
<?php endif; ?>

<script>
    (function() {
        const selCategoria = document.getElementById('filtroCategoria');
        const selStatus = document.getElementById('filtroStatus');
        const form = document.getElementById('formFiltrosEstoque');

        if (selCategoria && form) selCategoria.addEventListener('change', () => form.submit());
        if (selStatus && form) selStatus.addEventListener('change', () => form.submit());
    })();

    function toggleTodasEstoque(cb) {
        document.querySelectorAll('.check-item-estoque').forEach(c => c.checked = cb.checked);
    }
</script>

<?php require_once '_footer.php'; ?>