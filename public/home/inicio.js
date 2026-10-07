// =====================================================================
// NEXTLEAGUE - PORTADA: torneo destacado, estadísticas, torneos activos,
// próximos partidos y últimos resultados, con datos reales de la API.
// =====================================================================

// ── Torneo destacado (lo elige la API: uno con lugares libres y el mayor premio) ──
async function cargarDestacado() {
    const data = await nlApi.get('/torneos/destacado');
    const t = data.exito ? data.torneo : null;
    if (!t) return; // sin torneos activos, la tarjeta queda oculta

    const porcentaje = t.cupo > 0 ? Math.min(100, Math.round((t.inscriptos / t.cupo) * 100)) : 0;
    const tarjeta = document.getElementById('torneoDestacado');
    tarjeta.href = `../torneos/torneo?id=${t.id_torneo}`;
    tarjeta.setAttribute('aria-label', `Torneo destacado: ${t.nombre}`);
    document.getElementById('destacadoEstado').innerHTML = nlChipTorneo(t);
    document.getElementById('destacadoNombre').textContent = t.nombre;
    // Con premio, el monto va en grande; sin premio, en su lugar se dice qué
    // se juega (antes decía "Premio total" y abajo "Sin premio" en letra grande).
    const conPremio = Number(t.premio) > 0;
    document.getElementById('destacadoRotulo').textContent = conPremio ? 'Premio total' : `${t.juego} · ${nombreModulo(t.modulo)}`;
    const premio = document.getElementById('destacadoPremio');
    premio.textContent = conPremio ? formatearMoneda(t.premio, t.moneda) : '';
    premio.classList.toggle('oculto', !conPremio);
    document.getElementById('destacadoInscriptos').textContent =
        `${t.inscriptos} / ${t.cupo} ${t.modalidad === 'equipos' ? 'equipos' : 'jugadores'}`;
    const progreso = document.getElementById('destacadoProgreso');
    progreso.value = porcentaje;
    progreso.setAttribute('aria-label', `Lugares ocupados: ${porcentaje}%`);
    tarjeta.classList.remove('oculto');
}

// ── Estadísticas (antes decía "120+" fijo en el HTML) ──
// El rótulo se pone en singular cuando hay uno ("1 equipo registrado").
async function cargarEstadisticas() {
    const data = await nlApi.get('/estadisticas');
    if (!data.exito) return;
    const e = data.estadisticas;
    nlPintarNumero('cantidadEquipos', e.equipos);
    nlPintarNumero('cantidadUsuarios', e.usuarios);
    nlPintarNumero('cantidadTorneos', e.torneos);
    nlPintarNumero('cantidadElectronicos', e.torneos_electronicos);
}

// ── Torneos activos, con filtro por categoría ──
let categoriaElegida = '';

async function cargarTorneos() {
    const contenedor = document.getElementById('listaTorneos');
    contenedor.innerHTML = nlCargando('Cargando torneos…');

    const data = await nlApi.get(nlConParametros('/torneos', { estado: 'activos', categoria: categoriaElegida, por_pagina: 6 }));
    if (!data.exito) {
        contenedor.innerHTML = nlVacio(data.mensaje);
        return;
    }
    if (data.torneos.length === 0) {
        contenedor.innerHTML = nlVacio(
            categoriaElegida ? 'No hay torneos activos de esta categoría por ahora.' : 'Todavía no hay torneos activos.',
            { texto: 'Ver todos los torneos', href: nlConParametros('../torneos/explorar', { categoria: categoriaElegida }) }
        );
        return;
    }
    contenedor.innerHTML = data.torneos.map(nlTarjetaTorneo).join('');
}

document.querySelectorAll('#filtroCategoria [data-categoria]').forEach(boton => {
    boton.addEventListener('click', () => {
        document.querySelectorAll('#filtroCategoria [data-categoria]').forEach(b => b.setAttribute('aria-pressed', b === boton));
        categoriaElegida = boton.dataset.categoria;
        // "Ver todos" lleva a Explorar con la misma categoría ya filtrada
        document.getElementById('verTodos').href = nlConParametros('../torneos/explorar', { categoria: categoriaElegida });
        cargarTorneos();
    });
});

// ── Próximos partidos (como hojas de almanaque) y últimos resultados ──
async function cargarProximos() {
    const contenedor = document.getElementById('proximosPartidos');
    contenedor.innerHTML = nlCargando('Cargando calendario…');
    const data = await nlApi.get('/partidos/proximos?por_pagina=5');
    if (!data.exito || data.partidos.length === 0) {
        contenedor.innerHTML = nlVacio(data.exito ? 'No hay partidos programados por ahora.' : data.mensaje);
        return;
    }
    contenedor.innerHTML = data.partidos.map(p => nlCita(p)).join('');
}

async function cargarResultados() {
    const contenedor = document.getElementById('ultimosResultados');
    contenedor.innerHTML = nlCargando('Cargando resultados…');
    const data = await nlApi.get('/partidos/recientes?por_pagina=5');
    if (!data.exito || data.partidos.length === 0) {
        contenedor.innerHTML = nlVacio(data.exito ? 'Todavía no hay resultados cargados.' : data.mensaje);
        return;
    }
    contenedor.innerHTML = data.partidos.map(p => nlPartido(p, { torneo: true, enlace: true })).join('');
}

// ── "Crear torneo" solo tiene sentido para organizadores y administradores;
// a un participante logueado se le ofrece ir a sus torneos. ──
nextleagueObtenerSesion().then((sesion) => {
    if (sesion.logueado && sesion.usuario.rol === 'participante') {
        const boton = document.getElementById('botonCrearTorneo');
        boton.textContent = 'Mis torneos';
        boton.href = '../perfil/perfil#mis-torneos';
    }
});

cargarDestacado();
cargarEstadisticas();
cargarTorneos();
cargarProximos();
cargarResultados();
