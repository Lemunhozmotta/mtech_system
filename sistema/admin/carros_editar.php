<?php
$titulo_pagina = 'Editar Carro';
require_once '_header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: carros.php');
    exit;
}

$conn = conectar();

$stmt = $conn->prepare("
    SELECT cr.*, cl.nome AS cliente_nome
    FROM carros cr
    INNER JOIN clientes cl ON cl.id_cliente = cr.id_cliente
    WHERE cr.id_carro = ?
    LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    header('Location: carros.php');
    exit;
}

$car = $result->fetch_assoc();
$stmt->close();

$res_clientes = $conn->query("SELECT id_cliente, nome, telefone, whatsapp FROM clientes WHERE ativo = 1 ORDER BY nome ASC");
$clientes = $res_clientes->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<div class="admin-topo-pagina">
    <h1 class="admin-titulo-pagina">
        <a href="carros.php" class="admin-voltar" title="Voltar">
            <i class="fas fa-arrow-left"></i>
        </a>
        Editar Carro
    </h1>
    <?php if (!$car['ativo']): ?>
        <span class="admin-badge admin-badge-erro" style="margin-left: 15px; vertical-align: middle;">Inativo</span>
    <?php endif; ?>
</div>

<form action="carros_acao.php" method="POST" class="admin-form" id="formCarro">
    <input type="hidden" name="acao" value="editar">
    <input type="hidden" name="id_carro" value="<?php echo (int)$car['id_carro']; ?>">

    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-user"></i> Cliente (dono do carro)</span>
        </div>
        <div class="admin-form-grid">
            <div class="admin-form-campo admin-form-campo-full">
                <label for="busca_cliente">Buscar cliente por nome *</label>
                <div class="admin-input-grupo" style="position: relative;">
                    <input type="text" id="busca_cliente" placeholder="Digite o nome do cliente..." autocomplete="off"
                        value="<?php echo limpar($car['cliente_nome']); ?>">
                    <input type="hidden" name="id_cliente" id="id_cliente"
                        value="<?php echo (int)$car['id_cliente']; ?>">
                    <div id="listaClientes" class="admin-autocomplete-lista" style="display: none;"></div>
                </div>
                <small class="admin-dica">Troque o cliente se o carro mudou de dono.</small>
            </div>
        </div>
    </div>

    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-car"></i> Dados do Veículo</span>
        </div>

        <div class="admin-form-grid">
            <div class="admin-form-campo admin-form-campo-combobox">
                <label for="campo_marca">Marca *</label>
                <div class="admin-combobox" id="comboboxMarca">
                    <input type="text" id="campo_marca" placeholder="Digite ou clique na seta..." autocomplete="off"
                        required value="<?php echo limpar($car['marca']); ?>">
                    <input type="hidden" name="id_marca" id="id_marca"
                        value="<?php echo $car['id_marca'] ? (int)$car['id_marca'] : ''; ?>">
                    <input type="hidden" name="marca" id="marca_texto" value="<?php echo limpar($car['marca']); ?>">
                    <button type="button" class="admin-combobox-seta" id="setaMarca" aria-label="Abrir lista">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="admin-combobox-lista" id="listaMarca" style="display: none;"></div>
                </div>
            </div>

            <div class="admin-form-campo admin-form-campo-combobox">
                <label for="campo_modelo">Modelo *</label>
                <div class="admin-combobox" id="comboboxModelo">
                    <input type="text" id="campo_modelo" placeholder="Digite o modelo..." autocomplete="off" required
                        value="<?php echo limpar($car['modelo']); ?>" <?php echo $car['id_marca'] ? '' : 'disabled'; ?>>
                    <input type="hidden" name="id_modelo" id="id_modelo"
                        value="<?php echo $car['id_modelo'] ? (int)$car['id_modelo'] : ''; ?>">
                    <input type="hidden" name="modelo" id="modelo_texto" value="<?php echo limpar($car['modelo']); ?>">
                    <button type="button" class="admin-combobox-seta" id="setaModelo" aria-label="Abrir lista">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="admin-combobox-lista" id="listaModelo" style="display: none;"></div>
                </div>
            </div>

            <div class="admin-form-campo">
                <label for="ano">Ano</label>
                <input type="text" id="ano" name="ano" placeholder="Ex: 2018 ou 2018/2019"
                    value="<?php echo limpar($car['ano'] ?? ''); ?>" maxlength="10">
            </div>

            <div class="admin-form-campo">
                <label for="placa">Placa *</label>
                <input type="text" id="placa" name="placa" placeholder="ABC-1D23 ou ABC1234"
                    value="<?php echo limpar($car['placa']); ?>" required maxlength="10"
                    style="text-transform: uppercase;">
            </div>

            <div class="admin-form-campo">
                <label for="cor">Cor</label>
                <input type="text" id="cor" name="cor" placeholder="Ex: Prata, Preto, Branco..."
                    value="<?php echo limpar($car['cor'] ?? ''); ?>" maxlength="50">
            </div>

            <div class="admin-form-campo">
                <label for="km_atual">KM atual</label>
                <input type="number" id="km_atual" name="km_atual" placeholder="Ex: 85000" min="0" max="9999999"
                    step="1" value="<?php echo $car['km_atual'] !== null ? (int)$car['km_atual'] : ''; ?>">
            </div>
        </div>
    </div>

    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-comment-alt"></i> Observações</span>
        </div>
        <div class="admin-form-grid">
            <div class="admin-form-campo admin-form-campo-full">
                <label for="observacoes">Observações / Avarias do veículo</label>
                <textarea id="observacoes" name="observacoes" rows="4"
                    placeholder="Preencha este campo dizendo se o veículo tem alguma avaria e quais são, tanto externa como internamente!"></textarea>
            </div>
        </div>
    </div>

    <div class="admin-form-acoes">
        <a href="carros.php" class="admin-btn admin-btn-secundario">
            <i class="fas fa-times"></i> Cancelar
        </a>
        <button type="submit" class="admin-btn">
            <i class="fas fa-save"></i> Salvar Alterações
        </button>
    </div>
</form>

<script>
    (function() {
        // =========================================================
        // AUTOCOMPLETE DE CLIENTE
        // =========================================================
        const clientes =
            <?php echo json_encode($clientes ?: [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const campoBusca = document.getElementById('busca_cliente');
        const campoIdCliente = document.getElementById('id_cliente');
        const listaClientes = document.getElementById('listaClientes');

        function normalizar(t) {
            return t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
        }

        function mostrarClientes(termo) {
            const t = normalizar(termo);
            const filtrados = clientes.filter(c => normalizar(c.nome).includes(t)).slice(0, 8);

            if (filtrados.length === 0) {
                listaClientes.style.display = 'none';
                return;
            }

            listaClientes.innerHTML = filtrados.map(c => {
                const tel = c.whatsapp || c.telefone || '';
                return `<div class="admin-autocomplete-item" data-id="${c.id_cliente}" data-nome="${c.nome.replace(/"/g, '&quot;')}">
                <strong>${c.nome}</strong>${tel ? '<small>' + tel + '</small>' : ''}
            </div>`;
            }).join('');

            listaClientes.style.display = 'block';

            listaClientes.querySelectorAll('.admin-autocomplete-item').forEach(item => {
                item.addEventListener('click', () => {
                    campoBusca.value = item.dataset.nome;
                    campoIdCliente.value = item.dataset.id;
                    listaClientes.style.display = 'none';
                });
            });
        }

        campoBusca.addEventListener('input', () => {
            const termo = campoBusca.value.trim();
            if (campoIdCliente.value) campoIdCliente.value = '';
            if (termo.length < 2) {
                listaClientes.style.display = 'none';
                return;
            }
            mostrarClientes(termo);
        });

        campoBusca.addEventListener('blur', () => {
            setTimeout(() => {
                listaClientes.style.display = 'none';
            }, 200);
        });

        campoBusca.addEventListener('focus', () => {
            if (campoBusca.value.trim().length >= 2) mostrarClientes(campoBusca.value.trim());
        });

        // =========================================================
        // COMBOBOX — MARCA
        // =========================================================
        const campoMarca = document.getElementById('campo_marca');
        const idMarca = document.getElementById('id_marca');
        const marcaTexto = document.getElementById('marca_texto');
        const listaMarca = document.getElementById('listaMarca');
        const setaMarca = document.getElementById('setaMarca');
        const wrapperMarca = document.getElementById('comboboxMarca');

        // =========================================================
        // COMBOBOX — MODELO
        // =========================================================
        const campoModelo = document.getElementById('campo_modelo');
        const idModelo = document.getElementById('id_modelo');
        const modeloTexto = document.getElementById('modelo_texto');
        const listaModelo = document.getElementById('listaModelo');
        const setaModelo = document.getElementById('setaModelo');
        const wrapperModelo = document.getElementById('comboboxModelo');

        // =========================================================
        // HELPERS
        // =========================================================
        function abrirListaMarca() {
            listaMarca.style.display = 'block';
            wrapperMarca.classList.add('aberto');
        }

        function fecharListaMarca() {
            listaMarca.style.display = 'none';
            wrapperMarca.classList.remove('aberto');
            listaMarca.querySelectorAll('.admin-combobox-item').forEach(i => i.classList.remove('selecionado'));
        }

        function abrirListaModelo() {
            listaModelo.style.display = 'block';
            wrapperModelo.classList.add('aberto');
        }

        function fecharListaModelo() {
            listaModelo.style.display = 'none';
            wrapperModelo.classList.remove('aberto');
            listaModelo.querySelectorAll('.admin-combobox-item').forEach(i => i.classList.remove('selecionado'));
        }

        function limparModelo() {
            campoModelo.value = '';
            idModelo.value = '';
            modeloTexto.value = '';
            fecharListaModelo();
        }

        // =========================================================
        // NAVEGAÇÃO POR TECLADO (GLOBAL)
        // =========================================================
        function navegar(itens, direcao) {
            if (itens.length === 0) return;

            let idx = Array.from(itens).findIndex(i => i.classList.contains('selecionado'));

            if (idx === -1) {
                idx = direcao === 'baixo' ? 0 : itens.length - 1;
            } else {
                itens.forEach(i => i.classList.remove('selecionado'));
                if (direcao === 'baixo') {
                    idx = (idx + 1) % itens.length;
                } else {
                    idx = idx <= 0 ? itens.length - 1 : idx - 1;
                }
            }

            itens.forEach(i => i.classList.remove('selecionado'));
            itens[idx].classList.add('selecionado');
            itens[idx].scrollIntoView({
                block: 'nearest'
            });
        }

        // =========================================================
        // RENDERIZAR
        // =========================================================
        function renderizarListaMarca(itens) {
            if (!itens || itens.length === 0) {
                fecharListaMarca();
                return;
            }

            listaMarca.innerHTML = itens.map(i =>
                `<div class="admin-combobox-item" data-id="${i.id}" data-nome="${i.nome.replace(/"/g, '&quot;')}">${i.nome}</div>`
            ).join('');

            listaMarca.querySelectorAll('.admin-combobox-item').forEach(item => {
                item.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    selecionarMarca(item.dataset.id, item.dataset.nome);
                });
            });

            abrirListaMarca();
        }

        function renderizarListaModelo(itens) {
            if (!itens || itens.length === 0) {
                fecharListaModelo();
                return;
            }

            listaModelo.innerHTML = itens.map(i =>
                `<div class="admin-combobox-item" data-id="${i.id}" data-nome="${i.nome.replace(/"/g, '&quot;')}">${i.nome}</div>`
            ).join('');

            listaModelo.querySelectorAll('.admin-combobox-item').forEach(item => {
                item.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    selecionarModelo(item.dataset.id, item.dataset.nome);
                });
            });

            abrirListaModelo();
        }

        // =========================================================
        // SELECIONAR
        // =========================================================
        function selecionarMarca(id, nome) {
            campoMarca.value = nome;
            idMarca.value = id;
            marcaTexto.value = nome;

            fecharListaMarca();

            campoModelo.disabled = false;
            campoModelo.placeholder = 'Digite o modelo...';

            limparModelo();
        }

        function selecionarModelo(id, nome) {
            campoModelo.value = nome;
            idModelo.value = id;
            modeloTexto.value = nome;

            fecharListaModelo();
        }

        // =========================================================
        // BUSCA NO SERVIDOR
        // =========================================================
        async function buscarMarcas(termo) {
            try {
                const r = await fetch(`carros_buscar_marcas.php?termo=${encodeURIComponent(termo)}`);
                const itens = await r.json();
                renderizarListaMarca(itens);
            } catch (err) {
                console.error(err);
            }
        }

        async function buscarModelos(termo) {
            const idM = idMarca.value;
            if (idM === '') {
                fecharListaModelo();
                return;
            }
            try {
                const r = await fetch(
                    `carros_buscar_modelos.php?id_marca=${idM}&termo=${encodeURIComponent(termo)}`);
                const itens = await r.json();
                renderizarListaModelo(itens);
            } catch (err) {
                console.error(err);
            }
        }

        // =========================================================
        // MARCA — INPUT
        // =========================================================
        let timerMarca = null;

        campoMarca.addEventListener('input', () => {
            const termo = campoMarca.value.trim();

            idMarca.value = '';
            marcaTexto.value = '';
            limparModelo();

            if (termo === '') {
                fecharListaMarca();
                return;
            }

            clearTimeout(timerMarca);
            timerMarca = setTimeout(() => buscarMarcas(termo), 200);
        });

        // =========================================================
        // MARCA — CLIQUE NA SETINHA
        // =========================================================
        setaMarca.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (listaMarca.style.display === 'block') {
                fecharListaMarca();
                return;
            }

            buscarMarcas(campoMarca.value.trim());
        });

        // =========================================================
        // MARCA — TECLADO
        // =========================================================
        campoMarca.addEventListener('keydown', (e) => {
            const itens = listaMarca.querySelectorAll('.admin-combobox-item');
            const aberto = listaMarca.style.display === 'block';

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!aberto && itens.length > 0) abrirListaMarca();
                navegar(itens, 'baixo');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!aberto && itens.length > 0) abrirListaMarca();
                navegar(itens, 'cima');
            } else if (e.key === 'Enter') {
                const dest = listaMarca.querySelector('.admin-combobox-item.selecionado');
                if (dest) {
                    e.preventDefault();
                    selecionarMarca(dest.dataset.id, dest.dataset.nome);
                }
            } else if (e.key === 'Escape') {
                if (aberto) {
                    e.preventDefault();
                    fecharListaMarca();
                }
            } else if (e.key === 'Tab') {
                fecharListaMarca();
            }
        });

        // =========================================================
        // TECLADO GLOBAL (funciona sem foco no input)
        // =========================================================
        document.addEventListener('keydown', (e) => {
            const abertoMarca = listaMarca.style.display === 'block';
            const abertoModelo = listaModelo.style.display === 'block';

            if (abertoMarca && document.activeElement !== campoMarca) {
                const itens = listaMarca.querySelectorAll('.admin-combobox-item');

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    navegar(itens, 'baixo');
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    navegar(itens, 'cima');
                } else if (e.key === 'Enter') {
                    const dest = listaMarca.querySelector('.admin-combobox-item.selecionado');
                    if (dest) {
                        e.preventDefault();
                        selecionarMarca(dest.dataset.id, dest.dataset.nome);
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    fecharListaMarca();
                }
            }

            if (abertoModelo && document.activeElement !== campoModelo) {
                const itens = listaModelo.querySelectorAll('.admin-combobox-item');

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    navegar(itens, 'baixo');
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    navegar(itens, 'cima');
                } else if (e.key === 'Enter') {
                    const dest = listaModelo.querySelector('.admin-combobox-item.selecionado');
                    if (dest) {
                        e.preventDefault();
                        selecionarModelo(dest.dataset.id, dest.dataset.nome);
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    fecharListaModelo();
                }
            }
        });

        // =========================================================
        // MODELO — INPUT
        // =========================================================
        let timerModelo = null;

        campoModelo.addEventListener('input', () => {
            const termo = campoModelo.value.trim();

            idModelo.value = '';
            modeloTexto.value = '';

            if (termo === '') {
                fecharListaModelo();
                return;
            }

            clearTimeout(timerModelo);
            timerModelo = setTimeout(() => buscarModelos(termo), 200);
        });

        // =========================================================
        // MODELO — CLIQUE NA SETINHA
        // =========================================================
        setaModelo.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (idMarca.value === '') {
                alert('Escolha a marca primeiro.');
                campoMarca.focus();
                return;
            }

            if (listaModelo.style.display === 'block') {
                fecharListaModelo();
                return;
            }

            buscarModelos(campoModelo.value.trim());
        });

        // =========================================================
        // MODELO — TECLADO
        // =========================================================
        campoModelo.addEventListener('keydown', (e) => {
            const itens = listaModelo.querySelectorAll('.admin-combobox-item');
            const aberto = listaModelo.style.display === 'block';

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!aberto && itens.length > 0) abrirListaModelo();
                navegar(itens, 'baixo');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!aberto && itens.length > 0) abrirListaModelo();
                navegar(itens, 'cima');
            } else if (e.key === 'Enter') {
                const dest = listaModelo.querySelector('.admin-combobox-item.selecionado');
                if (dest) {
                    e.preventDefault();
                    selecionarModelo(dest.dataset.id, dest.dataset.nome);
                }
            } else if (e.key === 'Escape') {
                if (aberto) {
                    e.preventDefault();
                    fecharListaModelo();
                }
            } else if (e.key === 'Tab') {
                fecharListaModelo();
            }
        });

        // =========================================================
        // FECHAR AO CLICAR FORA
        // =========================================================
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.admin-combobox')) {
                fecharListaMarca();
                fecharListaModelo();
            }
        });

        // =========================================================
        // VALIDAÇÃO NO SUBMIT
        // =========================================================
        document.getElementById('formCarro').addEventListener('submit', (e) => {
            if (!campoIdCliente.value) {
                e.preventDefault();
                alert('Selecione um cliente da lista.');
                campoBusca.focus();
                return;
            }

            if (campoMarca.value.trim() !== '') marcaTexto.value = campoMarca.value.trim();
            if (campoModelo.value.trim() !== '') modeloTexto.value = campoModelo.value.trim();
        });
    })();
</script>

<?php require_once '_footer.php'; ?>