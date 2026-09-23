<?php
/* =========================================================
   M-TECH SYSTEM — DASHBOARD (dinâmico)
   ========================================================= */

$titulo_pagina = 'Dashboard';
require_once '_header.php';

$polling_ativo = true;

$usuarioLogado = usuarioLogado();
$nomeUsuario = limpar($usuarioLogado['nome']);
$primeiroNome = explode(' ', $nomeUsuario)[0];

$conn = conectar();

// ===== CARD 1: OS EM ANDAMENTO =====
$res = $conn->query("SELECT COUNT(*) AS total FROM ordens_servico WHERE status = 'em_andamento'");
$os_em_andamento = (int)$res->fetch_assoc()['total'];

// ===== CARD 2: OS ENTREGUES NO MÊS =====
$res = $conn->query("SELECT COUNT(*) AS total FROM ordens_servico
                     WHERE status = 'concluida'
                       AND MONTH(data_conclusao) = MONTH(CURDATE())
                       AND YEAR(data_conclusao) = YEAR(CURDATE())");
$os_entregues_mes = (int)$res->fetch_assoc()['total'];

// ===== CARD 3: AGUARDANDO PEÇA =====
$res = $conn->query("SELECT COUNT(*) AS total FROM ordens_servico WHERE status = 'aguardando_peca'");
$os_aguardando_peca = (int)$res->fetch_assoc()['total'];

// ===== CARD 4: AGENDAMENTOS DE HOJE =====
$res = $conn->query("SELECT COUNT(*) AS total FROM agendamentos
                     WHERE DATE(data_agendamento) = CURDATE()
                       AND status IN ('pendente', 'confirmado')");
$agendamentos_hoje = (int)$res->fetch_assoc()['total'];

// ===== ÚLTIMAS 5 OS =====
$ultimas_os = $conn->query("
    SELECT os.id_os, os.numero_os, os.status, os.valor_total, os.data_abertura,
           cl.nome AS cliente_nome,
           cr.marca, cr.modelo, cr.placa
    FROM ordens_servico os
    INNER JOIN clientes cl ON cl.id_cliente = os.id_cliente
    INNER JOIN carros cr ON cr.id_carro = os.id_carro
    ORDER BY os.id_os DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$conn->close();

// ===== HELPERS =====
function nomeStatusDash($s)
{
    return [
        'aberta' => 'Aberta',
        'em_andamento' => 'Em Andamento',
        'aguardando_peca' => 'Aguardando Peça',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada',
    ][$s] ?? $s;
}
function classeStatusDash($s)
{
    return [
        'aberta' => 'admin-badge-info',
        'em_andamento' => 'admin-badge-alerta',
        'aguardando_peca' => 'admin-badge-erro',
        'concluida' => 'admin-badge-sucesso',
        'cancelada' => 'admin-badge-erro',
    ][$s] ?? 'admin-badge-info';
}
?>

<h1 class="admin-titulo-pagina">Bem-vindo, <?php echo $primeiroNome; ?>! 👋</h1>

<!-- =====================================================
     CARDS DE RESUMO
     ===================================================== -->
<div class="admin-cards-resumo">

    <!-- Card 1: OS em andamento -->
    <div class="admin-card-resumo amarelo">
        <div class="admin-card-resumo-icone">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="admin-card-resumo-valor"><?php echo $os_em_andamento; ?></div>
        <div class="admin-card-resumo-titulo">OS em Andamento</div>
    </div>

    <!-- Card 2: OS entregues no mês -->
    <div class="admin-card-resumo verde">
        <div class="admin-card-resumo-icone">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="admin-card-resumo-valor"><?php echo $os_entregues_mes; ?></div>
        <div class="admin-card-resumo-titulo">OS Entregues (Mês)</div>
    </div>

    <!-- Card 3: Aguardando peça -->
    <div class="admin-card-resumo">
        <div class="admin-card-resumo-icone">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div class="admin-card-resumo-valor"><?php echo $os_aguardando_peca; ?></div>
        <div class="admin-card-resumo-titulo">Aguardando Peça</div>
    </div>

    <!-- Card 4: Agendamentos de hoje -->
    <div class="admin-card-resumo azul">
        <div class="admin-card-resumo-icone">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="admin-card-resumo-valor"><?php echo $agendamentos_hoje; ?></div>
        <div class="admin-card-resumo-titulo">Agendamentos Hoje</div>
    </div>

</div>

<!-- =====================================================
     BLOCO: ÚLTIMAS OS
     ===================================================== -->
<div class="admin-bloco">

    <div class="admin-bloco-titulo">
        <span><i class="fas fa-clipboard-list"></i> Últimas Ordens de Serviço</span>
        <a href="ordens.php" class="admin-btn admin-btn-secundario" style="font-size:12px; padding:6px 12px;">
            Ver todas <i class="fas fa-arrow-right"></i>
        </a>
    </div>

    <?php if (empty($ultimas_os)): ?>

        <div class="admin-vazio" style="padding: 40px 20px;">
            <i class="fas fa-inbox"></i>
            <p>Nenhuma OS cadastrada ainda.</p>
            <small>Clique em <a href="ordens_novo.php">Nova OS</a> pra começar.</small>
        </div>

    <?php else: ?>

        <table class="admin-tabela">
            <thead>
                <tr>
                    <th>Nº OS</th>
                    <th>Cliente</th>
                    <th>Veículo</th>
                    <th>Status</th>
                    <th>Valor</th>
                    <th style="text-align: right;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ultimas_os as $os): ?>
                    <tr>
                        <td>
                            <strong style="font-family: monospace; letter-spacing: 1px;">
                                <?php echo limpar($os['numero_os']); ?>
                            </strong>
                        </td>
                        <td><?php echo limpar($os['cliente_nome']); ?></td>
                        <td>
                            <strong><?php echo limpar($os['marca']); ?></strong>
                            <?php echo limpar($os['modelo']); ?>
                            <br>
                            <small style="color: var(--mtech-text-muted); font-family: monospace;">
                                <?php echo limpar(strtoupper($os['placa'])); ?>
                            </small>
                        </td>
                        <td>
                            <span class="admin-badge <?php echo classeStatusDash($os['status']); ?>">
                                <?php echo nomeStatusDash($os['status']); ?>
                            </span>
                        </td>
                        <td>
                            R$ <?php echo number_format((float)$os['valor_total'], 2, ',', '.'); ?>
                        </td>
                        <td style="text-align: right;">
                            <a href="ordens_ver.php?id=<?php echo (int)$os['id_os']; ?>" class="admin-btn-acao" title="Ver">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>

</div>

<!-- =====================================================
     BLOCO: CONTAS A RECEBER
     ===================================================== -->
<div class="admin-bloco">

    <div class="admin-bloco-titulo">
        <span><i class="fas fa-money-bill-wave"></i> Contas a Receber</span>
        <span>Em breve dados reais</span>
    </div>

    <table class="admin-tabela">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Vencimento</th>
                <th>Valor</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="4" style="text-align: center; padding: 40px; color: var(--mtech-text-muted);">
                    <i class="fas fa-inbox"
                        style="font-size: 32px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                    Nenhuma conta a receber.<br>
                    <small>O módulo financeiro será criado em breve.</small>
                </td>
            </tr>
        </tbody>
    </table>

</div>

<?php require_once '_footer.php'; ?>