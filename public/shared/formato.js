// =====================================================================
// NEXTLEAGUE - FORMATO DE DATOS PARA MOSTRAR (compartido)
// Antes formatearFecha, formatearMoneda y los nombres de los formatos
// estaban copiados en varias páginas; ahora están en un solo lugar.
// =====================================================================

const NOMBRE_ESTADO_TORNEO = {
    pendiente: 'Inscripciones abiertas',
    en_curso: 'En curso',
    finalizado: 'Finalizado',
    cancelado: 'Cancelado',
};

const NOMBRE_ESTADO_RONDA = { borrador: 'Borrador', publicada: 'Publicada', cerrada: 'Cerrada' };

const NOMBRE_ESTADO_PARTIDO = { pendiente: 'Por jugar', finalizado: 'Finalizado' };

const NOMBRE_MODULO = {
    liga: 'Liga',
    eliminacion_directa: 'Eliminación directa',
    sistema_suizo: 'Sistema suizo',
};

const NOMBRE_MODALIDAD = { individual: 'Individual', equipos: 'Por equipos' };

// "2026-10-17" o "2026-10-17 19:00:00" → "17/10/2026"
function formatearFecha(fechaISO) {
    if (!fechaISO) return '';
    const [anio, mes, dia] = String(fechaISO).slice(0, 10).split('-');
    return `${dia}/${mes}/${anio}`;
}

// "2026-10-17 19:00:00" → "17/10/2026 19:00"
function formatearFechaHora(fechaISO) {
    if (!fechaISO) return '';
    return `${formatearFecha(fechaISO)} ${String(fechaISO).slice(11, 16)}`.trim();
}

// Ahora, con el formato de la base ("2026-10-17 19:00"), para comparar
// como texto con las fechas que manda la API (hora local).
function nlAhora() {
    const d = new Date();
    const dos = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())} ${dos(d.getHours())}:${dos(d.getMinutes())}`;
}

// Hoy: "2026-10-17"
const nlHoy = () => nlAhora().slice(0, 10);

const NL_MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'set', 'oct', 'nov', 'dic'];
const NL_DIAS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

// "2026-10-17" → "17 oct"
function formatearFechaCorta(fechaISO) {
    if (!fechaISO) return '';
    const [, mes, dia] = String(fechaISO).slice(0, 10).split('-');
    return `${Number(dia)} ${NL_MESES[Number(mes) - 1]}`;
}

// "2026-10-17 19:00:00" → { semana: 'vie', dia: 17, mes: 'oct', hora: '19:00' }
// (para las "hojas de almanaque" del calendario)
function partesDeFecha(fechaISO) {
    if (!fechaISO) return null;
    const [anio, mes, dia] = String(fechaISO).slice(0, 10).split('-').map(Number);
    return {
        semana: NL_DIAS[new Date(anio, mes - 1, dia).getDay()],
        dia,
        mes: NL_MESES[mes - 1],
        hora: String(fechaISO).slice(11, 16),
    };
}

const NL_DIAS_LARGOS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
const NL_MESES_LARGOS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
    'setiembre', 'octubre', 'noviembre', 'diciembre'];

// "2026-10-17" → "Hoy", "Mañana" o "Viernes 17 de octubre" (con el año si no es el actual)
function formatearDia(fechaISO) {
    if (!fechaISO) return '';
    const [anio, mes, dia] = String(fechaISO).slice(0, 10).split('-').map(Number);
    const fecha = new Date(anio, mes - 1, dia);
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const diferencia = Math.round((fecha - hoy) / 86400000);
    const texto = `${NL_DIAS_LARGOS[fecha.getDay()]} ${dia} de ${NL_MESES_LARGOS[mes - 1]}${anio !== hoy.getFullYear() ? ' de ' + anio : ''}`;
    if (diferencia === 0) return `Hoy · ${texto}`;
    if (diferencia === 1) return `Mañana · ${texto}`;
    if (diferencia === -1) return `Ayer · ${texto}`;
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

// Rango de fechas de un torneo:
//   "2026-09-20", "2026-09-30" → "20 al 30 de setiembre de 2026"
//   "2026-09-20", "2026-10-05" → "20 de setiembre al 5 de octubre de 2026"
function formatearRango(inicioISO, finISO) {
    if (!inicioISO) return '';
    const [a1, m1, d1] = String(inicioISO).slice(0, 10).split('-').map(Number);
    const [a2, m2, d2] = String(finISO || inicioISO).slice(0, 10).split('-').map(Number);
    if (a1 === a2 && m1 === m2 && d1 === d2) return `${d1} de ${NL_MESES_LARGOS[m1 - 1]} de ${a1}`;
    if (a1 === a2 && m1 === m2) return `${d1} al ${d2} de ${NL_MESES_LARGOS[m1 - 1]} de ${a1}`;
    if (a1 === a2) return `${d1} de ${NL_MESES_LARGOS[m1 - 1]} al ${d2} de ${NL_MESES_LARGOS[m2 - 1]} de ${a1}`;
    return `${d1} de ${NL_MESES_LARGOS[m1 - 1]} de ${a1} al ${d2} de ${NL_MESES_LARGOS[m2 - 1]} de ${a2}`;
}

// "2026-10-05 16:28:01" → "octubre de 2026"
function formatearMesAnio(fechaISO) {
    if (!fechaISO) return '';
    const [anio, mes] = String(fechaISO).slice(0, 7).split('-').map(Number);
    return `${NL_MESES_LARGOS[mes - 1]} de ${anio}`;
}

function formatearMoneda(monto, moneda) {
    const simbolo = moneda === 'USD' ? 'US$' : '$';
    return `${simbolo} ${Number(monto).toLocaleString('es-UY')}`;
}

function nombreModulo(codigo) {
    return NOMBRE_MODULO[codigo] || codigo;
}
