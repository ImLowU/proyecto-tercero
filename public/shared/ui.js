// =====================================================================
// NEXTLEAGUE - PIEZAS DE INTERFAZ (compartidas por todas las páginas)
// Antes cada página armaba a mano sus tarjetas de torneo, sus filas de
// partido y sus alertas, y usaba emojis como íconos. Ahora esas piezas
// se arman acá, así se ven igual en todo el sitio.
// Se incluye después de api.js y formato.js.
// =====================================================================

// Escapa texto antes de insertarlo en el HTML (nombres de torneo, equipo o
// usuario los escribió otra persona; sin esto, un nombre con
// "<img onerror=...>" se ejecutaría para cualquiera que lo vea).
// También escapa las comillas, porque muchos textos van dentro de
// atributos (aria-label="..."): antes un nombre con " podía cerrar el
// atributo y agregar otro (por ejemplo onmouseover).
function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// Lee un parámetro de la URL: nlParametro('id') en torneo?id=4 → "4"
function nlParametro(nombre) {
    return new URLSearchParams(window.location.search).get(nombre);
}

// ── Íconos de línea (SVG): heredan el color del texto que los rodea ──
const NL_ICONOS = {
    inicio: '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
    trofeo: '<path d="M8 21h8M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4a3 3 0 0 0 3 4M17 6h3a3 3 0 0 1-3 4"/>',
    calendario: '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M16 3v4M8 3v4M4 11h16"/>',
    lista: '<path d="M9 6h11M9 12h11M9 18h11M5 6h.01M5 12h.01M5 18h.01"/>',
    usuario: '<circle cx="12" cy="8" r="4"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/>',
    usuarios: '<circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2M16 3.13a4 4 0 0 1 0 7.75M21 21v-2a4 4 0 0 0-3-3.85"/>',
    'usuario-mas': '<circle cx="9" cy="8" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2M19 8v6M16 11h6"/>',
    'usuario-menos': '<circle cx="9" cy="8" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2M16 11h6"/>',
    buscar: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-5-5"/>',
    mas: '<path d="M12 5v14M5 12h14"/>',
    editar: '<path d="M4 20h4L18.5 9.5a2.83 2.83 0 0 0-4-4L4 16v4"/><path d="M13.5 6.5l4 4"/>',
    salir: '<path d="M14 8V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-2"/><path d="M9 12h12M18 9l3 3-3 3"/>',
    escudo: '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
    ajustes: '<path d="M4 6h8M16 6h4M4 12h2M10 12h10M4 18h12"/><circle cx="14" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
    historial: '<path d="M12 8v4l3 2"/><path d="M3.05 11a9 9 0 1 1 .5 4"/><path d="M3 4v5h5"/>',
    descarga: '<path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M7 11l5 5 5-5M12 4v12"/>',
    modulos: '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>',
    grafico: '<path d="M4 20h16M7 16v-4M12 16V8M17 16V5"/>',
    ubicacion: '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
    reloj: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    archivo: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
    check: '<path d="M5 12l5 5L20 7"/>',
    x: '<path d="M18 6L6 18M6 6l12 12"/>',
    'flecha-derecha': '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'flecha-izquierda': '<path d="M19 12H5M11 6l-6 6 6 6"/>',
    alerta: '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
    ojo: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'ojo-tachado': '<path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.4 5.4A9.8 9.8 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6C3.9 8.3 2 12 2 12s3.5 7 10 7a9.6 9.6 0 0 0 5.4-1.6"/>',
    basura: '<path d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>',
    candado: '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    correo: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
    bandera: '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
    jugar: '<path d="M7 4v16l13-8z"/>',
    llaves: '<path d="M3 5h5v14H3M8 12h5M13 8h4v8h-4M17 12h4"/>',
    podio: '<path d="M3 20h18M4 20v-7h5v7M9 20V8h6v12M15 20v-5h5v5"/>',
    imagen: '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 8"/>',
    externo: '<path d="M14 4h6v6M20 4l-9 9M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',
    refrescar: '<path d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7"/>',
    control: '<rect x="2" y="7" width="20" height="11" rx="5"/><path d="M7 11v3M5.5 12.5h3M15 12h.01M18 11h.01"/>',
    pelota: '<circle cx="12" cy="12" r="9"/><path d="M12 7l4 3-1.5 5h-5L8 10z"/>',
    peon: '<circle cx="12" cy="6" r="3"/><path d="M10 9l-1 7h6l-1-7M7 20h10l-1-4H8z"/>',
    premio: '<circle cx="12" cy="12" r="9"/><path d="M15 9.5c-.5-1-1.6-1.5-3-1.5-1.7 0-3 .9-3 2s1.3 1.8 3 2 3 .9 3 2-1.3 2-3 2c-1.4 0-2.5-.5-3-1.5M12 6v2M12 16v2"/>',
    enviar: '<path d="M4 12l16-8-6 16-2.5-6.5z"/>',
    camara: '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/>',
};

// Ícono de categoría de juego (para chips y filtros)
const NL_ICONO_CATEGORIA = { deportivo: 'pelota', mental: 'peon', electronico: 'control' };
const NL_NOMBRE_CATEGORIA = { deportivo: 'Deportivo', mental: 'Mental', electronico: 'Electrónico' };

// Opciones de un <select> de juegos, agrupadas por categoría (<optgroup>).
// "primera" es el texto de la opción vacía (por ejemplo "Todos").
function nlOpcionesJuegos(juegos, primera) {
    const porCategoria = {};
    juegos.forEach(j => (porCategoria[j.categoria] ??= []).push(j));
    return `<option value="">${escapeHtml(primera)}</option>` + Object.entries(porCategoria).map(([categoria, lista]) => `
        <optgroup label="${escapeHtml(NL_NOMBRE_CATEGORIA[categoria] || categoria)}">
            ${lista.map(j => `<option value="${j.id_juego}">${escapeHtml(j.nombre)}</option>`).join('')}
        </optgroup>
    `).join('');
}

function nlIcono(nombre, clase = '') {
    return `<svg class="icono ${clase}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${NL_ICONOS[nombre] || ''}</svg>`;
}

// ── Chips de estado ──
const NL_CLASE_ESTADO_TORNEO = { pendiente: '', en_curso: 'chip-exito', finalizado: 'chip-gris', cancelado: 'chip-peligro' };
const NL_CLASE_ESTADO_RONDA = { borrador: 'chip-gris', publicada: 'chip-exito', cerrada: '' };

function nlChipEstadoTorneo(estado) {
    return `<span class="chip ${NL_CLASE_ESTADO_TORNEO[estado] ?? 'chip-gris'}">${escapeHtml(NOMBRE_ESTADO_TORNEO[estado] || estado)}</span>`;
}

function nlChipEstadoRonda(estado) {
    return `<span class="chip ${NL_CLASE_ESTADO_RONDA[estado] ?? 'chip-gris'}">${escapeHtml(NOMBRE_ESTADO_RONDA[estado] || estado)}</span>`;
}

// Chip de un torneo: si todavía no empezó pero el cupo está lleno, dice
// "Cupo completo" (antes decía "Inscripciones abiertas" aunque nadie más
// pudiera anotarse); si pasó su fecha de inicio sin que el organizador
// generara el calendario, "Inscripciones cerradas".
function nlChipTorneo(t) {
    if (t.estado === 'pendiente' && Number(t.inscripcion_vencida)) {
        return '<span class="chip chip-aviso">Inscripciones cerradas</span>';
    }
    if (t.estado === 'pendiente' && Number(t.cupo) > 0 && Number(t.inscriptos) >= Number(t.cupo)) {
        return '<span class="chip chip-aviso">Cupo completo</span>';
    }
    return nlChipEstadoTorneo(t.estado);
}

// ── Números con su rótulo en singular o plural ("1 equipo registrado") ──
// El rótulo lleva data-numero="<id del número>" y data-singular; el plural
// es el texto que ya tiene en el HTML.
function nlPintarNumero(id, valor) {
    const numero = document.getElementById(id);
    if (!numero) return;
    numero.textContent = Number(valor).toLocaleString('es-UY');
    document.querySelectorAll(`[data-numero="${id}"]`).forEach(rotulo => {
        rotulo.dataset.plural ??= rotulo.textContent;
        rotulo.textContent = Number(valor) === 1 ? rotulo.dataset.singular : rotulo.dataset.plural;
    });
}

// ── Desplazamiento: suave, salvo que la persona pidió "reducir movimiento"
// en su sistema (por mareos o por comodidad) ──
function nlDesplazar(elemento, opciones = {}) {
    if (!elemento) return;
    const reducir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    elemento.scrollIntoView({ behavior: reducir ? 'auto' : 'smooth', ...opciones });
}

// ── Imágenes ──
const NL_AVATAR_POR_DEFECTO = '../iconos/profile-128.webp';

// Portada de un torneo: su logo o, si no subieron uno, una portada genérica
// con el ícono de la categoría y el nombre del juego (antes se usaba la
// misma imagen de Valorant para cualquier torneo, aunque fuera de ajedrez).
function nlPortadaTorneo(t, ancho = 320, alto = 180) {
    if (t.logo) {
        return `<img src="${nlRutaSubida('torneos/logos', t.logo)}" alt="" loading="lazy" width="${ancho}" height="${alto}">`;
    }
    const icono = t.categoria === 'electronico' ? 'videogame' : 'trophy';
    return `
        <span class="portada-generica portada-generica--${escapeHtml(t.categoria || '')}">
            <img src="../iconos/${icono}-128.webp" alt="" width="64" height="64" loading="lazy">
            <span>${escapeHtml(t.juego || '')}</span>
        </span>
    `;
}

function nlAvatar(foto) {
    return nlRutaSubida('perfiles', foto) || NL_AVATAR_POR_DEFECTO;
}

// ── Tarjeta de torneo (portada, explorar y perfiles) ──
function nlTarjetaTorneo(t) {
    const porcentaje = t.cupo > 0 ? Math.min(100, Math.round((t.inscriptos / t.cupo) * 100)) : 0;
    const unidad = t.modalidad === 'equipos' ? 'equipos' : 'jugadores';
    const premio = Number(t.premio) > 0
        ? `<span class="tarjeta-torneo__premio">${formatearMoneda(t.premio, t.moneda)}</span>`
        : '<span>Sin premio</span>';
    return `
        <a class="tarjeta-torneo" href="../torneos/torneo?id=${t.id_torneo}">
            <div class="tarjeta-torneo__imagen">
                ${nlPortadaTorneo(t)}
                <span class="tarjeta-torneo__fecha">${formatearFechaCorta(t.fecha_inicio)}</span>
                <span class="tarjeta-torneo__estado">${nlChipTorneo(t)}</span>
            </div>
            <div class="tarjeta-torneo__cuerpo">
                <h3 class="tarjeta-torneo__titulo">${escapeHtml(t.nombre)}</h3>
                <p class="tarjeta-torneo__meta">${escapeHtml(t.juego)} · ${escapeHtml(nombreModulo(t.modulo))} · ${escapeHtml(NOMBRE_MODALIDAD[t.modalidad] || '')}</p>
                <div class="tarjeta-torneo__pie">
                    <div class="tarjeta-torneo__fila">
                        <span>${t.inscriptos} / ${t.cupo} ${unidad}</span>
                        ${premio}
                    </div>
                    <progress class="progreso" max="100" value="${porcentaje}" aria-label="Lugares ocupados: ${porcentaje}%"></progress>
                </div>
            </div>
        </a>
    `;
}

// ── Fila de partido (calendario, resultados, perfil) ──
// opciones.torneo: muestra el nombre del torneo (listas que mezclan torneos)
// opciones.enlace: la fila entera lleva a la página del torneo
// opciones.soloHora: muestra solo la hora (listas ya agrupadas por día)
// opciones.ronda: false para no repetir la ronda (listas ya agrupadas por ronda)
// opciones.destacado: resalta un partido del usuario
// opciones.etiqueta: HTML que va primero en el detalle (por ejemplo "Ganó")
function nlPartido(p, opciones = {}) {
    const jugado = p.estado === 'finalizado' && !Number(p.es_pase_libre);
    let clase = '';
    if (jugado && p.id_ganador) {
        clase = Number(p.id_ganador) === Number(p.id_local) ? 'ganador-local' : 'ganador-visitante';
    }
    if (Number(p.es_pase_libre)) clase = 'pase-libre';
    if (opciones.destacado) clase += ' partido--propio';

    const centro = jugado
        ? `<span class="partido__marcador">${p.puntaje_local} - ${p.puntaje_visitante}</span>`
        : `<span class="partido__vs">vs</span>`;
    const detalle = [];
    if (opciones.torneo && p.torneo) detalle.push(`<span>${nlIcono('trofeo')}${escapeHtml(p.torneo)}</span>`);
    if (opciones.ronda !== false && (p.nombre_ronda || p.ronda)) detalle.push(`<span>${escapeHtml(p.nombre_ronda || 'Ronda ' + p.ronda)}</span>`);
    if (opciones.destacado) detalle.unshift('<span class="chip">Tu partido</span>');
    if (opciones.etiqueta) detalle.unshift(opciones.etiqueta);
    if (Number(p.es_pase_libre)) {
        detalle.push('<span>Pase libre</span>');
    } else {
        // Sin fecha programada: si todavía no se jugó, la fecha está "a confirmar";
        // si ya se jugó, no se muestra ninguna (antes decía "Fecha a confirmar"
        // también en los resultados).
        if (p.fecha_hora) {
            const cuando = opciones.soloHora ? String(p.fecha_hora).slice(11, 16) : formatearFechaHora(p.fecha_hora);
            detalle.push(`<span>${nlIcono('reloj')}${cuando}</span>`);
        } else if (!jugado) {
            detalle.push(`<span>${nlIcono('reloj')}Fecha a confirmar</span>`);
        }
        if (p.lugar) detalle.push(`<span>${nlIcono('ubicacion')}${escapeHtml(p.lugar)}</span>`);
    }

    const etiqueta = opciones.enlace ? 'a' : 'div';
    const destino = opciones.enlace ? ` href="../torneos/torneo?id=${p.id_torneo}"` : '';
    return `
        <${etiqueta} class="partido ${clase}"${destino}>
            <span class="partido__lado partido__lado--local">${escapeHtml(p.nombre_local || 'Por definir')}</span>
            <span class="partido__centro">${centro}</span>
            <span class="partido__lado partido__lado--visitante">${escapeHtml(p.nombre_visitante || (Number(p.es_pase_libre) ? '—' : 'Por definir'))}</span>
            <span class="partido__detalle">${detalle.join('')}</span>
        </${etiqueta}>
    `;
}

// ── Próximo partido como "cita" del calendario ──
// opciones.hoja: 'fecha' (hoja de almanaque con el día) o 'hora' (cuando la
// lista ya está agrupada por día y alcanza con la hora).
function nlCita(p, opciones = {}) {
    const fecha = partesDeFecha(p.fecha_hora);
    let hoja;
    if (!fecha) {
        hoja = `<span class="cita__fecha cita__fecha--sin">${nlIcono('calendario')}<small>A conf.</small></span>`;
    } else if (opciones.hoja === 'hora') {
        hoja = `<span class="cita__fecha cita__fecha--hora"><small>${fecha.semana}</small><strong>${fecha.hora}</strong></span>`;
    } else {
        hoja = `<span class="cita__fecha"><small>${fecha.semana}</small><strong>${fecha.dia}</strong><small>${fecha.mes}</small></span>`;
    }

    const detalle = [];
    if (fecha && opciones.hoja !== 'hora') detalle.push(`<span>${nlIcono('reloj')}${fecha.hora}</span>`);
    if (p.lugar) detalle.push(`<span>${nlIcono('ubicacion')}${escapeHtml(p.lugar)}</span>`);
    detalle.push(`<span>${nlIcono('trofeo')}${escapeHtml(p.torneo)} · ${escapeHtml(p.nombre_ronda || 'Ronda ' + p.ronda)}</span>`);

    return `
        <a class="cita" href="../torneos/torneo?id=${p.id_torneo}">
            ${hoja}
            <span>
                <span class="cita__equipos">${escapeHtml(p.nombre_local)}<span class="partido__vs">vs</span>${escapeHtml(p.nombre_visitante)}</span>
                <span class="cita__detalle">${detalle.join('')}</span>
            </span>
        </a>
    `;
}

// ── Resultado de un partido visto desde uno de sus lados ──
// idPropio: el participante (jugador o equipo) desde el que se mira.
// persona: 'vos' → "Ganaste"; si no, "Ganó".
function nlChipResultado(p, idPropio, persona = 'otro') {
    const local = Number(p.id_local) === Number(idPropio);
    const propio = Number(local ? p.puntaje_local : p.puntaje_visitante);
    const rival = Number(local ? p.puntaje_visitante : p.puntaje_local);
    const [texto, clase] = propio > rival ? ['Ganó', 'chip-exito'] : propio < rival ? ['Perdió', 'chip-peligro'] : ['Empató', 'chip-gris'];
    const conjugado = persona === 'vos' ? { 'Ganó': 'Ganaste', 'Perdió': 'Perdiste', 'Empató': 'Empataste' }[texto] : texto;
    return `<span class="chip ${clase}">${conjugado}</span>`;
}

// ── Enlaces a los perfiles públicos ──
function nlEnlaceJugador(idUsuario) {
    return `../perfil/jugador?id=${Number(idUsuario)}`;
}

function nlEnlaceEquipo(idEquipo) {
    return `../perfil/equipo?id=${Number(idEquipo)}`;
}

// Enlace al perfil de un participante de torneo (jugador o equipo)
function nlEnlaceParticipante(p) {
    if (p.tipo === 'equipo' && p.id_equipo) return nlEnlaceEquipo(p.id_equipo);
    return p.id_usuario ? nlEnlaceJugador(p.id_usuario) : null;
}

// ── Requisitos de la contraseña, marcados mientras se escribe ──
// Son los mismos que controla la API (Validador::problemaContrasena).
const NL_REQUISITOS_CONTRASENA = [
    ['Entre 8 y 72 caracteres', (c) => c.length >= 8 && c.length <= 72],
    ['Una mayúscula', (c) => /[A-Z]/.test(c)],
    ['Un número', (c) => /[0-9]/.test(c)],
    ['Un carácter especial (!@#$%…)', (c) => /[^a-zA-Z0-9]/.test(c)],
];

function nlRequisitosContrasena(input, lista) {
    if (typeof input === 'string') input = document.getElementById(input);
    if (typeof lista === 'string') lista = document.getElementById(lista);
    function pintar() {
        lista.innerHTML = NL_REQUISITOS_CONTRASENA.map(([texto, cumple]) => {
            const ok = cumple(input.value);
            return `<li class="${ok ? 'cumple' : ''}">${nlIcono(ok ? 'check' : 'x')}<span>${texto}</span><span class="solo-lectores">${ok ? '(cumplido)' : '(falta)'}</span></li>`;
        }).join('');
    }
    input.addEventListener('input', pintar);
    pintar();
    return () => NL_REQUISITOS_CONTRASENA.every(([, cumple]) => cumple(input.value));
}

// Botón para mostrar u ocultar lo escrito en un campo de contraseña
function nlBotonVerContrasena(boton, input) {
    if (typeof boton === 'string') boton = document.getElementById(boton);
    if (typeof input === 'string') input = document.getElementById(input);
    boton.innerHTML = nlIcono('ojo');
    boton.setAttribute('aria-label', 'Mostrar contraseña');
    boton.addEventListener('click', () => {
        const ver = input.type === 'password';
        input.type = ver ? 'text' : 'password';
        boton.innerHTML = nlIcono(ver ? 'ojo-tachado' : 'ojo');
        boton.setAttribute('aria-label', ver ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
}

// ── Estados de carga y vacíos ──
function nlCargando(texto = 'Cargando…') {
    return `<div class="cargando" role="status"><span class="girando" aria-hidden="true"></span>${escapeHtml(texto)}</div>`;
}

// accion (opcional): { texto, href }
function nlVacio(texto, accion = null) {
    return `<div class="vacio"><p>${escapeHtml(texto)}</p>${accion ? `<a class="btn btn-contorno btn-chico" href="${accion.href}">${escapeHtml(accion.texto)}</a>` : ''}</div>`;
}

// ── Alertas: role="alert" para que los lectores de pantalla las anuncien ──
// tipo: 'exito' | 'error' | 'aviso' | 'info'
function nlAlerta(contenedor, mensaje, tipo = 'info') {
    if (typeof contenedor === 'string') contenedor = document.getElementById(contenedor);
    if (!contenedor) return;
    if (!mensaje) {
        contenedor.innerHTML = '';
        contenedor.classList.add('oculto');
        return;
    }
    const icono = { exito: 'check', error: 'alerta', aviso: 'alerta', info: 'info' }[tipo] || 'info';
    contenedor.classList.remove('oculto');
    contenedor.innerHTML = `<div class="alerta ${tipo === 'info' ? '' : 'alerta-' + tipo}" role="${tipo === 'error' ? 'alert' : 'status'}">${nlIcono(icono)}<span>${escapeHtml(mensaje)}</span></div>`;
}

// ── Botón ocupado mientras se espera a la API (evita doble envío) ──
function nlBotonOcupado(boton, ocupado, textoOcupado = 'Guardando…') {
    if (!boton) return;
    if (ocupado) {
        boton.dataset.textoOriginal = boton.innerHTML;
        boton.disabled = true;
        boton.innerHTML = `<span class="girando" aria-hidden="true"></span>${escapeHtml(textoOcupado)}`;
    } else {
        boton.disabled = false;
        if (boton.dataset.textoOriginal) boton.innerHTML = boton.dataset.textoOriginal;
    }
}

// ── Confirmación con una ventana propia en vez del confirm() del navegador ──
// Devuelve una promesa: true si la persona acepta.
function nlConfirmar(mensaje, { titulo = '¿Estás seguro?', aceptar = 'Aceptar', peligro = false } = {}) {
    return new Promise((resolver) => {
        const dialogo = document.createElement('dialog');
        dialogo.className = 'modal';
        dialogo.setAttribute('aria-labelledby', 'nlConfirmarTitulo');
        dialogo.innerHTML = `
            <h2 id="nlConfirmarTitulo">${escapeHtml(titulo)}</h2>
            <p class="texto-secundario">${escapeHtml(mensaje)}</p>
            <div class="modal__acciones">
                <button type="button" class="btn btn-secundario" value="no">Volver</button>
                <button type="button" class="btn ${peligro ? 'btn-peligro' : 'btn-primario'}" value="si">${escapeHtml(aceptar)}</button>
            </div>
        `;
        // Se responde en el mismo clic (no se espera al evento "close", que el
        // navegador dispara más tarde). Escape dispara "cancel" en el momento.
        let respondida = false;
        function responder(valor) {
            if (respondida) return;
            respondida = true;
            if (dialogo.open) dialogo.close();
            dialogo.remove();
            resolver(valor);
        }
        dialogo.querySelectorAll('button').forEach(boton => boton.addEventListener('click', () => responder(boton.value === 'si')));
        dialogo.addEventListener('cancel', (e) => { e.preventDefault(); responder(false); });
        dialogo.addEventListener('close', () => responder(false));
        document.body.appendChild(dialogo);
        dialogo.showModal();
    });
}

// ── Paginación: botones Anterior / Siguiente ──
function nlPaginacion(contenedor, paginacion, alCambiar) {
    if (typeof contenedor === 'string') contenedor = document.getElementById(contenedor);
    if (!contenedor) return;
    if (!paginacion || paginacion.paginas <= 1) {
        contenedor.innerHTML = '';
        return;
    }
    const { pagina, paginas } = paginacion;
    contenedor.innerHTML = `
        <button type="button" class="btn btn-secundario btn-chico" data-pagina="${pagina - 1}" ${pagina <= 1 ? 'disabled' : ''}>${nlIcono('flecha-izquierda')}Anterior</button>
        <span>Página ${pagina} de ${paginas}</span>
        <button type="button" class="btn btn-secundario btn-chico" data-pagina="${pagina + 1}" ${pagina >= paginas ? 'disabled' : ''}>Siguiente${nlIcono('flecha-derecha')}</button>
    `;
    contenedor.querySelectorAll('[data-pagina]').forEach(boton => {
        boton.addEventListener('click', () => alCambiar(Number(boton.dataset.pagina)));
    });
}

// ── Pestañas accesibles (role="tablist") ──
// Cada botón .pestana tiene aria-controls con el id del panel que muestra.
// claseOculta: la clase que esconde los paneles no elegidos (una página
// puede usar una que solo oculte en celular).
function nlPestanas(lista, alCambiar = null, claseOculta = 'oculto') {
    if (typeof lista === 'string') lista = document.getElementById(lista);
    if (!lista) return;
    const botones = [...lista.querySelectorAll('[role="tab"]')];

    function activar(boton, enfocar = false) {
        botones.forEach(b => {
            const activo = b === boton;
            b.setAttribute('aria-selected', activo);
            b.tabIndex = activo ? 0 : -1;
            document.getElementById(b.getAttribute('aria-controls'))?.classList.toggle(claseOculta, !activo);
        });
        if (enfocar) boton.focus();
        if (alCambiar) alCambiar(boton.dataset.pestana || boton.id);
    }

    // Las pestañas que no aplican están ocultas (por ejemplo "Llaves" en una
    // liga): el teclado las saltea. Antes la flecha caía en una pestaña oculta
    // y quedaba un panel vacío sin ninguna pestaña marcada.
    const visibles = () => botones.filter(b => b.getClientRects().length > 0);

    botones.forEach((boton) => {
        boton.addEventListener('click', () => activar(boton));
        // Teclado, como en el patrón de pestañas accesibles: flechas para la
        // anterior o la siguiente; Inicio y Fin para la primera o la última.
        boton.addEventListener('keydown', (e) => {
            const disponibles = visibles();
            const i = disponibles.indexOf(boton);
            const destino = {
                ArrowRight: disponibles[(i + 1) % disponibles.length],
                ArrowLeft: disponibles[(i - 1 + disponibles.length) % disponibles.length],
                Home: disponibles[0],
                End: disponibles[disponibles.length - 1],
            }[e.key];
            if (!destino) return;
            e.preventDefault(); // Inicio y Fin no mueven la página
            activar(destino, true);
        });
    });
    return { activar: (clave) => { const b = botones.find(x => x.dataset.pestana === clave); if (b) activar(b); } };
}
