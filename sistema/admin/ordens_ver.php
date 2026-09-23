<?php
/* =========================================================
   M-TECH SYSTEM — ORDENS DE SERVIÇO (ver detalhes)
   ========================================================= */

$titulo_pagina = 'Detalhes da OS';
require_once '_header.php';

$polling_ativo = true;

$usuarioLogado = usuarioLogado();
$conn = conectar();

$id_os = (int)($_GET['id'] ?? 0);
if ($id_os <= 0) {
    $conn->close();
    redirecionar('ordens.php?msg=erro');
}

// ===== BUSCA A OS =====
$stmt = $conn->prepare("
    SELECT os.*,
           cl.nome AS cliente_nome, cl.telefone AS cliente_telefone, cl.whatsapp AS cliente_whatsapp,
           cr.marca, cr.modelo, cr.placa, cr.ano, cr.cor, cr.km_atual, cr.observacoes AS carro_obs,
           u.nome AS mecanico_nome,
           ua.nome AS abertura_nome
    FROM ordens_servico os
    INNER JOIN clientes cl ON cl.id_cliente = os.id_cliente
    INNER JOIN carros cr ON cr.id_carro = os.id_carro
    LEFT JOIN usuarios u ON u.id_usuario = os.id_mecanico
    LEFT JOIN usuarios ua ON ua.id_usuario = os.id_usuario_abertura
    WHERE os.id_os = ? LIMIT 1
");
$stmt->bind_param('i', $id_os);
$stmt->execute();
$os = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$os) {
    $conn->close();
    redirecionar('ordens.php?msg=erro');
}

// ===== APONTAMENTO ATUAL =====
$stmt = $conn->prepare("
    SELECT ap.id_apontamento, ap.data_apontamento, ap.id_usuario, u.nome AS mecanico_nome
    FROM os_apontamentos ap
    INNER JOIN usuarios u ON u.id_usuario = ap.id_usuario
    WHERE ap.id_os = ? AND ap.data_desapontamento IS NULL
    ORDER BY ap.id_apontamento DESC LIMIT 1
");
$stmt->bind_param('i', $id_os);
$stmt->execute();
$apontamento_atual = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ===== HISTÓRICO DE APONTAMENTOS =====
$stmt = $conn->prepare("
    SELECT ap.*, u.nome AS mecanico_nome,
           up.nome AS apontou_nome,
           ud.nome AS desapontou_nome,
           TIMESTAMPDIFF(MINUTE, ap.data_apontamento,
               COALESCE(ap.data_desapontamento, NOW())) AS minutos
    FROM os_apontamentos ap
    INNER JOIN usuarios u ON u.id_usuario = ap.id_usuario
    LEFT JOIN usuarios up ON up.id_usuario = ap.apontado_por
    LEFT JOIN usuarios ud ON ud.id_usuario = ap.id_usuario_desapontou
    WHERE ap.id_os = ?
    ORDER BY ap.id_apontamento DESC
");
$stmt->bind_param('i', $id_os);
$stmt->execute();
$apontamentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== SOLICITAÇÕES DE PEÇA =====
$stmt = $conn->prepare("
    SELECT sp.*,
           us.nome AS solicitou_nome,
           ua.nome AS aprovou_nome,
           e.nome AS estoque_nome, e.quantidade AS estoque_qtd, e.localizacao AS estoque_local,
           c.status AS compra_status
    FROM os_solicitacoes_peca sp
    INNER JOIN usuarios us ON us.id_usuario = sp.id_usuario_solicitou
    LEFT JOIN usuarios ua ON ua.id_usuario = sp.id_usuario_aprovou
    LEFT JOIN estoque e ON e.id_estoque = sp.id_estoque
    LEFT JOIN compras_solicitacoes c ON c.id_compra = sp.id_compra
    WHERE sp.id_os = ?
    ORDER BY sp.status ASC, sp.id_solicitacao ASC
");
$stmt->bind_param('i', $id_os);
$stmt->execute();
$solicitacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== SEPARA SOLICITAÇÕES POR STATUS =====
$solic_pendentes = [];
$solic_aprovadas = [];
$solic_historico = [];

foreach ($solicitacoes as $sp) {
    if ($sp['status'] === 'pendente') {
        $solic_pendentes[] = $sp;
    } elseif (in_array($sp['status'], ['aprovada_estoque', 'aprovada_compra'])) {
        $solic_aprovadas[] = $sp;
    } else {
        $solic_historico[] = $sp;
    }
}

// ===== CARRINHO DO ESTOQUE (sessão) =====
if (!isset($_SESSION['estoque_carrinho'])) {
    $_SESSION['estoque_carrinho'] = [];
}
$carrinho_ids = $_SESSION['estoque_carrinho'];
$carrinho_itens = [];

if (!empty($carrinho_ids)) {
    $ids_limpos = array_map('intval', $carrinho_ids);
    $lista = implode(',', $ids_limpos);
    $res_carrinho = $conn->query("
        SELECT id_estoque, nome, quantidade, categoria, marca, valor_venda
        FROM estoque
        WHERE id_estoque IN ({$lista}) AND ativo = 1
    ");
    $carrinho_itens = $res_carrinho->fetch_all(MYSQLI_ASSOC);
}

// ===== LISTA DE MECÂNICOS =====
$podeApontarOutro = in_array($usuarioLogado['nivel'], [1, 2]);
$mecanicos = [];
if ($podeApontarOutro) {
    $res = $conn->query("SELECT id_usuario, nome FROM usuarios WHERE ativo = 1 AND nivel = 3 ORDER BY nome ASC");
    $mecanicos = $res->fetch_all(MYSQLI_ASSOC);
}

// ===== PERMISSÕES =====
$nivel = (int)$usuarioLogado['nivel'];
$idUsuario = (int)$usuarioLogado['id_usuario'];

$souMecanico = ($nivel === 3);
$souAdmin = ($nivel === 1);
$souFinanceiro = ($nivel === 2);

$abriuOS = ((int)$os['id_usuario_abertura'] === $idUsuario);
$estouApontado = $apontamento_atual && ((int)$apontamento_atual['id_usuario'] === $idUsuario);

$podeEditar = $souAdmin || $abriuOS || ($souMecanico && $estouApontado);
$podeCancelar = in_array($nivel, [1, 2]);
$podeAprovar = in_array($nivel, [1, 2]);
$podeApontarSiMesmo = $souMecanico;

$statusFinal = in_array($os['status'], ['concluida', 'cancelada']);

$conn->close();

function nomeStatusOS($s)
{
    return [
        'aberta'                => 'Aberta',
        'em_andamento'          => 'Em Andamento',
        'aguardando_aprovacao'  => 'Aguardando Aprovação',
        'orcamento_enviado'     => 'Orçamento Enviado',
        'aprovado'              => 'Aprovado',
        'aguardando_pagamento'  => 'Aguardando Pagamento',
        'aguardando_peca'       => 'Aguardando Peça',
        'concluida'             => 'Concluída',
        'cancelada'             => 'Cancelada',
    ][$s] ?? $s;
}
function classeStatusOS($s)
{
    return [
        'aberta'                => 'admin-badge-info',
        'em_andamento'          => 'admin-badge-alerta',
        'aguardando_aprovacao'  => 'admin-badge-alerta',
        'orcamento_enviado'     => 'admin-badge-info',
        'aprovado'              => 'admin-badge-sucesso',
        'aguardando_pagamento'  => 'admin-badge-alerta',
        'aguardando_peca'       => 'admin-badge-erro',
        'concluida'             => 'admin-badge-sucesso',
        'cancelada'             => 'admin-badge-erro',
    ][$s] ?? 'admin-badge-info';
}
function formatarMinutos($min)
{
    if ($min < 60) return $min . ' min';
    $h = floor($min / 60);
    $m = $min % 60;
    return $h . 'h' . ($m > 0 ? ' ' . $m . 'min' : '');
}
?>

<div class="admin-topo-pagina">
    <h1 class="admin-titulo-pagina">
        <a href="ordens.php" class="admin-voltar" title="Voltar"><i class="fas fa-arrow-left"></i></a>
        OS <?php echo limpar($os['numero_os']); ?>
    </h1>
</div>

<?php
$msg = $_GET['msg'] ?? '';
$mensagens = [
    'cadastrada'          => ['texto' => 'OS aberta com sucesso!', 'tipo' => 'sucesso'],
    'apontado'            => ['texto' => 'Mecânico apontado com sucesso!', 'tipo' => 'sucesso'],
    'desapontado'         => ['texto' => 'Apontamento encerrado.', 'tipo' => 'alerta'],
    'peca_solicitada'     => ['texto' => 'Peça(s) solicitada(s)! O responsável verá na lista.', 'tipo' => 'sucesso'],
    'carrinho_add'        => ['texto' => 'Peça(s) do estoque pré-selecionada(s). Clique em "Solicitar Peças" pra enviar.', 'tipo' => 'sucesso'],
    'aprovadas'           => ['texto' => 'Peça(s) aprovada(s)!', 'tipo' => 'sucesso'],
    'negadas'             => ['texto' => 'Peça(s) negada(s).', 'tipo' => 'alerta'],
    'concluida'           => ['texto' => 'OS concluída com sucesso!', 'tipo' => 'sucesso'],
    'cancelada'           => ['texto' => 'OS cancelada. Ela continua no histórico.', 'tipo' => 'alerta'],
    'erro'                => ['texto' => 'Ocorreu um erro.', 'tipo' => 'erro'],
    'sem_permissao'       => ['texto' => 'Você não tem permissão para essa ação.', 'tipo' => 'erro'],
    'ja_apontado_outra'   => ['texto' => 'Este mecânico já está apontado em outra OS. Desaponte-o primeiro.', 'tipo' => 'erro'],
    'ja_apontado_nesta'   => ['texto' => 'Este mecânico já está apontado nesta OS.', 'tipo' => 'erro'],
    'nao_apontado'        => ['texto' => 'Você não está apontado nesta OS.', 'tipo' => 'erro'],
    'editada'             => ['texto' => 'OS atualizada com sucesso!', 'tipo' => 'sucesso'],
    'erro_obrig'          => ['texto' => 'Preencha todos os campos obrigatórios.', 'tipo' => 'erro'],
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

<!-- ===== STATUS + AÇÕES ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo">
        <span><i class="fas fa-info-circle"></i> Status</span>
        <span class="admin-badge <?php echo classeStatusOS($os['status']); ?>"
            style="font-size: 13px; padding: 6px 14px;">
            <?php echo nomeStatusOS($os['status']); ?>
        </span>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">

        <?php if ($podeApontarSiMesmo && !$estouApontado && !$statusFinal && !$apontamento_atual): ?>
            <form action="ordens_apontar_acao.php" method="POST" style="display:inline;">
                <input type="hidden" name="acao" value="apontar">
                <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                <button type="submit" class="admin-btn"><i class="fas fa-play"></i> Apontar-me nesta OS</button>
            </form>
        <?php endif; ?>

        <?php if ($estouApontado && !$statusFinal): ?>
            <span class="admin-badge admin-badge-alerta" style="font-size:13px; padding:8px 16px;">
                <i class="fas fa-user-check"></i>&nbsp; Você está trabalhando desde
                <?php echo date('H:i', strtotime($apontamento_atual['data_apontamento'])); ?>
            </span>
            <a href="ordens_editar.php?id=<?php echo $id_os; ?>" class="admin-btn">
                <i class="fas fa-edit"></i> Editar OS / Diagnóstico
            </a>
            <button type="button" class="admin-btn admin-btn-secundario" onclick="abrirModalSolicitarPeca()">
                <i class="fas fa-box-open"></i> Solicitar Peças
                <?php if (!empty($carrinho_itens)): ?>
                    <span class="admin-badge admin-badge-sucesso" style="margin-left:6px; font-size:10px;">
                        <?php echo count($carrinho_itens); ?> do estoque
                    </span>
                <?php endif; ?>
            </button>
            <form action="ordens_apontar_acao.php" method="POST" style="display:inline;">
                <input type="hidden" name="acao" value="desapontar">
                <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                <button type="submit" class="admin-btn admin-btn-secundario"><i class="fas fa-stop"></i> Desapontar</button>
            </form>
            <form action="ordens_status_acao.php" method="POST" style="display:inline;">
                <input type="hidden" name="acao" value="concluir">
                <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                <button type="submit" class="admin-btn"
                    onclick="return confirm('Concluir a OS <?php echo limpar($os['numero_os']); ?>?');">
                    <i class="fas fa-check"></i> Concluir OS
                </button>
            </form>
        <?php endif; ?>

        <?php if ($podeApontarOutro && !$apontamento_atual && !$statusFinal): ?>
            <button type="button" class="admin-btn" onclick="abrirModalApontar()">
                <i class="fas fa-user-plus"></i> Apontar mecânico
            </button>
        <?php endif; ?>

        <?php if ($podeApontarOutro && $apontamento_atual): ?>
            <form action="ordens_apontar_acao.php" method="POST" style="display:inline;">
                <input type="hidden" name="acao" value="desapontar">
                <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                <button type="submit" class="admin-btn admin-btn-secundario"
                    onclick="return confirm('Desapontar <?php echo limpar($apontamento_atual['mecanico_nome']); ?>?');">
                    <i class="fas fa-user-slash"></i> Desapontar <?php echo limpar($apontamento_atual['mecanico_nome']); ?>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($podeEditar && !$statusFinal && !$estouApontado): ?>
            <a href="ordens_editar.php?id=<?php echo $id_os; ?>" class="admin-btn admin-btn-secundario">
                <i class="fas fa-edit"></i> Editar OS
            </a>
        <?php endif; ?>

        <?php if ($podeCancelar && !$statusFinal): ?>
            <form action="ordens_status_acao.php" method="POST" style="display:inline; margin-left:auto;">
                <input type="hidden" name="acao" value="cancelar">
                <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                <button type="submit" class="admin-btn admin-btn-secundario"
                    style="border-color: var(--mtech-red); color: var(--mtech-red);"
                    onclick="return confirm('Cancelar a OS <?php echo limpar($os['numero_os']); ?>?\n\nEla NÃO será apagada — só ficará com status Cancelada.');">
                    <i class="fas fa-ban"></i> Cancelar OS
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- ===== AVISO DO CARRINHO DO ESTOQUE ===== -->
<?php if (!empty($carrinho_itens) && $estouApontado): ?>
    <div class="admin-bloco" style="border-left: 4px solid #25d366;">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-shopping-basket"></i> Peças pré-selecionadas do estoque</span>
            <span><?php echo count($carrinho_itens); ?> item(ns)</span>
        </div>
        <p style="font-size:13px; color:var(--mtech-text-muted); margin-bottom:10px;">
            Você marcou estas peças no estoque. Clique em <strong>"Solicitar Peças"</strong> pra enviar como solicitação da
            OS.
        </p>
        <ul style="list-style:none; display:grid; gap:6px;">
            <?php foreach ($carrinho_itens as $ci): ?>
                <li
                    style="display:flex; justify-content:space-between; padding:8px 12px; background:rgba(37,211,102,0.05); border-radius:6px; font-size:13px;">
                    <span><strong><?php echo limpar($ci['nome']); ?></strong>
                        <?php echo $ci['marca'] ? '— ' . limpar($ci['marca']) : ''; ?></span>
                    <span style="color:#25d366;">Tem <?php echo (int)$ci['quantidade']; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <div style="margin-top:12px;">
            <a href="estoque_limpar_carrinho_acao.php" class="admin-btn admin-btn-secundario"
                style="font-size:12px; padding:6px 12px;">
                <i class="fas fa-times"></i> Limpar seleção
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- ===== APONTAMENTO ATUAL ===== -->
<?php if ($apontamento_atual): ?>
    <div class="admin-bloco">
        <div class="admin-bloco-titulo"><span><i class="fas fa-user-cog"></i> Trabalhando agora</span></div>
        <p style="font-size:15px;">
            <strong style="color: var(--mtech-yellow);"><?php echo limpar($apontamento_atual['mecanico_nome']); ?></strong>
            está apontado desde
            <strong><?php echo date('d/m/Y \à\s H:i', strtotime($apontamento_atual['data_apontamento'])); ?></strong>
        </p>
    </div>
<?php endif; ?>

<!-- ===== DIAGNÓSTICO / SOLUÇÃO ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo">
        <span><i class="fas fa-stethoscope"></i> Diagnóstico e Solução</span>
        <?php if ($podeEditar && !$statusFinal): ?>
            <a href="ordens_editar.php?id=<?php echo $id_os; ?>" style="font-size:12px; color: var(--mtech-yellow);">
                <i class="fas fa-pen"></i> editar
            </a>
        <?php endif; ?>
    </div>
    <div class="admin-form-campo">
        <label>Diagnóstico</label>
        <textarea disabled rows="4" placeholder="O mecânico ainda não preencheu o diagnóstico."
            style="font-family: 'Poppins', sans-serif; resize: none;"><?php echo limpar($os['diagnostico'] ?? ''); ?></textarea>
    </div>
    <div class="admin-form-campo" style="margin-top: 15px;">
        <label>Solução aplicada</label>
        <textarea disabled rows="4" placeholder="O mecânico ainda não preencheu a solução."
            style="font-family: 'Poppins', sans-serif; resize: none;"><?php echo limpar($os['solucao'] ?? ''); ?></textarea>
    </div>
</div>

<!-- ===== DADOS DA OS ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo"><span><i class="fas fa-clipboard-list"></i> Dados da OS</span></div>
    <div class="admin-form-grid">
        <div class="admin-form-campo"><label>Número</label>
            <input type="text" value="<?php echo limpar($os['numero_os']); ?>" disabled
                style="font-family:monospace; letter-spacing:1px;">
        </div>
        <div class="admin-form-campo"><label>Abertura</label>
            <input type="text" value="<?php echo date('d/m/Y H:i', strtotime($os['data_abertura'])); ?>" disabled>
        </div>
        <div class="admin-form-campo"><label>Aberta por</label>
            <input type="text" value="<?php echo limpar($os['abertura_nome'] ?? '—'); ?>" disabled>
        </div>
        <div class="admin-form-campo"><label>Previsão de entrega</label>
            <input type="text"
                value="<?php echo $os['data_previsao'] ? date('d/m/Y', strtotime($os['data_previsao'])) : '—'; ?>"
                disabled>
        </div>
        <?php if ($os['data_conclusao']): ?>
            <div class="admin-form-campo"><label>Concluída em</label>
                <input type="text" value="<?php echo date('d/m/Y H:i', strtotime($os['data_conclusao'])); ?>" disabled>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== CLIENTE E VEÍCULO ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo"><span><i class="fas fa-user"></i> Cliente e Veículo</span></div>
    <div class="admin-form-grid">
        <div class="admin-form-campo"><label>Cliente</label>
            <input type="text" value="<?php echo limpar($os['cliente_nome']); ?>" disabled>
        </div>
        <div class="admin-form-campo"><label>Telefone / WhatsApp</label>
            <input type="text" value="<?php echo limpar($os['cliente_whatsapp'] ?: $os['cliente_telefone'] ?: '—'); ?>"
                disabled>
        </div>
        <div class="admin-form-campo"><label>Veículo</label>
            <input type="text" value="<?php echo limpar($os['marca'] . ' ' . $os['modelo']); ?>" disabled>
        </div>
        <div class="admin-form-campo"><label>Placa</label>
            <input type="text" value="<?php echo limpar(strtoupper($os['placa'])); ?>" disabled
                style="font-family:monospace; letter-spacing:1px;">
        </div>
        <div class="admin-form-campo"><label>Ano / Cor</label>
            <input type="text" value="<?php echo limpar(($os['ano'] ?: '—') . ' / ' . ($os['cor'] ?: '—')); ?>"
                disabled>
        </div>
        <div class="admin-form-campo"><label>KM atual</label>
            <input type="text"
                value="<?php echo $os['km_atual'] !== null ? number_format((int)$os['km_atual'], 0, ',', '.') : '—'; ?>"
                disabled>
        </div>
        <?php if (!empty($os['carro_obs'])): ?>
            <div class="admin-form-campo admin-form-campo-full"><label>Avarias / Observações do veículo</label>
                <textarea disabled rows="3"><?php echo limpar($os['carro_obs']); ?></textarea>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== DESCRIÇÃO ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo"><span><i class="fas fa-tools"></i> Descrição do Serviço</span></div>
    <div class="admin-form-campo"><label>Problema relatado</label>
        <textarea disabled rows="5"><?php echo limpar($os['descricao_problema']); ?></textarea>
    </div>
</div>

<!-- ===== SOLICITAÇÕES PENDENTES ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo">
        <span><i class="fas fa-hourglass-half"></i> Solicitações Pendentes de Aprovação</span>
        <span><?php echo count($solic_pendentes); ?> pendente(s)</span>
    </div>

    <?php if (empty($solic_pendentes)): ?>
        <div class="admin-vazio" style="padding:30px 20px;">
            <i class="fas fa-check-circle"></i>
            <p>Nenhuma solicitação pendente.</p>
            <small>Quando o mecânico solicitar peças, elas aparecerão aqui pra aprovação.</small>
        </div>
    <?php else: ?>
        <?php if ($podeAprovar): ?>
            <form action="ordens_aprovar_pecas_acao.php" method="POST" id="formAprovarPecas">
                <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">

                <div style="margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                        <input type="checkbox" id="selecionarTodas" onchange="toggleTodas(this)"> Selecionar todas
                    </label>
                    <button type="submit" class="admin-btn" style="margin-left: auto;">
                        <i class="fas fa-check-double"></i> Aprovar selecionadas
                    </button>
                    <button type="button" class="admin-btn admin-btn-secundario"
                        style="border-color: var(--mtech-red); color: var(--mtech-red);" onclick="abrirModalNegarPecas()">
                        <i class="fas fa-times"></i> Negar selecionadas
                    </button>
                </div>

                <div style="overflow-x:auto;">
                    <table class="admin-tabela">
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>Peça</th>
                                <th style="text-align:center;">Qtd</th>
                                <th>Solicitado por</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solic_pendentes as $sp): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="ids[]" value="<?php echo (int)$sp['id_solicitacao']; ?>"
                                            class="check-peca">
                                    </td>
                                    <td>
                                        <strong><?php echo limpar($sp['nome_peca']); ?></strong>
                                        <?php if (!empty($sp['observacoes'])): ?>
                                            <br><small
                                                style="color:var(--mtech-text-muted);"><?php echo limpar($sp['observacoes']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php echo number_format((float)$sp['quantidade'], 0, ',', '.'); ?>
                                    </td>
                                    <td><?php echo limpar($sp['solicitou_nome']); ?></td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($sp['data_solicitacao'])); ?>
                                        <br><small
                                            style="color:var(--mtech-text-muted);"><?php echo date('H:i', strtotime($sp['data_solicitacao'])); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="admin-tabela">
                    <thead>
                        <tr>
                            <th>Peça</th>
                            <th style="text-align:center;">Qtd</th>
                            <th>Solicitado por</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solic_pendentes as $sp): ?>
                            <tr>
                                <td><strong><?php echo limpar($sp['nome_peca']); ?></strong></td>
                                <td style="text-align:center;"><?php echo number_format((float)$sp['quantidade'], 0, ',', '.'); ?>
                                </td>
                                <td><?php echo limpar($sp['solicitou_nome']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sp['data_solicitacao'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p style="color:var(--mtech-text-muted); font-size:13px; margin-top:15px;">
                <i class="fas fa-info-circle"></i> Apenas níveis 1 e 2 podem aprovar/negar.
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ===== APROVADAS ===== -->
<?php if (!empty($solic_aprovadas)): ?>
    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-clipboard-check"></i> Aguardando Chegada / Retirada</span>
            <span><?php echo count($solic_aprovadas); ?> item(ns)</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="admin-tabela">
                <thead>
                    <tr>
                        <th>Peça</th>
                        <th style="text-align:center;">Qtd</th>
                        <th>Origem</th>
                        <th>Aprovado por</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solic_aprovadas as $sp): ?>
                        <tr>
                            <td><strong><?php echo limpar($sp['nome_peca']); ?></strong></td>
                            <td style="text-align:center;"><?php echo number_format((float)$sp['quantidade'], 0, ',', '.'); ?>
                            </td>
                            <td>
                                <?php if ($sp['origem'] === 'estoque'): ?>
                                    <span class="admin-badge admin-badge-sucesso"><i class="fas fa-boxes"></i> Estoque</span>
                                <?php elseif ($sp['origem'] === 'compra'): ?>
                                    <span class="admin-badge admin-badge-alerta"><i class="fas fa-shopping-cart"></i> Compra</span>
                                <?php else: ?>
                                    <span style="color:var(--mtech-text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo limpar($sp['aprovou_nome'] ?? '—'); ?></td>
                            <td>
                                <?php if ($sp['status'] === 'aprovada_estoque'): ?>
                                    <span class="admin-badge admin-badge-info">Aguardando retirada</span>
                                <?php elseif ($sp['status'] === 'aprovada_compra'): ?>
                                    <span class="admin-badge admin-badge-alerta">Aguardando compra/chegada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ===== HISTÓRICO ===== -->
<?php if (!empty($solic_historico)): ?>
    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-history"></i> Histórico de Solicitações</span>
            <span><?php echo count($solic_historico); ?> item(ns)</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="admin-tabela">
                <thead>
                    <tr>
                        <th>Peça</th>
                        <th style="text-align:center;">Qtd</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solic_historico as $sp): ?>
                        <tr>
                            <td><strong><?php echo limpar($sp['nome_peca']); ?></strong></td>
                            <td style="text-align:center;"><?php echo number_format((float)$sp['quantidade'], 0, ',', '.'); ?>
                            </td>
                            <td>
                                <?php if ($sp['status'] === 'entregue'): ?>
                                    <span class="admin-badge admin-badge-sucesso"><i class="fas fa-check"></i> Entregue</span>
                                <?php elseif ($sp['status'] === 'negada'): ?>
                                    <span class="admin-badge admin-badge-erro"><i class="fas fa-times"></i> Negada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($sp['data_solicitacao'])); ?>
                                <br><small
                                    style="color:var(--mtech-text-muted);"><?php echo date('H:i', strtotime($sp['data_solicitacao'])); ?></small>
                            </td>
                            <td>
                                <?php if ($sp['status'] === 'negada' && !empty($sp['motivo_recusa'])): ?>
                                    <small
                                        style="color:var(--mtech-red);"><em><?php echo limpar($sp['motivo_recusa']); ?></em></small>
                                <?php elseif ($sp['status'] === 'entregue' && $sp['data_entrega']): ?>
                                    <small style="color:var(--mtech-text-muted);">Entregue em
                                        <?php echo date('d/m/Y H:i', strtotime($sp['data_entrega'])); ?></small>
                                <?php else: ?>
                                    <span style="color:var(--mtech-text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ===== HISTÓRICO DE APONTAMENTOS ===== -->
<?php if (!empty($apontamentos) && ($souAdmin || $souFinanceiro)): ?>
    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-clock"></i> Histórico de Apontamentos</span>
            <span><?php echo count($apontamentos); ?> registro(s)</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="admin-tabela">
                <thead>
                    <tr>
                        <th>Mecânico</th>
                        <th>Entrou</th>
                        <th>Saiu</th>
                        <th>Tempo</th>
                        <th>Apontado por</th>
                        <th>Desapontado por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apontamentos as $ap): ?>
                        <tr>
                            <td><strong><?php echo limpar($ap['mecanico_nome']); ?></strong></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($ap['data_apontamento'])); ?></td>
                            <td><?php echo $ap['data_desapontamento'] ? date('d/m/Y H:i', strtotime($ap['data_desapontamento'])) : '<span class="admin-badge admin-badge-alerta">Em andamento</span>'; ?>
                            </td>
                            <td><?php echo formatarMinutos((int)$ap['minutos']); ?></td>
                            <td><?php echo $ap['apontou_nome'] ? limpar($ap['apontou_nome']) : '—'; ?></td>
                            <td><?php echo $ap['desapontou_nome'] ? limpar($ap['desapontou_nome']) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ===== MODAL: SOLICITAR PEÇAS ===== -->
<div class="admin-modal-fundo" id="modalSolicitarPecaFundo"></div>
<div class="admin-modal" id="modalSolicitarPeca" style="max-width: 750px;">
    <div class="admin-modal-titulo"><i class="fas fa-box-open"></i> Solicitar Peças</div>
    <form action="ordens_solicitar_peca_acao.php" method="POST" class="admin-modal-form" id="formSolicitarPeca">
        <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">

        <?php if (!empty($carrinho_itens)): ?>
            <div
                style="padding: 12px; background: rgba(37, 211, 102, 0.08); border-left: 3px solid #25d366; border-radius: 6px;">
                <p style="font-size: 13px; margin-bottom: 8px;">
                    <i class="fas fa-check-circle" style="color: #25d366;"></i>
                    <strong><?php echo count($carrinho_itens); ?></strong> peça(s) pré-selecionada(s) do estoque:
                </p>
                <?php foreach ($carrinho_itens as $ci): ?>
                    <div style="display:flex; justify-content:space-between; padding:4px 0; font-size:13px;">
                        <span><?php echo limpar($ci['nome']); ?></span>
                        <span style="color:var(--mtech-text-muted);">R$
                            <?php echo number_format((float)$ci['valor_venda'], 2, ',', '.'); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div id="linhasPecas"></div>

        <button type="button" class="admin-btn admin-btn-secundario" onclick="adicionarLinhaPeca()"
            style="align-self: flex-start;">
            <i class="fas fa-plus"></i> Adicionar outra peça
        </button>

        <div class="admin-modal-acoes">
            <button type="button" class="admin-btn admin-btn-secundario"
                onclick="fecharModalSolicitarPeca()">Cancelar</button>
            <button type="submit" class="admin-btn"><i class="fas fa-paper-plane"></i> Enviar Solicitação</button>
        </div>
    </form>
</div>

<!-- ===== MODAL: NEGAR PEÇAS EM LOTE ===== -->
<div class="admin-modal-fundo" id="modalNegarPecasFundo"></div>
<div class="admin-modal" id="modalNegarPecas">
    <div class="admin-modal-titulo"><i class="fas fa-times-circle"></i> Negar Peças Selecionadas</div>
    <form action="ordens_negar_pecas_acao.php" method="POST" class="admin-modal-form" id="formNegarPecas">
        <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
        <div id="idsNegarContainer"></div>

        <div class="admin-form-campo">
            <label for="motivo_negacao_lote">Motivo da negação *</label>
            <textarea id="motivo_negacao_lote" name="motivo" rows="4" required
                placeholder="Ex: Peça não disponível no fornecedor, cliente não autorizou, valor muito alto..."></textarea>
        </div>

        <p style="font-size:13px; color:var(--mtech-text-muted);" id="resumoNegar"></p>

        <div class="admin-modal-acoes">
            <button type="button" class="admin-btn admin-btn-secundario"
                onclick="fecharModalNegarPecas()">Cancelar</button>
            <button type="submit" class="admin-btn" style="background: var(--mtech-red);">
                <i class="fas fa-times"></i> Confirmar Negação
            </button>
        </div>
    </form>
</div>

<!-- ===== MODAL: APONTAR MECÂNICO ===== -->
<div class="admin-modal-fundo" id="modalApontarFundo"></div>
<div class="admin-modal" id="modalApontar">
    <div class="admin-modal-titulo"><i class="fas fa-user-plus"></i> Apontar Mecânico</div>
    <form action="ordens_apontar_acao.php" method="POST" class="admin-modal-form">
        <input type="hidden" name="acao" value="apontar">
        <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
        <div class="admin-form-campo">
            <label for="id_mecanico_apontar">Mecânico *</label>
            <select id="id_mecanico_apontar" name="id_mecanico" required>
                <option value="">— Selecione —</option>
                <?php foreach ($mecanicos as $mec): ?>
                    <option value="<?php echo (int)$mec['id_usuario']; ?>"><?php echo limpar($mec['nome']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-modal-acoes">
            <button type="button" class="admin-btn admin-btn-secundario"
                onclick="fecharModalApontar()">Cancelar</button>
            <button type="submit" class="admin-btn"><i class="fas fa-play"></i> Apontar</button>
        </div>
    </form>
</div>

<style>
    .linha-peca {
        display: grid;
        grid-template-columns: 1fr 90px 1fr 42px;
        gap: 10px;
        align-items: end;
        padding: 12px;
        border: 1px solid var(--mtech-border);
        border-radius: 8px;
        margin-bottom: 10px;
        background: rgba(255, 255, 255, 0.02);
    }

    .linha-peca .admin-form-campo {
        gap: 5px;
    }

    .linha-peca .admin-form-campo label {
        font-size: 11px;
    }

    .linha-peca input {
        padding: 9px 12px;
        font-size: 13px;
    }

    .linha-peca .btn-remover-linha {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        color: var(--mtech-red);
        border: 1px solid var(--mtech-border);
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
    }

    .linha-peca .btn-remover-linha:hover {
        background: rgba(214, 45, 45, 0.1);
        border-color: var(--mtech-red);
    }

    @media (max-width: 600px) {
        .linha-peca {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    let contadorLinhas = 0;

    // Peças do carrinho da sessão
    const carrinhoItens =
        <?php echo json_encode($carrinho_itens, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function criarLinhaPeca(indice, dados) {
        const wrapper = document.createElement('div');
        wrapper.className = 'linha-peca';
        const nome = dados ? dados.nome : '';
        const qtd = dados ? 1 : 1;
        const idEstoque = dados ? dados.id_estoque : '';
        const valor = dados ? dados.valor_venda : 0;
        const badge = dados ?
            '<span class="admin-badge admin-badge-sucesso" style="font-size:10px; margin-left:6px;">🟢 do estoque</span>' :
            '';

        wrapper.innerHTML = `
            <div class="admin-form-campo">
                <label>Nome da peça * ${badge}</label>
                <input type="text" name="pecas[${indice}][nome]" required placeholder="Ex: Pastilha de freio" value="${String(nome).replace(/"/g, '&quot;')}">
                <input type="hidden" name="pecas[${indice}][id_estoque]" value="${idEstoque}">
            </div>
            <div class="admin-form-campo">
                <label>Qtd *</label>
                <input type="number" name="pecas[${indice}][qtd]" value="${qtd}" min="1" step="1" required>
            </div>
            <div class="admin-form-campo">
                <label>Observações</label>
                <input type="text" name="pecas[${indice}][obs]" placeholder="Marca, código, urgência...">
            </div>
            <button type="button" class="btn-remover-linha" title="Remover" onclick="this.parentElement.remove()">
                <i class="fas fa-trash"></i>
            </button>
        `;
        return wrapper;
    }

    function adicionarLinhaPeca(dados) {
        const container = document.getElementById('linhasPecas');
        const linha = criarLinhaPeca(contadorLinhas, dados || null);
        container.appendChild(linha);
        contadorLinhas++;
        if (!dados) {
            const primeiroInput = linha.querySelector('input[type="text"]');
            if (primeiroInput) primeiroInput.focus();
        }
    }

    function abrirModalSolicitarPeca() {
        const container = document.getElementById('linhasPecas');
        container.innerHTML = '';
        contadorLinhas = 0;

        // Pré-preenche com itens do carrinho
        if (carrinhoItens.length > 0) {
            carrinhoItens.forEach(item => adicionarLinhaPeca(item));
        } else {
            adicionarLinhaPeca();
        }

        document.getElementById('modalSolicitarPeca').classList.add('ativo');
        document.getElementById('modalSolicitarPecaFundo').classList.add('ativo');
    }

    function fecharModalSolicitarPeca() {
        document.getElementById('modalSolicitarPeca').classList.remove('ativo');
        document.getElementById('modalSolicitarPecaFundo').classList.remove('ativo');
    }
    document.getElementById('modalSolicitarPecaFundo').addEventListener('click', fecharModalSolicitarPeca);

    function toggleTodas(cb) {
        document.querySelectorAll('.check-peca').forEach(c => c.checked = cb.checked);
    }

    function abrirModalNegarPecas() {
        const marcados = document.querySelectorAll('.check-peca:checked');
        if (marcados.length === 0) {
            alert('Selecione pelo menos uma peça.');
            return;
        }
        const container = document.getElementById('idsNegarContainer');
        container.innerHTML = '';
        marcados.forEach(c => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = c.value;
            container.appendChild(input);
        });
        document.getElementById('resumoNegar').textContent = marcados.length + ' peça(s) serão negadas.';
        document.getElementById('modalNegarPecas').classList.add('ativo');
        document.getElementById('modalNegarPecasFundo').classList.add('ativo');
    }

    function fecharModalNegarPecas() {
        document.getElementById('modalNegarPecas').classList.remove('ativo');
        document.getElementById('modalNegarPecasFundo').classList.remove('ativo');
    }
    document.getElementById('modalNegarPecasFundo').addEventListener('click', fecharModalNegarPecas);

    function abrirModalApontar() {
        document.getElementById('modalApontar').classList.add('ativo');
        document.getElementById('modalApontarFundo').classList.add('ativo');
    }

    function fecharModalApontar() {
        document.getElementById('modalApontar').classList.remove('ativo');
        document.getElementById('modalApontarFundo').classList.remove('ativo');
    }
    document.getElementById('modalApontarFundo').addEventListener('click', fecharModalApontar);
</script>

<?php require_once '_footer.php'; ?>