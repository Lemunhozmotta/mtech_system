<?php
/* =========================================================
   M-TECH SYSTEM — PAGAMENTOS (registrar pagamento)
   Só níveis 1 e 2
   ========================================================= */

$titulo_pagina = 'Registrar Pagamento';
require_once '_header.php';

$usuarioLogado = usuarioLogado();
if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    redirecionar('dashboard.php?erro=sem_permissao');
}

$conn = conectar();
$id_orcamento = (int)($_GET['id'] ?? 0);

if ($id_orcamento <= 0) {
    $conn->close();
    redirecionar('pagamentos.php?msg=erro');
}

$stmt = $conn->prepare("
    SELECT o.*,
           os.id_os, os.numero_os, os.status AS os_status,
           cl.nome AS cliente_nome, cl.telefone AS cliente_telefone, cl.whatsapp AS cliente_whatsapp,
           cr.marca, cr.modelo, cr.placa
    FROM os_orcamentos o
    INNER JOIN ordens_servico os ON os.id_os = o.id_os
    INNER JOIN clientes cl ON cl.id_cliente = os.id_cliente
    INNER JOIN carros cr ON cr.id_carro = os.id_carro
    WHERE o.id_orcamento = ? LIMIT 1
");
$stmt->bind_param('i', $id_orcamento);
$stmt->execute();
$o = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$o) {
    $conn->close();
    redirecionar('pagamentos.php?msg=erro');
}

// Itens
$stmt = $conn->prepare("SELECT * FROM os_orcamento_itens WHERE id_orcamento = ? ORDER BY tipo DESC, id_item ASC");
$stmt->bind_param('i', $id_orcamento);
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Pagamentos já registrados
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

$ja_pago = false;
foreach ($pagamentos as $p) {
    if ($p['status'] === 'confirmado') $ja_pago = true;
}

$conn->close();
?>

<div class="admin-topo-pagina">
    <h1 class="admin-titulo-pagina">
        <a href="pagamentos.php" class="admin-voltar" title="Voltar"><i class="fas fa-arrow-left"></i></a>
        Registrar Pagamento — Orçamento <?php echo limpar($o['numero_orcamento']); ?>
    </h1>
</div>

<?php
$msg = $_GET['msg'] ?? '';
$mensagens = [
    'erro'       => ['texto' => 'Ocorreu um erro.', 'tipo' => 'erro'],
    'erro_obrig' => ['texto' => 'Preencha os campos obrigatórios.', 'tipo' => 'erro'],
];
if (!empty($msg) && isset($mensagens[$msg])):
    $m = $mensagens[$msg];
    $icone = $m['tipo'] === 'sucesso' ? 'check-circle' : ($m['tipo'] === 'alerta' ? 'exclamation-circle' : 'times-circle');
?>
<div class="admin-alerta admin-alerta-<?php echo $m['tipo']; ?>">
    <i class="fas <?php echo $m['tipo'] === 'erro' ? 'fa-times-circle' : 'fa-check-circle'; ?>"></i>
    <?php echo $m['texto']; ?>
</div>
<?php endif; ?>

<?php if ($ja_pago): ?>
<div class="admin-alerta admin-alerta-sucesso">
    <i class="fas fa-check-circle"></i>
    Este orçamento <strong>já foi pago</strong>. A OS já está em <strong>aguardando peça</strong>.
</div>
<?php endif; ?>

<!-- ===== DADOS DO ORÇAMENTO ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo"><span><i class="fas fa-user"></i> Cliente e Veículo</span></div>
    <div class="admin-form-grid">
        <div class="admin-form-campo"><label>Cliente</label>
            <input type="text" value="<?php echo limpar($o['cliente_nome']); ?>" disabled>
        </div>
        <div class="admin-form-campo"><label>WhatsApp / Telefone</label>
            <input type="text" value="<?php echo limpar($o['cliente_whatsapp'] ?: $o['cliente_telefone'] ?: '—'); ?>"
                disabled>
        </div>
        <div class="admin-form-campo"><label>Veículo</label>
            <input type="text" value="<?php echo limpar($o['marca'] . ' ' . $o['modelo']); ?>" disabled>
        </div>
        <div class="admin-form-campo"><label>Placa</label>
            <input type="text" value="<?php echo limpar(strtoupper($o['placa'])); ?>" disabled
                style="font-family:monospace;">
        </div>
    </div>
</div>

<!-- ===== ITENS ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo">
        <span><i class="fas fa-list"></i> Itens do Orçamento</span>
        <span><?php echo count($itens); ?> item(ns)</span>
    </div>

    <div style="overflow-x:auto;">
        <table class="admin-tabela">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Descrição</th>
                    <th style="text-align:center;">Qtd</th>
                    <th style="text-align:right;">Valor Un.</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $it): ?>
                <tr>
                    <td>
                        <?php if ($it['tipo'] === 'peca'): ?>
                        <span class="admin-badge admin-badge-info"><i class="fas fa-cog"></i> Peça</span>
                        <?php else: ?>
                        <span class="admin-badge admin-badge-alerta"><i class="fas fa-wrench"></i> Serviço</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo limpar($it['descricao']); ?></td>
                    <td style="text-align:center;"><?php echo number_format((float)$it['quantidade'], 2, ',', '.'); ?>
                    </td>
                    <td style="text-align:right;">R$
                        <?php echo number_format((float)$it['valor_unitario'], 2, ',', '.'); ?></td>
                    <td style="text-align:right; font-weight:600;">R$
                        <?php echo number_format((float)$it['valor_total'], 2, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div
        style="margin-top: 20px; padding: 15px; background: rgba(235, 175, 0, 0.08); border-radius: 8px; text-align: right;">
        <span style="font-size: 14px; color: var(--mtech-text-muted);">TOTAL:</span>
        <strong style="font-size: 24px; color: var(--mtech-yellow); margin-left: 15px;">
            R$ <?php echo number_format((float)$o['valor_total'], 2, ',', '.'); ?>
        </strong>
    </div>
</div>

<!-- ===== REGISTRAR PAGAMENTO ===== -->
<?php if (!$ja_pago): ?>
<div class="admin-bloco">
    <div class="admin-bloco-titulo"><span><i class="fas fa-dollar-sign"></i> Registrar Pagamento</span></div>

    <form action="pagamentos_acao.php" method="POST" class="admin-form">
        <input type="hidden" name="acao" value="registrar">
        <input type="hidden" name="id_orcamento" value="<?php echo $id_orcamento; ?>">

        <div class="admin-form-grid">
            <div class="admin-form-campo">
                <label for="forma">Forma de Pagamento *</label>
                <select id="forma" name="forma" required onchange="atualizarCamposBoleto()">
                    <option value="">— Selecione —</option>
                    <option value="pix">Pix</option>
                    <option value="credito">Cartão de Crédito</option>
                    <option value="debito">Cartão de Débito</option>
                    <option value="dinheiro">Dinheiro</option>
                    <option value="transferencia">Transferência</option>
                    <option value="boleto">Boleto</option>
                </select>
            </div>

            <div class="admin-form-campo">
                <label for="valor">Valor Recebido (R$) *</label>
                <input type="number" id="valor" name="valor" min="0.01" step="0.01"
                    value="<?php echo number_format((float)$o['valor_total'], 2, '.', ''); ?>" required>
            </div>

            <div class="admin-form-campo" id="campo_parcelas" style="display:none;">
                <label for="parcelas">Parcelas</label>
                <select id="parcelas" name="parcelas">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?php echo $i; ?>"><?php echo $i; ?>x</option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="admin-form-campo" id="campo_vencimento" style="display:none;">
                <label for="data_vencimento">Data de Vencimento</label>
                <input type="date" id="data_vencimento" name="data_vencimento">
            </div>

            <div class="admin-form-campo admin-form-campo-full">
                <label for="observacoes">Observações</label>
                <textarea id="observacoes" name="observacoes" rows="3"
                    placeholder="Ex: Cliente pagou em dinheiro, comprovante enviado por WhatsApp..."></textarea>
            </div>
        </div>

        <div class="admin-alerta admin-alerta-alerta" id="aviso_confirmacao" style="margin-top: 15px;">
            <i class="fas fa-info-circle"></i>
            <div>
                Ao confirmar, o orçamento vira <strong>aprovado</strong> e a OS vai pra <strong>aguardando peça</strong>
                automaticamente.
            </div>
        </div>

        <div class="admin-form-acoes">
            <a href="pagamentos.php" class="admin-btn admin-btn-secundario"><i class="fas fa-times"></i> Cancelar</a>
            <button type="submit" class="admin-btn"><i class="fas fa-check"></i> Confirmar Pagamento</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ===== HISTÓRICO DE PAGAMENTOS ===== -->
<?php if (!empty($pagamentos)): ?>
<div class="admin-bloco">
    <div class="admin-bloco-titulo">
        <span><i class="fas fa-history"></i> Histórico</span>
        <span><?php echo count($pagamentos); ?> registro(s)</span>
    </div>

    <table class="admin-tabela">
        <thead>
            <tr>
                <th>Data</th>
                <th>Forma</th>
                <th style="text-align:right;">Valor</th>
                <th>Parcelas</th>
                <th>Registrado por</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pagamentos as $p): ?>
            <tr>
                <td><?php echo date('d/m/Y H:i', strtotime($p['data_pagamento'] ?: $p['criado_em'])); ?></td>
                <td><?php echo ucfirst(str_replace('_', ' ', $p['forma'])); ?></td>
                <td style="text-align:right; font-weight:600;">R$
                    <?php echo number_format((float)$p['valor'], 2, ',', '.'); ?></td>
                <td><?php echo (int)$p['parcelas']; ?>x</td>
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

<script>
function atualizarCamposBoleto() {
    const forma = document.getElementById('forma').value;
    const campoParcelas = document.getElementById('campo_parcelas');
    const campoVencimento = document.getElementById('campo_vencimento');
    const aviso = document.getElementById('aviso_confirmacao');

    // Parcelas só pra cartão de crédito
    if (forma === 'credito') {
        campoParcelas.style.display = 'flex';
    } else {
        campoParcelas.style.display = 'none';
    }

    // Vencimento só pra boleto
    if (forma === 'boleto') {
        campoVencimento.style.display = 'flex';
        aviso.innerHTML =
            '<i class="fas fa-info-circle"></i><div>Boleto: a OS só vai pra <strong>aguardando peça</strong> quando o boleto compensar. Confirme o pagamento depois.</div>';
    } else {
        campoVencimento.style.display = 'none';
        aviso.innerHTML =
            '<i class="fas fa-info-circle"></i><div>Ao confirmar, o orçamento vira <strong>aprovado</strong> e a OS vai pra <strong>aguardando peça</strong> automaticamente.</div>';
    }
}
</script>

<?php require_once '_footer.php'; ?>