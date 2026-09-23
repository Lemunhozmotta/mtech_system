<?php
/* =========================================================
   M-TECH SYSTEM — Página pública do orçamento
   Cliente acessa via link com token
   ========================================================= */

require_once 'sistema/conexao.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    die('Link inválido.');
}

$conn = conectar();

$stmt = $conn->prepare("
    SELECT o.*,
           os.numero_os, os.descricao_problema, os.diagnostico,
           cl.nome AS cliente_nome,
           cr.marca, cr.modelo, cr.placa
    FROM os_orcamentos o
    INNER JOIN ordens_servico os ON os.id_os = o.id_os
    INNER JOIN clientes cl ON cl.id_cliente = os.id_cliente
    INNER JOIN carros cr ON cr.id_carro = os.id_carro
    WHERE o.token_publico = ? LIMIT 1
");
$stmt->bind_param('s', $token);
$stmt->execute();
$o = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$o) {
    $conn->close();
    die('Orçamento não encontrado ou link expirado.');
}

$stmt = $conn->prepare("
    SELECT * FROM os_orcamento_itens
    WHERE id_orcamento = ?
    ORDER BY tipo DESC, id_item ASC
");
$stmt->bind_param('i', $o['id_orcamento']);
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Verifica se já foi pago
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM os_orcamento_pagamentos
                        WHERE id_orcamento = ? AND status = 'confirmado'");
$stmt->bind_param('i', $o['id_orcamento']);
$stmt->execute();
$ja_pago = (int)$stmt->get_result()->fetch_assoc()['total'] > 0;
$stmt->close();

$conn->close();

$data_validade = date('d/m/Y', strtotime($o['data_envio'] ?: $o['data_criacao']) + (86400 * (int)$o['validade_dias']));

// ===== CHAVE PIX DO RENATO (TROCAR AQUI) =====
$chave_pix = 'renato@mtech.com';
$nome_recebedor = 'M-Teck Performance Automotive';
$valor_formatado = number_format((float)$o['valor_total'], 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamento <?php echo htmlspecialchars($o['numero_orcamento']); ?> — M-Teck</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
            color: #1a1a1a;
            padding: 20px;
            line-height: 1.5;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo h1 {
            font-size: 38px;
            font-weight: 900;
            color: #D62D2D;
            letter-spacing: 3px;
        }

        .logo h1 span {
            color: #EBAF00;
        }

        .logo p {
            color: #888;
            font-size: 12px;
            margin-top: 4px;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.06);
        }

        .card h2 {
            font-size: 16px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #EBAF00;
            color: #1a1a1a;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-grid p {
            font-size: 13px;
            line-height: 1.7;
        }

        .info-grid strong {
            color: #D62D2D;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background: #1a1a1a;
            color: #fff;
            text-align: left;
            padding: 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        th.right {
            text-align: right;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        td.right {
            text-align: right;
        }

        .totais {
            margin-top: 15px;
        }

        .totais .linha {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .totais .total {
            border-top: 2px solid #1a1a1a;
            margin-top: 8px;
            padding-top: 12px;
            font-size: 22px;
            font-weight: 700;
            color: #D62D2D;
        }

        .box-pix {
            background: linear-gradient(135deg, #EBAF00, #F5C21A);
            color: #1a1a1a;
        }

        .box-pix h2 {
            border-bottom-color: #1a1a1a;
            color: #1a1a1a;
        }

        .pix-info {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pix-info .chave {
            font-family: monospace;
            font-size: 13px;
            word-break: break-all;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #D62D2D;
            color: #fff;
            padding: 14px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            border: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            transition: all 0.2s;
            margin-top: 15px;
            width: 100%;
        }

        .btn:hover {
            background: #E23E3E;
            transform: translateY(-1px);
        }

        .btn-verde {
            background: #25d366;
        }

        .btn-verde:hover {
            background: #1eb858;
        }

        .btn-secundario {
            background: #1a1a1a;
        }

        .btn-secundario:hover {
            background: #333;
        }

        .metodo-pagamento {
            display: none;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed #ccc;
        }

        .metodo-pagamento.ativo {
            display: block;
        }

        .cartao-form {
            display: grid;
            gap: 12px;
            margin-top: 15px;
        }

        .cartao-form input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }

        .cartao-form .linha {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .aviso-teste {
            background: #fff3e0;
            border-left: 4px solid #EBAF00;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 12px;
            color: #666;
            margin-top: 15px;
        }

        .pago {
            background: linear-gradient(135deg, #25d366, #4ade80);
            color: #fff;
            text-align: center;
            padding: 30px;
        }

        .pago h2 {
            color: #fff;
            border: none;
            font-size: 22px;
        }

        .footer {
            text-align: center;
            color: #888;
            font-size: 11px;
            margin-top: 30px;
            line-height: 1.7;
        }

        @media (max-width: 500px) {
            .info-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .cartao-form .linha {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">

        <div class="logo">
            <h1>M-<span>TECK</span></h1>
            <p>Performance Automotive</p>
        </div>

        <?php if ($ja_pago): ?>
            <div class="card pago">
                <h2><i class="fas fa-check-circle"></i> Orçamento Pago</h2>
                <p style="margin-top:10px;">Obrigado! Seu pagamento foi confirmado. Em breve daremos andamento no serviço.
                </p>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Orçamento #<?php echo htmlspecialchars($o['numero_orcamento']); ?></h2>

            <div class="info-grid">
                <div>
                    <p><strong>Cliente:</strong><br><?php echo htmlspecialchars($o['cliente_nome']); ?></p>
                </div>
                <div>
                    <p><strong>Veículo:</strong><br>
                        <?php echo htmlspecialchars($o['marca'] . ' ' . $o['modelo']); ?>
                        <br><span
                            style="font-family:monospace;"><?php echo htmlspecialchars(strtoupper($o['placa'])); ?></span>
                    </p>
                </div>
            </div>

            <?php if (!empty($o['descricao_problema'])): ?>
                <p style="font-size:13px; color:#666; margin-top:10px;">
                    <strong>Serviço:</strong> <?php echo htmlspecialchars($o['descricao_problema']); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Itens</h2>
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th class="right" style="width:100px;">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $it): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($it['descricao']); ?>
                                <?php if ((float)$it['quantidade'] != 1): ?>
                                    <br><small style="color:#888;">Qtd:
                                        <?php echo number_format((float)$it['quantidade'], 2, ',', '.'); ?> × R$
                                        <?php echo number_format((float)$it['valor_unitario'], 2, ',', '.'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="right"><strong>R$
                                    <?php echo number_format((float)$it['valor_total'], 2, ',', '.'); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totais">
                <div class="linha">
                    <span>Peças:</span>
                    <strong>R$ <?php echo number_format((float)$o['valor_pecas'], 2, ',', '.'); ?></strong>
                </div>
                <div class="linha">
                    <span>Mão de obra:</span>
                    <strong>R$ <?php echo number_format((float)$o['valor_mao_obra'], 2, ',', '.'); ?></strong>
                </div>
                <?php if ((float)$o['valor_desconto'] > 0): ?>
                    <div class="linha" style="color:#D62D2D;">
                        <span>Desconto:</span>
                        <strong>- R$ <?php echo number_format((float)$o['valor_desconto'], 2, ',', '.'); ?></strong>
                    </div>
                <?php endif; ?>
                <div class="linha total">
                    <span>Total:</span>
                    <strong>R$ <?php echo number_format((float)$o['valor_total'], 2, ',', '.'); ?></strong>
                </div>
            </div>

            <?php if (!empty($o['observacoes'])): ?>
                <p
                    style="margin-top:15px; font-size:12px; color:#666; padding:12px; background:#f9f9f9; border-left:3px solid #EBAF00; border-radius:4px;">
                    <?php echo nl2br(htmlspecialchars($o['observacoes'])); ?>
                </p>
            <?php endif; ?>

            <p style="text-align:center; font-size:11px; color:#888; margin-top:15px;">
                ⏱ Válido até <?php echo $data_validade; ?>
            </p>
        </div>

        <?php if (!$ja_pago && (float)$o['valor_total'] > 0): ?>

            <!-- ===== ESCOLHA DE PAGAMENTO ===== -->
            <div class="card">
                <h2><i class="fas fa-credit-card"></i> Como quer pagar?</h2>

                <button type="button" class="btn" onclick="mostrarMetodo('pix')" style="background:#25d366;">
                    <i class="fas fa-qrcode"></i> Pagar com Pix
                </button>

                <button type="button" class="btn btn-secundario" onclick="mostrarMetodo('cartao')">
                    <i class="fas fa-credit-card"></i> Pagar com Cartão de Crédito
                </button>

                <button type="button" class="btn btn-secundario" onclick="mostrarMetodo('loja')" style="background:#666;">
                    <i class="fas fa-store"></i> Prefiro pagar na loja
                </button>

                <!-- PIX -->
                <div class="metodo-pagamento" id="metodo-pix">
                    <h3 style="font-size:14px; margin-bottom:15px; text-align:center;">
                        <i class="fas fa-qrcode"></i> Escaneie o QR Code ou copie a chave
                    </h3>

                    <div style="background:#f9f9f9; border-radius:8px; padding:20px; text-align:center;">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo urlencode($chave_pix); ?>"
                            alt="QR Code Pix" style="display:inline-block;">
                    </div>

                    <div class="pix-info">
                        <div style="text-align:left;">
                            <small style="color:#666; font-size:10px; text-transform:uppercase;">Chave Pix</small>
                            <div class="chave"><?php echo htmlspecialchars($chave_pix); ?></div>
                        </div>
                        <button type="button" class="btn" onclick="copiarPix()"
                            style="margin:0; padding:8px 16px; font-size:12px; width:auto;">
                            <i class="fas fa-copy"></i> Copiar
                        </button>
                    </div>

                    <div class="aviso-teste">
                        <i class="fas fa-info-circle"></i>
                        <strong>Valor a pagar: R$ <?php echo $valor_formatado; ?></strong><br>
                        Após pagar, mande o comprovante no WhatsApp pra confirmarmos.
                    </div>
                </div>

                <!-- CARTÃO -->
                <div class="metodo-pagamento" id="metodo-cartao">
                    <h3 style="font-size:14px; margin-bottom:5px; text-align:center;">
                        <i class="fas fa-credit-card"></i> Dados do Cartão
                    </h3>
                    <p style="font-size:11px; color:#888; text-align:center; margin-bottom:15px;">
                        Ambiente de teste — pagamento com cartão é simulado
                    </p>

                    <div class="cartao-form">
                        <input type="text" id="cartao_numero" placeholder="Número do cartão" maxlength="19">
                        <input type="text" id="cartao_nome" placeholder="Nome impresso no cartão">
                        <div class="linha">
                            <input type="text" id="cartao_validade" placeholder="Validade (MM/AA)" maxlength="5">
                            <input type="text" id="cartao_cvv" placeholder="CVV" maxlength="4">
                        </div>
                        <div class="linha">
                            <select id="cartao_parcelas"
                                style="padding:12px; border:1px solid #ddd; border-radius:8px; font-family:'Poppins',sans-serif; font-size:14px;">
                                <?php for ($i = 1; $i <= 12; $i++):
                                    $valor_parcela = (float)$o['valor_total'] / $i;
                                ?>
                                    <option value="<?php echo $i; ?>">
                                        <?php echo $i; ?>x de R$ <?php echo number_format($valor_parcela, 2, ',', '.'); ?>
                                        <?php echo $i === 1 ? ' (à vista)' : ''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="button" class="btn btn-verde" onclick="simularCartao()">
                            <i class="fas fa-lock"></i> Pagar R$ <?php echo $valor_formatado; ?>
                        </button>
                    </div>

                    <div class="aviso-teste">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Ambiente de testes.</strong> NÃO insira dados reais de cartão. A integração real será feita
                        quando o gateway for contratado.
                    </div>
                </div>

                <!-- LOJA -->
                <div class="metodo-pagamento" id="metodo-loja">
                    <h3 style="font-size:14px; margin-bottom:15px; text-align:center;">
                        <i class="fas fa-store"></i> Combinado!
                    </h3>
                    <p style="font-size:13px; text-align:center; line-height:1.7;">
                        Apresente este orçamento na oficina.<br>
                        Aceitamos <strong>Pix, cartão, débito, boleto e dinheiro</strong>.
                    </p>
                    <p style="text-align:center; font-size:12px; color:#888; margin-top:15px;">
                        <strong>M-Teck Performance Automotive</strong><br>
                        Mauá - SP<br>
                        (11) 99999-9999
                    </p>
                </div>
            </div>

        <?php endif; ?>

        <div class="footer">
            <strong>M-Teck Performance Automotive</strong><br>
            Mauá - SP | (11) 99999-9999<br>
            Dúvidas? Fale com a gente pelo WhatsApp.
        </div>

    </div>

    <script>
        function mostrarMetodo(tipo) {
            document.querySelectorAll('.metodo-pagamento').forEach(el => el.classList.remove('ativo'));
            const el = document.getElementById('metodo-' + tipo);
            if (el) {
                el.classList.add('ativo');
                el.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }

        function copiarPix() {
            const chave = '<?php echo htmlspecialchars($chave_pix); ?>';
            navigator.clipboard.writeText(chave).then(() => {
                alert('Chave Pix copiada!');
            }).catch(() => {
                const input = document.createElement('input');
                input.value = chave;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                alert('Chave Pix copiada!');
            });
        }

        function simularCartao() {
            const num = document.getElementById('cartao_numero').value.trim();
            const nome = document.getElementById('cartao_nome').value.trim();
            const val = document.getElementById('cartao_validade').value.trim();
            const cvv = document.getElementById('cartao_cvv').value.trim();

            if (!num || !nome || !val || !cvv) {
                alert('Preencha todos os campos do cartão.');
                return;
            }

            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

            setTimeout(() => {
                alert(
                    '✅ Pagamento aprovado (simulação)\n\nEm produção, isso vai comunicar com o gateway de pagamento e avisar o sistema automaticamente.'
                );
                window.location.reload();
            }, 2000);
        }
    </script>

</body>

</html>