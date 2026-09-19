/* =========================================================
   GEOLOCALIZAÇÃO
   ========================================================= */
const btn = document.getElementById("btnLocalizacao");
const campoLocal = document.getElementById("local");
const mapa = document.getElementById("mapa");

if (btn) {
    btn.addEventListener("click", () => {
        if (!navigator.geolocation) {
            alert("Geolocalização não suportada");
            return;
        }

        btn.innerText = "Buscando localização...";
        btn.classList.add("loading");

        navigator.geolocation.getCurrentPosition(async (pos) => {
            const lat = pos.coords.latitude;
            const lon = pos.coords.longitude;

            mapa.style.display = "block";
            mapa.innerHTML = `<iframe 
                width="100%" 
                height="100%" 
                style="border:0;" 
                src="https://maps.google.com/maps?q=${lat},${lon}&z=15&output=embed">
            </iframe>`;

            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json`);
                const data = await res.json();
                campoLocal.value = data.display_name;
            } catch (erro) {
                campoLocal.value = `https://www.google.com/maps?q=${lat},${lon}`;
            }

            btn.innerText = "Localização capturada ✅";
            btn.classList.remove("loading");
        }, () => {
            alert("Erro ao pegar localização");
            btn.innerText = "📍 Usar minha localização";
            btn.classList.remove("loading");
        });
    });
}

/* =========================================================
   HEADER: SIDEBAR + RECUO + CLASSES NO BODY
   ========================================================= */
const headerEl = document.querySelector('.cabecalho');
const footerEl = document.querySelector('footer');
const bodyEl = document.body;

function atualizarHeader() {
    if (!headerEl) return;

    const sidebarAtiva = window.scrollY > 50;

    if (sidebarAtiva) {
        headerEl.classList.add('transformado');
        bodyEl.classList.add('sidebar-ativa');
    } else {
        headerEl.classList.remove('transformado');
        bodyEl.classList.remove('sidebar-ativa');
    }

    if (window.scrollY > 80) {
        bodyEl.classList.add('rolado');
    } else {
        bodyEl.classList.remove('rolado');
    }

    if (footerEl && sidebarAtiva) {
        const footerTopo = footerEl.getBoundingClientRect().top;
        const alturaTela = window.innerHeight;

        if (footerTopo < (alturaTela * 0.8) + 50) {
            headerEl.classList.add('recuado');
        } else {
            headerEl.classList.remove('recuado');
        }
    } else {
        headerEl.classList.remove('recuado');
    }
}

window.addEventListener('scroll', atualizarHeader);
window.addEventListener('load', atualizarHeader);
window.addEventListener('resize', atualizarHeader);

/* =========================================================
   MENU HAMBÚRGUER MOBILE
   ========================================================= */
const btnMenuMobile = document.getElementById('btnMenuMobile');
const btnMenuHeader = document.getElementById('btnMenuHeader');
const overlayMobile = document.getElementById('overlayMobile');
const gavetaMobile = document.getElementById('gavetaMobile');

function abrirGaveta() {
    bodyEl.classList.add('gaveta-aberta');
}

function fecharGaveta() {
    bodyEl.classList.remove('gaveta-aberta');
}

if (btnMenuMobile) {
    btnMenuMobile.addEventListener('click', () => {
        bodyEl.classList.contains('gaveta-aberta') ? fecharGaveta() : abrirGaveta();
    });
}

if (btnMenuHeader) {
    btnMenuHeader.addEventListener('click', () => {
        bodyEl.classList.contains('gaveta-aberta') ? fecharGaveta() : abrirGaveta();
    });
}

if (overlayMobile) {
    overlayMobile.addEventListener('click', fecharGaveta);
}

if (gavetaMobile) {
    gavetaMobile.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', fecharGaveta);
    });

    const btnContatoGaveta = gavetaMobile.querySelector('.btn-contato-mobile');
    if (btnContatoGaveta) {
        btnContatoGaveta.addEventListener('click', fecharGaveta);
    }
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') fecharGaveta();
});

/* =========================================================
   LINK ATIVO
   ========================================================= */
document.addEventListener("DOMContentLoaded", function () {
    const secoes = document.querySelectorAll('main section, body > section');
    const linksMenu = document.querySelectorAll('a[href^="#"]');

    const opcoes = {
        root: null,
        rootMargin: "0px",
        threshold: 0.4
    };

    const observador = new IntersectionObserver((entradas) => {
        entradas.forEach(entrada => {
            if (entrada.isIntersecting) {
                const idSecaoAtiva = entrada.target.getAttribute('id');

                linksMenu.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === `#${idSecaoAtiva}`) {
                        link.classList.add('active');
                    }
                });
            }
        });
    }, opcoes);

    secoes.forEach(secao => {
        if (secao.getAttribute('id')) {
            observador.observe(secao);
        }
    });
});

/* =========================================================
   ENVIO WHATSAPP
   ========================================================= */
const form = document.getElementById("form");
if (form) {
    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const nome = document.getElementById("nome").value;
        const telefone = document.getElementById("telefone").value;
        const servico = document.getElementById("servico").value;
        const local = document.getElementById("local").value;
        const data = document.getElementById("data").value;

        const msg = `🚗 *AUTO SOCORRO 24H* %0A
━━━━━━━━━━━━━━━%0A
👤 *Nome:* ${nome} %0A
📞 *Telefone:* ${telefone} %0A
🛠 *Serviço:* ${servico} %0A
📍 *Local:* ${local} %0A
📅 *Data:* ${data} %0A
━━━━━━━━━━━━━━━`;

        window.open(`https://wa.me/5513974014642?text=${msg}`, "_blank");
    });
}

/* =========================================================
   ALTERNAR TEMA CLARO / ESCURO
   ========================================================= */
const btnTema = document.getElementById('btnTema');
const btnTemaMobile = document.getElementById('btnTemaMobile');
const btnTemaHeader = document.getElementById('btnTemaHeader');

const iconeTema = btnTema ? btnTema.querySelector('i') : null;
const iconeTemaMobile = btnTemaMobile ? btnTemaMobile.querySelector('i') : null;
const iconeTemaHeader = btnTemaHeader ? btnTemaHeader.querySelector('i') : null;

const logoMtech = document.getElementById('logoMtech');
const logoMtechMobile = document.getElementById('logoMtechMobile');

function aplicarTema(tema) {
    const html = document.documentElement;

    if (tema === 'light') {
        html.classList.add('light');

        if (iconeTema) { iconeTema.classList.remove('fa-sun'); iconeTema.classList.add('fa-moon'); }
        if (iconeTemaMobile) { iconeTemaMobile.classList.remove('fa-sun'); iconeTemaMobile.classList.add('fa-moon'); }
        if (iconeTemaHeader) { iconeTemaHeader.classList.remove('fa-sun'); iconeTemaHeader.classList.add('fa-moon'); }

        if (logoMtech) logoMtech.src = 'img/logo-pr.png';
        if (logoMtechMobile) logoMtechMobile.src = 'img/logo-pr.png';
    } else {
        html.classList.remove('light');

        if (iconeTema) { iconeTema.classList.remove('fa-moon'); iconeTema.classList.add('fa-sun'); }
        if (iconeTemaMobile) { iconeTemaMobile.classList.remove('fa-moon'); iconeTemaMobile.classList.add('fa-sun'); }
        if (iconeTemaHeader) { iconeTemaHeader.classList.remove('fa-moon'); iconeTemaHeader.classList.add('fa-sun'); }

        if (logoMtech) logoMtech.src = 'img/logo-br.png';
        if (logoMtechMobile) logoMtechMobile.src = 'img/logo-br.png';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const temaSalvo = localStorage.getItem('mtech-tema') || 'dark';
    aplicarTema(temaSalvo);
});

function toggleTema() {
    const temaAtual = document.documentElement.classList.contains('light') ? 'light' : 'dark';
    const novoTema = temaAtual === 'light' ? 'dark' : 'light';

    aplicarTema(novoTema);
    localStorage.setItem('mtech-tema', novoTema);
}

if (btnTema) btnTema.addEventListener('click', toggleTema);
if (btnTemaMobile) btnTemaMobile.addEventListener('click', toggleTema);
if (btnTemaHeader) btnTemaHeader.addEventListener('click', toggleTema);

/* =========================================================
   FORMULÁRIO DE CONTATO — SIMULAÇÃO DE ENVIO
   ========================================================= */
const formContato = document.getElementById("formContato");
const msgSucesso = document.getElementById("msgSucesso");

if (formContato) {
    formContato.addEventListener("submit", function (e) {
        e.preventDefault();

        // Pega os valores (por enquanto só pra validar visualmente)
        const nome = document.getElementById("contatoNome").value.trim();
        const email = document.getElementById("contatoEmail").value.trim();
        const telefone = document.getElementById("contatoTelefone").value.trim();
        const assunto = document.getElementById("contatoAssunto").value;
        const mensagem = document.getElementById("contatoMensagem").value.trim();

        // Esconde o formulário
        formContato.style.display = "none";

        // Mostra a mensagem de sucesso
        msgSucesso.classList.add("visivel");

        // (Opcional) log pra debug — pode remover depois
        console.log("Formulário enviado:", { nome, email, telefone, assunto, mensagem });

        // (Opcional) depois de 5 segundos, volta ao formulário
        // Descomente a linha abaixo se quiser que volte automaticamente
        // setTimeout(() => {
        //     formContato.reset();
        //     formContato.style.display = "flex";
        //     msgSucesso.classList.remove("visivel");
        // }, 5000);
    });
}