// =====================================================================
// NEXTLEAGUE - PERFIL PÚBLICO DE UN JUGADOR (jugador?id=7)
// Página nueva: antes no había forma de ver a otro jugador. La API nunca
// devuelve el correo (antes cualquiera podía juntar los correos de todos
// cambiando el número en la dirección) y solo cuenta torneos publicados.
// =====================================================================

const idJugador = Number(nlParametro('id'));
const campo = (id) => document.getElementById(id);

function mostrarError(mensaje) {
    campo('estadoCarga').innerHTML = nlVacio(mensaje, { texto: 'Ver torneos', href: '../torneos/explorar' });
}

function pintar(j, sesion) {
    document.title = `${j.nombre_usuario} · NextLeague`;
    campo('fotoJugador').src = nlAvatar(j.foto_perfil);
    campo('fotoJugador').alt = `Foto de ${j.nombre_usuario}`;
    campo('nombreJugador').textContent = j.nombre_usuario;
    campo('chipsJugador').innerHTML = `<span class="chip">${escapeHtml(j.nombre_rol || NL_NOMBRE_ROL[j.rol] || j.rol)}</span>`;
    campo('metaJugador').innerHTML = `<span>${nlIcono('calendario')}En NextLeague desde ${escapeHtml(formatearMesAnio(j.fecha_registro))}</span>`;
    campo('bioJugador').textContent = j.biografia || '';
    if (sesion.logueado && Number(sesion.usuario.id_usuario) === Number(j.id_usuario)) {
        campo('accionesJugador').innerHTML = `<a class="btn btn-secundario btn-chico" href="perfil#cuenta">${nlIcono('editar')}Editar mi perfil</a>`;
    }

    // Números: la efectividad es el porcentaje de partidos ganados
    const p = j.partidos;
    const efectividad = p.pj ? `${Math.round((p.pg / p.pj) * 100)}%` : '—';
    const numeros = [
        [j.torneos_jugados, 'Torneos jugados'],
        [p.pj, 'Partidos jugados'],
        [p.pg, 'Victorias'],
        [efectividad, 'Efectividad'],
    ];
    if (j.torneos_organizados > 0) numeros.push([j.torneos_organizados, 'Torneos organizados']);
    campo('numerosJugador').innerHTML = numeros.map(([valor, texto]) =>
        `<li class="numero-perfil"><strong>${escapeHtml(String(valor))}</strong><span>${texto}</span></li>`).join('');

    campo('proximosJugador').innerHTML = j.proximos.length
        ? j.proximos.map(partido => nlCita(partido)).join('')
        : nlVacio('No tiene partidos programados.');
    campo('resultadosJugador').innerHTML = j.recientes.length
        ? j.recientes.map(partido => nlPartido(partido, { torneo: true, enlace: true, etiqueta: nlChipResultado(partido, partido.id_propio) })).join('')
        : nlVacio('Todavía no jugó partidos.');

    campo('torneosJuega').innerHTML = j.torneos.length
        ? j.torneos.map(nlTarjetaTorneo).join('')
        : nlVacio('Todavía no juega ningún torneo.');
    if (j.organiza.length) {
        campo('bloqueOrganiza').classList.remove('oculto');
        campo('torneosOrganiza').innerHTML = j.organiza.map(nlTarjetaTorneo).join('');
    }
}

async function iniciar() {
    if (!idJugador) {
        mostrarError('Falta indicar qué jugador querés ver.');
        return;
    }
    const [data, sesion] = await Promise.all([nlApi.get(`/usuarios/${idJugador}`), nextleagueObtenerSesion()]);
    if (!data.exito) {
        mostrarError(data.mensaje || 'No encontramos a este jugador.');
        return;
    }
    pintar(data.usuario, sesion);
    campo('estadoCarga').classList.add('oculto');
    campo('contenidoJugador').classList.remove('oculto');
}

iniciar();
