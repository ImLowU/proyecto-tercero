// =====================================================================
// NEXTLEAGUE - EXPLORAR TORNEOS
// Búsqueda por texto y filtros por categoría, juego, formato, modalidad y
// estado, todos aplicados en el servidor y con paginación.
// Los filtros quedan en la URL (explorar?categoria=mental&pagina=2),
// así el enlace se puede compartir y "atrás" vuelve a la misma búsqueda.
// "Mis inscripciones" y "Mis torneos creados" pasaron al perfil.
// =====================================================================

const FILTROS = ['buscar', 'categoria', 'id_juego', 'modulo', 'modalidad', 'estado'];
const AVANZADOS = { id_juego: 'filtroJuego', modulo: 'filtroModulo', modalidad: 'filtroModalidad', estado: 'filtroEstado' };

// Estado inicial: lo que venga en la URL
const filtros = Object.fromEntries(FILTROS.map(f => [f, nlParametro(f) || '']));
let pagina = Math.max(1, Number(nlParametro('pagina')) || 1);
let juegos = [];
let pedidoActual = 0; // para descartar respuestas viejas si se escribe rápido

const lista = document.getElementById('listaTorneos');
const inputBuscar = document.getElementById('buscar');

function actualizarUrl() {
    history.replaceState(null, '', nlConParametros(window.location.pathname, { ...filtros, pagina: pagina > 1 ? pagina : '' }));
}

async function cargarTorneos() {
    const numero = ++pedidoActual;
    lista.innerHTML = nlCargando('Buscando torneos…');
    actualizarUrl();
    pintarIndicadores();

    const data = await nlApi.get(nlConParametros('/torneos', { ...filtros, pagina }));
    if (numero !== pedidoActual) return; // llegó una respuesta de una búsqueda anterior

    const cantidad = document.getElementById('cantidadTorneos');
    if (!data.exito) {
        lista.innerHTML = nlVacio(data.mensaje);
        cantidad.textContent = '';
        nlPaginacion('paginacion', null);
        return;
    }
    const total = data.paginacion.total;
    cantidad.textContent = total === 1 ? '1 torneo' : `${total.toLocaleString('es-UY')} torneos`;

    if (data.torneos.length === 0) {
        lista.innerHTML = hayFiltros()
            ? nlVacio('No encontramos torneos con esos filtros. Probá con otros o limpialos.')
            : nlVacio('Todavía no hay torneos publicados.');
    } else {
        lista.innerHTML = data.torneos.map(nlTarjetaTorneo).join('');
    }
    nlPaginacion('paginacion', data.paginacion, (nueva) => {
        pagina = nueva;
        cargarTorneos();
        nlDesplazar(document.getElementById('contenido'));
    });
}

// Cada cambio de filtro vuelve a la primera página
function cambiarFiltro(nombre, valor) {
    filtros[nombre] = valor;
    pagina = 1;
    cargarTorneos();
}

function hayFiltros() {
    return FILTROS.some(f => filtros[f]);
}

// Muestra cuántos filtros avanzados hay activos (en el botón "Filtros" del
// celular) y el botón "Limpiar filtros".
function pintarIndicadores() {
    const activos = Object.keys(AVANZADOS).filter(f => filtros[f]).length;
    const contador = document.getElementById('cantidadFiltros');
    contador.textContent = activos;
    contador.classList.toggle('oculto', activos === 0);
    document.getElementById('botonLimpiar').classList.toggle('oculto', !hayFiltros());
}

// ── Búsqueda por texto: espera a que se deje de escribir ──
let esperaBusqueda = null;
inputBuscar.value = filtros.buscar;
inputBuscar.addEventListener('input', () => {
    clearTimeout(esperaBusqueda);
    esperaBusqueda = setTimeout(() => cambiarFiltro('buscar', inputBuscar.value.trim()), 350);
});
document.getElementById('formFiltros').addEventListener('submit', (e) => {
    e.preventDefault();
    clearTimeout(esperaBusqueda);
    cambiarFiltro('buscar', inputBuscar.value.trim());
});

// ── Categoría (chips) ──
function marcarCategoria() {
    document.querySelectorAll('#filtroCategoria [data-categoria]').forEach(b => {
        b.setAttribute('aria-pressed', b.dataset.categoria === filtros.categoria);
    });
}
document.querySelectorAll('#filtroCategoria [data-categoria]').forEach(boton => {
    boton.addEventListener('click', () => {
        filtros.categoria = boton.dataset.categoria;
        marcarCategoria();
        // Si el juego elegido no es de la nueva categoría, se quita
        const juego = juegos.find(j => String(j.id_juego) === filtros.id_juego);
        if (juego && filtros.categoria && juego.categoria !== filtros.categoria) filtros.id_juego = '';
        pintarJuegos();
        cambiarFiltro('categoria', filtros.categoria);
    });
});

// ── Juego: solo los de la categoría elegida, agrupados por categoría ──
function pintarJuegos() {
    const select = document.getElementById('filtroJuego');
    const visibles = juegos.filter(j => !filtros.categoria || j.categoria === filtros.categoria);
    select.innerHTML = nlOpcionesJuegos(visibles, 'Todos');
    select.value = filtros.id_juego;
}

async function cargarJuegos() {
    const data = await nlApi.get('/juegos');
    if (data.exito) juegos = data.juegos;
    pintarJuegos();
}

// ── Filtros avanzados (selects) ──
Object.entries(AVANZADOS).forEach(([filtro, id]) => {
    const select = document.getElementById(id);
    if (filtro !== 'id_juego') select.value = filtros[filtro];
    select.addEventListener('change', () => cambiarFiltro(filtro, select.value));
});

// En celular los filtros avanzados se abren con un botón
const botonFiltros = document.getElementById('botonFiltros');
botonFiltros.addEventListener('click', () => {
    const abierto = document.getElementById('filtrosAvanzados').classList.toggle('abierto');
    botonFiltros.setAttribute('aria-expanded', abierto);
});

document.getElementById('botonLimpiar').addEventListener('click', () => {
    FILTROS.forEach(f => { filtros[f] = ''; });
    inputBuscar.value = '';
    Object.values(AVANZADOS).forEach(id => { document.getElementById(id).value = ''; });
    marcarCategoria();
    pintarJuegos();
    pagina = 1;
    cargarTorneos();
});

// Organizadores y administradores ven el atajo para crear un torneo
nextleagueObtenerSesion().then((sesion) => {
    if (sesion.logueado && ['organizador', 'administrador'].includes(sesion.usuario.rol)) {
        document.getElementById('botonCrear').classList.remove('oculto');
    }
});

marcarCategoria();
cargarJuegos();
cargarTorneos();
