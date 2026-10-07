// =====================================================================
// NEXTLEAGUE - PERFIL PÚBLICO DE UN EQUIPO (equipo?id=3)
// Página nueva: antes los equipos eran solo un nombre en la lista del
// torneo. Ahora se ven sus integrantes (con enlace a cada jugador), el
// capitán, sus partidos y sus números. Un equipo pertenece a un torneo:
// si el torneo está oculto, la API tampoco muestra el equipo.
// =====================================================================

const idEquipo = Number(nlParametro('id'));
const campo = (id) => document.getElementById(id);

function mostrarError(mensaje) {
    campo('estadoCarga').innerHTML = nlVacio(mensaje, { texto: 'Ver torneos', href: '../torneos/explorar' });
}

// Ganados, empatados y perdidos desde el lado del equipo
function resumenDePartidos(partidos, idPropio) {
    const jugados = partidos.filter(p => p.estado === 'finalizado' && !Number(p.es_pase_libre));
    const resumen = { pj: jugados.length, pg: 0, pe: 0, pp: 0 };
    jugados.forEach(p => {
        const local = Number(p.id_local) === Number(idPropio);
        const propio = Number(local ? p.puntaje_local : p.puntaje_visitante);
        const rival = Number(local ? p.puntaje_visitante : p.puntaje_local);
        if (propio > rival) resumen.pg++; else if (propio < rival) resumen.pp++; else resumen.pe++;
    });
    return resumen;
}

function pintar(e, torneo, posicion) {
    document.title = `${e.nombre} · NextLeague`;
    campo('nombreEquipo').textContent = e.nombre;
    if (e.logo) campo('logoEquipo').src = nlRutaSubida('equipos', e.logo);
    campo('logoEquipo').alt = `Escudo de ${e.nombre}`;
    campo('chipsEquipo').innerHTML = [
        `<span class="chip">Equipo</span>`,
        nlChipEstadoTorneo(e.estado_torneo),
        torneo ? `<span class="chip chip-gris">${nlIcono(NL_ICONO_CATEGORIA[torneo.categoria] || 'trofeo')}${escapeHtml(torneo.juego)}</span>` : '',
    ].join('');
    campo('metaEquipo').innerHTML = `
        <span>${nlIcono('trofeo')}<a class="tocable" href="../torneos/torneo?id=${e.id_torneo}">${escapeHtml(e.torneo)}</a></span>
        ${e.capitan ? `<span>${nlIcono('escudo')}Capitán: <a class="tocable" href="${nlEnlaceJugador(e.id_capitan)}">${escapeHtml(e.capitan)}</a></span>` : ''}
        <span>${nlIcono('calendario')}Creado en ${escapeHtml(formatearMesAnio(e.fecha_creacion))}</span>
    `;
    campo('accionesEquipo').innerHTML = `<a class="btn btn-secundario btn-chico" href="../torneos/torneo?id=${e.id_torneo}#participantes">${nlIcono('usuarios')}Ver todos los equipos</a>`;

    const r = resumenDePartidos(e.partidos, e.id_participante);
    const numeros = [
        [e.miembros.length, e.miembros.length === 1 ? 'Integrante' : 'Integrantes'],
        [r.pj, 'Partidos jugados'],
        [r.pg, 'Ganados'],
        [r.pe, 'Empatados'],
        [r.pp, 'Perdidos'],
    ];
    if (posicion) numeros.push([`${posicion}°`, 'En la tabla']);
    campo('numerosEquipo').innerHTML = numeros.map(([valor, texto]) =>
        `<li class="numero-perfil"><strong>${escapeHtml(String(valor))}</strong><span>${texto}</span></li>`).join('');

    campo('miembrosEquipo').innerHTML = e.miembros.length ? e.miembros.map(m => `
        <li class="miembro">
            <a href="${nlEnlaceJugador(m.id_usuario)}">
                <img src="${nlAvatar(m.foto_perfil)}" alt="" width="40" height="40" loading="lazy">
                <span>
                    <strong>${escapeHtml(m.nombre_usuario)}</strong>
                    <small>${Number(m.id_usuario) === Number(e.id_capitan) ? 'Capitán · ' : ''}Se unió el ${formatearFecha(m.fecha_union)}</small>
                </span>
            </a>
        </li>
    `).join('') : `<li>${nlVacio('El equipo todavía no tiene integrantes.')}</li>`;

    const proximos = e.partidos.filter(p => p.estado === 'pendiente' && p.id_local && p.id_visitante && p.estado_ronda !== 'borrador');
    const jugados = e.partidos.filter(p => p.estado === 'finalizado' && !Number(p.es_pase_libre)).reverse();
    campo('proximosEquipo').innerHTML = proximos.length
        ? proximos.map(p => nlCita({ ...p, torneo: e.torneo })).join('')
        : nlVacio(e.estado_torneo === 'pendiente' ? 'El calendario se arma cuando cierran las inscripciones.' : 'No tiene partidos pendientes.');
    campo('jugadosEquipo').innerHTML = jugados.length
        ? jugados.map(p => nlPartido(p, { etiqueta: nlChipResultado(p, e.id_participante) })).join('')
        : nlVacio('Todavía no jugó partidos.');
}

async function iniciar() {
    if (!idEquipo) {
        mostrarError('Falta indicar qué equipo querés ver.');
        return;
    }
    const data = await nlApi.get(`/equipos/${idEquipo}`);
    if (!data.exito) {
        mostrarError(data.mensaje || 'No encontramos este equipo.');
        return;
    }
    const equipo = data.equipo;
    // Datos del torneo (juego, formato) y, en liga o suizo, su lugar en la tabla
    const respuestaTorneo = await nlApi.get(`/torneos/${equipo.id_torneo}`);
    const torneo = respuestaTorneo.exito ? respuestaTorneo.torneo : null;
    let posicion = null;
    if (torneo && torneo.modulo !== 'eliminacion_directa') {
        const tabla = await nlApi.get(`/torneos/${equipo.id_torneo}/posiciones`);
        const fila = tabla.exito ? tabla.posiciones.find(f => Number(f.id_participante) === Number(equipo.id_participante)) : null;
        if (fila && Number(fila.pj) > 0) posicion = fila.posicion;
    }
    pintar(equipo, torneo, posicion);
    campo('estadoCarga').classList.add('oculto');
    campo('contenidoEquipo').classList.remove('oculto');
}

iniciar();
