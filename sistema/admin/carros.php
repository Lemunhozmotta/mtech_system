<?php
/* =========================================================
   M-TECH SYSTEM — CARROS (lista)
   ========================================================= */

$titulo_pagina = 'Carros';
require_once '_header.php';

$polling_ativo = true;

// ===== PEGA O USUÁRIO LOGADO =====
$usuarioLogado = usuarioLogado();
$podeDesativar = in_array($usuarioLogado['nivel'], [1, 2]);

// ===== CONECTA AO BANCO =====
$conn = conectar();

// ===== PARÂMETROS DE BUSCA/FILTRO =====
$busca = trim($_GET['busca'] ?? '');
$filtro = $_GET['filtro'] ?? 'ativos';
$id_cliente_filtro = (int)($_GET['id_cliente'] ?? 0);

// ===== MONTA A QUERY =====
$sql = "SELECT cr.id_carro, cr.marca, cr.modelo, cr.ano, cr.placa, cr.cor,
               cr.km_atual, cr.ativo, cr.id_cliente,
               cl.nome AS cliente_nome, cl.telefone AS cliente_telefone,
               cl.whatsapp AS cliente_whatsapp
        FROM carros cr
        INNER JOIN clientes cl ON cl.id_cliente = cr.id_cliente
        WHERE 1 = 1";

$params = [];
$tipos = '';

if ($filtro === 'ativos') {
    $sql .= " AND cr.ativo = 1";
} elseif ($filtro === 'inativos') {
    $sql .= " AND cr.ativo = 0";
}

// Filtro por cliente (quando vem da ficha do cliente)
if ($id_cliente_filtro > 0) {
    $sql .= " AND cr.id_cliente = ?";
    $params[] = $id_cliente_filtro;
    $tipos .= 'i';
}

if (!empty($busca)) {
    $sql .= " AND (cr.marca LIKE ? OR cr.modelo LIKE ? OR cr.placa LIKE ?
                   OR cr.cor LIKE ? OR cl.nome LIKE ?)";
    $termo = '%' . $busca . '%';
    $params[] = $termo;
    $params[] = $termo;
    $params[] = $termo;
    $params[] = $termo;
    $params[] = $termo;
    $tipos .= 'sssss';
}

$sql .= " ORDER BY cl.nome ASC, cr.marca ASC, cr.modelo ASC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$carros = $result->fetch_all(MYSQLI_ASSOC);
$total = count($carros);
?>

<!-- ===== TÍTULO ===== -->
<h1 class="admin-titulo-pagina">Carros</h1>

<!-- ===== MENSAGENS DE FEEDBACK ===== -->
<?php
$msg = $_GET['msg'] ?? '';
$mensagens = [
    'cadastrado'    => ['texto' => 'Carro cadastrado com sucesso!', 'tipo' => 'sucesso'],
    'editado'       => ['texto' => 'Carro atualizado com sucesso!', 'tipo' => 'sucesso'],
    'desativado'    => ['texto' => 'Carro desativado. Ele não foi apagado — pode ser reativado depois.', 'tipo' => 'alerta'],
    'reativado'     => ['texto' => 'Carro reativado com sucesso!', 'tipo' => 'sucesso'],
    'erro'          => ['texto' => 'Ocorreu um erro. Tente novamente.', 'tipo' => 'erro'],
    'erro_obrig'    => ['texto' => 'Preencha todos os campos obrigatórios (cliente, marca, modelo e placa).', 'tipo' => 'erro'],
    'erro_cliente'  => ['texto' => 'Cliente inválido ou não encontrado.', 'tipo' => 'erro'],
    'erro_placa'    => ['texto' => 'Já existe um carro cadastrado com essa placa.', 'tipo' => 'erro'],
    'sem_permissao' => ['texto' => 'Você não tem permissão para desativar carros.', 'tipo' => 'erro'],
    'tem_os_aberta' => ['texto' => 'Este carro tem Ordens de Serviço em aberto. Resolva-as antes de desativar.', 'tipo' => 'erro'],
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

<!-- ===== BARRA DE AÇÕES ===== -->
<div class="admin-bloco">

    <form method="GET" class="admin-barra-acoes">

        <?php if ($id_cliente_filtro > 0): ?>
            <input type="hidden" name="id_cliente" value="<?php echo (int)$id_cliente_filtro; ?>">
        <?php endif; ?>

        <div class="admin-busca">
            <i class="fas fa-search"></i>
            <input type="text" name="busca" placeholder="Buscar por placa, marca, modelo ou cliente..."
                value="<?php echo limpar($busca); ?>">
        </div>

        <select name="filtro" class="admin-select-filtro">
            <option value="ativos" <?php echo $filtro === 'ativos' ? 'selected' : ''; ?>>Apenas Ativos</option>
            <option value="inativos" <?php echo $filtro === 'inativos' ? 'selected' : ''; ?>>Apenas Inativos</option>
            <option value="todos" <?php echo $filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
        </select>

        <button type="submit" class="admin-btn admin-btn-secundario">
            <i class="fas fa-search"></i> Buscar
        </button>

        <a href="carros_novo.php<?php echo $id_cliente_filtro > 0 ? '?id_cliente=' . (int)$id_cliente_filtro : ''; ?>"
            class="admin-btn admin-btn-novo">
            <i class="fas fa-plus"></i> Novo Carro
        </a>

    </form>

</div>

<!-- ===== TABELA DE CARROS ===== -->
<div class="admin-bloco">

    <div class="admin-bloco-titulo">
        <span><i class="fas fa-car"></i> Lista de Carros</span>
        <span><?php echo $total; ?> carro<?php echo $total !== 1 ? 's' : ''; ?></span>
    </div>

    <?php if ($total === 0): ?>

        <div class="admin-vazio">
            <i class="fas fa-car-side"></i>
            <p>Nenhum carro encontrado.</p>
            <small>
                <?php if (!empty($busca) || $id_cliente_filtro > 0): ?>
                    Tente outra busca ou <a href="carros.php">limpe os filtros</a>.
                <?php else: ?>
                    Clique em <strong>"Novo Carro"</strong> pra começar.
                <?php endif; ?>
            </small>
        </div>

    <?php else: ?>

        <table class="admin-tabela">
            <thead>
                <tr>
                    <th>Placa</th>
                    <th>Veículo</th>
                    <th>Cliente</th>
                    <th>Ano</th>
                    <th>Cor</th>
                    <th style="text-align: center;">KM</th>
                    <th>Status</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($carros as $car): ?>
                    <tr>
                        <td>
                            <strong style="font-family: monospace; font-size: 14px; letter-spacing: 1px;">
                                <?php echo limpar(strtoupper($car['placa'])); ?>
                            </strong>
                        </td>
                        <td>
                            <strong><?php echo limpar($car['marca']); ?></strong>
                            <?php echo limpar($car['modelo']); ?>
                        </td>
                        <td>
                            <?php echo limpar($car['cliente_nome']); ?>
                        </td>
                        <td>
                            <?php echo $car['ano'] ? limpar($car['ano']) : '<span style="color: var(--mtech-text-muted);">—</span>'; ?>
                        </td>
                        <td>
                            <?php echo $car['cor'] ? limpar($car['cor']) : '<span style="color: var(--mtech-text-muted);">—</span>'; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php echo $car['km_atual'] !== null ? number_format((int)$car['km_atual'], 0, ',', '.') : '<span style="color: var(--mtech-text-muted);">—</span>'; ?>
                        </td>
                        <td>
                            <?php if ($car['ativo']): ?>
                                <span class="admin-badge admin-badge-sucesso">Ativo</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-erro">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">

                            <a href="carros_editar.php?id=<?php echo (int)$car['id_carro']; ?>" class="admin-btn-acao"
                                title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>

                            <?php if ($podeDesativar): ?>
                                <?php if ($car['ativo']): ?>
                                    <a href="carros_acao.php?acao=desativar&id=<?php echo (int)$car['id_carro']; ?>"
                                        class="admin-btn-acao admin-btn-acao-erro" title="Desativar"
                                        onclick="return confirm('Tem certeza que quer DESATIVAR o carro <?php echo limpar(addslashes($car['marca'] . ' ' . $car['modelo'] . ' (' . $car['placa'] . ')')); ?>?\n\nEle não será apagado do sistema — só ficará inativo.');">
                                        <i class="fas fa-ban"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="carros_acao.php?acao=reativar&id=<?php echo (int)$car['id_carro']; ?>"
                                        class="admin-btn-acao admin-btn-acao-sucesso" title="Reativar"
                                        onclick="return confirm('Reativar o carro <?php echo limpar(addslashes($car['marca'] . ' ' . $car['modelo'] . ' (' . $car['placa'] . ')')); ?>?');">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>

                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>

</div>

<?php
$stmt->close();
$conn->close();
require_once '_footer.php';
?>