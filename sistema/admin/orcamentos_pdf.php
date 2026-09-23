<?php
/* =========================================================
   M-TECH SYSTEM — PDF do Orçamento
   Gera HTML otimizado para impressão (Ctrl+P → Salvar como PDF)
   ========================================================= */

require_once '../conexao.php';
verificarLogin();

$usuarioLogado = usuarioLogado();
$conn = conectar();

$id_orcamento = (int)($_GET['id'] ?? 0);
if ($id_orcamento <= 0) {
    $conn->close();
    redirecionar('orcamentos.php');
}

$stmt = $conn->prepare("
    SELECT o.*,
           os.numero_os, os.descricao_problema, os.diagnostico,
           cl.nome AS cliente_nome, cl.telefone AS cliente_telefone, cl.whatsapp AS cliente_whatsapp,
           cl.email AS cliente_email, cl.cpf_cnpj,
           cr.marca, cr.modelo, cr.placa, cr.ano, cr.cor, cr.km_atual
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
    redirecionar('orcamentos.php');
}

$stmt = $conn->prepare("
    SELECT * FROM os_orcamento_itens
    WHERE id_orcamento = ?
    ORDER BY tipo DESC, id_item ASC
");
$stmt->bind_param('i', $id_orcamento);
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

$data_validade = date('d/m/Y', strtotime($o['data_envio'] ?: $o['data_criacao']) + (86400 * (int)$o['validade_dias']));
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Orçamento <?php echo htmlspecialchars($o['numero_orcamento']); ?> — M-Teck</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            color: #1a1a1a;
            background: #fff;
            padding: 40px;
            font-size: 13px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #EBAF00;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 32px;
            font-weight: 900;
            color: #D62D2D;
            letter-spacing: 2px;
        }

        .logo span {
            color: #EBAF00;
        }

        .empresa-info {
            text-align: right;
            font-size: 12px;
            color: #666;
            line-height: 1.6;
        }

        .titulo-orcamento {
            background: #f5f5f5;
            padding: 15px 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .titulo-orcamento h1 {
            font-size: 20px;
        }

        .titulo-orcamento .numero {
            font-family: monospace;
            font-size: 20px;
            font-weight: 700;
            color: #D62D2D;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .info-box {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
        }

        .info-box h3 {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .info-box p {
            line-height: 1.7;
        }

        .info-box strong {
            color: #1a1a1a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        th {
            background: #1a1a1a;
            color: #fff;
            text-align: left;
            padding: 10px 12px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        th.right {
            text-align: right;
        }

        th.center {
            text-align: center;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
        }

        td.right {
            text-align: right;
        }

        td.center {
            text-align: center;
        }

        td.mono {
            font-family: monospace;
        }

        .tipo-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .tipo-peca {
            background: #e3f2fd;
            color: #1976d2;
        }

        .tipo-servico {
            background: #fff3e0;
            color: #f57c00;
        }

        .totais {
            margin-left: auto;
            width: 320px;
            border: 2px solid #1a1a1a;
            border-radius: 8px;
            overflow: hidden;
        }

        .totais .linha {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }

        .totais .linha:last-child {
            border-bottom: none;
        }

        .totais .total {
            background: #EBAF00;
            color: #1a1a1a;
            font-size: 18px;
            font-weight: 700;
        }

        .obs {
            margin-top: 25px;
            padding: 15px;
            background: #f9f9f9;
            border-left: 3px solid #EBAF00;
            border-radius: 4px;
            font-size: 12px;
            line-height: 1.6;
        }

        .obs h4 {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 11px;
            color: #888;
        }

        .validade {
            background: #fff9e6;
            border: 1px solid #EBAF00;
            padding: 10px 15px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 12px;
            text-align: center;
        }

        @media print {
            body {
                padding: 20px;
            }

            .no-print {
                display: none;
            }
        }

        .btn-imprimir {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #D62D2D;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(214, 45, 45, 0.3);
        }

        .btn-imprimir:hover {
            background: #E23E3E;
        }
    </style>
</head>

<body>

    <button class="btn-imprimir no-print" onclick="window.print()">
        🖨️ Imprimir / Salvar PDF
    </button>

    <div class="header">
        <div class="logo">M-<span>TECK</span></div>
        <div class="empresa-info">
            <strong>M-Teck Performance Automotive</strong><br>
            Mauá - SP<br>
            (11) 99999-9999<br>
            contato@mtech.com
        </div>
    </div>

    <div class="titulo-orcamento">
        <h1>ORÇAMENTO</h1>
        <div class="numero">#<?php echo htmlspecialchars($o['numero_orcamento']); ?></div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <h3>Cliente</h3>
            <p>
                <strong><?php echo htmlspecialchars($o['cliente_nome']); ?></strong><br>
                <?php if ($o['cpf_cnpj']): ?>CPF/CNPJ:
                <?php echo htmlspecialchars($o['cpf_cnpj']); ?><br><?php endif; ?>
            <?php if ($o['cliente_whatsapp'] || $o['cliente_telefone']): ?>
                Tel: <?php echo htmlspecialchars($o['cliente_whatsapp'] ?: $o['cliente_telefone']); ?><br>
            <?php endif; ?>
            <?php if ($o['cliente_email']): ?>Email:
            <?php echo htmlspecialchars($o['cliente_email']); ?><?php endif; ?>
            </p>
        </div>
        <div class="info-box">
            <h3>Veículo</h3>
            <p>
                <strong><?php echo htmlspecialchars($o['marca'] . ' ' . $o['modelo']); ?></strong><br>
                Placa: <span class="mono"><?php echo htmlspecialchars(strtoupper($o['placa'])); ?></span><br>
                <?php if ($o['ano']): ?>Ano: <?php echo htmlspecialchars($o['ano']); ?><br><?php endif; ?>
            <?php if ($o['cor']): ?>Cor: <?php echo htmlspecialchars($o['cor']); ?><br><?php endif; ?>
        <?php if ($o['km_atual']): ?>KM:
        <?php echo number_format((int)$o['km_atual'], 0, ',', '.'); ?><?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (!empty($o['descricao_problema'])): ?>
        <div class="info-box" style="margin-bottom: 25px;">
            <h3>Descrição do Serviço</h3>
            <p><?php echo nl2br(htmlspecialchars($o['descricao_problema'])); ?></p>
        </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th style="width:80px;">Tipo</th>
                <th>Descrição</th>
                <th class="center" style="width:60px;">Qtd</th>
                <th class="right" style="width:110px;">Valor Un.</th>
                <th class="right" style="width:110px;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens as $it): ?>
                <tr>
                    <td>
                        <span class="tipo-badge <?php echo $it['tipo'] === 'peca' ? 'tipo-peca' : 'tipo-servico'; ?>">
                            <?php echo $it['tipo'] === 'peca' ? 'Peça' : 'Serviço'; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($it['descricao']); ?></td>
                    <td class="center"><?php echo number_format((float)$it['quantidade'], 2, ',', '.'); ?></td>
                    <td class="right">R$ <?php echo number_format((float)$it['valor_unitario'], 2, ',', '.'); ?></td>
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
            <div class="linha" style="color: #D62D2D;">
                <span>Desconto:</span>
                <strong>- R$ <?php echo number_format((float)$o['valor_desconto'], 2, ',', '.'); ?></strong>
            </div>
        <?php endif; ?>
        <div class="linha total">
            <span>TOTAL:</span>
            <strong>R$ <?php echo number_format((float)$o['valor_total'], 2, ',', '.'); ?></strong>
        </div>
    </div>

    <?php if (!empty($o['observacoes'])): ?>
        <div class="obs">
            <h4>Observações</h4>
            <?php echo nl2br(htmlspecialchars($o['observacoes'])); ?>
        </div>
    <?php endif; ?>

    <div class="validade">
        <strong>⏱ Validade deste orçamento:</strong> até <?php echo $data_validade; ?>
    </div>

    <?php
    // ===== LINK DE PAGAMENTO =====
    $url_pagamento = '';
    if (!empty($o['token_publico'])) {
        $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url_pagamento = $protocolo . '://' . $host . '/mtech_system/pagar.php?token=' . $o['token_publico'];
    }
    ?>

    <?php if (!empty($url_pagamento)): ?>
        <div
            style="background: #fff9e6; border: 2px solid #EBAF00; padding: 20px; border-radius: 8px; margin-top: 25px; text-align: center;">
            <h3 style="color: #D62D2D; font-size: 14px; margin-bottom: 10px;">💳 PAGUE ONLINE</h3>
            <p style="font-size: 12px; color: #666; margin-bottom: 12px;">
                Acesse o link abaixo para pagar via Pix ou Cartão:
            </p>
            <p
                style="font-family: monospace; font-size: 11px; color: #1a1a1a; word-break: break-all; background: #fff; padding: 10px; border-radius: 6px;">
                <?php echo htmlspecialchars($url_pagamento); ?>
            </p>
            <p style="font-size: 11px; color: #888; margin-top: 10px;">
                Ou pague presencialmente na oficina — aceitamos Pix, cartão, dinheiro e boleto.
            </p>
        </div>
    <?php endif; ?>

    <div class="footer">
        Este orçamento foi gerado em <?php echo date('d/m/Y \à\s H:i', strtotime($o['data_criacao'])); ?>.<br>
        OS de referência: <?php echo htmlspecialchars($o['numero_os']); ?>
    </div>

</body>

</html>