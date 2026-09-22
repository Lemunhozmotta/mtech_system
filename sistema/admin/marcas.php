<?php
/* =========================================================
   M-TECH SYSTEM — MARCAS E MODELOS
   ========================================================= */

$titulo_pagina = 'Marcas e Modelos';
require_once '_header.php';

// ===== SÓ NÍVEIS 1 E 2 =====
$usuarioLogado = usuarioLogado();
if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    header('Location: dashboard.php?msg=sem_permissao');
    exit;
}

$conn = conectar();

// ===== PARÂMETROS =====
$id_marca_sel = (int)($_GET['id_marca'] ?? 0);

// ===== BUSCA AS MARCAS =====
$sql = "SELECT m.id_marca, m.nome,
               (SELECT COUNT(*) FROM modelos WHERE id_marca = m.id_marca) AS qtd_modelos,
               (SELECT COUNT(*) FROM carros WHERE id_marca = m.id_marca) AS qtd_carros
        FROM marcas m
        ORDER BY m.nome ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$marcas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== SE NÃO TEM MARCA SELECIONADA, PEGA A PRIMEIRA =====
if ($id_marca_sel <= 0 && !empty($marcas)) {
    $id_marca_sel = (int)$marcas[0]['id_marca'];
}

// ===== BUSCA O NOME DA MARCA SELECIONADA =====
$nome_marca_sel = '';
foreach ($marcas as $m) {
    if ((int)$m['id_marca'] === $id_marca_sel) {
        $nome_marca_sel = $m['nome'];
        break;
    }
}

// ===== BUSCA OS MODELOS DA MARCA SELECIONADA =====
$modelos = [];
if ($id_marca_sel > 0) {
    $stmt = $conn->prepare("
        SELECT mo.id_modelo, mo.nome,
               (SELECT COUNT(*) FROM carros WHERE id_modelo = mo.id_modelo) AS qtd_carros
        FROM modelos mo
        WHERE mo.id_marca = ?
        ORDER BY mo.nome ASC
    ");
    $stmt->bind_param('i', $id_marca_sel);
    $stmt->execute();
    $modelos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();
?>

<h1 class="admin-titulo-pagina">Marcas e Modelos</h1>

<?php
$msg = $_GET['msg'] ?? '';
$mensagens = [
    'marca_cadastrada'    => ['texto' => 'Marca cadastrada com sucesso!', 'tipo' => 'sucesso'],
    'marca_editada'       => ['texto' => 'Marca atualizada com sucesso!', 'tipo' => 'sucesso'],
    'modelo_cadastrado'   => ['texto' => 'Modelo cadastrado com sucesso!', 'tipo' => 'sucesso'],
    'modelo_editado'      => ['texto' => 'Modelo atualizado com sucesso!', 'tipo' => 'sucesso'],
    'erro'                => ['texto' => 'Ocorreu um erro. Tente novamente.', 'tipo' => 'erro'],
    'erro_nome'           => ['texto' => 'O nome é obrigatório.', 'tipo' => 'erro'],
    'erro_duplicado'      => ['texto' => 'Já existe um registro com esse nome.', 'tipo' => 'erro'],
    'erro_marca'          => ['texto' => 'Marca inválida.', 'tipo' => 'erro'],
    'sem_permissao'       => ['texto' => 'Sem permissão.', 'tipo' => 'erro'],
];

if (!empty($msg) && isset($mensagens[$msg])):
    $m = $mensagens[$msg];
    $icone = $m['tipo'] === 'sucesso' ? 'check-circle' : 'times-circle';
?>
    <div class="admin-alerta admin-alerta-<?php echo $m['tipo']; ?>">
        <i class="fas fa-<?php echo $icone; ?>"></i>
        <?php echo $m['texto']; ?>
    </div>
<?php endif; ?>

<div class="admin-alerta admin-alerta-alerta" style="margin-bottom: 25px;">
    <i class="fas fa-info-circle"></i>
    Use esta tela pra cadastrar as marcas e os modelos que aparecem no formulário de carro.
</div>

<div class="admin-marcas-wrapper">

    <!-- ===== MARCAS ===== -->
    <div class="admin-bloco">

        <div class="admin-bloco-titulo">
            <span><i class="fas fa-tag"></i> Marcas</span>
            <span id="contadorMarcas"><?php echo count($marcas); ?></span>
        </div>

        <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
            <div class="admin-busca" style="flex: 1; min-width: 150px;">
                <i class="fas fa-search"></i>
                <input type="text" id="buscaMarca" placeholder="Buscar marca..." autocomplete="off">
            </div>
            <button type="button" class="admin-btn admin-btn-novo" onclick="abrirModalNovaMarca()">
                <i class="fas fa-plus"></i> Nova
            </button>
        </div>

        <?php if (empty($marcas)): ?>
            <div class="admin-vazio" style="padding: 30px 15px;">
                <i class="fas fa-tag"></i>
                <p>Nenhuma marca encontrada.</p>
            </div>
        <?php else: ?>
            <ul class="admin-lista-marcas" id="listaMarcas">
                <?php foreach ($marcas as $mar): ?>
                    <?php $id_m = (int)$mar['id_marca']; ?>
                    <li class="<?php echo $id_m === $id_marca_sel ? 'ativa' : ''; ?>"
                        data-nome="<?php echo limpar(mb_strtolower($mar['nome'], 'UTF-8')); ?>">
                        <a href="marcas.php?id_marca=<?php echo $id_m; ?>">
                            <span class="admin-lista-marca-nome"><?php echo limpar($mar['nome']); ?></span>
                            <span class="admin-lista-marca-info">
                                <span class="admin-badge admin-badge-info"><?php echo (int)$mar['qtd_modelos']; ?></span>
                                <?php if ((int)$mar['qtd_carros'] > 0): ?>
                                    <span class="admin-badge admin-badge-sucesso">
                                        <i class="fas fa-car" style="font-size: 9px;"></i> <?php echo (int)$mar['qtd_carros']; ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </a>
                        <button type="button" class="admin-btn-acao" title="Editar marca"
                            onclick="abrirModalEditarMarca(<?php echo $id_m; ?>, '<?php echo addslashes(limpar($mar['nome'])); ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div id="semResultadoMarca" class="admin-vazio" style="display: none; padding: 20px 15px;">
                <i class="fas fa-search"></i>
                <p>Nenhuma marca encontrada.</p>
            </div>
        <?php endif; ?>

    </div>

    <!-- ===== MODELOS ===== -->
    <div class="admin-bloco">

        <div class="admin-bloco-titulo">
            <span>
                <i class="fas fa-cogs"></i>
                Modelos de <strong style="color: var(--mtech-yellow);"><?php echo limpar($nome_marca_sel); ?></strong>
            </span>
            <span><?php echo count($modelos); ?></span>
        </div>

        <div style="margin-bottom: 15px; text-align: right;">
            <button type="button" class="admin-btn admin-btn-novo" onclick="abrirModalNovoModelo()">
                <i class="fas fa-plus"></i> Novo Modelo
            </button>
        </div>

        <?php if (empty($modelos)): ?>
            <div class="admin-vazio" style="padding: 30px 15px;">
                <i class="fas fa-cogs"></i>
                <p>Nenhum modelo cadastrado.</p>
                <small>Clique em "Novo Modelo" pra começar.</small>
            </div>
        <?php else: ?>
            <ul class="admin-lista-modelos">
                <?php foreach ($modelos as $mod): ?>
                    <li>
                        <span class="admin-lista-modelo-nome"><?php echo limpar($mod['nome']); ?></span>
                        <span class="admin-lista-modelo-info">
                            <?php if ((int)$mod['qtd_carros'] > 0): ?>
                                <span class="admin-badge admin-badge-sucesso">
                                    <i class="fas fa-car" style="font-size: 9px;"></i> <?php echo (int)$mod['qtd_carros']; ?>
                                </span>
                            <?php endif; ?>
                            <button type="button" class="admin-btn-acao" title="Editar modelo"
                                onclick="abrirModalEditarModelo(<?php echo (int)$mod['id_modelo']; ?>, '<?php echo addslashes(limpar($mod['nome'])); ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

    </div>

</div>

<!-- ===== MODAIS ===== -->
<div class="admin-modal-fundo" id="modalFundo" onclick="fecharModal(event)"></div>

<div class="admin-modal" id="modalMarca">
    <div class="admin-modal-titulo">
        <i class="fas fa-tag"></i>
        <span id="modalMarcaTitulo">Nova Marca</span>
    </div>
    <form action="marcas_acao.php" method="POST" class="admin-modal-form">
        <input type="hidden" name="acao" id="modalMarcaAcao" value="cadastrar_marca">
        <input type="hidden" name="id_marca" id="modalMarcaId" value="">
        <div class="admin-form-campo">
            <label for="modalMarcaNome">Nome da marca *</label>
            <input type="text" id="modalMarcaNome" name="nome" placeholder="Ex: Volkswagen" required maxlength="80"
                autocomplete="off">
        </div>
        <div class="admin-modal-acoes">
            <button type="button" class="admin-btn admin-btn-secundario" onclick="fecharModal()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" class="admin-btn">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </form>
</div>

<div class="admin-modal" id="modalModelo">
    <div class="admin-modal-titulo">
        <i class="fas fa-cogs"></i>
        <span id="modalModeloTitulo">Novo Modelo</span>
    </div>
    <form action="marcas_acao.php" method="POST" class="admin-modal-form">
        <input type="hidden" name="acao" id="modalModeloAcao" value="cadastrar_modelo">
        <input type="hidden" name="id_modelo" id="modalModeloId" value="">
        <input type="hidden" name="id_marca" value="<?php echo (int)$id_marca_sel; ?>">
        <div class="admin-form-campo">
            <label>Marca</label>
            <input type="text" value="<?php echo limpar($nome_marca_sel); ?>" disabled>
        </div>
        <div class="admin-form-campo">
            <label for="modalModeloNome">Nome do modelo *</label>
            <input type="text" id="modalModeloNome" name="nome" placeholder="Ex: Gol" required maxlength="100"
                autocomplete="off">
        </div>
        <div class="admin-modal-acoes">
            <button type="button" class="admin-btn admin-btn-secundario" onclick="fecharModal()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" class="admin-btn">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </form>
</div>

<script>
    console.log('=== SCRIPT MARCAS.PHP CARREGADO ===');

    (function() {
        console.log('IIFE rodando');

        // ===== BUSCA EM TEMPO REAL =====
        const inputBusca = document.getElementById('buscaMarca');
        const listaMarcas = document.getElementById('listaMarcas');
        const semResultado = document.getElementById('semResultadoMarca');
        const contador = document.getElementById('contadorMarcas');

        console.log('inputBusca:', inputBusca);
        console.log('listaMarcas:', listaMarcas);

        function normalizar(texto) {
            return texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
        }

        if (inputBusca && listaMarcas) {
            inputBusca.addEventListener('input', () => {
                const termo = normalizar(inputBusca.value);
                const itens = listaMarcas.querySelectorAll('li');
                let visiveis = 0;

                itens.forEach(li => {
                    const nome = normalizar(li.dataset.nome || '');
                    if (termo === '' || nome.includes(termo)) {
                        li.style.display = '';
                        visiveis++;
                    } else {
                        li.style.display = 'none';
                    }
                });

                if (semResultado) semResultado.style.display = visiveis === 0 ? 'block' : 'none';
                if (contador) contador.textContent = visiveis;
            });
        }

        // ===== MODAIS =====
        const modalFundo = document.getElementById('modalFundo');
        const modalMarca = document.getElementById('modalMarca');
        const modalModelo = document.getElementById('modalModelo');

        window.fecharModal = function(e) {
            if (e && e.target !== modalFundo) return;
            modalFundo.classList.remove('ativo');
            modalMarca.classList.remove('ativo');
            modalModelo.classList.remove('ativo');
            document.getElementById('modalMarcaNome').value = '';
            document.getElementById('modalModeloNome').value = '';
        };

        window.abrirModalNovaMarca = function() {
            document.getElementById('modalMarcaTitulo').textContent = 'Nova Marca';
            document.getElementById('modalMarcaAcao').value = 'cadastrar_marca';
            document.getElementById('modalMarcaId').value = '';
            document.getElementById('modalMarcaNome').value = '';
            modalFundo.classList.add('ativo');
            modalMarca.classList.add('ativo');
            setTimeout(() => document.getElementById('modalMarcaNome').focus(), 100);
        };

        window.abrirModalEditarMarca = function(id, nome) {
            document.getElementById('modalMarcaTitulo').textContent = 'Editar Marca';
            document.getElementById('modalMarcaAcao').value = 'editar_marca';
            document.getElementById('modalMarcaId').value = id;
            document.getElementById('modalMarcaNome').value = nome;
            modalFundo.classList.add('ativo');
            modalMarca.classList.add('ativo');
            setTimeout(() => document.getElementById('modalMarcaNome').focus(), 100);
        };

        window.abrirModalNovoModelo = function() {
            document.getElementById('modalModeloTitulo').textContent = 'Novo Modelo';
            document.getElementById('modalModeloAcao').value = 'cadastrar_modelo';
            document.getElementById('modalModeloId').value = '';
            document.getElementById('modalModeloNome').value = '';
            modalFundo.classList.add('ativo');
            modalModelo.classList.add('ativo');
            setTimeout(() => document.getElementById('modalModeloNome').focus(), 100);
        };

        window.abrirModalEditarModelo = function(id, nome) {
            document.getElementById('modalModeloTitulo').textContent = 'Editar Modelo';
            document.getElementById('modalModeloAcao').value = 'editar_modelo';
            document.getElementById('modalModeloId').value = id;
            document.getElementById('modalModeloNome').value = nome;
            modalFundo.classList.add('ativo');
            modalModelo.classList.add('ativo');
            setTimeout(() => document.getElementById('modalModeloNome').focus(), 100);
        };

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') fecharModal();
        });

        console.log('Script configurado com sucesso');
    })();
</script>

<?php require_once '_footer.php'; ?>