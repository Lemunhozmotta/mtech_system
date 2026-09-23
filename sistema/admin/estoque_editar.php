<?php
/* =========================================================
   M-TECH SYSTEM — ESTOQUE (editar)
   ========================================================= */

$titulo_pagina = 'Editar Item do Estoque';
require_once '_header.php';

$usuarioLogado = usuarioLogado();

if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    redirecionar('estoque.php?msg=sem_permissao');
}

$conn = conectar();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    $conn->close();
    redirecionar('estoque.php');
}

$stmt = $conn->prepare("SELECT * FROM estoque WHERE id_estoque = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    $conn->close();
    redirecionar('estoque.php');
}

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM estoque_movimentacoes WHERE id_estoque = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$total_mov = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$conn->close();
?>

<div class="admin-topo-pagina">
    <h1 class="admin-titulo-pagina">
        <a href="estoque.php" class="admin-voltar" title="Voltar"><i class="fas fa-arrow-left"></i></a>
        Editar Item
        <?php if (!$item['ativo']): ?>
            <span class="admin-badge admin-badge-erro" style="margin-left: 15px;">Inativo</span>
        <?php endif; ?>
    </h1>
</div>

<form action="estoque_acao.php" method="POST" class="admin-form" id="formEstoque">
    <input type="hidden" name="acao" value="editar">
    <input type="hidden" name="id_estoque" value="<?php echo (int)$item['id_estoque']; ?>">

    <div class="admin-bloco">
        <div class="admin-bloco-titulo"><span><i class="fas fa-box"></i> Identificação</span></div>
        <div class="admin-form-grid">
            <div class="admin-form-campo admin-form-campo-full">
                <label for="nome">Nome do item *</label>
                <input type="text" id="nome" name="nome" required maxlength="150"
                    value="<?php echo limpar($item['nome']); ?>">
            </div>
            <div class="admin-form-campo">
                <label for="codigo">Código interno</label>
                <input type="text" id="codigo" name="codigo" maxlength="50"
                    value="<?php echo limpar($item['codigo'] ?? ''); ?>">
            </div>
            <div class="admin-form-campo">
                <label for="codigo_barras">Código de barras</label>
                <input type="text" id="codigo_barras" name="codigo_barras" maxlength="50"
                    value="<?php echo limpar($item['codigo_barras'] ?? ''); ?>">
            </div>
            <div class="admin-form-campo admin-form-campo-combobox">
                <label for="campo_categoria">Categoria</label>
                <div class="admin-combobox" id="comboboxCategoria">
                    <input type="text" id="campo_categoria" placeholder="Digite ou clique na seta..." autocomplete="off"
                        value="<?php echo limpar($item['categoria'] ?? ''); ?>">
                    <input type="hidden" name="categoria" id="categoria_texto"
                        value="<?php echo limpar($item['categoria'] ?? ''); ?>">
                    <button type="button" class="admin-combobox-seta" id="setaCategoria" aria-label="Abrir lista">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="admin-combobox-lista" id="listaCategoria" style="display: none;"></div>
                </div>
            </div>
            <div class="admin-form-campo">
                <label for="marca">Marca</label>
                <input type="text" id="marca" name="marca" maxlength="80"
                    value="<?php echo limpar($item['marca'] ?? ''); ?>">
            </div>
        </div>
    </div>

    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-cubes"></i> Quantidade e Valores</span>
            <span><?php echo $total_mov; ?> movimentação(ões)</span>
        </div>

        <div class="admin-alerta admin-alerta-alerta" style="margin-bottom: 20px;">
            <i class="fas fa-info-circle"></i>
            <div>
                Pra alterar a quantidade, use o botão abaixo. <strong>Toda alteração é registrada no histórico.</strong>
            </div>
        </div>

        <div class="admin-form-grid">
            <div class="admin-form-campo">
                <label>Quantidade atual</label>
                <input type="text" value="<?php echo number_format((int)$item['quantidade'], 0, ',', '.'); ?>" disabled>
            </div>
            <div class="admin-form-campo">
                <label for="quantidade_minima">Quantidade mínima</label>
                <input type="number" id="quantidade_minima" name="quantidade_minima" min="0" step="1"
                    value="<?php echo (int)$item['quantidade_minima']; ?>">
            </div>
            <div class="admin-form-campo">
                <label for="valor_custo">Valor de custo (R$)</label>
                <input type="number" id="valor_custo" name="valor_custo" min="0" step="0.01"
                    value="<?php echo (float)$item['valor_custo']; ?>">
            </div>
            <div class="admin-form-campo">
                <label for="valor_venda">Valor de venda (R$)</label>
                <input type="number" id="valor_venda" name="valor_venda" min="0" step="0.01"
                    value="<?php echo (float)$item['valor_venda']; ?>">
            </div>
            <div class="admin-form-campo admin-form-campo-full">
                <label for="localizacao">Localização física</label>
                <input type="text" id="localizacao" name="localizacao" maxlength="100"
                    value="<?php echo limpar($item['localizacao'] ?? ''); ?>">
            </div>
        </div>

        <button type="button" class="admin-btn admin-btn-secundario" style="margin-top: 15px;"
            onclick="abrirModalAjuste()">
            <i class="fas fa-sliders-h"></i> Ajustar quantidade
        </button>
    </div>

    <div class="admin-bloco">
        <div class="admin-bloco-titulo"><span><i class="fas fa-comment-alt"></i> Observações</span></div>
        <div class="admin-form-grid">
            <div class="admin-form-campo admin-form-campo-full">
                <label for="observacoes">Observações</label>
                <textarea id="observacoes" name="observacoes"
                    rows="3"><?php echo limpar($item['observacoes'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>

    <div class="admin-form-acoes">
        <a href="estoque.php" class="admin-btn admin-btn-secundario"><i class="fas fa-times"></i> Cancelar</a>
        <button type="submit" class="admin-btn"><i class="fas fa-save"></i> Salvar Alterações</button>
    </div>
</form>

<!-- ===== MODAL: AJUSTAR QUANTIDADE ===== -->
<div class="admin-modal-fundo" id="modalAjusteFundo"></div>
<div class="admin-modal" id="modalAjuste">
    <div class="admin-modal-titulo"><i class="fas fa-sliders-h"></i> Ajustar Quantidade</div>
    <form action="estoque_acao.php" method="POST" class="admin-modal-form">
        <input type="hidden" name="acao" value="ajustar">
        <input type="hidden" name="id_estoque" value="<?php echo (int)$item['id_estoque']; ?>">

        <div class="admin-form-campo">
            <label>Quantidade atual</label>
            <input type="text" value="<?php echo number_format((int)$item['quantidade'], 0, ',', '.'); ?>" disabled>
        </div>

        <div class="admin-form-campo">
            <label for="tipo_ajuste">Tipo de ajuste *</label>
            <select id="tipo_ajuste" name="tipo_ajuste" required onchange="atualizarLabelAjuste()">
                <option value="entrada">Entrada (chegou peça)</option>
                <option value="saida">Saída (perda, uso interno, venda avulsa)</option>
                <option value="ajuste">Ajuste (corrigir inventário)</option>
                <option value="devolucao">Devolução (cliente devolveu)</option>
            </select>
        </div>

        <div class="admin-form-campo" id="campo_qtd_mov">
            <label for="quantidade_ajuste" id="label_qtd_ajuste">Quantidade a adicionar *</label>
            <input type="number" id="quantidade_ajuste" name="quantidade_ajuste" min="1" step="1" required>
        </div>

        <div class="admin-form-campo" id="campo_nova_qtd" style="display:none;">
            <label for="nova_quantidade">Nova quantidade (contagem física) *</label>
            <input type="number" id="nova_quantidade" name="nova_quantidade" min="0" step="1">
        </div>

        <div class="admin-form-campo">
            <label for="motivo_ajuste">Motivo *</label>
            <textarea id="motivo_ajuste" name="motivo" rows="3" required
                placeholder="Ex: Chegou pedido do fornecedor, inventário mensal, peça com defeito..."></textarea>
        </div>

        <div class="admin-modal-acoes">
            <button type="button" class="admin-btn admin-btn-secundario" onclick="fecharModalAjuste()">Cancelar</button>
            <button type="submit" class="admin-btn"><i class="fas fa-check"></i> Confirmar Ajuste</button>
        </div>
    </form>
</div>

<script>
    (function() {
        // =========================================================
        // COMBOBOX DE CATEGORIA
        // =========================================================
        const campoCategoria = document.getElementById('campo_categoria');
        const categoriaTexto = document.getElementById('categoria_texto');
        const listaCategoria = document.getElementById('listaCategoria');
        const setaCategoria = document.getElementById('setaCategoria');
        const wrapperCategoria = document.getElementById('comboboxCategoria');

        function abrirListaCategoria() {
            listaCategoria.style.display = 'block';
            wrapperCategoria.classList.add('aberto');
        }

        function fecharListaCategoria() {
            listaCategoria.style.display = 'none';
            wrapperCategoria.classList.remove('aberto');
            listaCategoria.querySelectorAll('.admin-combobox-item').forEach(i => i.classList.remove('selecionado'));
        }

        function navegar(itens, direcao) {
            if (itens.length === 0) return;
            let idx = Array.from(itens).findIndex(i => i.classList.contains('selecionado'));
            if (idx === -1) {
                idx = direcao === 'baixo' ? 0 : itens.length - 1;
            } else {
                itens.forEach(i => i.classList.remove('selecionado'));
                idx = direcao === 'baixo' ? (idx + 1) % itens.length : (idx <= 0 ? itens.length - 1 : idx - 1);
            }
            itens.forEach(i => i.classList.remove('selecionado'));
            itens[idx].classList.add('selecionado');
            itens[idx].scrollIntoView({
                block: 'nearest'
            });
        }

        function renderizarLista(itens) {
            if (!itens || itens.length === 0) {
                listaCategoria.innerHTML =
                    '<div class="admin-combobox-item" style="cursor:default; color:var(--mtech-text-muted);">Nenhuma categoria encontrada</div>';
                abrirListaCategoria();
                return;
            }
            listaCategoria.innerHTML = itens.map(i =>
                `<div class="admin-combobox-item" data-nome="${i.nome.replace(/"/g, '&quot;')}">${i.nome}</div>`
            ).join('');
            listaCategoria.querySelectorAll('.admin-combobox-item').forEach(item => {
                item.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    selecionarCategoria(item.dataset.nome);
                });
            });
            abrirListaCategoria();
        }

        function selecionarCategoria(nome) {
            campoCategoria.value = nome;
            categoriaTexto.value = nome;
            fecharListaCategoria();
        }

        async function buscarCategorias(termo) {
            try {
                const r = await fetch(`estoque_buscar_categorias.php?termo=${encodeURIComponent(termo)}`);
                const itens = await r.json();
                renderizarLista(itens);
            } catch (err) {
                console.error(err);
            }
        }

        let timerCategoria = null;

        campoCategoria.addEventListener('input', () => {
            const termo = campoCategoria.value.trim();
            categoriaTexto.value = termo;
            clearTimeout(timerCategoria);
            timerCategoria = setTimeout(() => buscarCategorias(termo), 200);
        });

        setaCategoria.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (listaCategoria.style.display === 'block') {
                fecharListaCategoria();
                return;
            }
            buscarCategorias(campoCategoria.value.trim());
        });

        campoCategoria.addEventListener('keydown', (e) => {
            const itens = listaCategoria.querySelectorAll('.admin-combobox-item');
            const aberto = listaCategoria.style.display === 'block';

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!aberto && itens.length > 0) abrirListaCategoria();
                navegar(itens, 'baixo');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!aberto && itens.length > 0) abrirListaCategoria();
                navegar(itens, 'cima');
            } else if (e.key === 'Enter') {
                const dest = listaCategoria.querySelector('.admin-combobox-item.selecionado');
                if (dest) {
                    e.preventDefault();
                    selecionarCategoria(dest.dataset.nome);
                }
            } else if (e.key === 'Escape') {
                if (aberto) {
                    e.preventDefault();
                    fecharListaCategoria();
                }
            } else if (e.key === 'Tab') {
                fecharListaCategoria();
            }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#comboboxCategoria')) {
                fecharListaCategoria();
            }
        });

        document.getElementById('formEstoque').addEventListener('submit', () => {
            if (campoCategoria.value.trim() !== '') {
                categoriaTexto.value = campoCategoria.value.trim();
            }
        });
    })();

    function abrirModalAjuste() {
        document.getElementById('modalAjuste').classList.add('ativo');
        document.getElementById('modalAjusteFundo').classList.add('ativo');
    }

    function fecharModalAjuste() {
        document.getElementById('modalAjuste').classList.remove('ativo');
        document.getElementById('modalAjusteFundo').classList.remove('ativo');
    }
    document.getElementById('modalAjusteFundo').addEventListener('click', fecharModalAjuste);

    function atualizarLabelAjuste() {
        const tipo = document.getElementById('tipo_ajuste').value;
        const campoQtdMov = document.getElementById('campo_qtd_mov');
        const labelQtd = document.getElementById('label_qtd_ajuste');
        const inputQtd = document.getElementById('quantidade_ajuste');
        const campoNova = document.getElementById('campo_nova_qtd');
        const inputNova = document.getElementById('nova_quantidade');

        if (tipo === 'ajuste') {
            campoQtdMov.style.display = 'none';
            inputQtd.required = false;
            inputQtd.value = '';
            campoNova.style.display = 'flex';
            inputNova.required = true;
        } else {
            campoQtdMov.style.display = 'flex';
            inputQtd.required = true;
            campoNova.style.display = 'none';
            inputNova.required = false;
            inputNova.value = '';
            if (tipo === 'entrada' || tipo === 'devolucao') labelQtd.textContent = 'Quantidade a adicionar *';
            else if (tipo === 'saida') labelQtd.textContent = 'Quantidade a retirar *';
        }
    }
</script>

<?php require_once '_footer.php'; ?>