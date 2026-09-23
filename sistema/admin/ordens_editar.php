<?php
/* =========================================================
   M-TECH SYSTEM — ORDENS DE SERVIÇO (editar)
   Regra: admin (1) OU quem abriu OU mecânico apontado
   ========================================================= */

$titulo_pagina = 'Editar OS';
require_once '_header.php';

$usuarioLogado = usuarioLogado();
$conn = conectar();

$id_os = (int)($_GET['id'] ?? 0);
if ($id_os <= 0) {
    $conn->close();
    redirecionar('ordens.php?msg=erro');
}

$stmt = $conn->prepare("
    SELECT os.*, cl.nome AS cliente_nome,
           cr.marca, cr.modelo, cr.placa, cr.ano,
           u.nome AS mecanico_nome
    FROM ordens_servico os
    INNER JOIN clientes cl ON cl.id_cliente = os.id_cliente
    INNER JOIN carros cr ON cr.id_carro = os.id_carro
    LEFT JOIN usuarios u ON u.id_usuario = os.id_mecanico
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

// ===== PERMISSÃO =====
$nivel = (int)$usuarioLogado['nivel'];
$idUsuario = (int)$usuarioLogado['id_usuario'];
$abriuOS = ((int)$os['id_usuario_abertura'] === $idUsuario);
$estaApontado = estaApontadoNaOS($conn, $id_os, $idUsuario);

$podeEditar = ($nivel === 1) || $abriuOS || ($nivel === 3 && $estaApontado);

if (!$podeEditar) {
    $conn->close();
    redirecionar('ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
}

if (in_array($os['status'], ['concluida', 'cancelada'])) {
    $conn->close();
    redirecionar('ordens_ver.php?id=' . $id_os . '&msg=sem_permissao');
}

// ===== MECÂNICOS =====
$res_mec = $conn->query("SELECT id_usuario, nome FROM usuarios WHERE ativo = 1 AND nivel = 3 ORDER BY nome ASC");
$mecanicos = $res_mec->fetch_all(MYSQLI_ASSOC);

$conn->close();

$data_prev = $os['data_previsao'] ? date('Y-m-d', strtotime($os['data_previsao'])) : '';

// Mecânico apontado NÃO pode trocar responsável
$podeTrocarResponsavel = ($nivel !== 3) || !$estaApontado;

// ===== AVISO PRA MECÂNICO APONTADO =====
$avisoMecanico = ($nivel === 3 && $estaApontado);
?>

<div class="admin-topo-pagina">
    <h1 class="admin-titulo-pagina">
        <a href="ordens_ver.php?id=<?php echo $id_os; ?>" class="admin-voltar" title="Voltar"><i
                class="fas fa-arrow-left"></i></a>
        Editar OS <?php echo limpar($os['numero_os']); ?>
    </h1>
</div>

<?php if ($avisoMecanico): ?>
    <div class="admin-alerta admin-alerta-alerta">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Você está apontado nesta OS.</strong> Preencha o diagnóstico e a solução conforme for trabalhando.
            Não esqueça de <strong>desapontar</strong> quando terminar pra liberar pra outro serviço.
        </div>
    </div>
<?php endif; ?>

<form action="ordens_acao.php" method="POST" class="admin-form">
    <input type="hidden" name="acao" value="editar">
    <input type="hidden" name="id_os" value="<?php echo $id_os; ?>">

    <div class="admin-bloco">
        <div class="admin-bloco-titulo"><span><i class="fas fa-car"></i> Cliente e Veículo (não editável)</span></div>
        <div class="admin-form-grid">
            <div class="admin-form-campo"><label>Cliente</label>
                <input type="text" value="<?php echo limpar($os['cliente_nome']); ?>" disabled>
            </div>
            <div class="admin-form-campo"><label>Veículo</label>
                <input type="text"
                    value="<?php echo limpar($os['marca'] . ' ' . $os['modelo'] . ' (' . strtoupper($os['placa']) . ')'); ?>"
                    disabled>
            </div>
        </div>
    </div>

    <div class="admin-bloco">
        <div class="admin-bloco-titulo"><span><i class="fas fa-clipboard-list"></i> Dados da OS</span></div>
        <div class="admin-form-grid">

            <?php if ($podeTrocarResponsavel): ?>
                <div class="admin-form-campo">
                    <label for="id_mecanico">Mecânico responsável</label>
                    <select id="id_mecanico" name="id_mecanico">
                        <option value="">— A definir —</option>
                        <?php foreach ($mecanicos as $mec): ?>
                            <option value="<?php echo (int)$mec['id_usuario']; ?>"
                                <?php echo ((int)$os['id_mecanico'] === (int)$mec['id_usuario']) ? 'selected' : ''; ?>>
                                <?php echo limpar($mec['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="admin-form-campo">
                    <label>Mecânico responsável</label>
                    <input type="text" value="<?php echo limpar($os['mecanico_nome'] ?? $usuarioLogado['nome']); ?>"
                        disabled>
                    <small class="admin-dica">Você não pode trocar o responsável enquanto está apontado.</small>
                </div>
            <?php endif; ?>

            <div class="admin-form-campo">
                <label for="data_previsao">Previsão de entrega</label>
                <input type="date" id="data_previsao" name="data_previsao" value="<?php echo $data_prev; ?>">
            </div>

            <div class="admin-form-campo admin-form-campo-full">
                <label for="descricao_problema">Problema relatado pelo cliente *</label>
                <textarea id="descricao_problema" name="descricao_problema" rows="4"
                    required><?php echo limpar($os['descricao_problema']); ?></textarea>
            </div>
        </div>
    </div>

    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-stethoscope"></i> Diagnóstico e Solução</span>
            <?php if ($avisoMecanico): ?>
                <span style="color: var(--mtech-yellow); font-size: 12px;">
                    <i class="fas fa-pen"></i> preencha conforme for trabalhando
                </span>
            <?php endif; ?>
        </div>
        <div class="admin-form-grid">
            <div class="admin-form-campo admin-form-campo-full">
                <label for="diagnostico">Diagnóstico técnico</label>
                <textarea id="diagnostico" name="diagnostico" rows="5"
                    placeholder="Ex: Realizado escaneamento OBD2. Detectado código de falha P0302 (misfire cilindro 2)..."><?php echo limpar($os['diagnostico'] ?? ''); ?></textarea>
            </div>
            <div class="admin-form-campo admin-form-campo-full">
                <label for="solucao">Solução aplicada</label>
                <textarea id="solucao" name="solucao" rows="4"
                    placeholder="Ex: Substituição do kit completo de velas e cabos. Teste final sem falhas."><?php echo limpar($os['solucao'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>

    <div class="admin-form-acoes">
        <a href="ordens_ver.php?id=<?php echo $id_os; ?>" class="admin-btn admin-btn-secundario"><i
                class="fas fa-times"></i> Cancelar</a>
        <button type="submit" class="admin-btn"><i class="fas fa-save"></i> Salvar Alterações</button>
    </div>
</form>

<?php require_once '_footer.php'; ?>