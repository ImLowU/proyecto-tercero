// =====================================================================
// NEXTLEAGUE - ESTRUCTURA COMÚN DE LAS PÁGINAS
// Antes cada página tenía su propia barra de navegación copiada en el
// HTML (con enlaces distintos según la página) y auth-ui.js le agregaba
// el menú del usuario con estilos inyectados. Ahora la barra, el menú
// del usuario y el pie se arman acá, iguales en todo el sitio.
//
// Cada página solo necesita:
//   <body data-pagina="torneos">        ← marca la sección actual del menú
//   <header id="encabezado"></header>
//   <main id="contenido"> ... </main>
//   <footer id="pie"></footer>
// y cargar, en este orden: api.js, formato.js, ui.js, layout.js.
// Todas las páginas están un nivel abajo de public/, por eso las rutas
// empiezan con "../". Los enlaces van sin ".html" ("../torneos/explorar")
// y la portada por su carpeta ("../home/"): public/.htaccess entrega el
// archivo y la barra de direcciones no lo muestra.
// =====================================================================

const NL_MENU = [
    { clave: 'inicio', texto: 'Inicio', href: '../home/' },
    { clave: 'torneos', texto: 'Torneos', href: '../torneos/explorar' },
    { clave: 'calendario', texto: 'Calendario', href: '../torneos/partidos' },
    { clave: 'resultados', texto: 'Resultados', href: '../torneos/partidos?vista=resultados' },
];

const NL_NOMBRE_ROL = { administrador: 'Administrador', organizador: 'Organizador', participante: 'Participante' };

// ── Sesión: se pide una sola vez por página y se reutiliza ──
let _sesionPromesa = null;
function nextleagueObtenerSesion() {
    if (!_sesionPromesa) _sesionPromesa = nlActualizarSesion();
    return _sesionPromesa;
}

async function nextleagueCerrarSesion() {
    await nlApi.post('/auth/logout');
    window.location.href = '../home/';
}

// La página actual como ruta relativa ("../torneos/torneo?id=3"),
// para volver a ella después de ingresar.
function nlRutaActual() {
    return '../' + window.location.pathname.split('/').slice(-2).join('/') + window.location.search;
}

function nlEnlaceIngresar() {
    const pagina = document.body.dataset.pagina;
    // Desde la portada o las páginas de acceso no hace falta volver
    if (['inicio', 'ingresar', 'registro', 'recuperar'].includes(pagina)) return '../home/login';
    return `../home/login?volver=${encodeURIComponent(nlRutaActual())}`;
}

// Para páginas que requieren estar logueado: si no hay sesión, lleva al
// login y, después de ingresar, vuelve a esta misma página. Si el sistema no
// respondió (por ejemplo, con la base de datos caída), pedir que ingrese no
// tiene sentido: se avisa que no está disponible (antes mandaba al login).
async function nextleagueExigirSesion() {
    const datos = await nextleagueObtenerSesion();
    if (datos.exito === false) {
        nlMostrarSinServicio();
    } else if (!datos.logueado) {
        window.location.href = nlEnlaceIngresar();
    }
    return datos;
}

// Reemplaza el contenido de la página por un aviso con un botón para reintentar.
function nlMostrarSinServicio() {
    const contenido = document.getElementById('contenido');
    if (!contenido) return;
    contenido.innerHTML = `
        <div class="sin-servicio" role="alert">
            ${nlIcono('alerta', 'icono-grande')}
            <h1>El sistema no está disponible</h1>
            <p class="texto-secundario">No pudimos conectarnos con el servidor. Probá de nuevo en unos minutos.</p>
            <button type="button" class="btn btn-primario" id="botonReintentar">Reintentar</button>
        </div>
    `;
    document.getElementById('botonReintentar').addEventListener('click', () => window.location.reload());
}

// Solo acepta volver a una página del propio sitio ("../carpeta/pagina?...",
// o con ".html", como eran las direcciones antes). Evita que un enlace armado
// por otro mande al usuario a un sitio externo.
function nlRutaDeVuelta(valor) {
    return /^\.\.\/[\w-]+\/([\w-]+(\.html)?)?(\?[\w=&%.-]*)?$/.test(valor || '') ? valor : null;
}

// ── Barra de navegación ──
function nlPintarEncabezado() {
    const encabezado = document.getElementById('encabezado');
    if (!encabezado) return;
    encabezado.classList.add('sitio-nav');
    encabezado.innerHTML = `
        <a class="saltar" href="#contenido">Saltar al contenido</a>
        <div class="contenedor sitio-nav__barra">
            <a class="sitio-nav__marca" href="../home/" aria-label="NextLeague, ir al inicio">
                <picture>
                    <source srcset="../logo/logo-96.webp" type="image/webp">
                    <img src="../logo/logoproyeco.png" alt="" width="46" height="38">
                </picture>
                <span>NextLeague</span>
            </a>
            <nav class="sitio-nav__menu" id="menuPrincipal" aria-label="Principal">
                ${NL_MENU.map(item => `<a href="${item.href}" data-clave="${item.clave}">${item.texto}</a>`).join('')}
                <span id="menuSesionCelular" class="sitio-nav__extra"></span>
            </nav>
            <div class="sitio-nav__sesion" id="authArea"></div>
            <button type="button" class="sitio-nav__abrir" id="botonMenu" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir menú">
                <img src="../iconos/menu-64.webp" alt="" width="26" height="26">
            </button>
        </div>
    `;
    nlMarcarMenu(document.body.dataset.pagina);

    const menu = document.getElementById('menuPrincipal');
    const boton = document.getElementById('botonMenu');
    const icono = boton.querySelector('img');
    function abrirMenu(abrir) {
        menu.classList.toggle('abierto', abrir);
        boton.setAttribute('aria-expanded', abrir);
        boton.setAttribute('aria-label', abrir ? 'Cerrar menú' : 'Abrir menú');
        icono.src = abrir ? '../iconos/closemenu-64.webp' : '../iconos/menu-64.webp';
    }
    boton.addEventListener('click', () => abrirMenu(!menu.classList.contains('abierto')));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && menu.classList.contains('abierto')) {
            abrirMenu(false);
            boton.focus();
        }
    });
    // Si se agranda la ventana con el menú abierto, se cierra solo.
    window.matchMedia('(min-width: 960px)').addEventListener('change', () => abrirMenu(false));
}

// Marca la sección actual (aria-current="page"): resaltada en el menú y
// anunciada por los lectores de pantalla.
function nlMarcarMenu(clave) {
    document.querySelectorAll('#menuPrincipal a[data-clave]').forEach(a => {
        if (a.dataset.clave === clave) a.setAttribute('aria-current', 'page');
        else a.removeAttribute('aria-current');
    });
}

// ── Zona de la sesión (derecha de la barra) ──
function nlPintarSesion(datos) {
    const area = document.getElementById('authArea');
    const extraCelular = document.getElementById('menuSesionCelular');
    if (!area) return;

    if (!datos.logueado) {
        // En la página de login no se ofrece "Ingresar", en la de registro no
        // se ofrece "Registrarse", y si el registro está cerrado tampoco.
        const pagina = document.body.dataset.pagina;
        const ofrecerRegistro = datos.registro_abierto !== false && pagina !== 'registro';
        area.innerHTML = `
            ${pagina !== 'ingresar' ? `<a class="btn btn-secundario btn-chico" href="${nlEnlaceIngresar()}">Ingresar</a>` : ''}
            ${ofrecerRegistro ? '<a class="btn btn-primario btn-chico solo-desde-chico" href="../register/register">Registrarse</a>' : ''}
        `;
        // En celulares angostos "Registrarse" no entra en la barra: va dentro del menú.
        if (extraCelular) extraCelular.innerHTML = ofrecerRegistro ? '<a class="solo-celular-angosto" href="../register/register">Crear cuenta</a>' : '';
        return;
    }

    const u = datos.usuario;
    const puedeCrear = u.rol === 'organizador' || u.rol === 'administrador';
    area.innerHTML = `
        <div class="menu-usuario">
            <button type="button" class="menu-usuario__boton" id="botonUsuario" aria-haspopup="true" aria-expanded="false" aria-controls="listaUsuario">
                <img class="menu-usuario__avatar" src="${nlAvatar(u.foto_perfil)}" alt="" width="28" height="28">
                <span>${escapeHtml(u.nombre_usuario)}</span>
            </button>
            <div class="menu-usuario__lista" id="listaUsuario">
                <p class="menu-usuario__rol">${escapeHtml(NL_NOMBRE_ROL[u.rol] || u.rol)}</p>
                <a href="../perfil/perfil">${nlIcono('usuario')}Mi perfil</a>
                <a href="../perfil/perfil#mis-torneos">${nlIcono('trofeo')}Mis torneos</a>
                ${puedeCrear ? `<a href="../createtournament/createtournament">${nlIcono('mas')}Crear torneo</a>` : ''}
                ${u.rol === 'administrador' ? `<a href="../admin/admin">${nlIcono('escudo')}Administración</a>` : ''}
                <button type="button" class="cerrar-sesion" id="botonCerrarSesion">${nlIcono('salir')}Cerrar sesión</button>
            </div>
        </div>
    `;
    if (extraCelular) extraCelular.innerHTML = '';

    const boton = document.getElementById('botonUsuario');
    const lista = document.getElementById('listaUsuario');
    function abrirLista(abrir) {
        lista.classList.toggle('abierto', abrir);
        boton.setAttribute('aria-expanded', abrir);
    }
    boton.addEventListener('click', () => abrirLista(!lista.classList.contains('abierto')));
    document.addEventListener('click', (e) => {
        if (!boton.contains(e.target) && !lista.contains(e.target)) abrirLista(false);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lista.classList.contains('abierto')) {
            abrirLista(false);
            boton.focus();
        }
    });
    document.getElementById('botonCerrarSesion').addEventListener('click', nextleagueCerrarSesion);
}

// ── Pie de página ──
function nlPintarPie() {
    const pie = document.getElementById('pie');
    if (!pie) return;
    pie.classList.add('sitio-pie');
    pie.innerHTML = `
        <div class="contenedor sitio-pie__contenido">
            <div class="sitio-pie__marca">
                <picture>
                    <source srcset="../logo/logo-96.webp" type="image/webp">
                    <img src="../logo/logoproyeco.png" alt="" width="41" height="34">
                </picture>
                <p>© ${new Date().getFullYear()} NextLeague · Torneos deportivos, mentales y electrónicos</p>
            </div>
            <nav class="sitio-pie__enlaces" aria-label="Enlaces del pie">
                ${NL_MENU.map(item => `<a href="${item.href}">${item.texto}</a>`).join('')}
            </nav>
        </div>
    `;
}

// ── Arranque ──
// Los scripts van al final del <body>, así que la estructura ya existe:
// la barra y el pie se dibujan enseguida y la sesión se completa al llegar.
nlPintarEncabezado();
nlPintarPie();
nextleagueObtenerSesion().then((datos) => {
    nlPintarSesion(datos);
    // Si la cuenta tiene que cambiar la contraseña (por ejemplo el admin
    // inicial, cuya contraseña está publicada), se la lleva al perfil.
    if (datos.logueado && datos.usuario.debe_cambiar_contrasena && !window.location.pathname.includes('/perfil/perfil')) {
        window.location.href = '../perfil/perfil?cambiar_contrasena=1';
    }
});
