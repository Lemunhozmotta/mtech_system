<?php
/* =========================================================
   M-TECH SYSTEM — COMPRAS (ver detalhes + ações)
   ========================================================= */

$titulo_pagina = 'Detalhes da Compra';
require_once '_header.php';

$usuarioLogado = usuarioLogado();
$conn = conectar();

$podeGerenciar = in_array($usuarioLogado['nivel'], [1, 2]);
$podeVerValores = in_array($usuarioLogado['nivel'], [1, 2]);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $conn->close();
    redirecionar('compras.php');
}

$stmt = $conn->prepare("
    SELECT c.*,
           os.numero_os, os.status AS os_status,
           sp.nome_peca AS solicitacao_nome,
           u_sol.nome AS solicitou_nome,
           u_apr.nome AS aprovou_nome,
           u_comp.nome AS comprou_nome,
           u_rec.nome AS recebeu_nome
    FROM compras_solicitacoes c
    LEFT JOIN ordens_servico os ON os.id_os = c.id_os
    LEFT JOIN os_solicitacoes_peca sp ON sp.id_solicitacao = c.id_solicitacao_peca
    INNER JOIN usuarios u_sol ON u_sol.id_usuario = c.id_usuario_solicitou
    LEFT JOIN usuarios u_apr ON u_apr.id_usuario = c.id_usuario_aprovou
    LEFT JOIN usuarios u_comp ON u_comp.id_usuario = c.id_usuario_comprou
    LEFT JOIN usuarios u_rec ON u_rec.id_usuario = c.id_usuario_recebeu
    WHERE c.id_compra = ? LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$compra = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$compra) {
    $conn->close();
    redirecionar('compras.php');
}

$conn->close();

function nomeStatusC($s)
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
function classeStatusC($s)
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

$statusFinal = in_array($compra['status'], ['recebida', 'negada', 'cancelada']);
?>

<div class="admin-topo-pagina">
    <h1 class="admin-titulo-pagina">
        <a href="compras.php" class="admin-voltar" title="Voltar"><i class="fas fa-arrow-left"></i></a>
        Compra #<?php echo (int)$compra['id_compra']; ?>
    </h1>
</div>

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
        <span class="admin-badge <?php echo classeStatusC($compra['status']); ?>"
            style="font-size:13px; padding:6px 14px;">
            <?php echo nomeStatusC($compra['status']); ?>
        </span>
    </div>

    <?php if ($podeGerenciar && !$statusFinal): ?>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">

            <?php if ($compra['status'] === 'aprovada'): ?>
                <button type="button" class="admin-btn" onclick="abrirModalComprar()">
                    <i class="fas fa-shopping-cart"></i> Marcar como Comprada
                </button>
                <form action="compras_acao.php" method="POST" style="display:inline; margin-left:auto;">
                    <input type="hidden" name="acao" value="negar">
                    <input type="hidden" name="id_compra" value="<?php echo $id; ?>">
                    <button type="submit" class="admin-btn admin-btn-secundario"
                        style="border-color: var(--mtech-red); color: var(--mtech-red);"
                        onclick="return confirm('Negar esta compra?');">
                        <i class="fas fa-ban"></i> Negar Compra
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($compra['status'] === 'comprada'): ?>
                <form action="compras_acao.php" method="POST" style="display:inline;">
                    <input type="hidden" name="acao" value="receber">
                    <input type="hidden" name="id_compra" value="<?php echo $id; ?>">
                    <input type="hidden" name="id_solicitacao"
                        value="<?php echo (int)($compra['id_solicitacao_peca'] ?? 0); ?>">
                    <button type="submit" class="admin-btn"
                        onclick="return confirm('Confirmar que a peça chegou?\n\nA entrada será registrada no estoque e a OS será atualizada.');">
                        <i class="fas fa-check"></i> Marcar como Recebida
                    </button>
                </form>
            <?php endif; ?>

        </div>
    <?php elseif ($statusFinal): ?>
        <p style="color: var(--mtech-text-muted); font-size:14px;">Compra finalizada. Não há mais ações disponíveis.</p>
    <?php else: ?>
        <p style="color: var(--mtech-text-muted); font-size:14px;">Você não tem permissão pra alterar esta compra.</p>
    <?php endif; ?>
</div>

<!-- ===== DADOS DA COMPRA ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo"><span><i class="fas fa-shopping-cart"></i> Dados da Compra</span></div>

    <div class="admin-form-grid">
        <div class="admin-form-campo admin-form-campo-full">
            <label>Peça</label>
            <input type="text" value="<?php echo limpar($compra['nome_peca']); ?>" disabled>
        </div>
        <div class="admin-form-campo">
            <label>Quantidade</label>
            <input type="text" value="<?php echo number_format((float)$compra['quantidade'], 0, ',', '.'); ?>" disabled>
        </div>
        <div class="admin-form-campo">
            <label>Fornecedor</label>
            <input type="text" value="<?php echo limpar($compra['fornecedor'] ?? '—'); ?>" disabled>
        </div>

        <?php if ($podeVerValores): ?>
            <div class="admin-form-campo">
                <label>Valor unitário</label>
                <input type="text"
                    value="<?php echo $compra['valor_unitario'] !== null ? 'R$ ' . number_format((float)$compra['valor_unitario'], 2, ',', '.') : '—'; ?>"
                    disabled>
            </div>
            <div class="admin-form-campo">
                <label>Valor total</label>
                <input type="text"
                    value="<?php echo $compra['valor_total'] !== null ? 'R$ ' . number_format((float)$compra['valor_total'], 2, ',', '.') : '—'; ?>"
                    disabled>
            </div>
        <?php endif; ?>

        <?php if (!empty($compra['observacoes'])): ?>
            <div class="admin-form-campo admin-form-campo-full">
                <label>Observações</label>
                <textarea disabled rows="3"><?php echo limpar($compra['observacoes']); ?></textarea>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== ORIGEM ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo"><span><i class="fas fa-project-diagram"></i> Origem</span></div>
    <div class="admin-form-grid">
        <div class="admin-form-campo">
            <label>OS de origem</label>
            <input type="text" value="<?php echo limpar($compra['numero_os'] ?? '—'); ?>" disabled>
        </div>
        <div class="admin-form-campo">
            <label>Solicitação do mecânico</label>
            <input type="text" value="<?php echo limpar($compra['solicitacao_nome'] ?? '—'); ?>" disabled>
        </div>
        <div class="admin-form-campo">
            <label>Solicitado por</label>
            <input type="text" value="<?php echo limpar($compra['solicitou_nome']); ?>" disabled>
        </div>
        <div class="admin-form-campo">
            <label>Solicitado em</label>
            <input type="text" value="<?php echo date('d/m/Y H:i', strtotime($compra['data_solicitacao'])); ?>"
                disabled>
        </div>
    </div>
</div>

<!-- ===== HISTÓRICO ===== -->
<div class="admin-bloco">
    <div class="admin-bloco-titulo"><span><i class="fas fa-history"></i> Histórico</span></div>

    <table class="admin-tabela">
        <thead>
            <tr>
                <th>Etapa</th>
                <th>Data</th>
                <th>Responsável</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Solicitado</td>
                <td><?php echo date('d/m/Y H:i', strtotime($compra['data_solicitacao'])); ?></td>
                <td><?php echo limpar($compra['solicitou_nome']); ?></td>
            </tr>
            <?php if ($compra['data_aprovacao']): ?>
                <tr>
                    <td>Aprovado</td>
                    <td><?php echo date('d/m/Y H:i', strtotime($compra['data_aprovacao'])); ?></td>
                    <td><?php echo limpar($compra['aprovou_nome'] ?? '—'); ?></td>
                </tr>
            <?php endif; ?>
            <?php if ($compra['data_compra']): ?>
                <tr>
                    <td>Comprado</td>
                    <td><?php echo date('d/m/Y H:i', strtotime($compra['data_compra'])); ?></td>
                    <td><?php echo limpar($compra['comprou_nome'] ?? '—'); ?></td>
                </tr>
            <?php endif; ?>
            <?php if ($compra['data_recebimento']): ?>
                <tr>
                    <td>Recebido</td>
                    <td><?php echo date('d/m/Y H:i', strtotime($compra['data_recebimento'])); ?></td>
                    <td><?php echo limpar($compra['recebeu_nome'] ?? '—'); ?></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($compra['motivo_negacao'])): ?>
                <tr>
                    <td style="color: var(--mtech-red);">Negado</td>
                    <td colspan="2"><em><?php echo limpar($compra['motivo_negacao']); ?></em></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ===== MODAL: MARCAR COMO COMPRADA ===== -->
<div class="admin-modal-fundo" id="modalComprarFundo"></div>
<div class="admin-modal" id="modalComprar">
    <div class="admin-modal-titulo"><i class="fas fa-shopping-cart"></i> Marcar como Comprada</div>
    <form action="compras_acao.php" method="POST" class="admin-modal-form">
        <input type="hidden" name="acao" value="comprar">
        <input type="hidden" name="id_compra" value="<?php echo $id; ?>">

        <div class="admin-form-campo">
            <label for="fornecedor">Fornecedor *</label>
            <input type="text" id="fornecedor" name="fornecedor" required maxlength="150"
                value="<?php echo limpar($compra['fornecedor'] ?? ''); ?>" placeholder="Ex: Auto Peças Silva">
        </div>

        <?php if ($podeVerValores): ?>
            <div class="admin-form-campo">
                <label for="valor_unitario">Valor unitário (R$)</label>
                <input type="number" id="valor_unitario" name="valor_unitario" min="0" step="0.01"
                    value="<?php echo $compra['valor_unitario'] !== null ? (float)$compra['valor_unitario'] : ''; ?>"
                    placeholder="0.00">
            </div>
        <?php endif; ?>

        <div class="admin-form-campo">
            <label for="obs_compra">Observações</label>
            <textarea id="obs_compra" name="observacoes" rows="3"
                placeholder="Ex: Prazo de entrega, condições de pagamento..."><?php echo limpar($compra['observacoes'] ?? ''); ?></textarea>
        </div>

        <div class="admin-modal-acoes">
            <button type="button" class="admin-btn admin-btn-secundario"
                onclick="fecharModalComprar()">Cancelar</button>
            <button type="submit" class="admin-btn"><i class="fas fa-check"></i> Confirmar Compra</button>
        </div>
    </form>
</div>

<script>
    function abrirModalComprar() {
        document.getElementById('modalComprar').classList.add('ativo');
        document.getElementById('modalComprarFundo').classList.add('ativo');
        setTimeout(() => document.getElementById('fornecedor').focus(), 100);
    }

    function fecharModalComprar() {
        document.getElementById('modalComprar').classList.remove('ativo');
        document.getElementById('modalComprarFundo').classList.remove('ativo');
    }
    document.getElementById('modalComprarFundo').addEventListener('click', fecharModalComprar);
</script>

<?php require_once '_footer.php'; ?>