<?php
/* =========================================================
   M-TECH SYSTEM — RETIRADAS PENDENTES (aprovação → aguardando retirada)
   Só níveis 1 e 2
   ========================================================= */

$titulo_pagina = 'Retiradas Pendentes';
require_once '_header.php';

$polling_ativo = true;

$usuarioLogado = usuarioLogado();
if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    redirecionar('estoque.php?msg=sem_permissao');
}

$conn = conectar();

$stmt = $conn->query("
    SELECT sp.*,
           os.numero_os, os.status AS os_status,
           e.nome AS estoque_nome, e.codigo AS estoque_codigo,
           e.quantidade AS estoque_qtd, e.localizacao AS estoque_local,
           u_sol.nome AS solicitou_nome,
           u_apr.nome AS aprovou_nome
    FROM os_solicitacoes_peca sp
    INNER JOIN ordens_servico os ON os.id_os = sp.id_os
    INNER JOIN estoque e ON e.id_estoque = sp.id_estoque
    INNER JOIN usuarios u_sol ON u_sol.id_usuario = sp.id_usuario_solicitou
    LEFT JOIN usuarios u_apr ON u_apr.id_usuario = sp.id_usuario_aprovou
    WHERE sp.status = 'aprovada_estoque'
    ORDER BY sp.data_aprovacao ASC
");
$pendentes = $stmt->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<div class="admin-topo-pagina">
    <h1 class="admin-titulo-pagina">
        <a href="estoque.php" class="admin-voltar" title="Voltar"><i class="fas fa-arrow-left"></i></a>
        Retiradas Pendentes
    </h1>
</div>

<div class="admin-bloco">
    <div class="admin-bloco-titulo">
        <span><i class="fas fa-clipboard-check"></i> Peças aguardando retirada</span>
        <span><?php echo count($pendentes); ?> pendente(s)</span>
    </div>

    <?php if (empty($pendentes)): ?>
    <div class="admin-vazio">
        <i class="fas fa-check-circle"></i>
        <p>Nenhuma retirada pendente.</p>
        <small>Quando o admin aprovar uma peça que tem em estoque, ela aparece aqui.</small>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="admin-tabela">
            <thead>
                <tr>
                    <th>OS</th>
                    <th>Peça</th>
                    <th>Solicitou</th>
                    <th style="text-align:center;">Qtd pedida</th>
                    <th style="text-align:center;">Em estoque</th>
                    <th>Local</th>
                    <th>Aprovado por</th>
                    <th style="text-align:right;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendentes as $p): ?>
                <tr>
                    <td>
                        <a href="ordens_ver.php?id=<?php echo (int)$p['id_os']; ?>"
                            style="color:var(--mtech-yellow); font-family:monospace;">
                            <?php echo limpar($p['numero_os']); ?>
                        </a>
                    </td>
                    <td><strong><?php echo limpar($p['nome_peca']); ?></strong></td>
                    <td><?php echo limpar($p['solicitou_nome']); ?></td>
                    <td style="text-align:center;">
                        <?php echo number_format((float)$p['quantidade'], 0, ',', '.'); ?>
                    </td>
                    <td style="text-align:center;">
                        <span
                            class="admin-badge <?php echo (int)$p['estoque_qtd'] >= (int)$p['quantidade'] ? 'admin-badge-sucesso' : 'admin-badge-erro'; ?>">
                            <?php echo number_format((int)$p['estoque_qtd'], 0, ',', '.'); ?>
                        </span>
                    </td>
                    <td><?php echo limpar($p['estoque_local'] ?? '—'); ?></td>
                    <td>
                        <?php echo limpar($p['aprovou_nome'] ?? '—'); ?>
                        <br><small style="color:var(--mtech-text-muted);">
                            <?php echo $p['data_aprovacao'] ? date('d/m H:i', strtotime($p['data_aprovacao'])) : ''; ?>
                        </small>
                    </td>
                    <td style="text-align:right; white-space:nowrap;">
                        <?php if ((int)$p['estoque_qtd'] >= (int)$p['quantidade']): ?>
                        <form action="estoque_retirada_acao.php" method="POST" style="display:inline;">
                            <input type="hidden" name="id_solicitacao" value="<?php echo (int)$p['id_solicitacao']; ?>">
                            <input type="hidden" name="id_estoque" value="<?php echo (int)$p['id_estoque']; ?>">
                            <input type="hidden" name="quantidade" value="<?php echo (float)$p['quantidade']; ?>">
                            <input type="hidden" name="id_os" value="<?php echo (int)$p['id_os']; ?>">
                            <button type="submit" class="admin-btn" style="font-size:12px; padding:6px 12px;"
                                onclick="return confirm('Confirmar retirada de <?php echo (int)$p['quantidade']; ?> unidade(s) de [<?php echo limpar(addslashes($p['nome_peca'])); ?>]?\n\nO estoque será baixado e a peça marcada como entregue.');">
                                <i class="fas fa-check"></i> Confirmar Retirada
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="admin-badge admin-badge-erro" title="Estoque insuficiente">
                            <i class="fas fa-exclamation-triangle"></i> Estoque insuficiente
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '_footer.php'; ?>