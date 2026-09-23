<?php
/* =========================================================
   M-TECH SYSTEM — ORÇAMENTOS (ver / revisar)
   Só níveis 1 e 2
   ========================================================= */

$titulo_pagina = 'Revisar Orçamento';
require_once '_header.php';

$usuarioLogado = usuarioLogado();
if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    redirecionar('dashboard.php?erro=sem_permissao');
}

$conn = conectar();
$id_orcamento = (int)($_GET['id'] ?? 0);
$idUsuario = (int)$usuarioLogado['id_usuario'];

if ($id_orcamento <= 0) {
    $conn->close();
    redirecionar('orcamentos.php?msg=erro');
}

// ===== BUSCA O ORÇAMENTO =====
$stmt = $conn->prepare("
    SELECT o.*,
           os.id_os, os.numero_os, os.status AS os_status, os.descricao_problema, os.diagnostico,
           cl.nome AS cliente_nome, cl.telefone AS cliente_telefone, cl.whatsapp AS cliente_whatsapp, cl.email AS cliente_email,
           cr.marca, cr.modelo, cr.placa, cr.ano, cr.cor, cr.km_atual
    FROM os_orcamentos o
    INNER JOIN ordens_servico os ON os.id_os = o.id_os
    INNER JOIN clientes cl ON cl.id_cliente = os.id_cliente
    INNER JOIN carros cr ON cr.id_carro = os.id_carro
    WHERE o.id_orcamento = ? LIMIT 1
");
$stmt->bind_param('i', $id_orcamento);
$stmt->execute();
$orcamento = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$orcamento) {
    $conn->close();
    redirecionar('orcamentos.php?msg=erro');
}

// ===== BUSCA OS ITENS =====
$stmt = $conn->prepare("
    SELECT * FROM os_orcamento_itens
    WHERE id_orcamento = ?
    ORDER BY tipo DESC, id_item ASC
");
$stmt->bind_param('i', $id_orcamento);
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== BUSCA OS PAGAMENTOS =====
$stmt = $conn->prepare("
    SELECT p.*, u.nome AS usuario_nome
    FROM os_orcamento_pagamentos p
    LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario_registrou
    WHERE p.id_orcamento = ?
    ORDER BY p.id_pagamento DESC
");
$stmt->bind_param('i', $id_orcamento);
$stmt->execute();
$pagamentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== CALCULA TEMPO APONTADO E MÃO DE OBRA SUGERIDA =====
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, data_apontamento,
        COALESCE(data_desapontamento, NOW()))), 0) AS total_min
    FROM os_apontamentos
    WHERE id_os = ?
");
$stmt->bind_param('i', $orcamento['id_os']);
$stmt->execute();
$tempo_total_min = (int)$stmt->get_result()->fetch_assoc()['total_min'];
$stmt->close();

$valor_hora_mo = 0;
$stmt = $conn->prepare("SELECT valor_venda FROM estoque WHERE codigo = 'MO-001' AND ativo = 1 LIMIT 1");
$stmt->execute();
$mo_item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($mo_item) {
    $valor_hora_mo = (float)$mo_item['valor_venda'];
}

$valor_minuto = $valor_hora_mo / 60;
$mao_obra_sugerida = $valor_minuto * $tempo_total_min;

$horas = floor($tempo_total_min / 60);
$minutos = $tempo_total_min % 60;
$tempo_formatado = '';
if ($horas > 0) $tempo_formatado .= $horas . 'h';
if ($minutos > 0) $tempo_formatado .= ($horas > 0 ? ' ' : '') . $minutos . 'min';
if ($tempo_formatado === '') $tempo_formatado = '0min';

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

$editavel = in_array($orcamento['status'], ['rascunho', 'aguardando_revisao', 'pronto_para_envio']);

$url_publica = '';
if (!empty($orcamento['token_publico'])) {
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $url_publica = $protocolo . '://' . $host . '/mtech_system/pagar.php?token=' . $orcamento['token_publico'];
}
?>

<div class="admin-topo-pagina">
    <h1 class="admin-titulo-pagina">
        <a href="orcamentos.php" class="admin-voltar" title="Voltar"><i class="fas fa-arrow-left"></i></a>
        Orçamento <?php echo limpar($orcamento['numero_orcamento']); ?>
        <?php if ((int)$orcamento['versao'] > 1): ?>
            <span class="admin-badge admin-badge-info"
                style="font-size:12px; margin-left:8px;">v<?php echo (int)$orcamento['versao']; ?></span>
        <?php endif; ?>
    </h1>
</div>

<?php
$msg = $_GET['msg'] ?? '';
$mensagens = [
    'editado'    => ['texto' => 'Orçamento atualizado!', 'tipo' => 'sucesso'],
    'item_add'   => ['texto' => 'Item adicionado.', 'tipo' => 'sucesso'],
    'item_del'   => ['texto' => 'Item removido.', 'tipo' => 'alerta'],
    'enviado'    => ['texto' => 'Orçamento enviado ao cliente! Novo link gerado.', 'tipo' => 'sucesso'],
    'erro'       => ['texto' => 'Ocorreu um erro.', 'tipo' => 'erro'],
    'erro_obrig' => ['texto' => 'Preencha os campos obrigatórios.', 'tipo' => 'erro'],
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
        <span class="admin-badge <?php echo classeStatusOrc($orcamento['status']); ?>"
            style="font-size:13px; padding:6px 14px;">
            <?php echo nomeStatusOrc($orcamento['status']); ?>
        </span>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">

        <?php if ($orcamento['status'] === 'enviado'): ?>
            <span class="admin-badge admin-badge-info" style="font-size:13px; padding:8px 16px;">
                <i class="fas fa-clock"></i> Aguardando o cliente pagar no link
            </span>
        <?php endif; ?>

        <?php if ($orcamento['status'] === 'aprovado'): ?>
            <span class="admin-badge admin-badge-sucesso" style="font-size:13px; padding:8px 16px;">
                <i class="fas fa-check-double"></i> Aprovado — siga pra Pagamentos
            </span>
            <a href="pagamentos.php" class="admin-btn admin-btn-secundario">
                <i class="fas fa-dollar-sign"></i> Ir pra Pagamentos
            </a>
        <?php endif; ?>

        <?php if ($orcamento['status'] === 'recusado'): ?>
            <span class="admin-badge admin-badge-erro" style="font-size:13px; padding:8px 16px;">
                <i class="fas fa-ban"></i> Recusado pelo cliente
            </span>
        <?php endif; ?>

    </div>
</div>

<!-- ===== LINK PÚBLICO ===== -->
<?php if (!empty($url_publica)): ?>
    <div class="admin-bloco">
        <div class="admin-bloco-titulo"><span><i class="fas fa-link"></i> Link do Cliente</span></div>

        <div class="admin-input-grupo">
            <input type="text" id="urlPublica" value="<?php echo limpar($url_publica); ?>" readonly>
            <button type="button" class="admin-btn admin-btn-secundario" onclick="copiarUrl()">
                <i class="fas fa-copy"></i> Copiar
            </button>
            <a href="orcamentos_pdf.php?id=<?php echo $id_orcamento; ?>" target="_blank" class="admin-btn">
                <i class="fas fa-file-pdf"></i> Ver PDF
            </a>
        </div>

        <small class="admin-dica">Manda esse link pro cliente. Ele abre uma página com Pix e cartão.</small>
    </div>
<?php endif; ?>

<!-- ===== DADOS DO CLIENTE E VEÍCULO ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo"><span><i class="fas fa-user"></i> Cliente e Veículo</span></div>
    <div class="admin-form-grid">
        <div class="admin-form-campo"><label>Cliente</label>
            <input type="text" value="<?php echo limpar($orcamento['cliente_nome']); ?>" disabled>
        </div>
        <div class="admin-form-campo"><label>WhatsApp / Telefone</label>
            <input type="text"
                value="<?php echo limpar($orcamento['cliente_whatsapp'] ?: $orcamento['cliente_telefone'] ?: '—'); ?>"
                disabled>
        </div>
        <div class="admin-form-campo"><label>Veículo</label>
            <input type="text" value="<?php echo limpar($orcamento['marca'] . ' ' . $orcamento['modelo']); ?>" disabled>
        </div>
        <div class="admin-form-campo"><label>Placa / Ano</label>
            <input type="text"
                value="<?php echo limpar(strtoupper($orcamento['placa']) . ' / ' . ($orcamento['ano'] ?: '—')); ?>"
                disabled>
        </div>
    </div>

    <?php if (!empty($orcamento['descricao_problema'])): ?>
        <div class="admin-form-campo" style="margin-top:15px;">
            <label>Problema relatado</label>
            <textarea disabled rows="2"><?php echo limpar($orcamento['descricao_problema']); ?></textarea>
        </div>
    <?php endif; ?>

    <?php if (!empty($orcamento['diagnostico'])): ?>
        <div class="admin-form-campo" style="margin-top:10px;">
            <label>Diagnóstico</label>
            <textarea disabled rows="3"><?php echo limpar($orcamento['diagnostico']); ?></textarea>
        </div>
    <?php endif; ?>
</div>

<!-- ===== ITENS DO ORÇAMENTO + RESUMO ===== -->
<form action="orcamentos_acao.php" method="POST" id="formItensOrcamento">
    <input type="hidden" name="acao" value="salvar_itens">
    <input type="hidden" name="id_orcamento" value="<?php echo $id_orcamento; ?>">

    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-list"></i> Itens do Orçamento</span>
            <span><?php echo count($itens); ?> item(ns)</span>
        </div>

        <?php if (empty($itens)): ?>
            <div class="admin-vazio" style="padding:30px 20px;">
                <i class="fas fa-box-open"></i>
                <p>Nenhum item ainda.</p>
                <small>Use o campo abaixo pra adicionar.</small>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="admin-tabela" id="tabelaItens">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Descrição</th>
                            <th style="text-align:center; width:90px;">Qtd</th>
                            <th style="text-align:right; width:120px;">Valor Un.</th>
                            <th style="text-align:right; width:120px;">Total</th>
                            <th style="text-align:right; width:60px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $it): ?>
                            <tr data-id="<?php echo (int)$it['id_item']; ?>">
                                <td>
                                    <?php if ($it['tipo'] === 'peca'): ?>
                                        <span class="admin-badge admin-badge-info"><i class="fas fa-cog"></i> Peça</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge-alerta"><i class="fas fa-wrench"></i> Serviço</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($editavel): ?>
                                        <input type="text" name="itens[<?php echo (int)$it['id_item']; ?>][descricao]"
                                            value="<?php echo limpar($it['descricao']); ?>"
                                            style="width:100%; padding:8px 10px; background:var(--mtech-bg); color:var(--mtech-text); border:1px solid var(--mtech-border); border-radius:6px; font-family:Poppins; font-size:13px;"
                                            maxlength="255">
                                    <?php else: ?>
                                        <strong><?php echo limpar($it['descricao']); ?></strong>
                                    <?php endif; ?>
                                    <?php if (!empty($it['observacoes'])): ?>
                                        <br><small
                                            style="color:var(--mtech-text-muted);"><?php echo limpar($it['observacoes']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($editavel): ?>
                                        <input type="number" name="itens[<?php echo (int)$it['id_item']; ?>][quantidade]"
                                            value="<?php echo rtrim(rtrim(number_format((float)$it['quantidade'], 2, '.', ''), '0'), '.'); ?>"
                                            min="0.01" step="0.01" class="input-qtd"
                                            style="width:70px; padding:8px 6px; background:var(--mtech-bg); color:var(--mtech-text); border:1px solid var(--mtech-border); border-radius:6px; font-family:Poppins; font-size:13px; text-align:center;">
                                    <?php else: ?>
                                        <?php echo number_format((float)$it['quantidade'], 2, ',', '.'); ?>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <?php if ($editavel): ?>
                                        <input type="number" name="itens[<?php echo (int)$it['id_item']; ?>][valor_unitario]"
                                            value="<?php echo number_format((float)$it['valor_unitario'], 2, '.', ''); ?>" min="0"
                                            step="0.01" class="input-valor"
                                            style="width:100px; padding:8px 6px; background:var(--mtech-bg); color:var(--mtech-text); border:1px solid var(--mtech-border); border-radius:6px; font-family:Poppins; font-size:13px; text-align:right;">
                                    <?php else: ?>
                                        R$ <?php echo number_format((float)$it['valor_unitario'], 2, ',', '.'); ?>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right; font-weight:600;" class="celula-total">
                                    R$ <?php echo number_format((float)$it['valor_total'], 2, ',', '.'); ?>
                                </td>
                                <td style="text-align:right;">
                                    <?php if ($editavel): ?>
                                        <button type="button" class="admin-btn-acao admin-btn-acao-erro" title="Remover"
                                            onclick="removerItem(<?php echo (int)$it['id_item']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== ADICIONAR ITEM ===== -->
    <?php if ($editavel): ?>
        <div class="admin-bloco">
            <div class="admin-bloco-titulo"><span><i class="fas fa-plus-circle"></i> Adicionar Item</span></div>

            <div class="admin-alerta admin-alerta-alerta" style="margin-bottom:15px;">
                <i class="fas fa-info-circle"></i>
                <div>
                    Digite o nome da peça ou serviço. Se tiver no estoque ou no catálogo, o sistema mostra sugestões. Se não
                    achar, use o botão <strong>"Item Livre"</strong> ao lado.
                </div>
            </div>

            <!-- Campo de busca -->
            <div style="position:relative; margin-bottom:15px;">
                <div class="admin-input-grupo">
                    <input type="text" id="buscaItem" placeholder="Ex: Filtro de óleo, Troca de pastilha..."
                        autocomplete="off" style="flex:1;">
                    <button type="button" class="admin-btn admin-btn-secundario" onclick="limparBusca()"
                        title="Limpar busca">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="listaBusca" class="admin-autocomplete-lista" style="display:none;"></div>
            </div>

            <!-- Botão Item Livre FORA do input-grupo -->
            <div style="margin-bottom:15px;">
                <button type="button" class="admin-btn" onclick="abrirItemLivre()" style="width:100%;">
                    <i class="fas fa-plus-circle"></i> Adicionar Item Livre (não está no estoque nem no catálogo)
                </button>
            </div>

            <!-- Resultado da seleção -->
            <div id="itemSelecionado"
                style="display:none; padding:15px; border:1px solid var(--mtech-border); border-radius:8px; background:rgba(37, 211, 102, 0.05);">
                <div style="display:grid; grid-template-columns: 3fr 100px 140px 42px; gap:10px; align-items:end;">
                    <div>
                        <label
                            style="font-size:11px; color:var(--mtech-text-muted); display:block; margin-bottom:5px;">Descrição</label>
                        <input type="text" id="sel_descricao"
                            style="width:100%; padding:10px 12px; background:var(--mtech-bg); color:var(--mtech-text); border:1px solid var(--mtech-border); border-radius:6px; font-family:Poppins; font-size:13px;">
                    </div>
                    <div>
                        <label
                            style="font-size:11px; color:var(--mtech-text-muted); display:block; margin-bottom:5px;">Qtd</label>
                        <input type="number" id="sel_qtd" value="1" min="0.01" step="0.01"
                            style="width:100%; padding:10px 8px; background:var(--mtech-bg); color:var(--mtech-text); border:1px solid var(--mtech-border); border-radius:6px; font-family:Poppins; font-size:13px; text-align:center;">
                    </div>
                    <div>
                        <label
                            style="font-size:11px; color:var(--mtech-text-muted); display:block; margin-bottom:5px;">Valor
                            Un. (R$)</label>
                        <input type="number" id="sel_valor" value="0.00" min="0" step="0.01"
                            style="width:100%; padding:10px 8px; background:var(--mtech-bg); color:var(--mtech-text); border:1px solid var(--mtech-border); border-radius:6px; font-family:Poppins; font-size:13px; text-align:right;">
                    </div>
                    <button type="button" class="admin-btn" onclick="adicionarItem()" title="Adicionar"
                        style="height:42px;">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <input type="hidden" id="sel_tipo" value="">
                <input type="hidden" id="sel_id_estoque" value="">
                <input type="hidden" id="sel_id_servico" value="">
            </div>
        </div>
    <?php endif; ?>

    <!-- ===== RESUMO + BOTÃO SALVAR ===== -->
    <div class="admin-bloco">
        <div class="admin-bloco-titulo"><span><i class="fas fa-calculator"></i> Resumo</span></div>

        <div class="admin-form-grid">
            <div class="admin-form-campo">
                <label for="valor_mao_obra">Mão de obra (R$)</label>
                <input type="number" id="valor_mao_obra" name="valor_mao_obra" min="0" step="0.01" value="<?php
                                                                                                            $mo_atual = (float)$orcamento['valor_mao_obra'];
                                                                                                            echo $mo_atual > 0 ? number_format($mo_atual, 2, '.', '') : number_format($mao_obra_sugerida, 2, '.', '');
                                                                                                            ?>"
                    <?php echo !$editavel ? 'disabled' : ''; ?> onchange="recalcularTotal()"
                    oninput="recalcularTotal()">

                <?php if ($tempo_total_min > 0 && $valor_hora_mo > 0): ?>
                    <small class="admin-dica" style="display:block; margin-top:6px; line-height:1.5;">
                        <i class="fas fa-calculator"></i>
                        Tempo apontado: <strong><?php echo $tempo_formatado; ?></strong>
                        (<?php echo $tempo_total_min; ?> min) ×
                        R$ <?php echo number_format($valor_hora_mo, 2, ',', '.'); ?>/hora
                        =
                        <strong style="color: var(--mtech-yellow);">R$
                            <?php echo number_format($mao_obra_sugerida, 2, ',', '.'); ?></strong> sugerido
                        <br>
                        <em>Pode arredondar ou ajustar livremente.</em>
                    </small>
                <?php elseif ($tempo_total_min === 0): ?>
                    <small class="admin-dica" style="display:block; margin-top:6px;">
                        <i class="fas fa-info-circle"></i> Nenhum tempo apontado nessa OS ainda.
                    </small>
                <?php elseif ($valor_hora_mo === 0): ?>
                    <small class="admin-dica" style="display:block; margin-top:6px;">
                        <i class="fas fa-exclamation-triangle" style="color: var(--mtech-yellow);"></i>
                        Item "Mão de Obra" (MO-001) não encontrado no estoque.
                    </small>
                <?php endif; ?>
            </div>
            <div class="admin-form-campo">
                <label for="valor_desconto">Desconto (R$)</label>
                <input type="number" id="valor_desconto" name="valor_desconto" min="0" step="0.01"
                    value="<?php echo (float)$orcamento['valor_desconto']; ?>"
                    <?php echo !$editavel ? 'disabled' : ''; ?> onchange="recalcularTotal()"
                    oninput="recalcularTotal()">
            </div>
            <div class="admin-form-campo">
                <label for="validade_dias">Validade (dias)</label>
                <input type="number" id="validade_dias" name="validade_dias" min="1" max="90"
                    value="<?php echo (int)$orcamento['validade_dias']; ?>" <?php echo !$editavel ? 'disabled' : ''; ?>>
            </div>
            <div class="admin-form-campo">
                <label>Total</label>
                <input type="text" id="totalExibido" disabled
                    value="R$ <?php echo number_format((float)$orcamento['valor_total'], 2, ',', '.'); ?>"
                    style="font-size:18px; font-weight:700; color: var(--mtech-yellow);">
            </div>
            <div class="admin-form-campo admin-form-campo-full">
                <label for="observacoes_orc">Observações pro cliente</label>
                <textarea id="observacoes_orc" name="observacoes" rows="3"
                    placeholder="Ex: Garantia de 90 dias nos serviços."
                    <?php echo !$editavel ? 'disabled' : ''; ?>><?php echo limpar($orcamento['observacoes'] ?? ''); ?></textarea>
            </div>
        </div>

        <?php if ($editavel): ?>
            <div class="admin-form-acoes">
                <button type="submit" class="admin-btn"><i class="fas fa-save"></i> Salvar Alterações</button>
            </div>
        <?php endif; ?>
    </div>
</form>

<!-- ===== AÇÕES DE ENVIO ===== -->
<?php if ($editavel): ?>
    <div class="admin-bloco">
        <div class="admin-bloco-titulo"><span><i class="fas fa-paper-plane"></i> Enviar ao Cliente</span></div>

        <p style="font-size:14px; color:var(--mtech-text-muted); margin-bottom:15px;">
            Ao enviar, o sistema gera um <strong>link novo</strong> (o antigo deixa de funcionar) e o cliente pode pagar por
            Pix ou cartão.
        </p>

        <form action="orcamentos_acao.php" method="POST">
            <input type="hidden" name="acao" value="enviar">
            <input type="hidden" name="id_orcamento" value="<?php echo $id_orcamento; ?>">
            <button type="submit" class="admin-btn"
                onclick="return confirm('Enviar este orçamento ao cliente?\n\nUm novo link será gerado. O link anterior deixará de funcionar.');">
                <i class="fas fa-paper-plane"></i>
                <?php echo $orcamento['status'] === 'enviado' ? 'Reenviar (gera novo link)' : 'Enviar ao Cliente'; ?>
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- ===== PAGAMENTOS REGISTRADOS ===== -->
<?php if (!empty($pagamentos)): ?>
    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-dollar-sign"></i> Pagamentos Registrados</span>
            <span><?php echo count($pagamentos); ?></span>
        </div>

        <table class="admin-tabela">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Forma</th>
                    <th style="text-align:right;">Valor</th>
                    <th>Registrado por</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pagamentos as $p): ?>
                    <tr>
                        <td><?php echo $p['data_pagamento'] ? date('d/m/Y H:i', strtotime($p['data_pagamento'])) : date('d/m/Y H:i', strtotime($p['criado_em'])); ?>
                        </td>
                        <td><?php echo strtoupper($p['forma']); ?></td>
                        <td style="text-align:right; font-weight:600;">R$
                            <?php echo number_format((float)$p['valor'], 2, ',', '.'); ?></td>
                        <td><?php echo limpar($p['usuario_nome'] ?? '—'); ?></td>
                        <td>
                            <?php if ($p['status'] === 'confirmado'): ?>
                                <span class="admin-badge admin-badge-sucesso">Confirmado</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-alerta">Pendente</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Form escondido pra remoção individual -->
<form action="orcamentos_acao.php" method="POST" id="formRemoverItem" style="display:none;">
    <input type="hidden" name="acao" value="item_excluir">
    <input type="hidden" name="id_orcamento" value="<?php echo $id_orcamento; ?>">
    <input type="hidden" name="id_item" id="remove_id_item" value="">
</form>

<script>
    // =========================================================
    // EDIÇÃO INLINE — recalcula total ao digitar
    // =========================================================
    function recalcularLinha(input) {
        const tr = input.closest('tr');
        const qtd = parseFloat(tr.querySelector('.input-qtd').value) || 0;
        const valor = parseFloat(tr.querySelector('.input-valor').value) || 0;
        const total = qtd * valor;
        tr.querySelector('.celula-total').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
        recalcularTotal();
    }

    document.querySelectorAll('.input-qtd, .input-valor').forEach(input => {
        input.addEventListener('input', () => recalcularLinha(input));
    });

    function recalcularTotal() {
        let totalPecas = 0;
        let totalServicos = 0;

        document.querySelectorAll('#tabelaItens tbody tr').forEach(tr => {
            const badge = tr.querySelector('.admin-badge');
            const tipo = badge ? badge.textContent.trim() : '';
            const qtd = parseFloat(tr.querySelector('.input-qtd')?.value) || 0;
            const valor = parseFloat(tr.querySelector('.input-valor')?.value) || 0;
            const subtotal = qtd * valor;

            if (tipo.includes('Peça')) {
                totalPecas += subtotal;
            } else {
                totalServicos += subtotal;
            }
        });

        const elMaoObra = document.getElementById('valor_mao_obra');
        const elDesconto = document.getElementById('valor_desconto');
        const maoObra = elMaoObra ? (parseFloat(elMaoObra.value) || 0) : 0;
        const desconto = elDesconto ? (parseFloat(elDesconto.value) || 0) : 0;
        const total = totalPecas + totalServicos + maoObra - desconto;

        const elTotal = document.getElementById('totalExibido');
        if (elTotal) {
            elTotal.value = 'R$ ' + (total < 0 ? 0 : total).toFixed(2).replace('.', ',');
        }
    }

    // =========================================================
    // REMOVER ITEM
    // =========================================================
    function removerItem(id) {
        if (!confirm('Remover este item?')) return;
        document.getElementById('remove_id_item').value = id;
        document.getElementById('formRemoverItem').submit();
    }

    // =========================================================
    // ABRIR ITEM LIVRE (botão fixo)
    // =========================================================
    function abrirItemLivre() {
        document.getElementById('sel_tipo').value = 'peca';
        document.getElementById('sel_descricao').value = '';
        document.getElementById('sel_valor').value = '0.00';
        document.getElementById('sel_qtd').value = 1;
        document.getElementById('sel_id_estoque').value = '';
        document.getElementById('sel_id_servico').value = '';
        document.getElementById('itemSelecionado').style.display = 'block';
        setTimeout(() => document.getElementById('sel_descricao').focus(), 100);
        document.getElementById('itemSelecionado').scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }

    // =========================================================
    // BUSCA UNIFICADA — estoque + serviços
    // =========================================================
    const campoBusca = document.getElementById('buscaItem');
    const listaBusca = document.getElementById('listaBusca');

    let timerBusca = null;

    if (campoBusca) {
        campoBusca.addEventListener('input', () => {
            const termo = campoBusca.value.trim();

            if (termo.length < 2) {
                listaBusca.style.display = 'none';
                return;
            }

            clearTimeout(timerBusca);
            timerBusca = setTimeout(() => buscarItens(termo), 250);
        });

        campoBusca.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                listaBusca.style.display = 'none';
                campoBusca.blur();
            }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#buscaItem') && !e.target.closest('#listaBusca')) {
                listaBusca.style.display = 'none';
            }
        });
    }

    async function buscarItens(termo) {
        try {
            const [rEstoque, rServicos] = await Promise.all([
                fetch(`orcamentos_buscar_estoque.php?termo=${encodeURIComponent(termo)}`),
                fetch(`orcamentos_buscar_servicos.php?termo=${encodeURIComponent(termo)}`)
            ]);

            const estoque = await rEstoque.json();
            const servicos = await rServicos.json();

            renderizarLista(estoque, servicos);
        } catch (err) {
            console.error(err);
        }
    }

    function renderizarLista(estoque, servicos) {
        const termoOriginal = campoBusca.value.trim();

        let html = '';
        let temResultado = false;

        if (estoque.length > 0) {
            temResultado = true;
            html += `<div style="padding:8px 14px; background:rgba(74, 144, 226, 0.08); font-size:11px; text-transform:uppercase; color:var(--mtech-text-muted); letter-spacing:1px; font-weight:600;">
            <i class="fas fa-boxes"></i> Estoque
        </div>`;
            estoque.forEach(item => {
                html += `
                <div class="admin-autocomplete-item item-busca" 
                     data-tipo="peca"
                     data-id-estoque="${item.id_estoque}"
                     data-descricao="${item.nome.replace(/"/g, '&quot;')}"
                     data-valor="${item.valor_venda}"
                     data-estoque="${item.quantidade}">
                    <strong>${item.nome}</strong>
                    <small>${item.codigo ? 'Código: ' + item.codigo + ' — ' : ''}${item.marca || ''} ${item.categoria ? '— ' + item.categoria : ''} — <span style="color:${item.quantidade > 0 ? '#25d366' : '#D62D2D'};">Qtd: ${item.quantidade}</span> — R$ ${parseFloat(item.valor_venda).toFixed(2).replace('.', ',')}</small>
                </div>
            `;
            });
        }

        if (servicos.length > 0) {
            temResultado = true;
            html += `<div style="padding:8px 14px; background:rgba(235, 175, 0, 0.08); font-size:11px; text-transform:uppercase; color:var(--mtech-text-muted); letter-spacing:1px; font-weight:600;">
            <i class="fas fa-wrench"></i> Serviços
        </div>`;
            servicos.forEach(serv => {
                const valor = serv.valor_fixo || 0;
                html += `
                <div class="admin-autocomplete-item item-busca" 
                     data-tipo="servico"
                     data-id-servico="${serv.id_servico}"
                     data-descricao="${serv.nome.replace(/"/g, '&quot;')}"
                     data-valor="${valor}">
                    <strong>${serv.nome}</strong>
                    <small>${serv.descricao || ''} ${valor > 0 ? '— R$ ' + valor.toFixed(2).replace('.', ',') : ''}</small>
                </div>
            `;
            });
        }

        // Sempre mostra a opção de item livre com o termo digitado
        html += `<div style="padding:8px 14px; background:rgba(37, 211, 102, 0.08); font-size:11px; text-transform:uppercase; color:var(--mtech-text-muted); letter-spacing:1px; font-weight:600;">
        <i class="fas fa-pen"></i> ${temResultado ? 'Ou adicione item livre' : 'Nenhum resultado no estoque/serviços'}
    </div>`;
        html += `
        <div class="admin-autocomplete-item item-busca-livre" data-descricao-livre="${termoOriginal.replace(/"/g, '&quot;')}" style="background:rgba(37, 211, 102, 0.05); border-left:3px solid #25d366;">
            <strong style="color:#25d366;">
                <i class="fas fa-plus-circle"></i> Adicionar "${termoOriginal}"
            </strong>
            <small>Item livre (não vinculado ao estoque)</small>
        </div>
    `;

        listaBusca.innerHTML = html;
        listaBusca.style.display = 'block';

        listaBusca.querySelectorAll('.item-busca').forEach(item => {
            item.addEventListener('mousedown', (e) => {
                e.preventDefault();
                selecionarItem(item);
            });
        });

        listaBusca.querySelectorAll('.item-busca-livre').forEach(item => {
            item.addEventListener('mousedown', (e) => {
                e.preventDefault();
                selecionarItemLivre(item.dataset.descricaoLivre);
            });
        });
    }

    function selecionarItem(el) {
        const tipo = el.dataset.tipo;
        const descricao = el.dataset.descricao;
        const valor = el.dataset.valor;

        document.getElementById('sel_tipo').value = tipo;
        document.getElementById('sel_descricao').value = descricao;
        document.getElementById('sel_valor').value = valor;
        document.getElementById('sel_qtd').value = 1;
        document.getElementById('sel_id_estoque').value = el.dataset.idEstoque || '';
        document.getElementById('sel_id_servico').value = el.dataset.idServico || '';

        document.getElementById('itemSelecionado').style.display = 'block';
        listaBusca.style.display = 'none';
        campoBusca.value = '';
    }

    function selecionarItemLivre(descricao) {
        document.getElementById('sel_tipo').value = 'peca';
        document.getElementById('sel_descricao').value = descricao;
        document.getElementById('sel_valor').value = '0.00';
        document.getElementById('sel_qtd').value = 1;
        document.getElementById('sel_id_estoque').value = '';
        document.getElementById('sel_id_servico').value = '';
        document.getElementById('itemSelecionado').style.display = 'block';
        listaBusca.style.display = 'none';
        campoBusca.value = '';
        setTimeout(() => document.getElementById('sel_valor').focus(), 100);
    }

    function limparBusca() {
        campoBusca.value = '';
        listaBusca.style.display = 'none';
        document.getElementById('itemSelecionado').style.display = 'none';
        document.getElementById('sel_tipo').value = '';
        document.getElementById('sel_id_estoque').value = '';
        document.getElementById('sel_id_servico').value = '';
    }

    // =========================================================
    // ADICIONAR ITEM (via AJAX)
    // =========================================================
    async function adicionarItem() {
        const tipo = document.getElementById('sel_tipo').value;
        const descricao = document.getElementById('sel_descricao').value;
        const qtd = parseFloat(document.getElementById('sel_qtd').value) || 0;
        const valor = parseFloat(document.getElementById('sel_valor').value) || 0;
        const idEstoque = document.getElementById('sel_id_estoque').value;
        const idServico = document.getElementById('sel_id_servico').value;

        if (!descricao || qtd <= 0) {
            alert('Preencha descrição e quantidade.');
            return;
        }

        const formData = new FormData();
        formData.append('acao', tipo === 'servico' ? 'item_add_servico' : 'item_add_peca');
        formData.append('id_orcamento', <?php echo $id_orcamento; ?>);
        formData.append('descricao', descricao);
        formData.append('quantidade', qtd);
        formData.append('valor_unitario', valor);
        if (idEstoque) formData.append('id_estoque', idEstoque);
        if (idServico) formData.append('id_servico', idServico);

        try {
            await fetch('orcamentos_acao.php', {
                method: 'POST',
                body: formData
            });
            window.location.href = 'orcamentos_ver.php?id=<?php echo $id_orcamento; ?>&msg=item_add';
        } catch (err) {
            alert('Erro ao adicionar item.');
            console.error(err);
        }
    }

    // =========================================================
    // COPIAR URL
    // =========================================================
    function copiarUrl() {
        const input = document.getElementById('urlPublica');
        input.select();
        input.setSelectionRange(0, 99999);
        document.execCommand('copy');
        alert('Link copiado!');
    }

    // Recalcula ao carregar
    recalcularTotal();
</script>

<?php require_once '_footer.php'; ?>