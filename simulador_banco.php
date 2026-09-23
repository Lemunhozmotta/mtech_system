<?php
/* =========================================================
   M-TECH SYSTEM — SIMULADOR DE BANCO (uso interno / teste)
   
   Página que simula o gateway de pagamento. Quando o cliente
   paga pelo link, o gateway real chamaria o webhook. Aqui,
   VOCÊ simula essa chamada manualmente.

   USO: apenas pra desenvolvimento. Deletar antes de ir pra
   produção com gateway real.
   ========================================================= */

require_once 'sistema/conexao.php';

$senha_acesso = 'mtech-simulador-2026';

// Verifica senha via GET
$senha = $_GET['senha'] ?? '';
$autenticado = ($senha === $senha_acesso);

// Se não autenticado, mostra tela de senha
if (!$autenticado):
?>
    <!DOCTYPE html>
    <html lang="pt-br">

    <head>
        <meta charset="UTF-8">
        <title>Simulador de Banco — M-Teck</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Poppins', sans-serif;
                background: #161616;
                color: #FEFEFE;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .card {
                background: #252424;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
                max-width: 400px;
                width: 100%;
                text-align: center;
                border-top: 4px solid #EBAF00;
            }

            h1 {
                font-size: 22px;
                margin-bottom: 10px;
                color: #EBAF00;
            }

            p {
                font-size: 13px;
                color: #888;
                margin-bottom: 25px;
            }

            input {
                width: 100%;
                padding: 14px;
                background: #161616;
                color: #FEFEFE;
                border: 1px solid rgba(204, 204, 204, 0.15);
                border-radius: 8px;
                font-family: 'Poppins', sans-serif;
                font-size: 14px;
                margin-bottom: 15px;
                outline: none;
            }

            input:focus {
                border-color: #EBAF00;
            }

            button {
                width: 100%;
                padding: 14px;
                background: #EBAF00;
                color: #161616;
                border: none;
                border-radius: 8px;
                font-family: 'Poppins', sans-serif;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
            }

            button:hover {
                background: #F5C21A;
            }
        </style>
    </head>

    <body>
        <div class="card">
            <h1>🏦 Simulador de Banco</h1>
            <p>Acesso restrito ao desenvolvedor</p>
            <form method="GET">
                <input type="password" name="senha" placeholder="Senha do simulador" required autofocus>
                <button type="submit">Entrar</button>
            </form>
        </div>
    </body>

    </html>
<?php
    exit;
endif;

// ===== BUSCA PAGAMENTOS PENDENTES =====
$conn = conectar();

$sql = "
    SELECT o.id_orcamento, o.numero_orcamento, o.token_publico, o.valor_total,
           o.status, o.data_envio,
           os.numero_os, os.status AS os_status,
           cl.nome AS cliente_nome
    FROM os_orcamentos o
    INNER JOIN ordens_servico os ON os.id_os = o.id_os
    INNER JOIN clientes cl ON cl.id_cliente = os.id_cliente
    WHERE o.status = 'enviado'
      AND o.token_publico IS NOT NULL
      AND NOT EXISTS (
          SELECT 1 FROM os_orcamento_pagamentos p
          WHERE p.id_orcamento = o.id_orcamento AND p.status = 'confirmado'
      )
    ORDER BY o.data_envio DESC
";
$pendentes = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Últimos pagamentos confirmados (histórico)
$historico = $conn->query("
    SELECT p.*, o.numero_orcamento, cl.nome AS cliente_nome
    FROM os_orcamento_pagamentos p
    INNER JOIN os_orcamentos o ON o.id_orcamento = p.id_orcamento
    INNER JOIN ordens_servico os ON os.id_os = o.id_os
    INNER JOIN clientes cl ON cl.id_cliente = os.id_cliente
    WHERE p.status = 'confirmado'
    ORDER BY p.id_pagamento DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Simulador de Banco — M-Teck</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f0f0f0;
            color: #1a1a1a;
            padding: 30px;
            min-height: 100vh;
        }

        .header {
            max-width: 1100px;
            margin: 0 auto 30px;
            background: #1a1a1a;
            color: #fff;
            padding: 20px 25px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 20px;
        }

        .header h1 span {
            color: #EBAF00;
        }

        .header .info {
            font-size: 12px;
            color: #888;
            text-align: right;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1a1a1a;
        }

        .pendente-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            border-left: 4px solid #EBAF00;
        }

        .pendente-info {
            flex: 1;
            min-width: 250px;
        }

        .pendente-info .titulo {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .pendente-info .detalhes {
            font-size: 12px;
            color: #666;
            line-height: 1.7;
        }

        .pendente-valor {
            font-size: 22px;
            font-weight: 700;
            color: #D62D2D;
            white-space: nowrap;
        }

        .pendente-acoes {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-aprovar,
        .btn-recusar {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-aprovar {
            background: #25d366;
            color: #fff;
        }

        .btn-aprovar:hover {
            background: #1eb858;
            transform: translateY(-1px);
        }

        .btn-recusar {
            background: #D62D2D;
            color: #fff;
        }

        .btn-recusar:hover {
            background: #E23E3E;
            transform: translateY(-1px);
        }

        .vazio {
            background: #fff;
            border-radius: 12px;
            padding: 50px 20px;
            text-align: center;
            color: #888;
            font-size: 14px;
        }

        .vazio i {
            font-size: 40px;
            color: #ccc;
            display: block;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            border-collapse: collapse;
            font-size: 13px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        th {
            background: #1a1a1a;
            color: #fff;
            text-align: left;
            padding: 12px 15px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        td.right {
            text-align: right;
        }

        td strong {
            font-weight: 600;
        }

        .badge-pago {
            background: #d4f5dd;
            color: #1a7f37;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .alerta {
            background: #fff3e0;
            border-left: 4px solid #EBAF00;
            padding: 15px 20px;
            border-radius: 8px;
            font-size: 13px;
            color: #666;
            margin-bottom: 25px;
        }

        .alerta strong {
            color: #1a1a1a;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>🏦 Simulador de <span>Banco</span> — M-Teck</h1>
        <div class="info">
            Ambiente de teste<br>
            Modo: <strong style="color:#EBAF00;">Desenvolvimento</strong>
        </div>
    </div>

    <div class="container">

        <div class="alerta">
            <strong>⚠️ Página de simulação.</strong> Aqui você finge ser o banco/gateway de pagamento.
            Quando o cliente paga pelo link, o gateway real chamaria o <code>webhook_pagamento.php</code>.
            Enquanto não há gateway, use os botões abaixo pra simular a resposta do banco.
        </div>

        <!-- ===== PENDENTES ===== -->
        <h2 class="section-title">
            <i class="fas fa-hourglass-half"></i> Aguardando resposta do banco (<?php echo count($pendentes); ?>)
        </h2>

        <?php if (empty($pendentes)): ?>
            <div class="vazio">
                <i class="fas fa-check-circle"></i>
                Nenhum pagamento pendente.<br>
                <small>Envie um orçamento ao cliente pra aparecer aqui.</small>
            </div>
        <?php else: ?>
            <?php foreach ($pendentes as $p): ?>
                <div class="pendente-card">
                    <div class="pendente-info">
                        <div class="titulo">
                            Orçamento <strong><?php echo htmlspecialchars($p['numero_orcamento']); ?></strong>
                            — OS <?php echo htmlspecialchars($p['numero_os']); ?>
                        </div>
                        <div class="detalhes">
                            Cliente: <strong><?php echo htmlspecialchars($p['cliente_nome']); ?></strong><br>
                            Enviado em <?php echo date('d/m/Y H:i', strtotime($p['data_envio'])); ?>
                        </div>
                    </div>
                    <div class="pendente-valor">
                        R$ <?php echo number_format((float)$p['valor_total'], 2, ',', '.'); ?>
                    </div>
                    <div class="pendente-acoes">
                        <button class="btn-aprovar"
                            onclick="simular('<?php echo htmlspecialchars($p['token_publico']); ?>', 'aprovado', 'pix', <?php echo (float)$p['valor_total']; ?>)">
                            <i class="fas fa-check"></i> Simular Pagamento Aprovado
                        </button>
                        <button class="btn-recusar"
                            onclick="simular('<?php echo htmlspecialchars($p['token_publico']); ?>', 'recusado', 'pix', <?php echo (float)$p['valor_total']; ?>)">
                            <i class="fas fa-times"></i> Simular Recusa
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- ===== HISTÓRICO ===== -->
        <h2 class="section-title" style="margin-top: 40px;">
            <i class="fas fa-history"></i> Últimos pagamentos confirmados
        </h2>

        <?php if (empty($historico)): ?>
            <div class="vazio">
                <i class="fas fa-inbox"></i>
                Nenhum pagamento confirmado ainda.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Orçamento</th>
                        <th>Cliente</th>
                        <th>Forma</th>
                        <th class="right">Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historico as $h): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($h['data_pagamento'] ?: $h['criado_em'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($h['numero_orcamento']); ?></strong></td>
                            <td><?php echo htmlspecialchars($h['cliente_nome']); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $h['forma'])); ?></td>
                            <td class="right"><strong>R$ <?php echo number_format((float)$h['valor'], 2, ',', '.'); ?></strong>
                            </td>
                            <td><span class="badge-pago">✓ Confirmado</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>

    <!-- ===== FORM ESCONDIDO PRO WEBHOOK ===== -->
    <form id="formWebhook" method="POST" action="webhook_pagamento.php" style="display:none;">
        <input type="hidden" name="senha" value="<?php echo htmlspecialchars($senha_acesso); ?>">
        <input type="hidden" name="token" id="wh_token">
        <input type="hidden" name="status" id="wh_status">
        <input type="hidden" name="forma" id="wh_forma">
        <input type="hidden" name="valor" id="wh_valor">
        <input type="hidden" name="transacao_id" id="wh_transacao">
    </form>

    <script>
        function simular(token, status, forma, valor) {
            const acao = status === 'aprovado' ? 'APROVAR' : 'RECUSAR';
            if (!confirm('Simular ' + acao +
                    ' o pagamento?\n\nO sistema vai processar como se o banco tivesse respondido.')) {
                return;
            }

            document.getElementById('wh_token').value = token;
            document.getElementById('wh_status').value = status;
            document.getElementById('wh_forma').value = forma;
            document.getElementById('wh_valor').value = valor;
            document.getElementById('wh_transacao').value = 'SIM-' + Date.now();

            document.getElementById('formWebhook').submit();
        }
    </script>

</body>

</html>