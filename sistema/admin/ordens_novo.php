<?php
/* =========================================================
   M-TECH SYSTEM — ORDENS DE SERVIÇO (nova)
   - Níveis 1, 2, 3, 4 abrem OS
   - Nível 3 (mecânico): abre OS aberta, ele se aponta depois
   - Níveis 1, 2, 4: escolhem o mecânico responsável
   ========================================================= */

$titulo_pagina = 'Nova OS';
require_once '_header.php';

$usuarioLogado = usuarioLogado();
$conn = conectar();

// ===== PERMISSÃO: RH (5) NÃO ABRE OS =====
$nivel = (int)$usuarioLogado['nivel'];
if (!in_array($nivel, [1, 2, 3, 4])) {
    $conn->close();
    redirecionar('ordens.php?msg=sem_permissao');
}

// ===== MECÂNICO (3) NÃO ESCOLHE RESPONSÁVEL =====
$souMecanico = ($nivel === 3);

// ===== PRÉ-SELEÇÃO POR id_carro (vindo da ficha do carro) =====
$id_carro_pre = (int)($_GET['id_carro'] ?? 0);
$carro_pre = null;
$cliente_pre_nome = '';

if ($id_carro_pre > 0) {
    $stmt = $conn->prepare("
        SELECT cr.*, cl.nome AS cliente_nome
        FROM carros cr
        INNER JOIN clientes cl ON cl.id_cliente = cr.id_cliente
        WHERE cr.id_carro = ? AND cr.ativo = 1 AND cl.ativo = 1
        LIMIT 1
    ");
    $stmt->bind_param('i', $id_carro_pre);
    $stmt->execute();
    $carro_pre = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($carro_pre) {
        $cliente_pre_nome = $carro_pre['cliente_nome'];
    } else {
        $id_carro_pre = 0;
    }
}

// ===== CARROS DISPONÍVEIS =====
$sql_carros = "SELECT cr.id_carro, cr.marca, cr.modelo, cr.placa, cr.ano,
                      cl.nome AS cliente_nome, cl.id_cliente
               FROM carros cr
               INNER JOIN clientes cl ON cl.id_cliente = cr.id_cliente
               WHERE cr.ativo = 1 AND cl.ativo = 1
               ORDER BY cl.nome ASC, cr.marca ASC";
$res_carros = $conn->query($sql_carros);
$carros = $res_carros->fetch_all(MYSQLI_ASSOC);

// ===== MECÂNICOS (só pros níveis 1, 2, 4) =====
$mecanicos = [];
if (!$souMecanico) {
    $res_mec = $conn->query("SELECT id_usuario, nome FROM usuarios WHERE ativo = 1 AND nivel = 3 ORDER BY nome ASC");
    $mecanicos = $res_mec->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>

<div class="admin-topo-pagina">
    <h1 class="admin-titulo-pagina">
        <a href="ordens.php" class="admin-voltar" title="Voltar">
            <i class="fas fa-arrow-left"></i>
        </a>
        Nova OS
    </h1>
</div>

<form action="ordens_acao.php" method="POST" class="admin-form" id="formOS">
    <input type="hidden" name="acao" value="cadastrar">

    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-car"></i> Cliente e Veículo</span>
        </div>

        <div class="admin-form-grid">
            <div class="admin-form-campo admin-form-campo-full">
                <label for="busca_carro">Buscar veículo *</label>
                <div class="admin-input-grupo" style="position: relative;">
                    <input type="text" id="busca_carro" placeholder="Digite placa, modelo ou nome do cliente..."
                        autocomplete="off" <?php echo $id_carro_pre > 0 ? 'disabled' : ''; ?>
                        value="<?php echo $carro_pre ? limpar($carro_pre['marca'] . ' ' . $carro_pre['modelo'] . ' (' . strtoupper($carro_pre['placa']) . ') — ' . $cliente_pre_nome) : ''; ?>">
                    <input type="hidden" name="id_carro" id="id_carro"
                        value="<?php echo $id_carro_pre > 0 ? (int)$id_carro_pre : ''; ?>">
                    <input type="hidden" name="id_cliente" id="id_cliente"
                        value="<?php echo $carro_pre ? (int)$carro_pre['id_cliente'] : ''; ?>">

                    <?php if ($id_carro_pre > 0): ?>
                    <a href="ordens_novo.php" class="admin-btn-icone" title="Trocar veículo">
                        <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>

                    <div id="listaCarros" class="admin-autocomplete-lista" style="display: none;"></div>
                </div>
                <small class="admin-dica">Digite pelo menos 2 letras da placa, modelo ou nome do cliente.</small>
            </div>

            <?php if (!$souMecanico): ?>
            <div class="admin-form-campo">
                <label for="id_mecanico">Mecânico responsável</label>
                <select id="id_mecanico" name="id_mecanico">
                    <option value="">— A definir —</option>
                    <?php foreach ($mecanicos as $mec): ?>
                    <option value="<?php echo (int)$mec['id_usuario']; ?>">
                        <?php echo limpar($mec['nome']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="admin-form-campo">
                <label>Mecânico responsável</label>
                <input type="text" value="<?php echo limpar($usuarioLogado['nome']); ?>" disabled>
                <small class="admin-dica">Você abre a OS. O apontamento você faz depois, quando começar a
                    trabalhar.</small>
            </div>
            <?php endif; ?>

            <div class="admin-form-campo">
                <label for="data_previsao">Previsão de entrega</label>
                <input type="date" id="data_previsao" name="data_previsao">
            </div>
        </div>
    </div>

    <div class="admin-bloco">
        <div class="admin-bloco-titulo">
            <span><i class="fas fa-clipboard-list"></i> Descrição do Serviço</span>
        </div>

        <div class="admin-form-grid">
            <div class="admin-form-campo admin-form-campo-full">
                <label for="descricao_problema">Problema relatado pelo cliente *</label>
                <textarea id="descricao_problema" name="descricao_problema" rows="5" required
                    placeholder="Ex: Carro faz barulho ao frear, luz da injeção acesa, perda de potência..."></textarea>
            </div>
        </div>
    </div>

    <div class="admin-form-acoes">
        <a href="ordens.php" class="admin-btn admin-btn-secundario">
            <i class="fas fa-times"></i> Cancelar
        </a>
        <button type="submit" class="admin-btn">
            <i class="fas fa-save"></i> Abrir OS
        </button>
    </div>
</form>

<script>
(function() {
    const carros =
        <?php echo json_encode($carros ?: [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const campoBusca = document.getElementById('busca_carro');
    const campoIdCarro = document.getElementById('id_carro');
    const campoIdCliente = document.getElementById('id_cliente');
    const listaCarros = document.getElementById('listaCarros');

    function normalizar(t) {
        return t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    if (campoBusca && !campoBusca.disabled) {
        function mostrarCarros(termo) {
            const t = normalizar(termo);
            const filtrados = carros.filter(c => {
                const alvo = normalizar(c.placa + ' ' + c.marca + ' ' + c.modelo + ' ' + (c.ano || '') +
                    ' ' + c.cliente_nome);
                return alvo.includes(t);
            }).slice(0, 10);

            if (filtrados.length === 0) {
                listaCarros.style.display = 'none';
                return;
            }

            listaCarros.innerHTML = filtrados.map(c => `
                    <div class="admin-autocomplete-item"
                         data-id="${c.id_carro}"
                         data-cliente="${c.id_cliente}"
                         data-texto="${(c.marca + ' ' + c.modelo + ' (' + c.placa.toUpperCase() + ') — ' + c.cliente_nome).replace(/"/g, '&quot;')}">
                        <strong>${c.marca} ${c.modelo} — ${c.placa.toUpperCase()}</strong>
                        <small>${c.cliente_nome}</small>
                    </div>
                `).join('');

            listaCarros.style.display = 'block';

            listaCarros.querySelectorAll('.admin-autocomplete-item').forEach(item => {
                item.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    selecionarCarro(item);
                });
            });
        }

        function selecionarCarro(item) {
            campoBusca.value = item.dataset.texto;
            campoIdCarro.value = item.dataset.id;
            campoIdCliente.value = item.dataset.cliente;
            listaCarros.style.display = 'none';
            listaCarros.querySelectorAll('.admin-autocomplete-item').forEach(i => i.classList.remove(
                'selecionado'));
        }

        function limparDestaques() {
            listaCarros.querySelectorAll('.admin-autocomplete-item').forEach(i => i.classList.remove(
                'selecionado'));
        }

        campoBusca.addEventListener('input', () => {
            const termo = campoBusca.value.trim();
            if (campoIdCarro.value) {
                campoIdCarro.value = '';
                campoIdCliente.value = '';
            }
            if (termo.length < 2) {
                listaCarros.style.display = 'none';
                return;
            }
            mostrarCarros(termo);
        });

        campoBusca.addEventListener('blur', () => {
            setTimeout(() => {
                listaCarros.style.display = 'none';
                limparDestaques();
            }, 200);
        });

        campoBusca.addEventListener('focus', () => {
            if (campoBusca.value.trim().length >= 2) mostrarCarros(campoBusca.value.trim());
        });

        campoBusca.addEventListener('keydown', (e) => {
            const itens = listaCarros.querySelectorAll('.admin-autocomplete-item');
            const aberto = listaCarros.style.display === 'block';

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!aberto && itens.length > 0) listaCarros.style.display = 'block';
                if (itens.length === 0) return;
                let idx = Array.from(itens).findIndex(i => i.classList.contains('selecionado'));
                limparDestaques();
                idx = (idx + 1) % itens.length;
                itens[idx].classList.add('selecionado');
                itens[idx].scrollIntoView({
                    block: 'nearest'
                });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!aberto && itens.length > 0) listaCarros.style.display = 'block';
                if (itens.length === 0) return;
                let idx = Array.from(itens).findIndex(i => i.classList.contains('selecionado'));
                limparDestaques();
                idx = idx <= 0 ? itens.length - 1 : idx - 1;
                itens[idx].classList.add('selecionado');
                itens[idx].scrollIntoView({
                    block: 'nearest'
                });
            } else if (e.key === 'Enter') {
                const dest = listaCarros.querySelector('.admin-autocomplete-item.selecionado');
                if (dest) {
                    e.preventDefault();
                    selecionarCarro(dest);
                }
            } else if (e.key === 'Escape') {
                if (aberto) {
                    e.preventDefault();
                    listaCarros.style.display = 'none';
                    limparDestaques();
                }
            } else if (e.key === 'Tab') {
                listaCarros.style.display = 'none';
            }
        });
    }

    document.getElementById('formOS').addEventListener('submit', (e) => {
        if (!campoIdCarro.value) {
            e.preventDefault();
            alert('Selecione um veículo da lista.');
            campoBusca.focus();
        }
    });
})();
</script>

<?php require_once '_footer.php'; ?>