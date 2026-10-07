// =====================================================================
// NEXTLEAGUE - FUNCIONES COMPARTIDAS POR "TORNEO" Y "GESTIONAR TORNEO"
// =====================================================================

function nombreRonda(ronda) {
    return ronda.nombre || `Ronda ${ronda.numero}`;
}

function esParticipanteDelPartido(p, idParticipante) {
    return idParticipante && [Number(p.id_local), Number(p.id_visitante)].includes(Number(idParticipante));
}

// El campeón de un torneo finalizado: el ganador de la final (eliminación
// directa) o el primero de la tabla (liga y suizo).
function campeonDelTorneo(torneo, rondas, posiciones) {
    if (torneo.estado !== 'finalizado') return null;
    if (torneo.modulo === 'eliminacion_directa') {
        const final = rondas[rondas.length - 1]?.partidos?.[0];
        if (!final || !final.id_ganador) return null;
        return Number(final.id_ganador) === Number(final.id_local) ? final.nombre_local : final.nombre_visitante;
    }
    return posiciones?.[0]?.nombre || null;
}

// Explicación en una frase de cómo se juega el torneo (pestaña Resumen).
function explicacionDelFormato(torneo) {
    const c = torneo.configuracion || {};
    const quienes = torneo.modalidad === 'equipos' ? 'equipos' : 'jugadores';
    if (torneo.modulo === 'liga') {
        return `Todos contra todos${Number(c.ida_y_vuelta) ? ', ida y vuelta' : ''}: cada partido suma puntos y gana quien termina primero en la tabla.`;
    }
    if (torneo.modulo === 'eliminacion_directa') {
        return `Llaves de eliminación directa: quien pierde queda afuera y el ganador avanza hasta la final. Si los ${quienes} no completan las llaves, algunos pasan la primera ronda sin jugar (pase libre).`;
    }
    if (torneo.modulo === 'sistema_suizo') {
        const rondas = Number(c.rondas_suizo) ? `${c.rondas_suizo} rondas` : 'una cantidad de rondas que depende de los inscriptos';
        return `Sistema suizo: ${rondas}. En cada ronda se enfrentan ${quienes} con puntajes parecidos, sin repetir rivales. Los empates en la tabla se desempatan por Buchholz (la suma de los puntos de los rivales).`;
    }
    return '';
}

// Datos de la configuración para mostrar en una lista <dl>.
function datosDeConfiguracion(torneo) {
    const c = torneo.configuracion || {};
    const si = (valor) => (Number(valor) ? 'Sí' : 'No');
    const datos = [
        ['Formato', nombreModulo(torneo.modulo)],
        ['Modalidad', NOMBRE_MODALIDAD[torneo.modalidad] || torneo.modalidad],
        ['Cupo', `${torneo.cupo} ${torneo.modalidad === 'equipos' ? 'equipos' : 'jugadores'}`],
    ];
    if (torneo.modulo !== 'eliminacion_directa') {
        datos.push(['Puntos', `${c.puntos_victoria} por victoria · ${c.puntos_empate} por empate · ${c.puntos_derrota} por derrota`]);
        datos.push(['Empates', Number(c.permitir_empates) ? 'Permitidos' : 'No permitidos']);
    }
    if (torneo.modulo === 'liga') datos.push(['Ida y vuelta', si(c.ida_y_vuelta)]);
    if (torneo.modulo === 'sistema_suizo') datos.push(['Rondas', Number(c.rondas_suizo) ? c.rondas_suizo : 'Automático']);
    if (torneo.modalidad === 'equipos' && c.max_integrantes_equipo) datos.push(['Integrantes por equipo', `Hasta ${c.max_integrantes_equipo}`]);
    datos.push(['Correcciones de resultados', Number(c.permitir_correcciones) ? 'El organizador está autorizado' : 'Solo el administrador']);
    return datos;
}

function pintarDatos(dl, datos) {
    dl.innerHTML = datos.map(([nombre, valor]) => `<div><dt>${escapeHtml(nombre)}</dt><dd>${escapeHtml(String(valor))}</dd></div>`).join('');
}

// Portada chica del encabezado: el logo o la portada genérica de la categoría.
function portadaDeTorneo(torneo) {
    return nlPortadaTorneo(torneo, 160, 160);
}
