// =====================================================================
// NEXTLEAGUE - PÁGINA DE UN TORNEO
// Antes esta página mezclaba todo en una sola columna: datos, inscripción,
// botones del organizador, participantes, tabla, llaves y el calendario con
// formularios para cargar resultados. Ahora:
//   - lo público va en pestañas: Resumen, Calendario, Llaves (eliminación
//     directa) o Posiciones (liga y suizo) y Participantes;
//   - todo lo del organizador pasó a "Gestionar torneo" (gestionar).
// La API devuelve, junto con el torneo, qué puede hacer el usuario actual
// (torneo.permisos): la página solo muestra lo que corresponde, y la API
// vuelve a controlar cada permiso en cada acción.
// =====================================================================

const idTorneo = Number(nlParametro('id'));
let torneo = null;
let sesion = null;
let rondas = [];
let posiciones = [];
let conBuchholz = false;
let participantes = [];

const PESTANAS = ['resumen', 'calendario', 'llaves', 'posiciones', 'participantes'];

// ── Carga ──
async function cargarTodo() {
    const data = await nlApi.get(`/torneos/${idTorneo}`);
    if (!data.exito) {
        mostrarError(data.mensaje || 'No encontramos este torneo.');
        return;
    }
    torneo = data.torneo;
    const usaTabla = torneo.modulo !== 'eliminacion_directa';
    const [calendario, tabla, inscriptos] = await Promise.all([
        nlApi.get(`/torneos/${idTorneo}/calendario`),
        usaTabla ? nlApi.get(`/torneos/${idTorneo}/posiciones`) : Promise.resolve(null),
        nlApi.get(`/torneos/${idTorneo}/participantes`),
    ]);
    rondas = calendario.exito ? calendario.rondas : [];
    posiciones = tabla?.exito ? tabla.posiciones : [];
    conBuchholz = Boolean(tabla?.buchholz);
    participantes = inscriptos.exito ? inscriptos.participantes : [];

    document.title = `${torneo.nombre} · NextLeague`;
    pintarCabecera();
    pintarAvisos();
    pintarResumen();
    pintarInscripcion();
    pintarCalendario();
    if (usaTabla) pintarPosiciones(); else pintarLlaves();
    pintarParticipantes();

    document.getElementById('estadoCarga').classList.add('oculto');
    document.getElementById('contenidoTorneo').classList.remove('oculto');
}

function mostrarError(mensaje) {
    document.getElementById('estadoCarga').innerHTML =
        nlVacio(mensaje, { texto: 'Ver todos los torneos', href: 'explorar' });
}

// Ejecuta una acción y, si salió bien, recarga los datos; recién entonces
// muestra el mensaje, así coincide con lo que se ve (por ejemplo "Ya estás
// inscripto" aparece junto con el cupo actualizado, no antes).
async function accion(promesa, boton = null) {
    nlBotonOcupado(boton, true, 'Un momento…');
    const data = await promesa;
    if (data.exito) await cargarTodo();
    nlBotonOcupado(boton, false);
    avisarTorneo(data.errores ? Object.values(data.errores)[0] : data.mensaje, data.exito ? 'exito' : 'error');
    return data;
}

function avisarTorneo(mensaje, tipo) {
    nlAlerta('alertaTorneo', mensaje, tipo);
    nlDesplazar(document.getElementById('alertaTorneo'), { block: 'nearest' });
}

// ── Encabezado ──
function pintarCabecera() {
    const t = torneo;
    document.getElementById('torneoPortada').innerHTML = portadaDeTorneo(t);
    document.getElementById('torneoNombre').textContent = t.nombre;
    document.getElementById('torneoChips').innerHTML = [
        nlChipTorneo(t),
        `<span class="chip">${escapeHtml(nombreModulo(t.modulo))}</span>`,
        `<span class="chip chip-gris">${escapeHtml(NOMBRE_MODALIDAD[t.modalidad] || t.modalidad)}</span>`,
        `<span class="chip chip-gris">${nlIcono(NL_ICONO_CATEGORIA[t.categoria] || 'trofeo')}${escapeHtml(t.juego)}</span>`,
    ].join('');

    const meta = [
        `<span>${nlIcono('usuario')}Organiza ${escapeHtml(t.organizador)}</span>`,
        `<span>${nlIcono('calendario')}${escapeHtml(formatearRango(t.fecha_inicio, t.fecha_fin))}</span>`,
    ];
    if (Number(t.premio) > 0) meta.push(`<span class="premio">${nlIcono('premio')}Premio ${formatearMoneda(t.premio, t.moneda)}</span>`);
    document.getElementById('torneoMeta').innerHTML = meta.join('');

    const acciones = [];
    if (t.reglas_pdf) {
        acciones.push(`<a class="btn btn-secundario" href="${nlRutaSubida('torneos/reglas', t.reglas_pdf)}" target="_blank" rel="noopener">${nlIcono('archivo')}Reglas (PDF)</a>`);
    }
    if (t.permisos.gestionar) {
        acciones.push(`<a class="btn btn-primario" href="gestionar?id=${t.id_torneo}">${nlIcono('ajustes')}Gestionar torneo</a>`);
    }
    document.getElementById('torneoAcciones').innerHTML = acciones.join('');

    // Llaves para eliminación directa; tabla de posiciones para liga y suizo
    const esEliminacion = t.modulo === 'eliminacion_directa';
    document.getElementById('pestanaLlaves').classList.toggle('oculto', !esEliminacion);
    document.getElementById('pestanaPosiciones').classList.toggle('oculto', esEliminacion);
}

function pintarAvisos() {
    const avisos = [];
    if (!Number(torneo.publicado)) {
        avisos.push(`<div class="alerta alerta-aviso">${nlIcono('ojo-tachado')}<span>Este torneo está oculto: solo lo ven su organizador y los administradores.</span></div>`);
    }
    if (torneo.estado === 'cancelado') {
        avisos.push(`<div class="alerta alerta-error">${nlIcono('alerta')}<span>Este torneo fue cancelado. Sus partidos y resultados quedan como historial.</span></div>`);
    }
    const campeon = campeonDelTorneo(torneo, rondas, posiciones);
    if (campeon) {
        avisos.push(`
            <div class="campeon">
                <img src="../iconos/trophy-128.webp" alt="" width="56" height="56">
                <div><span>Campeón</span><strong>${escapeHtml(campeon)}</strong></div>
            </div>
        `);
    }
    document.getElementById('avisosTorneo').innerHTML = avisos.join('');
}

// ── Resumen ──
function pintarResumen() {
    const descripcion = document.getElementById('torneoDescripcion');
    descripcion.textContent = torneo.descripcion || 'El organizador todavía no agregó una descripción.';
    descripcion.classList.toggle('texto-tenue', !torneo.descripcion);
    document.getElementById('torneoFormato').textContent = explicacionDelFormato(torneo);
    pintarDatos(document.getElementById('torneoConfiguracion'), datosDeConfiguracion(torneo));

    // Próximos partidos de este torneo (con los dos rivales definidos): de hoy
    // en adelante o sin fecha; uno de días anteriores sin resultado está atrasado.
    const hoy = nlHoy();
    const pendientes = rondas.flatMap(r => r.partidos.map(p => ({ ...p, nombre_ronda: nombreRonda(r), estado_ronda: r.estado })))
        .filter(p => p.estado === 'pendiente' && p.id_local && p.id_visitante && p.estado_ronda !== 'borrador')
        .filter(p => !p.fecha_hora || String(p.fecha_hora).slice(0, 10) >= hoy)
        .sort((a, b) => (a.fecha_hora || '9999').localeCompare(b.fecha_hora || '9999'))
        .slice(0, 4);
    const contenedor = document.getElementById('proximosDelTorneo');
    if (pendientes.length) {
        contenedor.innerHTML = pendientes.map(p => nlPartido(p, { destacado: esMio(p) })).join('');
    } else {
        const texto = torneo.estado === 'finalizado' ? 'El torneo terminó.'
            : torneo.estado === 'pendiente' ? 'El calendario se arma cuando cierran las inscripciones.'
            : 'No hay partidos pendientes publicados.';
        contenedor.innerHTML = nlVacio(texto);
    }
}

// ¿Es un partido del usuario? (torneos individuales)
function esMio(p) {
    return esParticipanteDelPartido(p, torneo.permisos.mi_participacion?.id_participante);
}

// ── Inscripción ──
function pintarInscripcion() {
    const t = torneo;
    const permisos = t.permisos;
    const porEquipos = t.modalidad === 'equipos';
    const porcentaje = t.cupo > 0 ? Math.min(100, Math.round((t.inscriptos / t.cupo) * 100)) : 0;
    document.getElementById('cupoTexto').textContent = `${t.inscriptos} / ${t.cupo}`;
    document.getElementById('cupoUnidad').textContent = porEquipos ? 'equipos inscriptos' : 'jugadores inscriptos';
    const progreso = document.getElementById('cupoProgreso');
    progreso.value = porcentaje;
    progreso.setAttribute('aria-label', `Lugares ocupados: ${porcentaje}%`);

    const pendiente = t.estado === 'pendiente';
    // Se puede anotar hasta el día de inicio inclusive (después se cierra sola)
    const abierto = pendiente && !Number(t.inscripcion_vencida);
    const lleno = t.inscriptos >= t.cupo;
    let html;

    if (permisos.estoy_inscripto) {
        html = `<div class="alerta alerta-exito">${nlIcono('check')}<span>${pendiente ? 'Ya estás inscripto.' : 'Participás de este torneo.'}</span></div>`;
        if (pendiente) {
            html += `<button type="button" class="btn btn-texto btn-chico" id="botonBaja">${nlIcono('usuario-menos')}Darme de baja</button>`;
        }
    } else if (pendiente && !abierto) {
        html = `<p class="texto-tenue">Las inscripciones se cerraron el ${escapeHtml(formatearFecha(t.fecha_inicio))}, la fecha de inicio del torneo.</p>`;
    } else if (!abierto) {
        html = '<p class="texto-tenue">Las inscripciones están cerradas.</p>';
    } else if (permisos.gestionar) {
        html = `<p class="texto-tenue">Sos el organizador: podés inscribir participantes desde <a href="gestionar?id=${t.id_torneo}">Gestionar torneo</a>.</p>`;
    } else if (lleno) {
        html = '<p class="texto-tenue">El cupo está completo.</p>';
    } else if (!sesion.logueado) {
        const volver = encodeURIComponent(`../torneos/torneo?id=${t.id_torneo}`);
        html = `
            <a class="btn btn-primario btn-bloque" href="../home/login?volver=${volver}">Ingresá para inscribirte</a>
            <p class="texto-tenue centrado">¿No tenés cuenta? <a href="../register/register">Registrate</a></p>
        `;
    } else if (permisos.inscribirse && porEquipos) {
        html = `
            <button type="button" class="btn btn-primario btn-bloque" id="botonCrearEquipo">${nlIcono('mas')}Crear mi equipo</button>
            <p class="texto-tenue centrado">o <button type="button" class="btn-enlace" data-ir-a="participantes">pedí unirte a un equipo</button></p>
        `;
    } else if (permisos.inscribirse) {
        html = `<button type="button" class="btn btn-primario btn-bloque" id="botonInscribirme">${nlIcono('usuario-mas')}Inscribirme</button>`;
    } else {
        html = '<p class="texto-tenue">No podés inscribirte en este torneo.</p>';
    }

    const contenido = document.getElementById('inscripcionContenido');
    contenido.innerHTML = html;
    contenido.querySelector('#botonInscribirme')?.addEventListener('click', (e) =>
        accion(nlApi.post(`/torneos/${idTorneo}/inscripcion`), e.currentTarget));
    contenido.querySelector('#botonCrearEquipo')?.addEventListener('click', abrirModalEquipo);
    contenido.querySelector('#botonBaja')?.addEventListener('click', async (e) => {
        const boton = e.currentTarget;
        const ok = await nlConfirmar('Vas a dejar de participar en este torneo. Si eras el único integrante de tu equipo, el equipo se elimina.',
            { titulo: '¿Darte de baja?', aceptar: 'Darme de baja', peligro: true });
        if (ok) accion(nlApi.delete(`/torneos/${idTorneo}/inscripcion`), boton);
    });
    activarAtajos(contenido);
}

// ── Crear equipo (ventana modal) ──
const modalEquipo = document.getElementById('modalEquipo');
const formEquipo = document.getElementById('formEquipo');

function abrirModalEquipo() {
    nlLimpiarErrores(formEquipo);
    formEquipo.reset();
    modalEquipo.showModal();
}
document.getElementById('cancelarEquipo').addEventListener('click', () => modalEquipo.close());
formEquipo.addEventListener('submit', async (e) => {
    e.preventDefault();
    nlLimpiarErrores(formEquipo);
    const boton = formEquipo.querySelector('[type="submit"]');
    nlBotonOcupado(boton, true, 'Creando…');
    const data = await nlApi.post(`/torneos/${idTorneo}/equipos`, { nombre: document.getElementById('nombreEquipo').value.trim() });
    nlBotonOcupado(boton, false);
    if (!data.exito) {
        if (data.errores) nlMostrarErrores(data.errores, { nombre: 'nombreEquipo' });
        else document.getElementById('nombreEquipoError').textContent = data.mensaje;
        return;
    }
    modalEquipo.close();
    await cargarTodo();
    avisarTorneo(data.mensaje, 'exito');
});

// ── Calendario (por ronda) ──
function pintarCalendario() {
    const contenedor = document.getElementById('listaRondas');
    if (rondas.length === 0) {
        contenedor.innerHTML = nlVacio(torneo.estado === 'pendiente'
            ? 'El calendario se arma cuando cierran las inscripciones.'
            : 'Todavía no hay rondas publicadas.');
        return;
    }
    contenedor.innerHTML = rondas.map(r => `
        <section class="ronda">
            <div class="ronda__cabecera">
                <h2>${escapeHtml(nombreRonda(r))}</h2>
                ${r.fecha ? `<span class="texto-tenue">${escapeHtml(formatearDia(r.fecha))}</span>` : ''}
                ${r.estado === 'borrador' ? '<span class="chip chip-gris">Borrador · no es pública</span>' : ''}
            </div>
            <div class="lista-partidos">
                ${r.partidos.map(p => nlPartido(p, { ronda: false, destacado: esMio(p) })).join('') || nlVacio('Sin partidos.')}
            </div>
        </section>
    `).join('');
}

// ── Llaves (eliminación directa), una columna por ronda ──
function pintarLlaves() {
    const contenedor = document.getElementById('llaves');
    if (rondas.length === 0) {
        contenedor.innerHTML = nlVacio(torneo.estado === 'pendiente'
            ? 'Las llaves se arman cuando cierran las inscripciones.'
            : 'Las llaves todavía no se publicaron.');
        return;
    }
    const campeon = campeonDelTorneo(torneo, rondas, posiciones);
    contenedor.innerHTML = `
        <div class="llaves">
            ${rondas.map(r => `
                <section class="llaves__ronda" aria-label="${escapeHtml(nombreRonda(r))}">
                    <h2 class="llaves__titulo">${escapeHtml(nombreRonda(r))}</h2>
                    <div class="llaves__partidos">${r.partidos.map(llave).join('')}</div>
                </section>
            `).join('')}
            <section class="llaves__ronda llaves__ronda--campeon" aria-label="Campeón">
                <h2 class="llaves__titulo">Campeón</h2>
                <div class="llaves__partidos">
                    <div class="llave llave--campeon">
                        <img src="../iconos/trophy-128.webp" alt="" width="40" height="40">
                        <span class="${campeon ? '' : 'texto-tenue'}">${campeon ? escapeHtml(campeon) : 'Por definir'}</span>
                    </div>
                </div>
            </section>
        </div>
        <p class="texto-tenue nota-deslizar oculto">Deslizá de costado para ver todas las rondas.</p>
    `;
    vigilarNotaDeslizar();
}

// "Deslizá de costado…" se muestra solo cuando las llaves no entran en la
// pantalla (antes se mostraba siempre, aunque entraran todas las rondas).
// El observador también avisa cuando la pestaña se hace visible o cambia
// el tamaño de la ventana.
let observadorLlaves = null;
function vigilarNotaDeslizar() {
    observadorLlaves?.disconnect();
    const llaves = document.querySelector('#llaves .llaves');
    const nota = document.querySelector('#llaves .nota-deslizar');
    if (!llaves || !nota || !window.ResizeObserver) return;
    observadorLlaves = new ResizeObserver(() => {
        nota.classList.toggle('oculto', llaves.scrollWidth <= llaves.clientWidth + 1);
    });
    observadorLlaves.observe(llaves);
}

function llave(p) {
    const jugado = p.estado === 'finalizado';
    const ganador = Number(p.id_ganador);
    const fila = (id, nombre, puntaje, vacio) => `
        <div class="llave__fila ${jugado && ganador && ganador === Number(id) ? 'llave__fila--ganador' : ''}">
            <span class="${nombre ? '' : 'texto-tenue'}">${nombre ? escapeHtml(nombre) : vacio}</span>
            <strong>${jugado && !Number(p.es_pase_libre) ? puntaje : ''}</strong>
        </div>`;
    const detalle = Number(p.es_pase_libre) ? 'Pase libre'
        : (p.fecha_hora ? formatearFechaHora(p.fecha_hora) : (jugado ? '' : 'Fecha a confirmar'));
    return `
        <div class="llave ${esMio(p) ? 'llave--propia' : ''}">
            ${fila(p.id_local, p.nombre_local, p.puntaje_local, 'Por definir')}
            ${fila(p.id_visitante, p.nombre_visitante, p.puntaje_visitante, Number(p.es_pase_libre) ? '—' : 'Por definir')}
            ${detalle ? `<span class="llave__detalle">${escapeHtml(detalle)}</span>` : ''}
        </div>
    `;
}

// ── Posiciones (liga y suizo): se calculan en la base con los resultados vigentes ──
function pintarPosiciones() {
    const contenedor = document.getElementById('tablaPosiciones');
    if (posiciones.length === 0) {
        contenedor.innerHTML = nlVacio('Todavía no hay inscriptos.');
        return;
    }
    const miId = Number(torneo.permisos.mi_participacion?.id_participante);
    const sinJugar = posiciones.every(p => Number(p.pj) === 0);
    // Para enlazar cada fila al perfil del jugador o del equipo
    const porId = Object.fromEntries(participantes.map(p => [Number(p.id_participante), p]));
    const nombreConEnlace = (fila) => {
        const enlace = porId[Number(fila.id_participante)] && nlEnlaceParticipante(porId[Number(fila.id_participante)]);
        return enlace ? `<a href="${enlace}">${escapeHtml(fila.nombre)}</a>` : `<span>${escapeHtml(fila.nombre)}</span>`;
    };
    contenedor.innerHTML = `
        ${sinJugar ? '<p class="texto-tenue">Todavía no se jugaron partidos: la tabla se actualiza con cada resultado.</p>' : ''}
        <div class="tabla-scroll panel panel--tabla">
            <table class="tabla tabla-posiciones">
                <caption class="solo-lectores">Tabla de posiciones</caption>
                <thead>
                    <tr>
                        <th scope="col" class="numero">#</th>
                        <th scope="col">${torneo.modalidad === 'equipos' ? 'Equipo' : 'Jugador'}</th>
                        <th scope="col" class="numero"><abbr title="Partidos jugados">PJ</abbr></th>
                        <th scope="col" class="numero"><abbr title="Ganados">PG</abbr></th>
                        <th scope="col" class="numero"><abbr title="Empatados">PE</abbr></th>
                        <th scope="col" class="numero"><abbr title="Perdidos">PP</abbr></th>
                        <th scope="col" class="numero col-goles"><abbr title="A favor">GF</abbr></th>
                        <th scope="col" class="numero col-goles"><abbr title="En contra">GC</abbr></th>
                        <th scope="col" class="numero"><abbr title="Diferencia">DIF</abbr></th>
                        <th scope="col" class="numero">Pts</th>
                        ${conBuchholz ? '<th scope="col" class="numero"><abbr title="Desempate: suma de los puntos de los rivales">Buch.</abbr></th>' : ''}
                    </tr>
                </thead>
                <tbody>
                    ${posiciones.map(p => `
                        <tr class="${Number(p.id_participante) === miId ? 'fila-propia' : ''}">
                            <td class="numero"><span class="puesto ${p.pj > 0 && p.posicion <= 3 ? 'puesto--' + p.posicion : ''}">${p.posicion}</span></td>
                            <td class="tabla-posiciones__nombre">${nombreConEnlace(p)}</td>
                            <td class="numero">${p.pj}</td>
                            <td class="numero">${p.pg}</td>
                            <td class="numero">${p.pe}</td>
                            <td class="numero">${p.pp}</td>
                            <td class="numero col-goles">${p.gf}</td>
                            <td class="numero col-goles">${p.gc}</td>
                            <td class="numero">${p.diferencia > 0 ? '+' : ''}${p.diferencia}</td>
                            <td class="numero destacada">${p.puntos}</td>
                            ${conBuchholz ? `<td class="numero">${p.buchholz}</td>` : ''}
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
        <p class="texto-tenue leyenda">PJ jugados · PG ganados · PE empatados · PP perdidos<span class="col-goles"> · GF a favor · GC en contra</span> · DIF diferencia${conBuchholz ? ' · Buch. Buchholz (desempate)' : ''}</p>
    `;
}

// ── Participantes ──
function pintarParticipantes() {
    const contenedor = document.getElementById('listaParticipantes');
    const porEquipos = torneo.modalidad === 'equipos';
    if (participantes.length === 0) {
        contenedor.innerHTML = nlVacio('Todavía no hay inscriptos.');
        return;
    }

    const miId = sesion.logueado ? Number(sesion.usuario.id_usuario) : null;
    const permisos = torneo.permisos;
    const maximo = Number(torneo.configuracion?.max_integrantes_equipo) || null;
    // Puede pedir unirse quien está logueado, no organiza el torneo y todavía no juega en él.
    const puedeSolicitar = porEquipos && torneo.estado === 'pendiente' && miId && !permisos.gestionar && !permisos.estoy_inscripto;

    contenedor.innerHTML = `
        <p class="texto-tenue">${participantes.length} de ${torneo.cupo} lugares ocupados.</p>
        <ul class="participantes ${porEquipos ? 'participantes--equipos' : ''}">
            ${participantes.map(p => {
                const imagen = p.imagen
                    ? nlRutaSubida(p.tipo === 'equipo' ? 'equipos' : 'perfiles', p.imagen)
                    : (p.tipo === 'equipo' ? '../iconos/group-128.webp' : NL_AVATAR_POR_DEFECTO);
                if (!porEquipos) {
                    return `<li><a class="participante" href="${nlEnlaceParticipante(p)}"><img src="${imagen}" alt="" width="36" height="36" loading="lazy"><span>${escapeHtml(p.nombre)}</span></a></li>`;
                }
                const soyCapitan = miId && Number(p.id_capitan) === miId;
                const completo = maximo && Number(p.cantidad_miembros) >= maximo;
                const sinCapitan = !p.id_capitan; // nadie podría aceptar la solicitud
                return `
                    <li class="participante participante--equipo">
                        <img src="${imagen}" alt="" width="44" height="44" loading="lazy">
                        <div class="participante__datos">
                            <strong><a class="tocable" href="${nlEnlaceParticipante(p)}">${escapeHtml(p.nombre)}</a></strong>
                            <span class="texto-tenue">${p.cantidad_miembros} ${Number(p.cantidad_miembros) === 1 ? 'jugador' : 'jugadores'}${maximo ? ` de ${maximo}` : ''}</span>
                        </div>
                        <div class="participante__acciones">
                            ${soyCapitan ? `<span class="chip">Sos el capitán</span><button type="button" class="btn btn-secundario btn-chico" data-solicitudes="${p.id_equipo}" aria-expanded="false">Solicitudes</button>` : ''}
                            ${puedeSolicitar && !completo && !sinCapitan ? `<button type="button" class="btn btn-contorno btn-chico" data-unirse="${p.id_equipo}">Pedir unirme</button>` : ''}
                            ${puedeSolicitar && completo ? '<span class="chip chip-gris">Completo</span>' : ''}
                        </div>
                        ${soyCapitan ? `<div class="participante__solicitudes oculto" id="solicitudes-${p.id_equipo}"></div>` : ''}
                    </li>
                `;
            }).join('')}
        </ul>
    `;

    contenedor.querySelectorAll('[data-unirse]').forEach(boton => boton.addEventListener('click', () =>
        accion(nlApi.post(`/equipos/${boton.dataset.unirse}/solicitudes`), boton)));
    contenedor.querySelectorAll('[data-solicitudes]').forEach(boton => boton.addEventListener('click', () =>
        verSolicitudes(boton)));
}

// Solicitudes para unirse al equipo: solo las ve y responde el capitán.
async function verSolicitudes(boton) {
    const idEquipo = boton.dataset.solicitudes;
    const panel = document.getElementById(`solicitudes-${idEquipo}`);
    const abrir = panel.classList.contains('oculto');
    panel.classList.toggle('oculto', !abrir);
    boton.setAttribute('aria-expanded', abrir);
    if (!abrir) return;

    panel.innerHTML = nlCargando('Cargando solicitudes…');
    const data = await nlApi.get(`/equipos/${idEquipo}/solicitudes`);
    if (!data.exito || data.solicitudes.length === 0) {
        panel.innerHTML = `<p class="texto-tenue">${data.exito ? 'No hay solicitudes pendientes.' : escapeHtml(data.mensaje)}</p>`;
        return;
    }
    panel.innerHTML = data.solicitudes.map(s => `
        <div class="solicitud">
            <span>${escapeHtml(s.nombre_usuario)}</span>
            <div class="grupo-botones">
                <button type="button" class="btn btn-primario btn-chico" data-responder="${s.id_solicitud}" data-respuesta="aceptar">Aceptar</button>
                <button type="button" class="btn btn-secundario btn-chico" data-responder="${s.id_solicitud}" data-respuesta="rechazar">Rechazar</button>
            </div>
        </div>
    `).join('');
    panel.querySelectorAll('[data-responder]').forEach(b => b.addEventListener('click', () =>
        accion(nlApi.post(`/solicitudes/${b.dataset.responder}/respuesta`, { accion: b.dataset.respuesta }), b)));
}

// ── Pestañas: la elegida queda en la dirección (#calendario) ──
let pestanas = null;

function activarAtajos(contenedor) {
    contenedor.querySelectorAll('[data-ir-a]').forEach(boton => {
        if (boton.dataset.atajo) return; // ya tiene su evento
        boton.dataset.atajo = '1';
        boton.addEventListener('click', () => {
            pestanas.activar(boton.dataset.irA);
            nlDesplazar(document.getElementById('pestanasTorneo'));
        });
    });
}

async function iniciar() {
    if (!idTorneo) {
        mostrarError('Falta indicar qué torneo querés ver.');
        return;
    }
    sesion = await nextleagueObtenerSesion();
    await cargarTodo();
    if (!torneo) return;

    pestanas = nlPestanas('pestanasTorneo', (clave) => history.replaceState(null, '', `#${clave}`));
    activarAtajos(document.getElementById('panelResumen'));
    abrirPestanaDeLaDireccion();
    window.addEventListener('hashchange', abrirPestanaDeLaDireccion);
}

// Abre la pestaña que indica la dirección (torneo?id=3#calendario),
// si existe para este formato de torneo.
function abrirPestanaDeLaDireccion() {
    const pedida = window.location.hash.slice(1);
    const boton = PESTANAS.includes(pedida) && document.querySelector(`#pestanasTorneo [data-pestana="${pedida}"]`);
    if (boton && !boton.classList.contains('oculto')) pestanas.activar(pedida);
}

iniciar();
