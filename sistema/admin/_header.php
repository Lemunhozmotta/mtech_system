<?php
/* =========================================================
   M-TECH SYSTEM — HEADER DO ADMIN
   Cabeçalho + sidebar (menu lateral)
   Todas as páginas do admin incluem este arquivo
   ========================================================= */

require_once '../conexao.php';

// Verifica se está logado (senão redireciona pro login)
verificarLogin();

// Pega dados do usuário logado
$usuario = usuarioLogado();

// Descobre qual página está ativa (pra marcar no menu)
$pagina_atual = basename($_SERVER['PHP_SELF']);

// Função helper pra marcar item ativo
function menuAtivo($pagina, $atual)
{
    return $pagina === $atual ? 'class="ativo"' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../img/logo-ico.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>M-Teck System — <?php echo $titulo_pagina ?? 'Admin'; ?></title>
    <link rel="stylesheet" href="../css/style-admin.css">
</head>

<body>

    <div class="admin-wrapper">

        <!-- =====================================================
             SIDEBAR (menu lateral)
             ===================================================== -->
        <aside class="admin-sidebar">

            <!-- Logo -->
            <div class="admin-sidebar-logo">
                <img src="../../img/logo-br.png" alt="M-Teck">
            </div>

            <!-- Menu principal -->
            <ul class="admin-sidebar-menu">
                <li>
                    <a href="dashboard.php" <?php echo menuAtivo('dashboard.php', $pagina_atual); ?>>
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="clientes.php" <?php echo menuAtivo('clientes.php', $pagina_atual); ?>>
                        <i class="fas fa-users"></i>
                        <span>Clientes</span>
                    </a>
                </li>
                <li>
                    <a href="carros.php" <?php echo menuAtivo('carros.php', $pagina_atual); ?>>
                        <i class="fas fa-car"></i>
                        <span>Carros</span>
                    </a>
                </li>
                <?php if (in_array($usuario['nivel'], [1, 2])): ?>
                    <li>
                        <a href="marcas.php" <?php echo menuAtivo('marcas.php', $pagina_atual); ?>>
                            <i class="fas fa-tags"></i>
                            <span>Marcas e Modelos</span>
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <a href="ordens.php" <?php echo menuAtivo('ordens.php', $pagina_atual); ?>>
                        <i class="fas fa-clipboard-list"></i>
                        <span>Ordens de Serviço</span>
                    </a>
                </li>
                <li>
                    <a href="estoque.php" <?php echo menuAtivo('estoque.php', $pagina_atual); ?>>
                        <i class="fas fa-boxes"></i>
                        <span>Estoque</span>
                    </a>
                </li>
                <li>
                    <a href="pagamentos.php" <?php echo menuAtivo('pagamentos.php', $pagina_atual); ?>>
                        <i class="fas fa-dollar-sign"></i>
                        <span>Pagamentos</span>
                    </a>
                </li>
                <li>
                    <a href="agendamentos.php" <?php echo menuAtivo('agendamentos.php', $pagina_atual); ?>>
                        <i class="fas fa-calendar-alt"></i>
                        <span>Agendamentos</span>
                    </a>
                </li>
                <li>
                    <a href="guincho.php" <?php echo menuAtivo('guincho.php', $pagina_atual); ?>>
                        <i class="fas fa-truck-pickup"></i>
                        <span>Guincho</span>
                    </a>
                </li>
                <li>
                    <a href="equipe.php" <?php echo menuAtivo('equipe.php', $pagina_atual); ?>>
                        <i class="fas fa-user-tie"></i>
                        <span>Equipe</span>
                    </a>
                </li>
                <li>
                    <a href="financeiro.php" <?php echo menuAtivo('financeiro.php', $pagina_atual); ?>>
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Financeiro</span>
                    </a>
                </li>
            </ul>

            <!-- Rodapé: Sair -->
            <ul class="admin-sidebar-footer">
                <li>
                    <a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Sair</span>
                    </a>
                </li>
            </ul>

        </aside>

        <!-- =====================================================
             ÁREA PRINCIPAL
             ===================================================== -->
        <div class="admin-main">

            <!-- Header -->
            <header class="admin-header">

                <div class="admin-header-titulo">
                    <?php echo $titulo_pagina ?? 'Dashboard'; ?>
                </div>

                <div class="admin-header-acoes">

                    <!-- Botão de tema -->
                    <button class="admin-header-btn" id="btnTemaAdmin" aria-label="Alternar tema">
                        <i class="fas fa-sun"></i>
                    </button>

                    <!-- Notificações -->
                    <button class="admin-header-btn" aria-label="Notificações">
                        <i class="fas fa-bell"></i>
                        <span class="badge-notif">3</span>
                    </button>

                    <!-- Usuário logado -->
                    <div class="admin-header-user">
                        <div class="admin-header-user-avatar">
                            <?php echo strtoupper(substr($usuario['nome'], 0, 1)); ?>
                        </div>
                        <div class="admin-header-user-info">
                            <span class="admin-header-user-nome"><?php echo limpar($usuario['nome']); ?></span>
                            <span class="admin-header-user-nivel"><?php echo nomeNivel($usuario['nivel']); ?></span>
                        </div>
                    </div>

                </div>

            </header>

            <!-- Conteúdo da página -->
            <main class="admin-conteudo">