<?php
/* =========================================================
   M-TECH SYSTEM — CLIENTES (novo)
   Formulário de cadastro de cliente
   ========================================================= */

$titulo_pagina = 'Novo Cliente';
require_once '_header.php';
?>

<!-- ===== TÍTULO COM BOTÃO VOLTAR ===== -->
<div class="admin-topo-pagina">
    <h1 class="admin-titulo-pagina">
        <a href="clientes.php" class="admin-voltar" title="Voltar">
            <i class="fas fa-arrow-left"></i>
        </a>
        Novo Cliente
    </h1>
</div>

<!-- =====================================================
     FORMULÁRIO DE CADASTRO
     ===================================================== -->
<form action="clientes_acao.php" method="POST" class="admin-form">

    <input type="hidden" name="acao" value="cadastrar">

    <!-- ===== BLOCO 1: DADOS PESSOAIS ===== -->
    <div class="admin-bloco">

        <div class="admin-bloco-titulo">
            <span><i class="fas fa-user"></i> Dados Pessoais</span>
        </div>

        <div class="admin-form-grid">

            <div class="admin-form-campo admin-form-campo-full">
                <label for="nome">Nome completo *</label>
                <input type="text" id="nome" name="nome" placeholder="Ex: João da Silva" required maxlength="150">
            </div>

            <div class="admin-form-campo">
                <label for="cpf_cnpj">CPF / CNPJ</label>
                <input type="text" id="cpf_cnpj" name="cpf_cnpj" placeholder="000.000.000-00" maxlength="20">
            </div>

            <div class="admin-form-campo">
                <label for="data_nascimento">Data de nascimento</label>
                <input type="date" id="data_nascimento" name="data_nascimento">
            </div>

        </div>

    </div>

    <!-- ===== BLOCO 2: CONTATO ===== -->
    <div class="admin-bloco">

        <div class="admin-bloco-titulo">
            <span><i class="fas fa-phone"></i> Contato</span>
        </div>

        <div class="admin-form-grid">

            <div class="admin-form-campo">
                <label for="telefone">Telefone</label>
                <input type="tel" id="telefone" name="telefone" placeholder="(11) 91234-5678" maxlength="20">
            </div>

            <div class="admin-form-campo">
                <label for="whatsapp">WhatsApp</label>
                <div class="admin-input-grupo">
                    <input type="tel" id="whatsapp" name="whatsapp" placeholder="(11) 91234-5678" maxlength="20">
                    <button type="button" class="admin-btn-icone" id="btnCopiarTelefone" title="Copiar do telefone">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>

            <div class="admin-form-campo admin-form-campo-full">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="cliente@email.com" maxlength="150">
            </div>

        </div>

    </div>

    <!-- ===== BLOCO 3: ENDEREÇO ===== -->
    <div class="admin-bloco">

        <div class="admin-bloco-titulo">
            <span><i class="fas fa-map-marker-alt"></i> Endereço</span>
        </div>

        <div class="admin-form-grid">

            <!-- CEP + botão buscar -->
            <div class="admin-form-campo admin-form-campo-full">
                <label for="cep">CEP</label>
                <div class="admin-input-grupo">
                    <input type="text" id="cep" name="cep" placeholder="00000-000" maxlength="9" inputmode="numeric">
                    <a href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank"
                        class="admin-btn-icone admin-btn-nao-sei-cep"
                        title="Não sei meu CEP — abrir busca nos Correios">
                        <i class="fas fa-question-circle"></i>
                        <span>Não sei meu CEP</span>
                    </a>
                </div>
                <small class="admin-dica">
                    Digite o CEP e o sistema preenche o endereço automaticamente.
                </small>
            </div>

            <!-- Logradouro -->
            <div class="admin-form-campo admin-form-campo-full">
                <label for="logradouro">Logradouro (Rua, Avenida...)</label>
                <input type="text" id="logradouro" name="logradouro" placeholder="Rua das Flores" maxlength="150">
            </div>

            <!-- Número -->
            <div class="admin-form-campo">
                <label for="numero">Número</label>
                <input type="text" id="numero" name="numero" placeholder="123" maxlength="20">
            </div>

            <!-- Complemento -->
            <div class="admin-form-campo">
                <label for="complemento">Complemento</label>
                <input type="text" id="complemento" name="complemento" placeholder="Apto 45, Bloco B..." maxlength="80">
            </div>

            <!-- Bairro -->
            <div class="admin-form-campo">
                <label for="bairro">Bairro</label>
                <input type="text" id="bairro" name="bairro" placeholder="Centro" maxlength="100">
            </div>

            <!-- Cidade -->
            <div class="admin-form-campo">
                <label for="cidade">Cidade</label>
                <input type="text" id="cidade" name="cidade" placeholder="Mauá" maxlength="100">
            </div>

            <!-- Estado -->
            <div class="admin-form-campo">
                <label for="estado">Estado (UF)</label>
                <input type="text" id="estado" name="estado" placeholder="SP" maxlength="2"
                    style="text-transform: uppercase;">
            </div>

        </div>

    </div>

    <!-- ===== BLOCO 4: OBSERVAÇÕES ===== -->
    <div class="admin-bloco">

        <div class="admin-bloco-titulo">
            <span><i class="fas fa-comment-alt"></i> Observações</span>
        </div>

        <div class="admin-form-grid">

            <div class="admin-form-campo admin-form-campo-full">
                <label for="observacoes">Observações internas</label>
                <textarea id="observacoes" name="observacoes" rows="4"
                    placeholder="Anotações sobre o cliente (não aparece pro cliente)..."></textarea>
            </div>

        </div>

    </div>

    <!-- ===== AÇÕES ===== -->
    <div class="admin-form-acoes">
        <a href="clientes.php" class="admin-btn admin-btn-secundario">
            <i class="fas fa-times"></i> Cancelar
        </a>
        <button type="submit" class="admin-btn">
            <i class="fas fa-save"></i> Salvar Cliente
        </button>
    </div>

</form>

<!-- =====================================================
     SCRIPTS: copiar telefone + buscar CEP automático
     ===================================================== -->
<script>
    (function() {
        // ===== COPIAR TELEFONE → WHATSAPP =====
        const btnCopiar = document.getElementById('btnCopiarTelefone');
        const campoTelefone = document.getElementById('telefone');
        const campoWhatsapp = document.getElementById('whatsapp');

        if (btnCopiar) {
            btnCopiar.addEventListener('click', () => {
                if (campoTelefone.value.trim() !== '') {
                    campoWhatsapp.value = campoTelefone.value;
                    btnCopiar.innerHTML = '<i class="fas fa-check"></i>';
                    setTimeout(() => {
                        btnCopiar.innerHTML = '<i class="fas fa-copy"></i>';
                    }, 1500);
                } else {
                    campoTelefone.focus();
                }
            });
        }

        // ===== BUSCAR CEP AUTOMÁTICO (ViaCEP) =====
        const campoCep = document.getElementById('cep');
        const campoLogradouro = document.getElementById('logradouro');
        const campoBairro = document.getElementById('bairro');
        const campoCidade = document.getElementById('cidade');
        const campoEstado = document.getElementById('estado');

        if (campoCep) {
            // Máscara: 00000-000 (aplicada a cada digitação)
            campoCep.addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, '').slice(0, 8);
                if (v.length > 5) {
                    v = v.slice(0, 5) + '-' + v.slice(5);
                }
                e.target.value = v;
            });

            // Busca automática ao sair do campo (blur) com CEP completo
            campoCep.addEventListener('blur', async () => {
                const cep = campoCep.value.replace(/\D/g, '');

                if (cep.length !== 8) return;

                // Feedback visual
                const iconeOriginal = campoCep.style.backgroundImage;
                campoCep.style.opacity = '0.5';
                campoCep.disabled = true;

                try {
                    const resposta = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                    const dados = await resposta.json();

                    if (!dados.erro) {
                        // Preenche (ou atualiza) os campos
                        campoLogradouro.value = dados.logradouro || '';
                        campoBairro.value = dados.bairro || '';
                        campoCidade.value = dados.localidade || '';
                        campoEstado.value = dados.uf || '';

                        // Foca no campo "número"
                        const campoNumero = document.getElementById('numero');
                        if (campoNumero) campoNumero.focus();

                        // Feedback visual de sucesso
                        campoCep.style.borderColor = '#25d366';
                        setTimeout(() => {
                            campoCep.style.borderColor = '';
                        }, 1500);

                    } else {
                        alert('CEP não encontrado. Confira o número ou use "Não sei meu CEP".');
                        campoCep.style.borderColor = '#D62D2D';
                        setTimeout(() => {
                            campoCep.style.borderColor = '';
                        }, 2000);
                    }
                } catch (err) {
                    console.error('Erro ao buscar CEP:', err);
                    // Falha silenciosa (usuário preenche manualmente)
                } finally {
                    campoCep.style.opacity = '1';
                    campoCep.disabled = false;
                }
            });
        }
    })();
</script>

<?php require_once '_footer.php'; ?>