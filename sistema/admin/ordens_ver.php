<?php
/* =========================================================
   M-TECH SYSTEM — ORDENS DE SERVIÇO (ver detalhes)
   Botões contextuais por status do fluxo novo
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

// ===== SOLICITAÇÕES =====
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

// ===== ORÇAMENTO =====
$stmt = $conn->prepare("
    SELECT o.*,
           (SELECT COALESCE(SUM(valor), 0) FROM os_orcamento_pagamentos
            WHERE id_orcamento = o.id_orcamento AND status = 'confirmado') AS total_pago
    FROM os_orcamentos o
    WHERE o.id_os = ?
    ORDER BY o.id_orcamento DESC LIMIT 1
");
$stmt->bind_param('i', $id_os);
$stmt->execute();
$orcamento = $stmt->get_result()->fetch_assoc();
$stmt->close();

$saldo_orc = null;
$tem_adendo = false;

if ($orcamento) {
    $total = (float)$orcamento['valor_total'];
    $pago = (float)$orcamento['total_pago'];
    $saldo = $total - $pago;
    if ($saldo < 0) $saldo = 0;
    $saldo_orc = [
        'total' => $total,
        'pago' => $pago,
        'saldo' => $saldo,
        'quitado' => ($saldo <= 0.009)
    ];

    if (!empty($orcamento['adendo_status']) && $orcamento['adendo_status'] !== 'nenhum') {
        $tem_adendo = true;
    }
}

// ===== CARRINHO =====
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

// ===== MECÂNICOS =====
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
$podeVerValores = in_array($nivel, [1, 2]);

$statusFinal = in_array($os['status'], ['concluida', 'cancelada']);

$conn->close();
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
    'apontado'            => ['texto' => 'Mecânico apontado!', 'tipo' => 'sucesso'],
    'desapontado'         => ['texto' => 'Apontamento encerrado.', 'tipo' => 'alerta'],
    'peca_solicitada'     => ['texto' => 'Peça(s) solicitada(s)!', 'tipo' => 'sucesso'],
    'adendo_gerado'       => ['texto' => 'Adendo gerado!', 'tipo' => 'sucesso'],
    'carrinho_add'        => ['texto' => 'Peça(s) pré-selecionada(s).', 'tipo' => 'sucesso'],
    'aprovadas'           => ['texto' => 'Peça(s) aprovada(s)!', 'tipo' => 'sucesso'],
    'negadas'             => ['texto' => 'Peça(s) negada(s).', 'tipo' => 'alerta'],
    'negada'              => ['texto' => 'Peça negada.', 'tipo' => 'alerta'],
    'requisitada'         => ['texto' => 'Peça requisitada do estoque!', 'tipo' => 'sucesso'],
    'compra_aprovada'     => ['texto' => 'Peça enviada para compra!', 'tipo' => 'sucesso'],
    'solicitacao_fechada' => ['texto' => 'Solicitação fechada.', 'tipo' => 'sucesso'],
    'pecas_chegaram'      => ['texto' => 'Peças chegaram!', 'tipo' => 'sucesso'],
    'pronta'              => ['texto' => 'OS marcada como pronta!', 'tipo' => 'sucesso'],
    'pronta_retirada'     => ['texto' => 'OS pronta e paga!', 'tipo' => 'sucesso'],
    'concluida'           => ['texto' => 'OS concluída!', 'tipo' => 'sucesso'],
    'cancelada'           => ['texto' => 'OS cancelada.', 'tipo' => 'alerta'],
    'erro'                => ['texto' => 'Ocorreu um erro.', 'tipo' => 'erro'],
    'sem_permissao'       => ['texto' => 'Você não tem permissão.', 'tipo' => 'erro'],
    'ja_apontado_outra'   => ['texto' => 'Este mecânico já está em outra OS.', 'tipo' => 'erro'],
    'ja_apontado_nesta'   => ['texto' => 'Este mecânico já está nesta OS.', 'tipo' => 'erro'],
    'nao_apontado'        => ['texto' => 'Você não está apontado nesta OS.', 'tipo' => 'erro'],
    'nao_pode_apontar'    => ['texto' => 'Esta OS não permite apontamento agora.', 'tipo' => 'erro'],
    'editada'             => ['texto' => 'OS atualizada!', 'tipo' => 'sucesso'],
    'erro_obrig'          => ['texto' => 'Preencha os campos obrigatórios.', 'tipo' => 'erro'],
    'estoque_insuficiente' => ['texto' => 'Estoque insuficiente.', 'tipo' => 'erro'],
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
        <span><i class="fas fa-info-circle"></i> Status da OS</span>
        <span class="admin-badge <?php echo classeStatusOS($os['status']); ?>"
            style="font-size: 13px; padding: 6px 14px;">
            <?php echo nomeStatusOS($os['status']); ?>
        </span>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">

        <?php // ===== ABERTA ===== 
        ?>
        <?php if ($os['status'] === 'aberta'): ?>
            <?php if ($souMecanico && !$apontamento_atual): ?>
                <form action="ordens_apontar_acao.php" method="POST" style="display:inline;">
                    <input type="hidden" name="acao" value="apontar">
                    <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                    <button type="submit" class="admin-btn"><i class="fas fa-play"></i> Apontar-me nesta OS</button>
                </form>
            <?php endif; ?>
            <?php if ($podeApontarOutro && !$apontamento_atual): ?>
                <button type="button" class="admin-btn" onclick="abrirModalApontar()">
                    <i class="fas fa-user-plus"></i> Apontar mecânico
                </button>
            <?php endif; ?>
            <?php if ($podeEditar): ?>
                <a href="ordens_editar.php?id=<?php echo $id_os; ?>" class="admin-btn admin-btn-secundario">
                    <i class="fas fa-edit"></i> Editar OS
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php // ===== EM ANDAMENTO ===== 
        ?>
        <?php if ($os['status'] === 'em_andamento'): ?>
            <?php if ($estouApontado): ?>
                <span class="admin-badge admin-badge-alerta" style="font-size:13px; padding:8px 16px;">
                    <i class="fas fa-user-check"></i>&nbsp; Você está apontado
                </span>
                <a href="ordens_editar.php?id=<?php echo $id_os; ?>" class="admin-btn">
                    <i class="fas fa-edit"></i> Editar / Diagnóstico
                </a>
                <button type="button" class="admin-btn admin-btn-secundario" onclick="abrirModalSolicitarPeca()">
                    <i class="fas fa-box-open"></i> Solicitar Peças
                    <?php if (!empty($carrinho_itens)): ?>
                        <span class="admin-badge admin-badge-sucesso" style="margin-left:6px; font-size:10px;">
                            <?php echo count($carrinho_itens); ?>
                        </span>
                    <?php endif; ?>
                </button>
                <form action="ordens_apontar_acao.php" method="POST" style="display:inline;">
                    <input type="hidden" name="acao" value="desapontar">
                    <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                    <button type="submit" class="admin-btn admin-btn-secundario">
                        <i class="fas fa-stop"></i> Desapontar
                    </button>
                </form>
            <?php elseif ($apontamento_atual): ?>
                <span class="admin-badge admin-badge-alerta" style="font-size:13px; padding:8px 16px;">
                    <i class="fas fa-user-check"></i>&nbsp; <?php echo limpar($apontamento_atual['mecanico_nome']); ?>
                    trabalhando
                </span>
                <?php if ($podeApontarOutro): ?>
                    <form action="ordens_apontar_acao.php" method="POST" style="display:inline;">
                        <input type="hidden" name="acao" value="desapontar">
                        <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                        <button type="submit" class="admin-btn admin-btn-secundario"
                            onclick="return confirm('Desapontar <?php echo limpar($apontamento_atual['mecanico_nome']); ?>?');">
                            <i class="fas fa-user-slash"></i> Desapontar
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($podeEditar): ?>
                    <a href="ordens_editar.php?id=<?php echo $id_os; ?>" class="admin-btn admin-btn-secundario">
                        <i class="fas fa-edit"></i> Editar OS
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($podeApontarOutro): ?>
                    <button type="button" class="admin-btn" onclick="abrirModalApontar()">
                        <i class="fas fa-user-plus"></i> Apontar mecânico
                    </button>
                <?php endif; ?>
                <?php if ($podeEditar): ?>
                    <a href="ordens_editar.php?id=<?php echo $id_os; ?>" class="admin-btn admin-btn-secundario">
                        <i class="fas fa-edit"></i> Editar OS
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php // ===== AGUARDANDO APROVAÇÃO ===== 
        ?>
        <?php if ($os['status'] === 'aguardando_aprovacao'): ?>
            <span class="admin-badge admin-badge-info" style="font-size:13px; padding:8px 16px;">
                <i class="fas fa-clock"></i>&nbsp; Aguardando revisão do orçamento
            </span>

            <?php if ($podeAprovar): ?>
                <a href="orcamentos.php?filtro=aguardando_revisao" class="admin-btn">
                    <i class="fas fa-file-invoice-dollar"></i> Ir pra Orçamentos
                </a>
            <?php endif; ?>

            <?php // Mecânico apontado ainda pode desapontar 
            ?>
            <?php if ($estouApontado): ?>
                <span class="admin-badge admin-badge-alerta" style="font-size:13px; padding:8px 16px;">
                    <i class="fas fa-user-check"></i>&nbsp; Você está apontado — desaponte pra liberar
                </span>
                <a href="ordens_editar.php?id=<?php echo $id_os; ?>" class="admin-btn admin-btn-secundario">
                    <i class="fas fa-edit"></i> Editar OS
                </a>
                <form action="ordens_apontar_acao.php" method="POST" style="display:inline;">
                    <input type="hidden" name="acao" value="desapontar">
                    <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                    <button type="submit" class="admin-btn admin-btn-secundario">
                        <i class="fas fa-stop"></i> Desapontar
                    </button>
                </form>
            <?php endif; ?>

            <?php // Admin pode desapontar 
            ?>
            <?php if ($podeApontarOutro && $apontamento_atual && !$estouApontado): ?>
                <form action="ordens_apontar_acao.php" method="POST" style="display:inline;">
                    <input type="hidden" name="acao" value="desapontar">
                    <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                    <button type="submit" class="admin-btn admin-btn-secundario"
                        onclick="return confirm('Desapontar <?php echo limpar($apontamento_atual['mecanico_nome']); ?>?');">
                        <i class="fas fa-user-slash"></i> Desapontar <?php echo limpar($apontamento_atual['mecanico_nome']); ?>
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php // ===== AGUARDANDO PEÇA ===== 
        ?>
        <?php if ($os['status'] === 'aguardando_peca'): ?>
            <span class="admin-badge admin-badge-erro" style="font-size:13px; padding:8px 16px;">
                <i class="fas fa-hourglass-half"></i>&nbsp; Aguardando chegada de peças
            </span>
            <?php if ($podeAprovar): ?>
                <form action="ordens_status_acao.php" method="POST" style="display:inline;">
                    <input type="hidden" name="acao" value="pecas_chegaram">
                    <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                    <button type="submit" class="admin-btn" onclick="return confirm('Confirmar que as peças chegaram?');">
                        <i class="fas fa-check"></i> Peças Chegaram
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php // ===== EM EXECUÇÃO ===== 
        ?>
        <?php if ($os['status'] === 'em_execucao'): ?>
            <?php if ($estouApontado): ?>
                <span class="admin-badge admin-badge-alerta" style="font-size:13px; padding:8px 16px;">
                    <i class="fas fa-user-check"></i>&nbsp; Executando
                </span>
                <a href="ordens_editar.php?id=<?php echo $id_os; ?>" class="admin-btn">
                    <i class="fas fa-edit"></i> Editar / Solução
                </a>
                <button type="button" class="admin-btn admin-btn-secundario" onclick="abrirModalSolicitarPeca()">
                    <i class="fas fa-box-open"></i> Solicitar mais peças
                </button>
                <form action="ordens_status_acao.php" method="POST" style="display:inline;">
                    <input type="hidden" name="acao" value="marcar_pronta">
                    <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                    <button type="submit" class="admin-btn" onclick="return confirm('Marcar esta OS como pronta?');">
                        <i class="fas fa-flag-checkered"></i> Marcar como Pronta
                    </button>
                </form>
            <?php elseif ($apontamento_atual): ?>
                <span class="admin-badge admin-badge-alerta" style="font-size:13px; padding:8px 16px;">
                    <i class="fas fa-user-check"></i>&nbsp; <?php echo limpar($apontamento_atual['mecanico_nome']); ?>
                    executando
                </span>
                <?php if ($podeAprovar): ?>
                    <form action="ordens_status_acao.php" method="POST" style="display:inline;">
                        <input type="hidden" name="acao" value="marcar_pronta">
                        <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                        <button type="submit" class="admin-btn" onclick="return confirm('Marcar esta OS como pronta?');">
                            <i class="fas fa-flag-checkered"></i> Marcar como Pronta
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php // ===== PRONTA ===== 
        ?>
        <?php if ($os['status'] === 'pronta'): ?>
            <span class="admin-badge admin-badge-sucesso" style="font-size:13px; padding:8px 16px;">
                <i class="fas fa-flag-checkered"></i>&nbsp; Serviço pronto
            </span>
            <?php if ($podeAprovar): ?>
                <a href="pagamentos.php" class="admin-btn">
                    <i class="fas fa-dollar-sign"></i> Ir pra Pagamentos
                </a>
                <form action="ordens_status_acao.php" method="POST" style="display:inline;">
                    <input type="hidden" name="acao" value="cliente_retirou">
                    <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                    <button type="submit" class="admin-btn admin-btn-secundario"
                        onclick="return confirm('Confirmar que o cliente retirou?');">
                        <i class="fas fa-check"></i> Cliente Retirou
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php // ===== AGUARDANDO RETIRADA ===== 
        ?>
        <?php if ($os['status'] === 'aguardando_retirada'): ?>
            <span class="admin-badge admin-badge-sucesso" style="font-size:13px; padding:8px 16px;">
                <i class="fas fa-hand-holding-usd"></i>&nbsp; Pago — aguardando retirada
            </span>
            <?php if ($podeAprovar): ?>
                <form action="ordens_status_acao.php" method="POST" style="display:inline;">
                    <input type="hidden" name="acao" value="cliente_retirou">
                    <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
                    <button type="submit" class="admin-btn" onclick="return confirm('Confirmar que o cliente retirou?');">
                        <i class="fas fa-check"></i> Cliente Retirou
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php // ===== FINAIS ===== 
        ?>
        <?php if ($os['status'] === 'concluida'): ?>
            <span class="admin-badge admin-badge-sucesso" style="font-size:13px; padding:8px 16px;">
                <i class="fas fa-check-circle"></i>&nbsp; OS concluída
            </span>
        <?php endif; ?>

        <?php if ($os['status'] === 'cancelada'): ?>
            <span class="admin-badge admin-badge-erro" style="font-size:13px; padding:8px 16px;">
                <i class="fas fa-ban"></i>&nbsp; OS cancelada
            </span>
            <?php if (!empty($os['motivo_cancelamento'])): ?>
                <span style="color: var(--mtech-text-muted); font-size: 13px; font-style: italic;">
                    Motivo: <?php echo limpar($os['motivo_cancelamento']); ?>
                </span>
            <?php endif; ?>
        <?php endif; ?>

        <?php // ===== CANCELAR ===== 
        ?>
        <?php if ($podeCancelar && !$statusFinal && !in_array($os['status'], ['pronta', 'aguardando_retirada'])): ?>
            <button type="button" class="admin-btn admin-btn-secundario"
                style="margin-left:auto; border-color: var(--mtech-red); color: var(--mtech-red);"
                onclick="abrirModalCancelar()">
                <i class="fas fa-ban"></i> Cancelar OS
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- ===== ORÇAMENTO (SALDO) — SÓ NÍVEIS 1 E 2 ===== -->
<?php if ($saldo_orc && $podeVerValores): ?>
    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-file-invoice-dollar"></i> Orçamento
                <span class="admin-badge <?php echo classeStatusOrc($orcamento['status']); ?>"
                    style="margin-left:8px; font-size:10px;">
                    <?php echo nomeStatusOrc($orcamento['status']); ?>
                </span>
                <?php if ($tem_adendo): ?>
                    <span class="admin-badge <?php echo classeStatusAdendo($orcamento['adendo_status']); ?>"
                        style="margin-left:4px; font-size:10px;">
                        <?php echo nomeStatusAdendo($orcamento['adendo_status']); ?>
                    </span>
                <?php endif; ?>
            </span>
            <a href="orcamentos_ver.php?id=<?php echo (int)$orcamento['id_orcamento']; ?>"
                style="font-size:13px; color: var(--mtech-yellow);">
                Ver orçamento <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
            <div class="admin-card-resumo azul" style="padding:15px;">
                <div class="admin-card-resumo-titulo" style="font-size:11px;">Total</div>
                <div class="admin-card-resumo-valor" style="font-size:22px;">
                    <?php echo formatarMoeda($saldo_orc['total']); ?>
                </div>
            </div>
            <div class="admin-card-resumo verde" style="padding:15px;">
                <div class="admin-card-resumo-titulo" style="font-size:11px;">Pago</div>
                <div class="admin-card-resumo-valor" style="font-size:22px;">
                    <?php echo formatarMoeda($saldo_orc['pago']); ?>
                </div>
            </div>
            <div class="admin-card-resumo"
                style="padding:15px; border-left-color: <?php echo $saldo_orc['quitado'] ? '#25d366' : '#D62D2D'; ?>;">
                <div class="admin-card-resumo-titulo" style="font-size:11px;">Saldo</div>
                <div class="admin-card-resumo-valor"
                    style="font-size:22px; color: <?php echo $saldo_orc['quitado'] ? '#25d366' : '#D62D2D'; ?>;">
                    <?php echo $saldo_orc['quitado'] ? '✓ Quitado' : formatarMoeda($saldo_orc['saldo']); ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ===== CARRINHO DO ESTOQUE ===== -->
<?php if (!empty($carrinho_itens) && $estouApontado): ?>
    <div class="admin-bloco" style="border-left: 4px solid #25d366;">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-shopping-basket"></i> Peças pré-selecionadas</span>
            <span><?php echo count($carrinho_itens); ?> item(ns)</span>
        </div>
        <ul style="list-style:none; display:grid; gap:6px;">
            <?php foreach ($carrinho_itens as $ci): ?>
                <li
                    style="display:flex; justify-content:space-between; padding:8px 12px; background:rgba(37,211,102,0.05); border-radius:6px; font-size:13px;">
                    <span><strong><?php echo limpar($ci['nome']); ?></strong></span>
                    <span style="color:#25d366;">Tem <?php echo (int)$ci['quantidade']; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <div style="margin-top:12px; display:flex; gap:10px;">
            <button type="button" class="admin-btn" onclick="abrirModalSolicitarPeca()">
                <i class="fas fa-paper-plane"></i> Enviar como solicitação
            </button>
            <a href="estoque_limpar_carrinho_acao.php" class="admin-btn admin-btn-secundario"
                style="font-size:12px; padding:6px 12px;">
                <i class="fas fa-times"></i> Limpar
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
        <textarea disabled rows="4" placeholder="Ainda não preenchido."
            style="font-family: 'Poppins', sans-serif; resize: none;"><?php echo limpar($os['diagnostico'] ?? ''); ?></textarea>
    </div>
    <div class="admin-form-campo" style="margin-top: 15px;">
        <label>Solução aplicada</label>
        <textarea disabled rows="4" placeholder="Ainda não preenchido."
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
            <div class="admin-form-campo admin-form-campo-full"><label>Avarias / Observações</label>
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
        <span><i class="fas fa-hourglass-half"></i> Solicitações Pendentes</span>
        <span><?php echo count($solic_pendentes); ?> pendente(s)</span>
    </div>

    <?php if (empty($solic_pendentes)): ?>
        <div class="admin-vazio" style="padding:30px 20px;">
            <i class="fas fa-check-circle"></i>
            <p>Nenhuma solicitação pendente.</p>
        </div>
    <?php else: ?>
        <?php if ($podeAprovar): ?>
            <form action="ordens_aprovar_pecas_acao.php" method="POST">
                <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">

                <div style="margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer;">
                        <input type="checkbox" id="selecionarTodas" onchange="toggleTodas(this)"> Selecionar todas
                    </label>
                    <button type="submit" class="admin-btn" style="margin-left: auto;">
                        <i class="fas fa-check-double"></i> Aprovar
                    </button>
                    <button type="button" class="admin-btn admin-btn-secundario"
                        style="border-color: var(--mtech-red); color: var(--mtech-red);" onclick="abrirModalNegarPecas()">
                        <i class="fas fa-times"></i> Negar
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
                                <th>Origem</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solic_pendentes as $sp): ?>
                                <tr>
                                    <td><input type="checkbox" name="ids[]" value="<?php echo (int)$sp['id_solicitacao']; ?>"
                                            class="check-peca"></td>
                                    <td>
                                        <strong><?php echo limpar($sp['nome_peca']); ?></strong>
                                        <?php if (!empty($sp['observacoes'])): ?>
                                            <br><small
                                                style="color:var(--mtech-text-muted);"><?php echo limpar($sp['observacoes']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php echo number_format((float)$sp['quantidade'], 0, ',', '.'); ?></td>
                                    <td><?php echo limpar($sp['solicitou_nome']); ?></td>
                                    <td>
                                        <?php if ($sp['id_estoque']): ?>
                                            <span class="admin-badge admin-badge-sucesso">Estoque</span>
                                        <?php else: ?>
                                            <span class="admin-badge admin-badge-alerta">Compra</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($sp['data_solicitacao'])); ?></td>
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
                            <th>Origem</th>
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
                                <td>
                                    <?php if ($sp['id_estoque']): ?>
                                        <span class="admin-badge admin-badge-sucesso">Estoque</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge-alerta">Compra</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sp['data_solicitacao'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
                                    <span class="admin-badge admin-badge-sucesso">Estoque</span>
                                <?php elseif ($sp['origem'] === 'compra'): ?>
                                    <span class="admin-badge admin-badge-alerta">Compra</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo limpar($sp['aprovou_nome'] ?? '—'); ?></td>
                            <td>
                                <?php if ($sp['status'] === 'aprovada_estoque'): ?>
                                    <span class="admin-badge admin-badge-info">Aguardando retirada</span>
                                <?php elseif ($sp['status'] === 'aprovada_compra'): ?>
                                    <span class="admin-badge admin-badge-alerta">Aguardando compra</span>
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
            <span><i class="fas fa-history"></i> Histórico</span>
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
                                    <span class="admin-badge admin-badge-sucesso">Entregue</span>
                                <?php elseif ($sp['status'] === 'negada'): ?>
                                    <span class="admin-badge admin-badge-erro">Negada</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($sp['data_solicitacao'])); ?></td>
                            <td>
                                <?php if ($sp['status'] === 'negada' && !empty($sp['motivo_recusa'])): ?>
                                    <small
                                        style="color:var(--mtech-red);"><em><?php echo limpar($sp['motivo_recusa']); ?></em></small>
                                <?php elseif ($sp['status'] === 'entregue' && $sp['data_entrega']): ?>
                                    <small style="color:var(--mtech-text-muted);">Entregue em
                                        <?php echo date('d/m/Y H:i', strtotime($sp['data_entrega'])); ?></small>
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
    <form action="ordens_solicitar_peca_acao.php" method="POST" class="admin-modal-form">
        <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">

        <?php if (!empty($carrinho_itens)): ?>
            <div
                style="padding: 12px; background: rgba(37, 211, 102, 0.08); border-left: 3px solid #25d366; border-radius: 6px;">
                <p style="font-size: 13px; margin-bottom: 8px;">
                    <i class="fas fa-check-circle" style="color: #25d366;"></i>
                    <strong><?php echo count($carrinho_itens); ?></strong> peça(s) pré-selecionada(s)
                </p>
                <?php foreach ($carrinho_itens as $ci): ?>
                    <div style="display:flex; justify-content:space-between; padding:4px 0; font-size:13px;">
                        <span><?php echo limpar($ci['nome']); ?></span>
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
            <button type="submit" class="admin-btn"><i class="fas fa-paper-plane"></i> Enviar</button>
        </div>
    </form>
</div>

<!-- ===== MODAL: NEGAR PEÇAS ===== -->
<div class="admin-modal-fundo" id="modalNegarPecasFundo"></div>
<div class="admin-modal" id="modalNegarPecas">
    <div class="admin-modal-titulo"><i class="fas fa-times-circle"></i> Negar Peças</div>
    <form action="ordens_negar_pecas_acao.php" method="POST" class="admin-modal-form">
        <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">
        <div id="idsNegarContainer"></div>

        <div class="admin-form-campo">
            <label for="motivo_negacao_lote">Motivo *</label>
            <textarea id="motivo_negacao_lote" name="motivo" rows="4" required></textarea>
        </div>

        <p style="font-size:13px; color:var(--mtech-text-muted);" id="resumoNegar"></p>

        <div class="admin-modal-acoes">
            <button type="button" class="admin-btn admin-btn-secundario"
                onclick="fecharModalNegarPecas()">Cancelar</button>
            <button type="submit" class="admin-btn" style="background: var(--mtech-red);">
                <i class="fas fa-times"></i> Confirmar
            </button>
        </div>
    </form>
</div>

<!-- ===== MODAL: APONTAR ===== -->
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

<!-- ===== MODAL: CANCELAR ===== -->
<div class="admin-modal-fundo" id="modalCancelarFundo"></div>
<div class="admin-modal" id="modalCancelar">
    <div class="admin-modal-titulo"><i class="fas fa-ban"></i> Cancelar OS</div>
    <form action="ordens_status_acao.php" method="POST" class="admin-modal-form">
        <input type="hidden" name="acao" value="cancelar">
        <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">

        <p style="font-size:14px; color: var(--mtech-text-muted);">
            A OS não será apagada — só ficará <strong>Cancelada</strong>.
        </p>

        <div class="admin-form-campo">
            <label for="motivo_cancelamento">Motivo (opcional)</label>
            <textarea id="motivo_cancelamento" name="motivo_cancelamento" rows="3"></textarea>
        </div>

        <div class="admin-modal-acoes">
            <button type="button" class="admin-btn admin-btn-secundario" onclick="fecharModalCancelar()">Voltar</button>
            <button type="submit" class="admin-btn" style="background: var(--mtech-red);">
                <i class="fas fa-ban"></i> Confirmar
            </button>
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
    const carrinhoItens =
        <?php echo json_encode($carrinho_itens, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function criarLinhaPeca(indice, dados) {
        const wrapper = document.createElement('div');
        wrapper.className = 'linha-peca';
        const nome = dados ? dados.nome : '';
        const idEstoque = dados ? dados.id_estoque : '';
        const badge = dados ? '<span class="admin-badge admin-badge-sucesso" style="font-size:10px;">🟢 estoque</span>' :
            '';

        wrapper.innerHTML = `
            <div class="admin-form-campo">
                <label>Peça * ${badge}</label>
                <input type="text" name="pecas[${indice}][nome]" required value="${String(nome).replace(/"/g, '&quot;')}">
                <input type="hidden" name="pecas[${indice}][id_estoque]" value="${idEstoque}">
            </div>
            <div class="admin-form-campo">
                <label>Qtd *</label>
                <input type="number" name="pecas[${indice}][qtd]" value="1" min="1" step="1" required>
            </div>
            <div class="admin-form-campo">
                <label>Observações</label>
                <input type="text" name="pecas[${indice}][obs]">
            </div>
            <button type="button" class="btn-remover-linha" onclick="this.parentElement.remove()">
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
    }

    function abrirModalSolicitarPeca() {
        document.getElementById('linhasPecas').innerHTML = '';
        contadorLinhas = 0;
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
        document.getElementById('resumoNegar').textContent = marcados.length + ' peça(s)';
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

    function abrirModalCancelar() {
        document.getElementById('modalCancelar').classList.add('ativo');
        document.getElementById('modalCancelarFundo').classList.add('ativo');
    }

    function fecharModalCancelar() {
        document.getElementById('modalCancelar').classList.remove('ativo');
        document.getElementById('modalCancelarFundo').classList.remove('ativo');
    }
    document.getElementById('modalCancelarFundo').addEventListener('click', fecharModalCancelar);
</script>

<?php require_once '_footer.php'; ?>