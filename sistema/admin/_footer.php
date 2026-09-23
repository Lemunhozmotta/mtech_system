</main>
<!-- Fim do admin-conteudo -->

</div>
<!-- Fim do admin-main -->

</div>
<!-- Fim do admin-wrapper -->

<!-- =====================================================
                 JAVASCRIPT DO ADMIN
                 ===================================================== -->
<script>
    /* =========================================================
                   ALTERNAR TEMA CLARO / ESCURO (com persistência)
                   ========================================================= */
    (function() {
        const btnTema = document.getElementById('btnTemaAdmin');
        const iconeTema = btnTema ? btnTema.querySelector('i') : null;

        function aplicarTema(tema) {
            const html = document.documentElement;
            if (tema === 'light') {
                html.classList.add('light');
                if (iconeTema) {
                    iconeTema.classList.remove('fa-sun');
                    iconeTema.classList.add('fa-moon');
                }
            } else {
                html.classList.remove('light');
                if (iconeTema) {
                    iconeTema.classList.remove('fa-moon');
                    iconeTema.classList.add('fa-sun');
                }
            }
        }

        const temaSalvo = localStorage.getItem('mtech-tema') || 'dark';
        aplicarTema(temaSalvo);

        function toggleTema() {
            const atual = document.documentElement.classList.contains('light') ? 'light' : 'dark';
            const novo = atual === 'light' ? 'dark' : 'light';
            aplicarTema(novo);
            localStorage.setItem('mtech-tema', novo);
        }

        if (btnTema) {
            btnTema.addEventListener('click', toggleTema);
        }
    })();

    /* =========================================================
       AUTO-REFRESH GLOBAL (POLLING)
       - Só roda em páginas que definiram $polling_ativo = true
       - Bate a cada 15s no endpoint polling.php
       - Se hash mudar, mostra toast "Há atualizações"
       - Se ninguém clicar em 30s, recarrega sozinho
       - Pausa quando aba não está visível
       ========================================================= */
    <?php if (!empty($polling_ativo)): ?>
            (function() {
                const INTERVALO = 15000; // 15s entre checagens
                const AUTO_RELOAD = 30000; // 30s pra auto-recarregar após aviso

                let ultimoHash = null;
                let timer = null;
                let autoReloadTimer = null;
                let avisoAberto = false;

                // ===== CRIA O TOAST (escondido) =====
                const toast = document.createElement('div');
                toast.className = 'admin-toast-atualizacao';
                toast.innerHTML = `
                        <i class="fas fa-sync-alt"></i>
                        <span>Há atualizações no sistema</span>
                        <button type="button" class="admin-toast-btn">
                            <i class="fas fa-rotate-right"></i> Recarregar
                        </button>
                        <button type="button" class="admin-toast-fechar" title="Fechar">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                document.body.appendChild(toast);

                const btnReload = toast.querySelector('.admin-toast-btn');
                const btnFechar = toast.querySelector('.admin-toast-fechar');

                function mostrarToast() {
                    if (avisoAberto) return;
                    avisoAberto = true;
                    toast.classList.add('ativo');

                    // Auto-reload depois de 30s
                    clearTimeout(autoReloadTimer);
                    autoReloadTimer = setTimeout(() => {
                        window.location.reload();
                    }, AUTO_RELOAD);
                }

                function esconderToast() {
                    avisoAberto = false;
                    toast.classList.remove('ativo');
                    clearTimeout(autoReloadTimer);
                }

                btnReload.addEventListener('click', () => {
                    window.location.reload();
                });

                btnFechar.addEventListener('click', () => {
                    esconderToast();
                    // Depois de fechar, atualiza o hash pra não avisar de novo pela mesma mudança
                    checar(true);
                });

                // ===== CHECA O ESTADO =====
                async function checar(silencioso = false) {
                    // Se a aba não está visível, pula
                    if (document.hidden) return;

                    try {
                        const r = await fetch('polling.php?_=' + Date.now(), {
                            cache: 'no-store'
                        });
                        const data = await r.json();

                        if (!data.ok) return;

                        // Primeira vez: só guarda o hash
                        if (ultimoHash === null) {
                            ultimoHash = data.hash;
                            return;
                        }

                        // Mudou?
                        if (data.hash !== ultimoHash) {
                            ultimoHash = data.hash;
                            if (!silencioso) {
                                mostrarToast();
                            }
                        }
                    } catch (err) {
                        // Silencioso — não quebra a página se o polling falhar
                        console.warn('Polling falhou:', err);
                    }
                }

                // ===== LOOP =====
                function iniciar() {
                    if (timer) clearInterval(timer);
                    checar();
                    timer = setInterval(checar, INTERVALO);
                }

                function parar() {
                    if (timer) {
                        clearInterval(timer);
                        timer = null;
                    }
                }

                // Pausa quando a aba perde o foco
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        parar();
                    } else {
                        // Quando volta, checa imediatamente
                        checar();
                        iniciar();
                    }
                });

                // Inicia
                iniciar();
            })();
    <?php endif; ?>
</script>

</body>

</html>