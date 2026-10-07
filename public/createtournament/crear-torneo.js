// =====================================================================
// NEXTLEAGUE - CREAR Y EDITAR TORNEO
//   createtournament         → crear un torneo nuevo
//   createtournament?id=7    → editar el torneo 7
// Antes el formulario solo servía para crear, no pedía la configuración
// (puntos, ida y vuelta, rondas del suizo, empates, correcciones, máximo
// de integrantes) ni dejaba elegir si se publicaba, y el formato era una
// lista sin explicación. Ahora:
//   - el formato se elige con tarjetas que explican cada uno;
//   - solo aparecen los campos que aplican al formato y la modalidad;
//   - la modalidad se completa sola con la que sugiere el juego;
//   - una vista previa muestra cómo va a quedar la tarjeta del torneo;
//   - al editar un torneo que ya tiene calendario, lo que cambiaría los
//     partidos queda bloqueado (la API también lo controla);
//   - el administrador elige el organizador a cargo y autoriza (o no) que
//     corrija resultados (letra, 5.1 y 5.2);
//   - "Guardar" se habilita recién cuando el formulario terminó de cargar:
//     antes, un clic mientras cargaba guardaba los valores por defecto.
// =====================================================================

const idTorneo = Number(nlParametro('id')) || null;
const form = document.getElementById('formTorneo');
const CAMPOS_TEXTO = ['nombre', 'id_juego', 'id_organizador', 'descripcion', 'cupo', 'max_integrantes_equipo', 'puntos_victoria',
    'puntos_empate', 'puntos_derrota', 'rondas_suizo', 'fecha_inicio', 'fecha_fin', 'premio', 'moneda'];
const CAMPOS_SI_NO = ['permitir_empates', 'ida_y_vuelta', 'publicado', 'permitir_correcciones'];
const ICONO_MODULO = { liga: 'podio', eliminacion_directa: 'llaves', sistema_suizo: 'modulos' };

let juegos = [];
let torneo = null;            // el torneo que se edita (null al crear)
let modalidadAMano = false;   // si la persona eligió la modalidad, no se pisa al cambiar el juego
let quitarLogo = false;
let quitarReglas = false;
let hayCambios = false;
let urlLogoLocal = null;
let formularioListo = false;  // hasta que termina de cargar, "Guardar" no hace nada

const campo = (id) => document.getElementById(id);
const moduloElegido = () => form.querySelector('[name="modulo"]:checked')?.value || '';
const modalidadElegida = () => form.querySelector('[name="modalidad"]:checked')?.value || '';
const juegoElegido = () => juegos.find(j => String(j.id_juego) === campo('id_juego').value) || null;
const hoy = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

// ── Opciones que vienen de la API ──
async function cargarOpciones() {
    const [respuestaJuegos, respuestaModulos] = await Promise.all([nlApi.get('/juegos'), nlApi.get('/modulos')]);
    juegos = respuestaJuegos.exito ? respuestaJuegos.juegos : [];
    campo('id_juego').innerHTML = respuestaJuegos.exito
        ? nlOpcionesJuegos(juegos, 'Elegí un juego')
        : '<option value="">No se pudieron cargar los juegos</option>';

    // Solo los formatos habilitados por el administrador. Al editar, el
    // formato del torneo se muestra aunque lo hayan deshabilitado después.
    const modulos = respuestaModulos.exito ? respuestaModulos.modulos : [];
    if (torneo && !modulos.some(m => m.codigo === torneo.modulo)) {
        modulos.push({ codigo: torneo.modulo, nombre: nombreModulo(torneo.modulo), descripcion: 'Este formato ya no se ofrece para torneos nuevos.' });
    }
    campo('modulo').innerHTML = modulos.length ? modulos.map(m => `
        <label class="opcion opcion--formato">
            <input type="radio" name="modulo" value="${escapeHtml(m.codigo)}" class="solo-lectores" data-estructural>
            <span class="opcion__icono">${nlIcono(ICONO_MODULO[m.codigo] || 'trofeo', 'icono-grande')}</span>
            <span class="opcion__texto"><strong>${escapeHtml(m.nombre)}</strong><small>${escapeHtml(m.descripcion || '')}</small></span>
        </label>
    `).join('') : '<p class="texto-tenue">No hay formatos disponibles. Pedile a un administrador que habilite alguno.</p>';
}

// ── Organizador a cargo: lo elige el administrador (letra, 5.1 y 5.2) ──
// Al crear, por defecto queda a cargo de quien lo crea; al editar, el
// actual aparece aunque ya no esté en la lista (por ejemplo, si lo desactivaron).
async function cargarOrganizadores(usuario) {
    if (usuario.rol !== 'administrador') return;
    const data = await nlApi.get(nlConParametros('/admin/usuarios', { rol: 'organizador', activo: 1, por_pagina: 100 }));
    const opciones = [{ id_usuario: usuario.id_usuario, nombre_usuario: `${usuario.nombre_usuario} (vos)` },
        ...(data.exito ? data.usuarios : [])];
    if (torneo && !opciones.some(o => Number(o.id_usuario) === Number(torneo.id_organizador))) {
        opciones.push({ id_usuario: torneo.id_organizador, nombre_usuario: torneo.organizador });
    }
    const lista = campo('id_organizador');
    lista.innerHTML = opciones.map(o => `<option value="${o.id_usuario}">${escapeHtml(o.nombre_usuario)}</option>`).join('');
    lista.value = String(torneo ? torneo.id_organizador : usuario.id_usuario);
    campo('campoOrganizador').classList.remove('oculto');
}

// La autorización para corregir resultados la da el administrador: el
// organizador ve si la tiene, pero no la puede cambiar.
function pintarCorrecciones(usuario) {
    if (usuario.rol === 'administrador') return;
    const casilla = campo('permitir_correcciones');
    casilla.disabled = true;
    campo('ayudaCorrecciones').textContent = casilla.checked
        ? '— el administrador te autorizó a corregir resultados en este torneo'
        : '— lo autoriza el administrador: pedíselo si necesitás corregir un resultado';
}

// ── Mostrar solo lo que aplica al formato y la modalidad elegidos ──
function actualizarSecciones() {
    const modulo = moduloElegido();
    const porEquipos = modalidadElegida() === 'equipos';
    const usaPuntos = modulo === 'liga' || modulo === 'sistema_suizo';

    campo('seccionPuntuacion').classList.toggle('oculto', !usaPuntos);
    document.querySelectorAll('.solo-liga').forEach(el => el.classList.toggle('oculto', modulo !== 'liga'));
    document.querySelectorAll('.solo-suizo').forEach(el => el.classList.toggle('oculto', modulo !== 'sistema_suizo'));
    document.querySelectorAll('.solo-equipos').forEach(el => el.classList.toggle('oculto', !porEquipos));

    // Números de sección seguidos aunque se oculte "Puntuación"
    let numero = 1;
    form.querySelectorAll('.seccion-form').forEach(seccion => {
        if (!seccion.classList.contains('oculto')) seccion.querySelector('.seccion-form__titulo span').textContent = numero++;
    });

    campo('unidadCupo').textContent = porEquipos ? '(equipos)' : '(jugadores)';
    const cupo = Number(campo('cupo').value);
    let ayuda = 'Entre 2 y 256.';
    if (modulo === 'liga' && cupo >= 2) {
        const partidos = (cupo * (cupo - 1)) / 2 * (campo('ida_y_vuelta').checked ? 2 : 1);
        ayuda = `Con el cupo completo se juegan ${partidos} partidos.`;
    } else if (modulo === 'eliminacion_directa' && cupo >= 2 && (cupo & (cupo - 1)) !== 0) {
        ayuda = 'Con 4, 8, 16, 32… las llaves quedan justas; con otra cantidad algunos pasan la primera ronda sin jugar.';
    }
    campo('ayudaCupo').textContent = ayuda;
    pintarVistaPrevia();
}

// Al elegir el juego se propone la modalidad que le corresponde.
function alCambiarJuego() {
    const juego = juegoElegido();
    const ayuda = campo('ayudaModalidad');
    ayuda.textContent = ayuda.dataset.fija || (juego
        ? `Para ${juego.nombre} se suele jugar ${juego.modalidad_sugerida === 'equipos' ? 'por equipos' : 'de forma individual'}.`
        : '');
    if (juego && !modalidadAMano) {
        const opcion = form.querySelector(`[name="modalidad"][value="${juego.modalidad_sugerida}"]`);
        if (opcion && !opcion.disabled) opcion.checked = true;
    }
    actualizarSecciones();
}

// ── Fechas: la de fin no puede ser anterior a la de inicio ──
function actualizarFechas() {
    if (!torneo) campo('fecha_inicio').min = hoy();
    campo('fecha_fin').min = campo('fecha_inicio').value || campo('fecha_inicio').min || '';
}

// ── Archivos: vista previa, quitar y deshacer ──
function formatearTamano(bytes) {
    return bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function pintarArchivo(tipo) {
    const input = campo(tipo);
    const caja = campo(`${tipo}Elegido`);
    const archivo = input.files[0];
    const actual = torneo ? (tipo === 'logo' ? torneo.logo : torneo.reglas_pdf) : null;
    const quitado = tipo === 'logo' ? quitarLogo : quitarReglas;
    let html = '';

    if (archivo) {
        if (tipo === 'logo') {
            if (urlLogoLocal) URL.revokeObjectURL(urlLogoLocal);
            urlLogoLocal = URL.createObjectURL(archivo);
        }
        html = `
            ${tipo === 'logo' ? `<img src="${urlLogoLocal}" alt="" width="48" height="48">` : nlIcono('archivo', 'icono-grande')}
            <span class="archivo-elegido__nombre">${escapeHtml(archivo.name)}<small>${formatearTamano(archivo.size)} · se sube al guardar</small></span>
            <button type="button" class="btn btn-texto btn-chico" data-accion="descartar">Quitar</button>
        `;
    } else if (actual && !quitado) {
        const url = nlRutaSubida(tipo === 'logo' ? 'torneos/logos' : 'torneos/reglas', actual);
        html = `
            ${tipo === 'logo' ? `<img src="${url}" alt="" width="48" height="48">` : nlIcono('archivo', 'icono-grande')}
            <span class="archivo-elegido__nombre">${tipo === 'logo' ? 'Logo actual' : `<a href="${url}" target="_blank" rel="noopener">Reglas actuales (PDF)</a>`}<small>Elegí otro archivo para reemplazarlo</small></span>
            <button type="button" class="btn btn-texto btn-chico" data-accion="quitar">${nlIcono('basura')}Quitar</button>
        `;
    } else if (actual && quitado) {
        html = `
            <span class="archivo-elegido__nombre">Se va a quitar al guardar</span>
            <button type="button" class="btn btn-texto btn-chico" data-accion="deshacer">Deshacer</button>
        `;
    }
    caja.innerHTML = html;
    caja.classList.toggle('oculto', !html);
    caja.querySelector('[data-accion="descartar"]')?.addEventListener('click', () => { input.value = ''; pintarArchivo(tipo); marcarCambio(); });
    caja.querySelector('[data-accion="quitar"]')?.addEventListener('click', () => { cambiarQuitar(tipo, true); });
    caja.querySelector('[data-accion="deshacer"]')?.addEventListener('click', () => { cambiarQuitar(tipo, false); });
    if (tipo === 'logo') pintarVistaPrevia();
}

function cambiarQuitar(tipo, valor) {
    if (tipo === 'logo') quitarLogo = valor; else quitarReglas = valor;
    pintarArchivo(tipo);
    marcarCambio();
}

// ── Vista previa de la tarjeta (la misma que se ve en los listados) ──
function pintarVistaPrevia() {
    const juego = juegoElegido();
    const t = {
        id_torneo: 0,
        nombre: campo('nombre').value.trim() || 'Nombre del torneo',
        juego: juego?.nombre || 'Juego',
        categoria: juego?.categoria || '',
        modulo: moduloElegido() || 'liga',
        modalidad: modalidadElegida() || 'individual',
        cupo: Number(campo('cupo').value) || 0,
        inscriptos: torneo ? Number(torneo.inscriptos) : 0,
        premio: Number(campo('premio').value) || 0,
        moneda: campo('moneda').value,
        fecha_inicio: campo('fecha_inicio').value || hoy(),
        estado: torneo ? torneo.estado : 'pendiente',
        logo: torneo && !quitarLogo ? torneo.logo : null,
    };
    const vista = campo('vistaPrevia');
    vista.innerHTML = nlTarjetaTorneo(t);
    // El logo elegido todavía no está subido: se muestra el archivo local
    if (campo('logo').files[0] && urlLogoLocal) {
        vista.querySelector('.tarjeta-torneo__imagen').firstElementChild.outerHTML = `<img src="${urlLogoLocal}" alt="" width="320" height="180">`;
    }
}

function marcarCambio() {
    hayCambios = true;
}

// Avisa antes de salir si hay cambios sin guardar
window.addEventListener('beforeunload', (e) => {
    if (hayCambios) e.preventDefault();
});

// ── Enviar ──
function armarDatos() {
    const datos = new FormData();
    const visible = (el) => !el.disabled && !el.closest('.oculto');
    CAMPOS_TEXTO.forEach(nombre => {
        const el = campo(nombre);
        if (visible(el)) datos.append(nombre, el.value.trim());
    });
    // Las casillas sin marcar no viajan en un formulario: se manda 1 o 0 explícito
    CAMPOS_SI_NO.forEach(nombre => {
        const el = campo(nombre);
        if (visible(el)) datos.append(nombre, el.checked ? '1' : '0');
    });
    ['modulo', 'modalidad'].forEach(nombre => {
        const elegido = form.querySelector(`[name="${nombre}"]:checked`);
        if (elegido && !elegido.disabled) datos.append(nombre, elegido.value);
    });
    if (campo('logo').files[0]) datos.append('logo', campo('logo').files[0]);
    if (campo('reglas').files[0]) datos.append('reglas', campo('reglas').files[0]);
    if (quitarLogo) datos.append('quitar_logo', '1');
    if (quitarReglas) datos.append('quitar_reglas', '1');
    return datos;
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!formularioListo) return;
    nlLimpiarErrores(form);
    form.querySelectorAll('.opciones-formato.input-error, .opciones-modalidad.input-error').forEach(el => el.classList.remove('input-error'));
    nlAlerta('alertaFormulario', '');

    const boton = campo('botonGuardar');
    nlBotonOcupado(boton, true, torneo ? 'Guardando…' : 'Creando…');
    const respuesta = await nlApi.post(torneo ? `/torneos/${torneo.id_torneo}` : '/torneos', armarDatos());
    nlBotonOcupado(boton, false);

    if (!respuesta.exito) {
        nlMostrarErrores(respuesta.errores);
        const cantidad = Object.keys(respuesta.errores || {}).length;
        nlAlerta('alertaFormulario', cantidad ? `Revisá ${cantidad === 1 ? 'el campo marcado' : `los ${cantidad} campos marcados`}.` : respuesta.mensaje, 'error');
        // Lleva al primer campo con error (en los grupos de opciones, a su primera opción)
        const primero = form.querySelector('.input-error');
        nlDesplazar(primero || campo('alertaFormulario'), { block: 'center' });
        const enfocable = primero?.matches('input, select, textarea') ? primero : primero?.querySelector('input');
        enfocable?.focus({ preventScroll: true });
        return;
    }

    hayCambios = false;
    const id = torneo ? torneo.id_torneo : respuesta.id_torneo;
    window.location.href = `../torneos/gestionar?id=${id}&${torneo ? 'guardado' : 'creado'}=1`;
});

// ── Editar: cargar el torneo y completar el formulario ──
function completarFormulario() {
    const t = torneo;
    const c = t.configuracion || {};
    const valores = {
        nombre: t.nombre, id_juego: t.id_juego, descripcion: t.descripcion || '', cupo: t.cupo,
        max_integrantes_equipo: c.max_integrantes_equipo ?? '', puntos_victoria: c.puntos_victoria,
        puntos_empate: c.puntos_empate, puntos_derrota: c.puntos_derrota, rondas_suizo: c.rondas_suizo ?? '',
        fecha_inicio: String(t.fecha_inicio).slice(0, 10), fecha_fin: String(t.fecha_fin).slice(0, 10),
        premio: Number(t.premio), moneda: t.moneda,
    };
    Object.entries(valores).forEach(([nombre, valor]) => { campo(nombre).value = valor; });
    campo('permitir_empates').checked = Number(c.permitir_empates) === 1;
    campo('ida_y_vuelta').checked = Number(c.ida_y_vuelta) === 1;
    campo('permitir_correcciones').checked = Number(c.permitir_correcciones) === 1;
    campo('publicado').checked = Number(t.publicado) === 1;
    form.querySelector(`[name="modulo"][value="${t.modulo}"]`).checked = true;
    form.querySelector(`[name="modalidad"][value="${t.modalidad}"]`).checked = true;
    modalidadAMano = true;

    document.title = `Editar ${t.nombre} · NextLeague`;
    campo('tituloFormulario').textContent = 'Editar torneo';
    campo('subtituloFormulario').textContent = t.nombre;
    campo('enlaceVolver').href = `../torneos/gestionar?id=${t.id_torneo}`;
    campo('enlaceVolver').classList.remove('oculto');
    campo('botonCancelar').href = `../torneos/gestionar?id=${t.id_torneo}`;
    campo('botonGuardar').textContent = 'Guardar cambios';
    campo('opcionPublicado').querySelector('span').innerHTML = 'Publicado <small class="texto-tenue">— visible para cualquier visitante</small>';

    // Con inscriptos, la modalidad ya no se cambia (habría jugadores sueltos
    // en un torneo por equipos o al revés)
    if (Number(t.inscriptos) > 0) {
        form.querySelectorAll('[name="modalidad"]').forEach(el => { el.disabled = true; });
        campo('ayudaModalidad').dataset.fija = 'Ya hay inscriptos: la modalidad no se puede cambiar.';
    }
    // Con el calendario armado, lo que cambiaría los partidos queda fijo
    if (t.estado !== 'pendiente') {
        form.querySelectorAll('[data-estructural]').forEach(el => { el.disabled = true; });
        nlAlerta('avisoBloqueo', 'Este torneo ya tiene calendario: el juego, el formato, la modalidad, el cupo y la puntuación quedan fijos para no cambiar los partidos ya armados.', 'aviso');
    }
    campo('contadorDescripcion').textContent = campo('descripcion').value.length;
    pintarArchivo('logo');
    pintarArchivo('reglas');
}

// Cuando no se puede crear o editar, se oculta el formulario y queda solo el aviso.
function bloquearFormulario(mensaje, tipo, accion) {
    nlAlerta('alertaFormulario', mensaje, tipo);
    campo('alertaFormulario').insertAdjacentHTML('beforeend',
        `<p class="accion-alerta"><a class="btn btn-secundario btn-chico" href="${accion.href}">${escapeHtml(accion.texto)}</a></p>`);
    document.querySelector('.formulario-torneo').classList.add('oculto');
    campo('subtituloFormulario').classList.add('oculto');
    nlAlerta('avisoBloqueo', '');
    hayCambios = false;
}

// ── Arranque ──
async function iniciar() {
    const sesion = await nextleagueExigirSesion();
    if (!sesion.logueado) return;

    if (idTorneo) {
        campo('tituloFormulario').textContent = 'Editar torneo';
        const data = await nlApi.get(`/torneos/${idTorneo}`);
        if (!data.exito || !data.torneo.permisos.gestionar) {
            bloquearFormulario(data.exito ? 'Solo el organizador del torneo y los administradores pueden editarlo.' : data.mensaje,
                'error', { texto: 'Ver torneos', href: '../torneos/explorar' });
            return;
        }
        torneo = data.torneo;
        if (torneo.estado === 'cancelado') {
            bloquearFormulario('Un torneo cancelado no se puede editar: queda como historial.', 'aviso',
                { texto: 'Ver el torneo', href: `../torneos/torneo?id=${torneo.id_torneo}` });
            return;
        }
    } else if (sesion.usuario.rol === 'participante') {
        bloquearFormulario('Tu cuenta es de participante: para crear torneos necesitás una cuenta de organizador. Pedíselo a un administrador.',
            'aviso', { texto: 'Ver torneos para inscribirte', href: '../torneos/explorar' });
        return;
    }
    await Promise.all([cargarOpciones(), cargarOrganizadores(sesion.usuario)]);

    if (torneo) {
        completarFormulario();
    } else {
        form.querySelector('[name="modulo"]')?.click(); // el primero queda elegido por defecto
        campo('fecha_inicio').value = hoy();
        hayCambios = false;
    }
    pintarCorrecciones(sesion.usuario);
    actualizarFechas();
    alCambiarJuego();
    formularioListo = true;
    campo('botonGuardar').disabled = false;
}

// ── Eventos ──
form.addEventListener('input', marcarCambio);
form.addEventListener('change', (e) => {
    marcarCambio();
    if (e.target.name === 'modalidad') modalidadAMano = true;
    if (e.target.id === 'id_juego') alCambiarJuego(); else actualizarSecciones();
    if (e.target.id === 'fecha_inicio') actualizarFechas();
    if (e.target.id === 'logo' || e.target.id === 'reglas') {
        if (e.target.id === 'logo') quitarLogo = false; else quitarReglas = false;
        pintarArchivo(e.target.id);
    }
});
['nombre', 'cupo', 'premio'].forEach(id => campo(id).addEventListener('input', () => (id === 'cupo' ? actualizarSecciones() : pintarVistaPrevia())));
campo('descripcion').addEventListener('input', () => { campo('contadorDescripcion').textContent = campo('descripcion').value.length; });

iniciar();
