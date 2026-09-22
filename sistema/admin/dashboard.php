<?php
/* =========================================================
   M-TECH SYSTEM — DASHBOARD
   Tela inicial pós-login
   ========================================================= */

$titulo_pagina = 'Dashboard';
require_once '_header.php';

// ===== DADOS DO USUÁRIO =====
$usuarioLogado = usuarioLogado();
$nomeUsuario = limpar($usuarioLogado['nome']);
$primeiroNome = explode(' ', $nomeUsuario)[0];
?>

<!-- ===== TÍTULO DE BOAS-VINDAS ===== -->
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
        <div class="admin-card-resumo-valor">0</div>
        <div class="admin-card-resumo-titulo">OS em Andamento</div>
    </div>

    <!-- Card 2: OS entregues no mês -->
    <div class="admin-card-resumo verde">
        <div class="admin-card-resumo-icone">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="admin-card-resumo-valor">0</div>
        <div class="admin-card-resumo-titulo">OS Entregues (Mês)</div>
    </div>

    <!-- Card 3: Aguardando peça -->
    <div class="admin-card-resumo">
        <div class="admin-card-resumo-icone">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div class="admin-card-resumo-valor">0</div>
        <div class="admin-card-resumo-titulo">Aguardando Peça</div>
    </div>

    <!-- Card 4: Agendamentos de hoje -->
    <div class="admin-card-resumo azul">
        <div class="admin-card-resumo-icone">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="admin-card-resumo-valor">0</div>
        <div class="admin-card-resumo-titulo">Agendamentos Hoje</div>
    </div>

</div>

<!-- =====================================================
     BLOCO: ÚLTIMAS OS
     ===================================================== -->
<div class="admin-bloco">

    <div class="admin-bloco-titulo">
        <span><i class="fas fa-clipboard-list"></i> Últimas Ordens de Serviço</span>
        <span>Em breve dados reais</span>
    </div>

    <table class="admin-tabela">
        <thead>
            <tr>
                <th>Nº OS</th>
                <th>Cliente</th>
                <th>Veículo</th>
                <th>Status</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="5" style="text-align: center; padding: 40px; color: var(--mtech-text-muted);">
                    <i class="fas fa-inbox"
                        style="font-size: 32px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                    Nenhuma OS cadastrada ainda.<br>
                    <small>O módulo de Ordens de Serviço será criado em breve.</small>
                </td>
            </tr>
        </tbody>
    </table>

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