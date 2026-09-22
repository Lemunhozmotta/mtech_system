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

                    // ===== APLICA O TEMA =====
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

                    // ===== CARREGA O TEMA SALVO AO ABRIR =====
                    const temaSalvo = localStorage.getItem('mtech-tema') || 'dark';
                    aplicarTema(temaSalvo);

                    // ===== TOGGLE =====
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
            </script>

            </body>

            </html>