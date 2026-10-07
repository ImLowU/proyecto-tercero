// =====================================================================
// NEXTLEAGUE - GESTIONAR TORNEO (organizador del torneo y administradores)
// Antes el organizador manejaba su torneo con unos botones sueltos en la
// misma página pública y un formulario por partido, sin poder programar
// fecha y lugar, ni inscribir a otros, ni ver el historial de correcciones.
// Ahora tiene su propia pantalla:
//   - los pasos del torneo y cuál es el próximo, con su botón;
//   - por ronda: fecha y lugar de cada partido, resultados (se guardan
//     todos juntos) y publicar / cerrar / reabrir la ronda;
//   - inscribir y quitar participantes, visibilidad, configuración,
//     reporte CSV, cancelar o eliminar.
// La página solo ordena las acciones: la API vuelve a controlar cada
// permiso y cada regla (por ejemplo, si se permiten correcciones).
// =====================================================================

const idTorneo = Number(nlParametro('id'));
let sesion = null;
let torneo = null;
let rondas = [];
let totalRondas = null;
let puedeGenerarSiguiente = false;
let participantes = [];
let posiciones = [];
let idRondaElegida = null;

// ── Carga ──
async function cargarTodo() {
    const data = await nlApi.get(`/torneos/${idTorneo}`);
    if (!data.exito) {
        mostrarError(data.mensaje || 'No encontramos este torneo.');
        return false;
    }
    if (!data.torneo.permisos.gestionar) {
        mostrarError('Solo el organizador del torneo y los administradores pueden gestionarlo.');
        return false;
    }
    torneo = data.torneo;
    const usaTabla = torneo.modulo !== 'eliminacion_directa';
    const [calendario, inscriptos, tabla] = await Promise.all([
        nlApi.get(`/torneos/${idTorneo}/calendario`),
        nlApi.get(`/torneos/${idTorneo}/participantes`),
        usaTabla && torneo.estado === 'finalizado' ? nlApi.get(`/torneos/${idTorneo}/posiciones`) : Promise.resolve(null),
    ]);
    rondas = calendario.exito ? calendario.rondas : [];
    totalRondas = calendario.total_rondas;
    puedeGenerarSiguiente = Boolean(calendario.puede_generar_siguiente);
    participantes = inscriptos.exito ? inscriptos.participantes : [];
    posiciones = tabla?.exito ? tabla.posiciones : [];

    pintarCabecera();
    pintarPasos();
    pintarProximoPaso();
    pintarRondas();
    pintarInscripciones();
    pintarVisibilidad();
    pintarDatos(document.getElementById('datosConfiguracion'), datosDeConfiguracion(torneo));
    pintarPeligro();

    document.getElementById('estadoCarga').classList.add('oculto');
    document.getElementById('contenidoGestion').classList.remove('oculto');
    return true;
}

function mostrarError(mensaje) {
    document.getElementById('estadoCarga').innerHTML = nlVacio(mensaje, { texto: 'Volver al torneo', href: `torneo?id=${idTorneo}` });
}

function avisar(mensaje, tipo) {
    nlAlerta('alertaGestion', mensaje, tipo);
    nlDesplazar(document.getElementById('alertaGestion'), { block: 'nearest' });
}

// Mientras se guarda y se recargan los datos, la zona de trabajo no se puede
// tocar: se va a volver a dibujar y lo que se escribiera se perdería.
function bloquearTrabajo(bloquear) {
    document.querySelector('.gestion').inert = bloquear;
}

// Ejecuta una acción de la API, recarga si salió bien y recién entonces
// avisa: cuando aparece el aviso, la pantalla ya muestra el estado nuevo.
// (Antes avisaba primero y recargaba después: por un momento se veía el
// aviso con la pantalla vieja.)
async function accion(promesa, boton = null, textoOcupado = 'Un momento…') {
    nlBotonOcupado(boton, true, textoOcupado);
    bloquearTrabajo(true);
    let data;
    try {
        data = await promesa;
        if (data.exito) await cargarTodo();
    } finally {
        bloquearTrabajo(false); // pase lo que pase, la pantalla no queda trabada
        nlBotonOcupado(boton, false);
    }
    avisar(data.errores ? Object.values(data.errores)[0] : data.mensaje, data.exito ? 'exito' : 'error');
    return data;
}

const esAdmin = () => sesion?.usuario?.rol === 'administrador';
const correccionesPermitidas = () => Number(torneo.configuracion?.permitir_correcciones) === 1 || esAdmin();
const torneoTerminado = () => ['finalizado', 'cancelado'].includes(torneo.estado);
const inscripcionAbierta = () => torneo.estado === 'pendiente' && !Number(torneo.inscripcion_vencida);

// Las fechas de los partidos y de las rondas van dentro de las del torneo
// (la API también lo controla)
const fueraDelTorneo = (dia) => Boolean(dia) && (dia < torneo.fecha_inicio || dia > torneo.fecha_fin);
const avisoFueraDelTorneo = () => `Tiene que ser entre el ${formatearFecha(torneo.fecha_inicio)} y el ${formatearFecha(torneo.fecha_fin)}, las fechas del torneo.`;

// Partidos que ya tendrían que haberse jugado (fecha de días anteriores) y no tienen resultado
const partidosAtrasados = () => partidosJugables()
    .filter(p => p.estado !== 'finalizado' && p.id_local && p.id_visitante && p.fecha_hora && String(p.fecha_hora).slice(0, 10) < nlHoy());

// ── Encabezado ──
function pintarCabecera() {
    document.title = `Gestionar ${torneo.nombre} · NextLeague`;
    document.getElementById('nombreTorneo').textContent = torneo.nombre;
    document.getElementById('chipsTorneo').innerHTML = [
        nlChipTorneo(torneo),
        `<span class="chip">${escapeHtml(nombreModulo(torneo.modulo))}</span>`,
        `<span class="chip chip-gris">${escapeHtml(NOMBRE_MODALIDAD[torneo.modalidad] || '')}</span>`,
    ].join('');
    document.getElementById('enlaceVolver').href = `torneo?id=${idTorneo}`;
    document.getElementById('enlaceReporte').href = `${NEXTLEAGUE_API}/torneos/${idTorneo}/reporte`;
    // Un torneo cancelado ya no se edita
    const editar = `../createtournament/createtournament?id=${idTorneo}`;
    ['enlaceEditar', 'enlaceEditarConfiguracion'].forEach(id => {
        const enlace = document.getElementById(id);
        enlace.href = editar;
        enlace.classList.toggle('oculto', torneo.estado === 'cancelado');
    });
}

// ── Pasos: inscripción → calendario → partidos → cierre ──
function partidosJugables() {
    return rondas.flatMap(r => r.partidos).filter(p => !Number(p.es_pase_libre));
}

function pintarPasos() {
    const t = torneo;
    const porcentaje = (parte, total) => (total > 0 ? Math.min(100, Math.round((parte / total) * 100)) : 0);
    const jugables = partidosJugables();
    const jugados = jugables.filter(p => p.estado === 'finalizado').length;
    const esSuizo = t.modulo === 'sistema_suizo';
    const rondasCompletas = rondas.filter(r => r.partidos.every(p => p.estado === 'finalizado')).length;

    const pasos = [
        { nombre: 'Inscripción', detalle: `${t.inscriptos} de ${t.cupo}`, hecho: t.estado !== 'pendiente',
          avance: t.estado === 'pendiente' ? porcentaje(t.inscriptos, t.cupo) : 100 },
        { nombre: 'Calendario', detalle: rondas.length ? `${rondas.length} ${rondas.length === 1 ? 'ronda' : 'rondas'}` : 'Sin generar',
          hecho: rondas.length > 0, avance: rondas.length ? 100 : 0 },
        { nombre: 'Partidos',
          detalle: esSuizo && totalRondas ? `Ronda ${rondas.length} de ${totalRondas}` : (jugables.length ? `${jugados} de ${jugables.length} jugados` : '—'),
          hecho: t.estado === 'finalizado',
          avance: esSuizo && totalRondas ? porcentaje(rondasCompletas, totalRondas) : porcentaje(jugados, jugables.length) },
        { nombre: 'Cierre', detalle: t.estado === 'finalizado' ? 'Finalizado' : (t.estado === 'cancelado' ? 'Cancelado' : 'Pendiente'),
          hecho: t.estado === 'finalizado', avance: t.estado === 'finalizado' ? 100 : 0 },
    ];
    const actual = t.estado === 'cancelado' ? -1 : pasos.findIndex(p => !p.hecho);

    document.getElementById('pasos').innerHTML = pasos.map((p, i) => `
        <li class="paso ${p.hecho ? 'paso--hecho' : ''} ${i === actual ? 'paso--actual' : ''}" ${i === actual ? 'aria-current="step"' : ''}>
            <progress class="progreso" max="100" value="${p.avance}" aria-hidden="true"></progress>
            <span class="paso__nombre">${p.hecho ? nlIcono('check') : ''}${p.nombre}</span>
            <span class="paso__detalle">${escapeHtml(p.detalle)}</span>
        </li>
    `).join('');
}

// ── Próximo paso: qué conviene hacer ahora, con su botón ──
function pintarProximoPaso() {
    const t = torneo;
    const texto = document.getElementById('textoProximoPaso');
    const acciones = document.getElementById('accionesProximoPaso');
    const borradores = rondas.filter(r => r.estado === 'borrador');
    const rondaParaCerrar = rondas.find(r => r.estado === 'publicada' && r.partidos.every(p => p.estado === 'finalizado'));
    const rondaConPendientes = rondas.find(r => r.estado === 'publicada' && r.partidos.some(p => p.estado !== 'finalizado'));
    let mensaje = '';
    let botones = '';

    if (t.estado === 'cancelado') {
        mensaje = 'El torneo está cancelado. Sus partidos y resultados quedan guardados como historial.';
    } else if (t.estado === 'pendiente' && Number(t.inscripcion_vencida)) {
        // Pasó la fecha de inicio sin calendario: la inscripción se cerró sola
        mensaje = `La fecha de inicio era el ${formatearFecha(t.fecha_inicio)} y el calendario no se generó: las inscripciones se cerraron solas. `
            + (t.inscriptos >= 2 ? 'Generá el calendario para empezar, o cambiá la fecha de inicio si querés sumar participantes.'
                : 'Hay menos de 2 inscriptos: cambiá la fecha de inicio para volver a abrir las inscripciones.');
        botones = (t.inscriptos >= 2 ? `<button type="button" class="btn btn-primario" id="botonGenerar">${nlIcono('calendario')}Generar calendario</button>` : '')
            + `<a class="btn btn-secundario" href="../createtournament/createtournament?id=${t.id_torneo}">${nlIcono('editar')}Cambiar fechas</a>`;
    } else if (t.estado === 'finalizado') {
        const campeon = campeonDelTorneo(t, rondas, posiciones);
        mensaje = campeon ? `El torneo terminó. Campeón: ${campeon}.` : 'El torneo terminó.';
        botones = `<a class="btn btn-secundario" href="torneo?id=${t.id_torneo}">Ver página del torneo</a>`;
    } else if (t.estado === 'pendiente' && t.inscriptos < 2) {
        mensaje = 'Esperando inscriptos: hacen falta al menos 2 para generar el calendario. Compartí el enlace del torneo o inscribí participantes desde el panel de inscripciones.';
        botones = `<button type="button" class="btn btn-secundario" id="botonCopiar">${nlIcono('externo')}Copiar enlace del torneo</button>`;
    } else if (t.estado === 'pendiente') {
        mensaje = `Ya podés generar el calendario (${nombreModulo(t.modulo).toLowerCase()}). Al hacerlo se cierran las inscripciones y las rondas quedan en borrador para que las revises antes de publicarlas.`;
        botones = `<button type="button" class="btn btn-primario" id="botonGenerar">${nlIcono('calendario')}Generar calendario</button>`;
    } else if (borradores.length) {
        mensaje = `Hay ${borradores.length === 1 ? 'una ronda' : borradores.length + ' rondas'} en borrador: el público no las ve y todavía no se pueden cargar sus resultados. Programá fecha y lugar y publicalas.`;
        botones = `<button type="button" class="btn btn-primario" id="botonPublicarTodas">${nlIcono('ojo')}Publicar ${borradores.length === 1 ? 'la ronda' : 'todas las rondas'}</button>`;
    } else if (puedeGenerarSiguiente) {
        mensaje = 'Se jugaron todos los partidos de la ronda: ya podés generar la siguiente. Se emparejan participantes con puntajes parecidos y sin repetir rivales.';
        botones = `<button type="button" class="btn btn-primario" id="botonSiguiente">${nlIcono('mas')}Generar ronda siguiente</button>`;
    } else if (rondaParaCerrar) {
        mensaje = `${nombreRonda(rondaParaCerrar)} ya tiene todos sus resultados. Cerrala para dejarlos firmes.`;
        botones = `<button type="button" class="btn btn-primario" data-cerrar="${rondaParaCerrar.id_ronda}">${nlIcono('candado')}Cerrar ${escapeHtml(nombreRonda(rondaParaCerrar))}</button>`;
    } else if (rondaConPendientes) {
        mensaje = `Cargá los resultados de ${nombreRonda(rondaConPendientes)} a medida que se juegan. La tabla y las llaves se actualizan solas.`;
        botones = `<button type="button" class="btn btn-secundario" data-ir-ronda="${rondaConPendientes.id_ronda}">Ir a ${escapeHtml(nombreRonda(rondaConPendientes))}</button>`;
    } else {
        mensaje = 'Todo al día.';
    }

    // Avisos según la fecha: partidos atrasados y fecha de fin vencida
    const atrasados = t.estado === 'en_curso' ? partidosAtrasados().length : 0;
    if (atrasados) {
        mensaje = `${atrasados === 1 ? 'Hay 1 partido' : `Hay ${atrasados} partidos`} con la fecha vencida y sin resultado: cargalos o reprogramalos. ${mensaje}`;
    }
    if (Number(t.fin_vencido)) {
        mensaje = `La fecha de fin era el ${formatearFecha(t.fecha_fin)} y todavía faltan partidos. ${mensaje}`;
    }

    texto.textContent = mensaje;
    acciones.innerHTML = botones;
    document.getElementById('panelProximoPaso').classList.toggle('panel--destacado', !torneoTerminado());

    acciones.querySelector('#botonGenerar')?.addEventListener('click', async (e) => {
        const boton = e.currentTarget;
        const ok = await nlConfirmar('Se cierran las inscripciones y se arman todos los partidos. Las rondas quedan en borrador hasta que las publiques.',
            { titulo: '¿Generar el calendario?', aceptar: 'Generar calendario' });
        if (ok) accion(nlApi.post(`/torneos/${idTorneo}/calendario`), boton, 'Generando…');
    });
    acciones.querySelector('#botonPublicarTodas')?.addEventListener('click', (e) =>
        accion(nlApi.post(`/torneos/${idTorneo}/rondas/publicar`), e.currentTarget, 'Publicando…'));
    acciones.querySelector('#botonSiguiente')?.addEventListener('click', (e) => {
        idRondaElegida = null; // al recargar se elige la ronda nueva
        accion(nlApi.post(`/torneos/${idTorneo}/rondas`), e.currentTarget, 'Generando…');
    });
    acciones.querySelector('[data-cerrar]')?.addEventListener('click', (e) =>
        accion(nlApi.put(`/rondas/${e.currentTarget.dataset.cerrar}`, { estado: 'cerrada' }), e.currentTarget));
    acciones.querySelector('[data-ir-ronda]')?.addEventListener('click', (e) => {
        elegirRonda(Number(e.currentTarget.dataset.irRonda));
        nlDesplazar(document.getElementById('tituloRondas'));
    });
    acciones.querySelector('#botonCopiar')?.addEventListener('click', copiarEnlace);
}

async function copiarEnlace(e) {
    const enlace = new URL(`torneo?id=${idTorneo}`, window.location.href).href;
    try {
        await navigator.clipboard.writeText(enlace);
        e.currentTarget.innerHTML = `${nlIcono('check')}Enlace copiado`;
    } catch {
        avisar(`Enlace del torneo: ${enlace}`, 'info');
    }
}

// ── Rondas ──
function rondaPorDefecto() {
    return rondas.find(r => r.estado === 'publicada' && r.partidos.some(p => p.estado !== 'finalizado'))
        || rondas.find(r => r.estado === 'borrador')
        || rondas[rondas.length - 1];
}

function pintarRondas() {
    const selector = document.getElementById('selectorRondas');
    const editor = document.getElementById('editorRonda');
    const completas = rondas.filter(r => r.estado === 'cerrada').length;
    document.getElementById('avanceRondas').textContent = rondas.length
        ? `${completas} de ${totalRondas || rondas.length} cerradas` : '';

    if (rondas.length === 0) {
        selector.innerHTML = '';
        editor.innerHTML = nlVacio(torneo.estado === 'pendiente'
            ? 'Las rondas aparecen acá cuando generes el calendario.'
            : 'Este torneo no tiene rondas.');
        return;
    }
    if (!rondas.some(r => Number(r.id_ronda) === idRondaElegida)) {
        idRondaElegida = Number(rondaPorDefecto().id_ronda);
    }
    selector.innerHTML = rondas.map(r => {
        const elegida = Number(r.id_ronda) === idRondaElegida;
        return `
            <button type="button" role="tab" class="selector-rondas__boton" data-ronda="${r.id_ronda}"
                    aria-selected="${elegida}" tabindex="${elegida ? 0 : -1}" aria-controls="editorRonda">
                <span class="punto punto--${r.estado}" aria-hidden="true"></span>${escapeHtml(nombreRonda(r))}
                <span class="solo-lectores">(${NOMBRE_ESTADO_RONDA[r.estado]})</span>
            </button>
        `;
    }).join('');
    selector.querySelectorAll('[data-ronda]').forEach(boton =>
        boton.addEventListener('click', () => elegirRonda(Number(boton.dataset.ronda))));
    pintarEditorRonda();
}

function elegirRonda(idRonda) {
    idRondaElegida = idRonda;
    pintarRondas();
}

function pintarEditorRonda() {
    const ronda = rondas.find(r => Number(r.id_ronda) === idRondaElegida);
    const editor = document.getElementById('editorRonda');
    const sinTerminar = ronda.partidos.filter(p => p.estado !== 'finalizado').length;
    const conResultados = ronda.partidos.some(p => p.estado === 'finalizado' && !Number(p.es_pase_libre));
    const activo = torneo.estado !== 'cancelado';

    const botones = [];
    if (activo && ronda.estado === 'borrador') {
        botones.push(`<button type="button" class="btn btn-primario btn-chico" data-estado-ronda="publicada">${nlIcono('ojo')}Publicar ronda</button>`);
    }
    if (activo && ronda.estado === 'publicada') {
        botones.push(`<button type="button" class="btn btn-secundario btn-chico" data-estado-ronda="cerrada" ${sinTerminar ? 'disabled title="Faltan resultados"' : ''}>${nlIcono('candado')}Cerrar ronda</button>`);
        if (!conResultados) botones.push(`<button type="button" class="btn btn-texto btn-chico" data-estado-ronda="borrador">Volver a borrador</button>`);
    }
    if (activo && ronda.estado === 'cerrada' && correccionesPermitidas()) {
        botones.push(`<button type="button" class="btn btn-texto btn-chico" data-estado-ronda="publicada">${nlIcono('editar')}Reabrir para corregir</button>`);
    }

    let ayuda = '';
    if (ronda.estado === 'borrador') ayuda = 'Borrador: el público no ve esta ronda. Podés programar fecha y lugar; los resultados se cargan después de publicarla.';
    else if (ronda.estado === 'cerrada') ayuda = correccionesPermitidas() ? 'Ronda cerrada: sus resultados quedaron firmes. Para corregir uno, reabrila.' : 'Ronda cerrada: sus resultados quedaron firmes. Este torneo no permite correcciones; solo un administrador puede reabrirla.';
    else if (sinTerminar) ayuda = `${sinTerminar === 1 ? 'Falta 1 resultado' : `Faltan ${sinTerminar} resultados`}. Completá los marcadores y guardá: se guardan todos juntos.`;
    else ayuda = 'Todos los resultados están cargados. Si algo está mal, corregilo y guardá; el resultado anterior queda en el historial.';

    editor.innerHTML = `
        <div class="ronda-editor">
            <div class="ronda-editor__cabecera">
                <div class="ronda-editor__titulo">
                    <h3>${escapeHtml(nombreRonda(ronda))}</h3>
                    ${nlChipEstadoRonda(ronda.estado)}
                </div>
                <div class="grupo-botones">${botones.join('')}</div>
            </div>
            <p class="texto-tenue">${escapeHtml(ayuda)}</p>
            <form id="formRonda" novalidate>
                <div class="campo campo--en-linea campo-fecha-ronda">
                    <label for="fechaRonda">Fecha de la ronda</label>
                    <input type="date" id="fechaRonda" value="${ronda.fecha || ''}" min="${torneo.fecha_inicio}" max="${torneo.fecha_fin}" ${activo ? '' : 'disabled'}>
                </div>
                <div class="partidos-editor">${ronda.partidos.map(p => filaEditor(p, ronda)).join('')}</div>
                ${activo ? `
                    <div class="ronda-editor__pie">
                        <button type="submit" class="btn btn-primario">${nlIcono('check')}Guardar cambios</button>
                    </div>` : ''}
            </form>
        </div>
    `;

    editor.querySelectorAll('[data-estado-ronda]').forEach(boton => boton.addEventListener('click', () =>
        accion(nlApi.put(`/rondas/${ronda.id_ronda}`, { estado: boton.dataset.estadoRonda }), boton)));
    editor.querySelectorAll('[data-historial]').forEach(boton => boton.addEventListener('click', () =>
        verHistorial(boton.dataset.historial)));
    // Al cambiar un resultado ya cargado, se pide (opcional) el motivo de la corrección
    editor.querySelectorAll('.editor-partido[data-finalizado="1"] input[type="number"]').forEach(input =>
        input.addEventListener('input', () => input.closest('.editor-partido').querySelector('.motivo')?.classList.remove('oculto')));
    document.getElementById('formRonda').addEventListener('submit', (e) => guardarRonda(e, ronda));
}

function filaEditor(p, ronda) {
    if (Number(p.es_pase_libre)) {
        return `<div class="editor-partido editor-partido--libre">${nlIcono('flecha-derecha')}<span><strong>${escapeHtml(p.nombre_local || 'Por definir')}</strong> pasa de ronda sin jugar (pase libre).</span></div>`;
    }
    const id = p.id_enfrentamiento;
    const finalizado = p.estado === 'finalizado';
    const ambos = p.id_local && p.id_visitante;
    const local = p.nombre_local || 'Por definir';
    const visitante = p.nombre_visitante || 'Por definir';

    let bloqueoResultado = '';
    if (torneo.estado === 'cancelado') bloqueoResultado = 'El torneo está cancelado.';
    else if (!ambos) bloqueoResultado = 'Esperando a los ganadores de la ronda anterior.';
    else if (ronda.estado !== 'publicada') bloqueoResultado = ronda.estado === 'borrador' ? 'Publicá la ronda para cargar el resultado.' : 'Ronda cerrada.';
    else if (p.fecha_hora && String(p.fecha_hora).slice(0, 16) > nlAhora()) bloqueoResultado = `Se juega el ${formatearFechaHora(p.fecha_hora)}: el resultado se carga después. Si ya se jugó, cambiá la fecha.`;
    else if (finalizado && !correccionesPermitidas()) bloqueoResultado = 'Este torneo no permite corregir resultados.';
    const resultadoBloqueado = bloqueoResultado ? 'disabled' : '';
    const programaBloqueado = torneoTerminado() ? 'disabled' : '';
    const fechaHora = p.fecha_hora ? String(p.fecha_hora).slice(0, 16).replace(' ', 'T') : '';

    return `
        <div class="editor-partido ${finalizado ? 'editor-partido--jugado' : ''}" data-id="${id}" data-finalizado="${finalizado ? 1 : 0}">
            <div class="editor-partido__marcador">
                <span class="editor-partido__nombre ${p.id_local ? '' : 'texto-tenue'}">${escapeHtml(local)}</span>
                <input type="number" class="control marcador" name="local" min="0" max="999" inputmode="numeric"
                       value="${finalizado ? p.puntaje_local : ''}" aria-label="Resultado de ${escapeHtml(local)}" ${resultadoBloqueado}>
                <span class="separador" aria-hidden="true">-</span>
                <input type="number" class="control marcador" name="visitante" min="0" max="999" inputmode="numeric"
                       value="${finalizado ? p.puntaje_visitante : ''}" aria-label="Resultado de ${escapeHtml(visitante)}" ${resultadoBloqueado}>
                <span class="editor-partido__nombre editor-partido__nombre--visitante ${p.id_visitante ? '' : 'texto-tenue'}">${escapeHtml(visitante)}</span>
            </div>
            <div class="editor-partido__programa">
                <label class="solo-lectores" for="fecha-${id}">Fecha y hora del partido</label>
                <input type="datetime-local" class="control" id="fecha-${id}" name="fecha_hora" value="${fechaHora}"
                       min="${torneo.fecha_inicio}T00:00" max="${torneo.fecha_fin}T23:59" ${programaBloqueado}>
                <label class="solo-lectores" for="lugar-${id}">Lugar del partido</label>
                <input type="text" class="control" id="lugar-${id}" name="lugar" maxlength="100" placeholder="Lugar" value="${escapeHtml(p.lugar || '')}" ${programaBloqueado}>
                ${finalizado ? `<button type="button" class="btn btn-texto btn-chico" data-historial="${id}">${nlIcono('historial')}Historial</button>` : ''}
            </div>
            ${finalizado && !bloqueoResultado ? `
                <div class="motivo oculto">
                    <label class="solo-lectores" for="motivo-${id}">Motivo de la corrección</label>
                    <input type="text" class="control" id="motivo-${id}" name="observacion" maxlength="255" placeholder="Motivo de la corrección (opcional)">
                </div>` : ''}
            ${bloqueoResultado && ambos && ronda.estado !== 'borrador' ? `<span class="editor-partido__nota">${escapeHtml(bloqueoResultado)}</span>` : ''}
            <span class="error-campo" data-error-partido></span>
        </div>
    `;
}

// Guarda todo lo que cambió en la ronda: fecha de la ronda, fecha y lugar
// de cada partido y resultados. Se envía partido por partido; si alguno
// falla, el resto se guarda igual y el error queda marcado en su fila.
async function guardarRonda(e, ronda) {
    e.preventDefault();
    const form = e.target;
    const boton = form.querySelector('[type="submit"]');
    const partidosPorId = Object.fromEntries(ronda.partidos.map(p => [String(p.id_enfrentamiento), p]));
    const errores = {};
    let guardados = 0;
    let terminoElTorneo = false;

    // Primero se revisan los marcadores a medio completar: si hay alguno no
    // se envía nada, así no se pierde lo que se estaba escribiendo.
    form.querySelectorAll('.editor-partido--error').forEach(f => f.classList.remove('editor-partido--error'));
    form.querySelectorAll('[data-error-partido]').forEach(e => { e.textContent = ''; });
    const incompletos = [...form.querySelectorAll('.editor-partido[data-id]')].filter(fila => {
        const [local, visitante] = fila.querySelectorAll('input[type="number"]');
        return !local.disabled && (local.value === '') !== (visitante.value === '');
    });
    if (incompletos.length) {
        marcarErrores(Object.fromEntries(incompletos.map(f => [f.dataset.id, 'Completá los dos marcadores.'])));
        avisar('Hay marcadores a medio completar: completalos o borralos antes de guardar.', 'error');
        return;
    }
    nlBotonOcupado(boton, true, 'Guardando…');
    bloquearTrabajo(true);
    try {
        const fechaRonda = document.getElementById('fechaRonda');
        if (!fechaRonda.disabled && fechaRonda.value !== (ronda.fecha || '')) {
            if (fueraDelTorneo(fechaRonda.value)) {
                errores.ronda = avisoFueraDelTorneo();
            } else {
                const r = await nlApi.put(`/rondas/${ronda.id_ronda}`, { fecha: fechaRonda.value });
                if (r.exito) guardados++; else errores.ronda = r.errores?.fecha || r.mensaje;
            }
        }

        for (const fila of form.querySelectorAll('.editor-partido[data-id]')) {
            const id = fila.dataset.id;
            const original = partidosPorId[id];

            // Fecha, hora y lugar
            const fecha = fila.querySelector('[name="fecha_hora"]');
            const lugar = fila.querySelector('[name="lugar"]');
            if (!fecha.disabled) {
                const fechaOriginal = original.fecha_hora ? String(original.fecha_hora).slice(0, 16).replace(' ', 'T') : '';
                if (fecha.value !== fechaOriginal || lugar.value.trim() !== (original.lugar || '')) {
                    if (fueraDelTorneo(fecha.value.slice(0, 10))) {
                        errores[id] = avisoFueraDelTorneo();
                        continue;
                    }
                    const r = await nlApi.put(`/enfrentamientos/${id}`, { fecha_hora: fecha.value, lugar: lugar.value.trim() });
                    if (!r.exito) {
                        errores[id] = r.errores ? Object.values(r.errores)[0] : r.mensaje;
                        continue;
                    }
                    guardados++;
                }
            }

            // Resultado (o corrección)
            const local = fila.querySelector('[name="local"]');
            const visitante = fila.querySelector('[name="visitante"]');
            if (local.disabled || local.value === '') continue;
            const cambio = original.estado !== 'finalizado'
                || Number(local.value) !== Number(original.puntaje_local)
                || Number(visitante.value) !== Number(original.puntaje_visitante);
            if (!cambio) continue;

            const r = await nlApi.post(`/enfrentamientos/${id}/resultado`, {
                puntaje_local: local.value,
                puntaje_visitante: visitante.value,
                observacion: fila.querySelector('[name="observacion"]')?.value.trim() || '',
            });
            if (r.exito) {
                guardados++;
                terminoElTorneo = terminoElTorneo || Boolean(r.torneo_finalizado);
            } else {
                errores[id] = r.errores ? Object.values(r.errores)[0] : r.mensaje;
            }
        }
        if (guardados > 0) await cargarTodo();
    } finally {
        bloquearTrabajo(false); // pase lo que pase, la pantalla no queda trabada
        nlBotonOcupado(boton, false);
    }
    const cantidadErrores = Object.keys(errores).length;
    const cambios = guardados === 1 ? 'Se guardó 1 cambio' : `Se guardaron ${guardados} cambios`;
    marcarErrores(errores);

    if (guardados === 0 && cantidadErrores === 0) {
        avisar('No había cambios para guardar.', 'info');
    } else if (cantidadErrores) {
        avisar(`${guardados ? `${cambios}, pero ` : ''}${cantidadErrores === 1 ? 'un partido no se pudo guardar' : cantidadErrores + ' partidos no se pudieron guardar'}: revisá los mensajes en rojo.`, 'error');
    } else {
        avisar(terminoElTorneo ? '¡Listo! Era el último partido: el torneo terminó.' : `${cambios}. La tabla y las llaves ya están actualizadas.`, 'exito');
    }
}

// Después de recargar, vuelve a mostrar el error de cada partido que falló.
function marcarErrores(errores) {
    Object.entries(errores).forEach(([id, mensaje]) => {
        if (id === 'ronda') {
            document.getElementById('fechaRonda')?.classList.add('input-error');
            return;
        }
        const fila = document.querySelector(`.editor-partido[data-id="${id}"]`);
        if (!fila) return;
        fila.classList.add('editor-partido--error');
        fila.querySelector('[data-error-partido]').textContent = mensaje;
    });
}

// ── Historial de un resultado (cargas y correcciones) ──
const modalHistorial = document.getElementById('modalHistorial');
document.getElementById('cerrarHistorial').addEventListener('click', () => modalHistorial.close());

async function verHistorial(idPartido) {
    const contenido = document.getElementById('contenidoHistorial');
    contenido.innerHTML = nlCargando();
    modalHistorial.showModal();
    const data = await nlApi.get(`/enfrentamientos/${idPartido}/resultados`);
    if (!data.exito || data.resultados.length === 0) {
        contenido.innerHTML = `<p class="texto-tenue">${data.exito ? 'Sin registros.' : escapeHtml(data.mensaje)}</p>`;
        return;
    }
    contenido.innerHTML = `
        <ol class="historial">
            ${data.resultados.map(r => `
                <li class="${Number(r.vigente) ? 'historial--vigente' : ''}">
                    <div><strong>${r.puntaje_local} - ${r.puntaje_visitante}</strong> ${Number(r.vigente) ? '<span class="chip chip-exito">Vigente</span>' : '<span class="chip chip-gris">Reemplazado</span>'}</div>
                    <span class="texto-tenue">${formatearFechaHora(r.fecha_carga)} · ${escapeHtml(r.cargado_por || 'Usuario eliminado')}</span>
                    ${r.observacion ? `<span class="texto-secundario">“${escapeHtml(r.observacion)}”</span>` : ''}
                </li>
            `).join('')}
        </ol>
    `;
}

// ── Inscripciones ──
function pintarInscripciones() {
    const t = torneo;
    const porEquipos = t.modalidad === 'equipos';
    const abierto = t.estado === 'pendiente'; // mientras no empieza se puede quitar gente
    document.getElementById('cupoChip').textContent = `${t.inscriptos} / ${t.cupo}`;

    const form = document.getElementById('formInscribir');
    form.classList.toggle('oculto', !inscripcionAbierta() || t.inscriptos >= t.cupo);
    document.getElementById('campoUsuario').classList.toggle('oculto', porEquipos);
    document.getElementById('campoEquipo').classList.toggle('oculto', !porEquipos);
    document.getElementById('campoCapitan').classList.toggle('oculto', !porEquipos);

    const lista = document.getElementById('listaInscriptos');
    if (participantes.length === 0) {
        lista.innerHTML = `<li class="texto-tenue">${inscripcionAbierta() ? 'Todavía no hay inscriptos.' : 'Sin inscriptos.'}</li>`;
        return;
    }
    lista.innerHTML = participantes.map(p => {
        const imagen = p.imagen ? nlRutaSubida(p.tipo === 'equipo' ? 'equipos' : 'perfiles', p.imagen)
            : (p.tipo === 'equipo' ? '../iconos/group-128.webp' : NL_AVATAR_POR_DEFECTO);
        return `
            <li class="inscripto">
                <img src="${imagen}" alt="" width="32" height="32" loading="lazy">
                <span class="inscripto__nombre"><a class="tocable" href="${nlEnlaceParticipante(p)}">${escapeHtml(p.nombre)}</a>${p.tipo === 'equipo' ? `<small>${p.cantidad_miembros} ${Number(p.cantidad_miembros) === 1 ? 'jugador' : 'jugadores'}</small>` : ''}</span>
                ${abierto ? `<button type="button" class="btn btn-texto btn-chico boton-quitar" data-quitar="${p.id_participante}" data-nombre="${escapeHtml(p.nombre)}" aria-label="Quitar a ${escapeHtml(p.nombre)}">${nlIcono('x')}</button>` : ''}
            </li>
        `;
    }).join('');
    lista.querySelectorAll('[data-quitar]').forEach(boton => boton.addEventListener('click', async () => {
        const ok = await nlConfirmar(`${boton.dataset.nombre} deja de participar en el torneo.`, { titulo: '¿Quitar participante?', aceptar: 'Quitar', peligro: true });
        if (ok) accion(nlApi.delete(`/torneos/${idTorneo}/participantes/${boton.dataset.quitar}`), boton);
    }));
}

document.getElementById('formInscribir').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    nlLimpiarErrores(form);
    const datos = torneo.modalidad === 'equipos'
        ? { nombre_equipo: document.getElementById('inscribirEquipo').value.trim(), capitan: document.getElementById('inscribirCapitan').value.trim() }
        : { nombre_usuario: document.getElementById('inscribirUsuario').value.trim() };
    const boton = form.querySelector('[type="submit"]');
    nlBotonOcupado(boton, true, 'Inscribiendo…');
    const data = await nlApi.post(`/torneos/${idTorneo}/participantes`, datos);
    nlBotonOcupado(boton, false);
    if (!data.exito) {
        if (data.errores) {
            nlMostrarErrores(data.errores, { nombre_usuario: 'inscribirUsuario', nombre_equipo: 'inscribirEquipo', capitan: 'inscribirCapitan' });
        } else {
            avisar(data.mensaje, 'error');
        }
        return;
    }
    form.reset();
    await cargarTodo(); // primero se actualiza la lista y después se avisa
    avisar(data.mensaje, 'exito');
});

// ── Visibilidad ──
function pintarVisibilidad() {
    const interruptor = document.getElementById('interruptorPublicado');
    interruptor.checked = Number(torneo.publicado) === 1;
    document.getElementById('textoPublicado').textContent = interruptor.checked
        ? 'Público: lo ve cualquier visitante.'
        : 'Oculto: solo lo ven vos y los administradores.';
}

document.getElementById('interruptorPublicado').addEventListener('change', async (e) => {
    const interruptor = e.target;
    interruptor.disabled = true;
    const data = await nlApi.put(`/torneos/${idTorneo}/publicacion`, { publicado: interruptor.checked });
    if (data.exito) await cargarTodo();
    else interruptor.checked = !interruptor.checked;
    interruptor.disabled = false;
    avisar(data.mensaje, data.exito ? 'exito' : 'error');
});

// ── Zona de peligro: cancelar (conserva todo) o eliminar (solo sin calendario) ──
function pintarPeligro() {
    const panel = document.getElementById('panelPeligro');
    const puedeEliminar = torneo.estado === 'pendiente' && rondas.length === 0;
    const puedeCancelar = ['pendiente', 'en_curso'].includes(torneo.estado);
    panel.classList.toggle('oculto', !puedeCancelar);
    if (!puedeCancelar) return;

    document.getElementById('textoPeligro').textContent = puedeEliminar
        ? 'Mientras no tenga calendario, el torneo se puede eliminar por completo. Si preferís conservarlo como historial, cancelalo.'
        : 'El torneo ya tiene calendario: no se puede eliminar, pero sí cancelar. Sus partidos y resultados se conservan como historial.';
    document.getElementById('accionesPeligro').innerHTML = `
        <button type="button" class="btn btn-peligro btn-chico" id="botonCancelar">${nlIcono('x')}Cancelar torneo</button>
        ${puedeEliminar ? `<button type="button" class="btn btn-peligro btn-chico" id="botonEliminar">${nlIcono('basura')}Eliminar torneo</button>` : ''}
    `;
    document.getElementById('botonCancelar').addEventListener('click', async (e) => {
        const boton = e.currentTarget;
        const ok = await nlConfirmar('El torneo deja de jugarse. No se borra nada: queda como historial y no se puede deshacer.',
            { titulo: '¿Cancelar el torneo?', aceptar: 'Cancelar torneo', peligro: true });
        if (ok) accion(nlApi.post(`/torneos/${idTorneo}/cancelar`), boton);
    });
    document.getElementById('botonEliminar')?.addEventListener('click', async (e) => {
        const boton = e.currentTarget;
        const ok = await nlConfirmar('Se borran el torneo y sus inscripciones. No se puede deshacer.',
            { titulo: '¿Eliminar el torneo?', aceptar: 'Eliminar', peligro: true });
        if (!ok) return;
        nlBotonOcupado(boton, true, 'Eliminando…');
        const data = await nlApi.delete(`/torneos/${idTorneo}`);
        if (data.exito) {
            window.location.href = '../perfil/perfil#mis-torneos';
            return;
        }
        nlBotonOcupado(boton, false);
        avisar(data.mensaje, 'error');
    });
}

// ── Arranque ──
async function iniciar() {
    if (!idTorneo) {
        mostrarError('Falta indicar qué torneo querés gestionar.');
        return;
    }
    sesion = await nextleagueExigirSesion();
    if (!sesion.logueado) return;
    if (!(await cargarTodo())) return;

    // Viene del formulario de crear o editar
    if (nlParametro('creado')) {
        avisar('¡Torneo creado! Compartí el enlace para recibir inscripciones; cuando haya al menos 2 podés generar el calendario.', 'exito');
    } else if (nlParametro('guardado')) {
        avisar('Cambios guardados.', 'exito');
    }
    history.replaceState(null, '', `gestionar?id=${idTorneo}`);
}

iniciar();
