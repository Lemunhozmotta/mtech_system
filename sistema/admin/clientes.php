<?php
/* =========================================================
   M-TECH SYSTEM — CLIENTES (lista)
   ========================================================= */

$titulo_pagina = 'Clientes';
require_once '_header.php';

// ===== PEGA O USUÁRIO LOGADO =====
$usuarioLogado = usuarioLogado();
$podeDesativar = in_array($usuarioLogado['nivel'], [1, 2]);

// ===== CONECTA AO BANCO =====
$conn = conectar();

// ===== PARÂMETROS DE BUSCA/FILTRO =====
$busca = trim($_GET['busca'] ?? '');
$filtro = $_GET['filtro'] ?? 'ativos';

// ===== MONTA A QUERY =====
$sql = "SELECT c.id_cliente, c.nome, c.telefone, c.whatsapp, c.email,
               c.cidade, c.estado, c.ativo,
               (SELECT COUNT(*) FROM carros WHERE id_cliente = c.id_cliente AND ativo = 1) AS qtd_carros
        FROM clientes c
        WHERE 1 = 1";

$params = [];
$tipos = '';

if ($filtro === 'ativos') {
    $sql .= " AND c.ativo = 1";
} elseif ($filtro === 'inativos') {
    $sql .= " AND c.ativo = 0";
}

if (!empty($busca)) {
    $sql .= " AND (c.nome LIKE ? OR c.telefone LIKE ? OR c.whatsapp LIKE ? OR c.email LIKE ?)";
    $termo = '%' . $busca . '%';
    $params = [$termo, $termo, $termo, $termo];
    $tipos = 'ssss';
}

$sql .= " ORDER BY c.nome ASC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$clientes = $result->fetch_all(MYSQLI_ASSOC);
$total = count($clientes);
?>

<!-- ===== TÍTULO ===== -->
<h1 class="admin-titulo-pagina">Clientes</h1>

<!-- ===== MENSAGENS DE FEEDBACK ===== -->
<?php
$msg = $_GET['msg'] ?? '';
$mensagens = [
    'cadastrado'    => ['texto' => 'Cliente cadastrado com sucesso!', 'tipo' => 'sucesso'],
    'editado'       => ['texto' => 'Cliente atualizado com sucesso!', 'tipo' => 'sucesso'],
    'desativado'    => ['texto' => 'Cliente desativado. Ele não foi apagado — pode ser reativado depois.', 'tipo' => 'alerta'],
    'reativado'     => ['texto' => 'Cliente reativado com sucesso!', 'tipo' => 'sucesso'],
    'erro'          => ['texto' => 'Ocorreu um erro. Tente novamente.', 'tipo' => 'erro'],
    'erro_nome'     => ['texto' => 'O nome do cliente é obrigatório.', 'tipo' => 'erro'],
    'sem_permissao' => ['texto' => 'Você não tem permissão para desativar clientes.', 'tipo' => 'erro'],
    'tem_os_aberta' => ['texto' => 'Este cliente tem Ordens de Serviço em aberto. Resolva-as antes de desativar.', 'tipo' => 'erro'],
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

        <div class="admin-busca">
            <i class="fas fa-search"></i>
            <input type="text" name="busca" placeholder="Buscar por nome, telefone ou e-mail..."
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

        <a href="clientes_novo.php" class="admin-btn admin-btn-novo">
            <i class="fas fa-plus"></i> Novo Cliente
        </a>

    </form>

</div>

<!-- ===== TABELA DE CLIENTES ===== -->
<div class="admin-bloco">

    <div class="admin-bloco-titulo">
        <span><i class="fas fa-users"></i> Lista de Clientes</span>
        <span><?php echo $total; ?> cliente<?php echo $total !== 1 ? 's' : ''; ?></span>
    </div>

    <?php if ($total === 0): ?>

        <div class="admin-vazio">
            <i class="fas fa-user-slash"></i>
            <p>Nenhum cliente encontrado.</p>
            <small>
                <?php if (!empty($busca)): ?>
                    Tente outra busca ou <a href="clientes.php">limpe os filtros</a>.
                <?php else: ?>
                    Clique em <strong>"Novo Cliente"</strong> pra começar.
                <?php endif; ?>
            </small>
        </div>

    <?php else: ?>

        <table class="admin-tabela">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Telefone</th>
                    <th>E-mail</th>
                    <th>Cidade</th>
                    <th style="text-align: center;">Veículos</th>
                    <th>Status</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $cli): ?>
                    <tr>
                        <td>
                            <strong><?php echo limpar($cli['nome']); ?></strong>
                        </td>
                        <td>
                            <?php
                            $tel = $cli['whatsapp'] ?: $cli['telefone'];
                            echo $tel ? limpar($tel) : '<span style="color: var(--mtech-text-muted);">—</span>';
                            ?>
                        </td>
                        <td>
                            <?php echo $cli['email'] ? limpar($cli['email']) : '<span style="color: var(--mtech-text-muted);">—</span>'; ?>
                        </td>
                        <td>
                            <?php
                            if ($cli['cidade']) {
                                echo limpar($cli['cidade']);
                                if ($cli['estado']) echo '/' . limpar($cli['estado']);
                            } else {
                                echo '<span style="color: var(--mtech-text-muted);">—</span>';
                            }
                            ?>
                        </td>
                        <td style="text-align: center;">
                            <span class="admin-badge admin-badge-info"><?php echo (int)$cli['qtd_carros']; ?></span>
                        </td>
                        <td>
                            <?php if ($cli['ativo']): ?>
                                <span class="admin-badge admin-badge-sucesso">Ativo</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-erro">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">

                            <a href="clientes_editar.php?id=<?php echo (int)$cli['id_cliente']; ?>" class="admin-btn-acao"
                                title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>

                            <?php if ($podeDesativar): ?>
                                <?php if ($cli['ativo']): ?>
                                    <a href="clientes_acao.php?acao=desativar&id=<?php echo (int)$cli['id_cliente']; ?>"
                                        class="admin-btn-acao admin-btn-acao-erro" title="Desativar"
                                        onclick="return confirm('Tem certeza que quer DESATIVAR o cliente <?php echo limpar(addslashes($cli['nome'])); ?>?\n\nEle não será apagado do sistema — só ficará inativo.');">
                                        <i class="fas fa-ban"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="clientes_acao.php?acao=reativar&id=<?php echo (int)$cli['id_cliente']; ?>"
                                        class="admin-btn-acao admin-btn-acao-sucesso" title="Reativar"
                                        onclick="return confirm('Reativar o cliente <?php echo limpar(addslashes($cli['nome'])); ?>?');">
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