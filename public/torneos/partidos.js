// =====================================================================
// NEXTLEAGUE - CALENDARIO Y RESULTADOS (página pública)
// Antes el calendario y los resultados eran dos secciones de la portada
// con 20 partidos como máximo. Ahora tienen su propia página: filtro por
// juego, partidos agrupados por día y paginación.
// En PC las dos listas van lado a lado (como en la portada); en celular
// se elige una con las pestañas. La pestaña y el juego quedan en la URL
// (partidos?vista=resultados&id_juego=28) para poder compartir el enlace.
// =====================================================================

const POR_PAGINA = 20;

const VISTAS = {
    proximos: {
        ruta: '/partidos/proximos', lista: 'listaProximos', paginacion: 'paginacionProximos', menu: 'calendario',
        vacio: 'No hay partidos programados por ahora.',
    },
    resultados: {
        ruta: '/partidos/recientes', lista: 'listaResultados', paginacion: 'paginacionResultados', menu: 'resultados',
        vacio: 'Todavía no hay resultados cargados.',
    },
};

const estado = {
    vista: nlParametro('vista') === 'resultados' ? 'resultados' : 'proximos',
    idJuego: nlParametro('id_juego') || '',
    pagina: { proximos: 1, resultados: 1 },
};

function actualizarUrl() {
    history.replaceState(null, '', nlConParametros(window.location.pathname, {
        vista: estado.vista === 'resultados' ? 'resultados' : '',
        id_juego: estado.idJuego,
    }));
}

// Agrupa los partidos por día, respetando el orden en que llegan de la API.
function agruparPorDia(partidos, fechaDe) {
    const grupos = new Map();
    partidos.forEach(p => {
        const fecha = fechaDe(p);
        const clave = fecha ? String(fecha).slice(0, 10) : '';
        if (!grupos.has(clave)) grupos.set(clave, []);
        grupos.get(clave).push(p);
    });
    return [...grupos];
}

function tituloDelDia(dia) {
    return dia ? escapeHtml(formatearDia(dia)) : 'Fecha a confirmar';
}

function pintarProximos(partidos) {
    return agruparPorDia(partidos, p => p.fecha_hora).map(([dia, lista]) => `
        <section class="dia">
            <h2 class="dia__titulo">${nlIcono('calendario')}${tituloDelDia(dia)}</h2>
            <div class="calendario">${lista.map(p => nlCita(p, { hoja: 'hora' })).join('')}</div>
        </section>
    `).join('');
}

function pintarResultados(partidos) {
    // Si un partido no tenía fecha programada, se agrupa por el día en que se cargó el resultado.
    return agruparPorDia(partidos, p => p.fecha_hora || p.fecha_resultado).map(([dia, lista]) => `
        <section class="dia">
            <h2 class="dia__titulo">${nlIcono('calendario')}${tituloDelDia(dia)}</h2>
            <div class="lista-partidos">${lista.map(p => nlPartido(p, { torneo: true, enlace: true, soloHora: true })).join('')}</div>
        </section>
    `).join('');
}

async function cargar(vista) {
    const config = VISTAS[vista];
    const lista = document.getElementById(config.lista);
    lista.innerHTML = nlCargando();

    const data = await nlApi.get(nlConParametros(config.ruta, {
        id_juego: estado.idJuego, pagina: estado.pagina[vista], por_pagina: POR_PAGINA,
    }));
    if (!data.exito) {
        lista.innerHTML = nlVacio(data.mensaje);
        nlPaginacion(config.paginacion, null);
        return;
    }
    if (data.partidos.length === 0) {
        lista.innerHTML = nlVacio(estado.idJuego ? 'No hay partidos de este juego por ahora.' : config.vacio);
    } else {
        lista.innerHTML = vista === 'proximos' ? pintarProximos(data.partidos) : pintarResultados(data.partidos);
    }
    nlPaginacion(config.paginacion, data.paginacion, (pagina) => {
        estado.pagina[vista] = pagina;
        cargar(vista);
        nlDesplazar(document.getElementById(vista === 'proximos' ? 'panelProximos' : 'panelResultados'));
    });
}

// ── Filtro por juego, con los juegos agrupados por categoría ──
async function cargarJuegos() {
    const select = document.getElementById('filtroJuego');
    const data = await nlApi.get('/juegos');
    if (data.exito) {
        select.innerHTML = nlOpcionesJuegos(data.juegos, 'Todos los juegos');
        select.value = estado.idJuego;
    }
    select.addEventListener('change', () => {
        estado.idJuego = select.value;
        estado.pagina = { proximos: 1, resultados: 1 };
        actualizarUrl();
        cargar('proximos');
        cargar('resultados');
    });
}

// ── Pestañas (solo en celular; en PC se ven las dos columnas) ──
const pestanas = nlPestanas('pestanasPartidos', (vista) => {
    estado.vista = vista;
    nlMarcarMenu(VISTAS[vista].menu);
    actualizarUrl();
}, 'pestana-oculta');

cargarJuegos();
cargar('proximos');
cargar('resultados');
pestanas.activar(estado.vista);
