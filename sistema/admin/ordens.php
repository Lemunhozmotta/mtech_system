<?php
/* =========================================================
   M-TECH SYSTEM — ORDENS DE SERVIÇO (lista)
   9 status, 9 balões (3×3), filtros do fluxo novo
   ========================================================= */

$titulo_pagina = 'Ordens de Serviço';
require_once '_header.php';

$polling_ativo = true;

$usuarioLogado = usuarioLogado();
$conn = conectar();

$busca = trim($_GET['busca'] ?? '');
$filtro = $_GET['filtro'] ?? 'ativas';

$sql = "SELECT os.id_os, os.numero_os, os.status, os.data_abertura,
               os.descricao_problema,
               cl.nome AS cliente_nome,
               cr.marca, cr.modelo, cr.placa,
               u.nome AS mecanico_nome,
               ap.nome AS apontado_nome,
               ap.data_apontamento AS apontado_desde
        FROM ordens_servico os
        INNER JOIN clientes cl ON cl.id_cliente = os.id_cliente
        INNER JOIN carros cr ON cr.id_carro = os.id_carro
        LEFT JOIN usuarios u ON u.id_usuario = os.id_mecanico
        LEFT JOIN (
            SELECT a.id_os, a.data_apontamento, us.nome
            FROM os_apontamentos a
            INNER JOIN usuarios us ON us.id_usuario = a.id_usuario
            WHERE a.data_desapontamento IS NULL
        ) ap ON ap.id_os = os.id_os
        WHERE 1 = 1";

$params = [];
$tipos = '';

// ===== FILTROS =====
if ($filtro === 'ativas') {
    $sql .= " AND os.status IN ('aberta', 'em_andamento', 'aguardando_aprovacao', 'aguardando_peca', 'em_execucao')";
} elseif ($filtro === 'aberta') {
    $sql .= " AND os.status = 'aberta'";
} elseif ($filtro === 'em_andamento') {
    $sql .= " AND os.status = 'em_andamento'";
} elseif ($filtro === 'aguardando_aprovacao') {
    $sql .= " AND os.status = 'aguardando_aprovacao'";
} elseif ($filtro === 'aguardando_peca') {
    $sql .= " AND os.status = 'aguardando_peca'";
} elseif ($filtro === 'em_execucao') {
    $sql .= " AND os.status = 'em_execucao'";
} elseif ($filtro === 'pronta') {
    $sql .= " AND os.status = 'pronta'";
} elseif ($filtro === 'aguardando_retirada') {
    $sql .= " AND os.status = 'aguardando_retirada'";
} elseif ($filtro === 'concluida') {
    $sql .= " AND os.status = 'concluida'";
} elseif ($filtro === 'cancelada') {
    $sql .= " AND os.status = 'cancelada'";
}

if ($busca !== '') {
    $sql .= " AND (os.numero_os LIKE ? OR cl.nome LIKE ? OR cr.placa LIKE ?
                   OR cr.marca LIKE ? OR cr.modelo LIKE ?)";
    $t = '%' . $busca . '%';
    $params = [$t, $t, $t, $t, $t];
    $tipos = 'sssss';
}

$sql .= " ORDER BY os.id_os DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$ordens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== CONTADORES =====
$contadores = [
    'aberta'               => 0,
    'em_andamento'         => 0,
    'aguardando_aprovacao' => 0,
    'aguardando_peca'      => 0,
    'em_execucao'          => 0,
    'pronta'               => 0,
    'aguardando_retirada'  => 0,
    'concluida'            => 0,
    'cancelada'            => 0
];

$res = $conn->query("SELECT status, COUNT(*) AS total FROM ordens_servico GROUP BY status");
while ($row = $res->fetch_assoc()) {
    $contadores[$row['status']] = (int)$row['total'];
}

$conn->close();

$nivel = (int)$usuarioLogado['nivel'];
$mostraApontado = in_array($nivel, [1, 2]);
?>

<h1 class="admin-titulo-pagina">Ordens de Serviço</h1>

<?php
$msg = $_GET['msg'] ?? '';
$mensagens = [
    'cadastrada'    => ['texto' => 'OS aberta com sucesso!', 'tipo' => 'sucesso'],
    'editada'       => ['texto' => 'OS atualizada com sucesso!', 'tipo' => 'sucesso'],
    'status'        => ['texto' => 'Status atualizado com sucesso!', 'tipo' => 'sucesso'],
    'cancelada'     => ['texto' => 'OS cancelada.', 'tipo' => 'alerta'],
    'erro'          => ['texto' => 'Ocorreu um erro.', 'tipo' => 'erro'],
    'erro_obrig'    => ['texto' => 'Preencha todos os campos obrigatórios.', 'tipo' => 'erro'],
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

<!-- ===== CONTADORES (9 balões em 3×3) ===== -->
<div class="admin-cards-resumo" style="grid-template-columns: repeat(3, 1fr);">

    <div class="admin-card-resumo azul">
        <div class="admin-card-resumo-icone"><i class="fas fa-folder-open"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $contadores['aberta']; ?></div>
        <div class="admin-card-resumo-titulo">Aberta</div>
    </div>

    <div class="admin-card-resumo amarelo">
        <div class="admin-card-resumo-icone"><i class="fas fa-wrench"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $contadores['em_andamento']; ?></div>
        <div class="admin-card-resumo-titulo">Em Andamento</div>
    </div>

    <div class="admin-card-resumo" style="border-left-color: #EBAF00;">
        <div class="admin-card-resumo-icone"><i class="fas fa-clock"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $contadores['aguardando_aprovacao']; ?></div>
        <div class="admin-card-resumo-titulo">Aguardando Aprovação</div>
    </div>

    <div class="admin-card-resumo" style="border-left-color: #D62D2D;">
        <div class="admin-card-resumo-icone"><i class="fas fa-hourglass-half"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $contadores['aguardando_peca']; ?></div>
        <div class="admin-card-resumo-titulo">Aguardando Peça</div>
    </div>

    <div class="admin-card-resumo" style="border-left-color: #4A90E2;">
        <div class="admin-card-resumo-icone"><i class="fas fa-tools"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $contadores['em_execucao']; ?></div>
        <div class="admin-card-resumo-titulo">Em Execução</div>
    </div>

    <div class="admin-card-resumo" style="border-left-color: #25d366;">
        <div class="admin-card-resumo-icone"><i class="fas fa-flag-checkered"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $contadores['pronta']; ?></div>
        <div class="admin-card-resumo-titulo">Pronta</div>
    </div>

    <div class="admin-card-resumo" style="border-left-color: #25d366;">
        <div class="admin-card-resumo-icone"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $contadores['aguardando_retirada']; ?></div>
        <div class="admin-card-resumo-titulo">Aguardando Retirada</div>
    </div>

    <div class="admin-card-resumo verde">
        <div class="admin-card-resumo-icone"><i class="fas fa-check-circle"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $contadores['concluida']; ?></div>
        <div class="admin-card-resumo-titulo">Concluída</div>
    </div>

    <div class="admin-card-resumo" style="border-left-color: #666;">
        <div class="admin-card-resumo-icone"><i class="fas fa-ban"></i></div>
        <div class="admin-card-resumo-valor"><?php echo $contadores['cancelada']; ?></div>
        <div class="admin-card-resumo-titulo">Cancelada</div>
    </div>

</div>

<!-- ===== BARRA DE BUSCA + FILTROS ===== -->
<div class="admin-bloco">
    <form method="GET" class="admin-barra-acoes" id="formBuscaOS">
        <div class="admin-busca">
            <i class="fas fa-search"></i>
            <input type="text" name="busca" placeholder="Buscar por nº OS, cliente, placa, marca ou modelo..."
                value="<?php echo limpar($busca); ?>">
        </div>
        <select name="filtro" class="admin-select-filtro" id="filtroOS">
            <option value="ativas" <?php echo $filtro === 'ativas' ? 'selected' : ''; ?>>Ativas</option>
            <option value="aberta" <?php echo $filtro === 'aberta' ? 'selected' : ''; ?>>Aberta</option>
            <option value="em_andamento" <?php echo $filtro === 'em_andamento' ? 'selected' : ''; ?>>Em Andamento
            </option>
            <option value="aguardando_aprovacao" <?php echo $filtro === 'aguardando_aprovacao' ? 'selected' : ''; ?>>
                Aguardando Aprovação</option>
            <option value="aguardando_peca" <?php echo $filtro === 'aguardando_peca' ? 'selected' : ''; ?>>Aguardando
                Peça</option>
            <option value="em_execucao" <?php echo $filtro === 'em_execucao' ? 'selected' : ''; ?>>Em Execução</option>
            <option value="pronta" <?php echo $filtro === 'pronta' ? 'selected' : ''; ?>>Pronta</option>
            <option value="aguardando_retirada" <?php echo $filtro === 'aguardando_retirada' ? 'selected' : ''; ?>>
                Aguardando Retirada</option>
            <option value="concluida" <?php echo $filtro === 'concluida' ? 'selected' : ''; ?>>Concluída</option>
            <option value="cancelada" <?php echo $filtro === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
            <option value="todas" <?php echo $filtro === 'todas' ? 'selected' : ''; ?>>Todas</option>
        </select>
        <button type="submit" class="admin-btn admin-btn-secundario"><i class="fas fa-search"></i> Buscar</button>
        <a href="ordens_novo.php" class="admin-btn admin-btn-novo"><i class="fas fa-plus"></i> Nova OS</a>
    </form>
</div>

<!-- ===== LISTA ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo">
        <span><i class="fas fa-clipboard-list"></i> Lista de OS</span>
        <span><?php echo count($ordens); ?> OS</span>
    </div>

    <?php if (empty($ordens)): ?>
        <div class="admin-vazio">
            <i class="fas fa-clipboard-list"></i>
            <p>Nenhuma OS encontrada.</p>
            <small>
                <?php if (!empty($busca)): ?>
                    Tente outra busca ou <a href="ordens.php">limpe os filtros</a>.
                <?php else: ?>
                    Clique em <strong>"Nova OS"</strong> pra começar.
                <?php endif; ?>
            </small>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="admin-tabela">
                <thead>
                    <tr>
                        <th>Nº OS</th>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th>Abertura</th>
                        <th>Mecânico</th>
                        <?php if ($mostraApontado): ?><th>Apontado agora</th><?php endif; ?>
                        <th>Status</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordens as $os): ?>
                        <tr>
                            <td>
                                <strong style="font-family: monospace; letter-spacing: 1px;">
                                    <?php echo limpar($os['numero_os']); ?>
                                </strong>
                            </td>
                            <td><?php echo limpar($os['cliente_nome']); ?></td>
                            <td>
                                <strong><?php echo limpar($os['marca']); ?></strong> <?php echo limpar($os['modelo']); ?>
                                <br><small style="color: var(--mtech-text-muted); font-family: monospace;">
                                    <?php echo strtoupper(limpar($os['placa'])); ?>
                                </small>
                            </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($os['data_abertura'])); ?>
                                <br><small style="color: var(--mtech-text-muted);">
                                    <?php echo date('H:i', strtotime($os['data_abertura'])); ?>
                                </small>
                            </td>
                            <td>
                                <?php echo $os['mecanico_nome'] ? limpar($os['mecanico_nome']) : '<span style="color: var(--mtech-text-muted);">—</span>'; ?>
                            </td>
                            <?php if ($mostraApontado): ?>
                                <td>
                                    <?php if ($os['apontado_nome']): ?>
                                        <span class="admin-badge admin-badge-alerta"
                                            title="Desde <?php echo date('d/m/Y H:i', strtotime($os['apontado_desde'])); ?>">
                                            <i class="fas fa-user-check"></i>&nbsp;<?php echo limpar($os['apontado_nome']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--mtech-text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <span class="admin-badge <?php echo classeStatusOS($os['status']); ?>">
                                    <?php echo nomeStatusOS($os['status']); ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="ordens_ver.php?id=<?php echo (int)$os['id_os']; ?>" class="admin-btn-acao" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="ordens_editar.php?id=<?php echo (int)$os['id_os']; ?>" class="admin-btn-acao"
                                    title="Editar">
                                    <i class="fas fa-edit"></i>
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
        const filtro = document.getElementById('filtroOS');
        const form = document.getElementById('formBuscaOS');
        if (filtro && form) {
            filtro.addEventListener('change', () => form.submit());
        }
    })();
</script>

<?php require_once '_footer.php'; ?>