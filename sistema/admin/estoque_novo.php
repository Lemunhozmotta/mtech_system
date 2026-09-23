<?php
/* =========================================================
   M-TECH SYSTEM — ESTOQUE (novo)
   ========================================================= */

$titulo_pagina = 'Novo Item no Estoque';
require_once '_header.php';

$usuarioLogado = usuarioLogado();

if (!in_array($usuarioLogado['nivel'], [1, 2])) {
    redirecionar('estoque.php?msg=sem_permissao');
}
?>

<div class="admin-topo-pagina">
    <h1 class="admin-titulo-pagina">
        <a href="estoque.php" class="admin-voltar" title="Voltar"><i class="fas fa-arrow-left"></i></a>
        Novo Item no Estoque
    </h1>
</div>

<form action="estoque_acao.php" method="POST" class="admin-form" id="formEstoque">
    <input type="hidden" name="acao" value="cadastrar">

    <div class="admin-bloco">
        <div class="admin-bloco-titulo"><span><i class="fas fa-box"></i> Identificação</span></div>
        <div class="admin-form-grid">
            <div class="admin-form-campo admin-form-campo-full">
                <label for="nome">Nome do item *</label>
                <input type="text" id="nome" name="nome" required maxlength="150"
                    placeholder="Ex: Pastilha de freio dianteira">
            </div>
            <div class="admin-form-campo">
                <label for="codigo">Código interno</label>
                <input type="text" id="codigo" name="codigo" maxlength="50" placeholder="Ex: PF-001">
            </div>
            <div class="admin-form-campo">
                <label for="codigo_barras">Código de barras</label>
                <input type="text" id="codigo_barras" name="codigo_barras" maxlength="50"
                    placeholder="EAN / código do fabricante">
            </div>
            <div class="admin-form-campo admin-form-campo-combobox">
                <label for="campo_categoria">Categoria</label>
                <div class="admin-combobox" id="comboboxCategoria">
                    <input type="text" id="campo_categoria" placeholder="Digite ou clique na seta..."
                        autocomplete="off">
                    <input type="hidden" name="categoria" id="categoria_texto">
                    <button type="button" class="admin-combobox-seta" id="setaCategoria" aria-label="Abrir lista">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="admin-combobox-lista" id="listaCategoria" style="display: none;"></div>
                </div>
            </div>
            <div class="admin-form-campo">
                <label for="marca">Marca</label>
                <input type="text" id="marca" name="marca" maxlength="80" placeholder="Ex: Bosch, Cofap...">
            </div>
        </div>
    </div>

    <div class="admin-bloco">
        <div class="admin-bloco-titulo"><span><i class="fas fa-cubes"></i> Quantidade e Valores</span></div>
        <div class="admin-form-grid">
            <div class="admin-form-campo">
                <label for="quantidade">Quantidade inicial *</label>
                <input type="number" id="quantidade" name="quantidade" min="0" step="1" value="0" required>
            </div>
            <div class="admin-form-campo">
                <label for="quantidade_minima">Quantidade mínima</label>
                <input type="number" id="quantidade_minima" name="quantidade_minima" min="0" step="1" value="0">
                <small class="admin-dica">Alerta quando o estoque ficar abaixo deste valor.</small>
            </div>
            <div class="admin-form-campo">
                <label for="valor_custo">Valor de custo (R$)</label>
                <input type="number" id="valor_custo" name="valor_custo" min="0" step="0.01" value="0.00">
            </div>
            <div class="admin-form-campo">
                <label for="valor_venda">Valor de venda (R$)</label>
                <input type="number" id="valor_venda" name="valor_venda" min="0" step="0.01" value="0.00">
            </div>
            <div class="admin-form-campo admin-form-campo-full">
                <label for="localizacao">Localização física</label>
                <input type="text" id="localizacao" name="localizacao" maxlength="100"
                    placeholder="Ex: Prateleira A3, Gaveta 12...">
            </div>
        </div>
    </div>

    <div class="admin-bloco">
        <div class="admin-bloco-titulo"><span><i class="fas fa-comment-alt"></i> Observações</span></div>
        <div class="admin-form-grid">
            <div class="admin-form-campo admin-form-campo-full">
                <label for="observacoes">Observações</label>
                <textarea id="observacoes" name="observacoes" rows="3"
                    placeholder="Fornecedor preferencial, prazo de entrega..."></textarea>
            </div>
        </div>
    </div>

    <div class="admin-form-acoes">
        <a href="estoque.php" class="admin-btn admin-btn-secundario"><i class="fas fa-times"></i> Cancelar</a>
        <button type="submit" class="admin-btn"><i class="fas fa-save"></i> Cadastrar Item</button>
    </div>
</form>

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

        // Garante que o valor foi pro hidden ao enviar
        document.getElementById('formEstoque').addEventListener('submit', () => {
            if (campoCategoria.value.trim() !== '') {
                categoriaTexto.value = campoCategoria.value.trim();
            }
        });
    })();
</script>

<?php require_once '_footer.php'; ?>