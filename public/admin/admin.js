// =====================================================================
// NEXTLEAGUE - PANEL DE ADMINISTRACIÓN
// Antes el panel tenía tres pestañas (usuarios, torneos y registro de
// actividad) con tablas de hasta 100 filas, sin búsqueda ni filtros, y
// no permitía crear usuarios, manejar los módulos ni la configuración,
// ni descargar reportes, aunque la API ya lo hacía. Ahora tiene un menú
// lateral con siete secciones:
//   Resumen · Usuarios · Torneos · Módulos · Configuración · Auditoría · Reportes
// Cada sección se carga la primera vez que se abre y queda en la
// dirección (admin#usuarios). Todo lo controla también la API: si
// alguien que no es administrador llama a /api/admin/..., recibe 403.
// =====================================================================

const SECCIONES = ['resumen', 'usuarios', 'torneos', 'modulos', 'configuracion', 'auditoria', 'reportes'];
const seccionesCargadas = new Set();
let sesion = null;
const campo = (id) => document.getElementById(id);

function avisar(mensaje, tipo) {
    nlAlerta('alertaAdmin', mensaje, tipo);
    if (mensaje) nlDesplazar(campo('alertaAdmin'), { block: 'nearest' });
}

// Espera a que se deje de escribir antes de buscar
function alEscribir(input, accion) {
    let espera = null;
    input.addEventListener('input', () => {
        clearTimeout(espera);
        espera = setTimeout(accion, 350);
    });
}

// ── Navegación entre secciones ──
function mostrarSeccion(nombre) {
    if (!SECCIONES.includes(nombre)) nombre = 'resumen';
    SECCIONES.forEach(s => campo(`seccion-${s}`).classList.toggle('oculto', s !== nombre));
    document.querySelectorAll('#menuAdmin a').forEach(a => {
        if (a.dataset.seccion === nombre) {
            a.setAttribute('aria-current', 'page');
            a.scrollIntoView({ block: 'nearest', inline: 'center' }); // en celular el menú se desliza
        } else {
            a.removeAttribute('aria-current');
        }
    });
    nlAlerta('alertaAdmin', '');
    document.title = `${campo(`seccion-${nombre}`).querySelector('h1').textContent} · Administración · NextLeague`;
    if (!seccionesCargadas.has(nombre)) {
        seccionesCargadas.add(nombre);
        CARGAR[nombre]?.();
    }
}

// ═════════════════════════════ RESUMEN ═════════════════════════════
async function cargarResumen() {
    campo('tarjetasResumen').innerHTML = `<li>${nlCargando()}</li>`;
    const [data, actividad] = await Promise.all([nlApi.get('/admin/resumen'), nlApi.get('/admin/auditoria?por_pagina=6')]);
    if (!data.exito) {
        campo('tarjetasResumen').innerHTML = `<li>${nlVacio(data.mensaje)}</li>`;
        return;
    }
    const r = data.resumen;
    const tarjetas = [
        [r.usuarios, 'Usuarios activos', 'usuarios'],
        [r.torneos_activos, 'Torneos activos', 'trofeo'],
        [r.equipos, 'Equipos', 'escudo'],
        [r.partidos_jugados, 'Partidos jugados', 'check'],
        [r.partidos_esta_semana, 'Partidos esta semana', 'calendario'],
        [r.cambios_hoy, 'Cambios registrados hoy', 'historial'],
    ];
    campo('tarjetasResumen').innerHTML = tarjetas.map(([valor, texto, icono]) => `
        <li class="tarjeta-resumen">
            <span class="tarjeta-resumen__icono">${nlIcono(icono)}</span>
            <strong>${Number(valor).toLocaleString('es-UY')}</strong>
            <span>${texto}</span>
        </li>
    `).join('');

    const roles = r.usuarios_por_rol.map(f => [NL_NOMBRE_ROL[f.rol] || f.rol, Number(f.cantidad)]);
    pintarBarras('barrasRoles', roles);
    const porEstado = Object.fromEntries(r.torneos_por_estado.map(f => [f.estado, Number(f.cantidad)]));
    pintarBarras('barrasEstados', ['pendiente', 'en_curso', 'finalizado', 'cancelado'].map(e => [NOMBRE_ESTADO_TORNEO[e], porEstado[e] || 0]));

    campo('actividadReciente').innerHTML = actividad.exito && actividad.registros.length
        ? tablaAuditoria(actividad.registros, true)
        : nlVacio('Todavía no hay actividad registrada.');
}

// Barras horizontales con <progress> (sin estilos en línea)
function pintarBarras(id, filas) {
    const total = filas.reduce((suma, [, cantidad]) => suma + cantidad, 0) || 1;
    campo(id).innerHTML = filas.map(([nombre, cantidad]) => `
        <li class="barra">
            <span class="barra__nombre">${escapeHtml(nombre)}</span>
            <progress class="progreso" max="${total}" value="${cantidad}" aria-label="${escapeHtml(nombre)}: ${cantidad}"></progress>
            <strong class="barra__valor">${cantidad}</strong>
        </li>
    `).join('');
}

// ═════════════════════════════ USUARIOS ═════════════════════════════
const filtrosUsuarios = { buscar: '', rol: '', activo: '', pagina: 1 };

// Cada lista recuerda el número de su último pedido: si se cambian los
// filtros rápido y la respuesta de uno anterior llega tarde, se descarta
// (si no, podía pisar el resultado del filtro nuevo). Explorar hace lo mismo.
const ultimoPedido = { usuarios: 0, torneos: 0, auditoria: 0 };

async function cargarUsuarios() {
    const contenedor = campo('tablaUsuarios');
    const numero = ++ultimoPedido.usuarios;
    contenedor.innerHTML = nlCargando('Cargando usuarios…');
    const data = await nlApi.get(nlConParametros('/admin/usuarios', { ...filtrosUsuarios, por_pagina: 20 }));
    if (numero !== ultimoPedido.usuarios) return; // llegó la respuesta de un filtro anterior
    if (!data.exito) {
        contenedor.innerHTML = nlVacio(data.mensaje);
        return;
    }
    const total = data.paginacion.total;
    campo('cantidadUsuarios').textContent = total === 1 ? '1 usuario' : `${total} usuarios`;
    if (data.usuarios.length === 0) {
        contenedor.innerHTML = nlVacio('No hay usuarios con esos filtros.');
        nlPaginacion('paginacionUsuarios', null);
        return;
    }
    const miId = Number(sesion.usuario.id_usuario);
    contenedor.innerHTML = `
        <div class="tabla-scroll panel panel--tabla">
            <table class="tabla tabla-admin">
                <caption class="solo-lectores">Usuarios</caption>
                <thead><tr>
                    <th scope="col">Usuario</th><th scope="col">Correo</th><th scope="col">Rol</th><th scope="col">Estado</th>
                    <th scope="col">Último acceso</th><th scope="col" class="numero">Torneos</th><th scope="col"><span class="solo-lectores">Acciones</span></th>
                </tr></thead>
                <tbody>
                    ${data.usuarios.map(u => {
                        const soyYo = Number(u.id_usuario) === miId;
                        return `
                        <tr>
                            <td>
                                <div class="fila-usuario">
                                    <a class="celda-usuario" href="${nlEnlaceJugador(u.id_usuario)}">
                                        <img src="${nlAvatar(u.foto_perfil)}" alt="" width="28" height="28" loading="lazy">
                                        <span>${escapeHtml(u.nombre_usuario)}</span>
                                    </a>
                                    ${soyYo ? '<span class="chip">Vos</span>' : ''}
                                    ${Number(u.debe_cambiar_contrasena) ? '<span class="chip chip-aviso" title="Tiene que cambiar la contraseña al entrar">Clave temporal</span>' : ''}
                                </div>
                            </td>
                            <td>${escapeHtml(u.correo)}</td>
                            <td>
                                <select class="control control--chico" data-rol="${u.id_usuario}" data-nombre="${escapeHtml(u.nombre_usuario)}" aria-label="Rol de ${escapeHtml(u.nombre_usuario)}" ${soyYo ? 'disabled title="No podés cambiar tu propio rol"' : ''}>
                                    ${['participante', 'organizador', 'administrador'].map(r => `<option value="${r}" ${r === u.rol ? 'selected' : ''}>${NL_NOMBRE_ROL[r]}</option>`).join('')}
                                </select>
                            </td>
                            <td>${Number(u.activo) ? '<span class="chip chip-exito">Activo</span>' : '<span class="chip chip-gris">Inactivo</span>'}</td>
                            <td>${u.ultimo_acceso ? formatearFechaHora(u.ultimo_acceso) : '<span class="texto-tenue">Nunca</span>'}</td>
                            <td class="numero">${u.torneos_creados}</td>
                            <td class="acciones-tabla">
                                <button type="button" class="btn btn-texto btn-chico" data-editar="${u.id_usuario}" aria-label="Editar a ${escapeHtml(u.nombre_usuario)}">${nlIcono('editar')}</button>
                                ${soyYo ? '' : (Number(u.activo)
                                    ? `<button type="button" class="btn btn-secundario btn-chico" data-activo="0" data-id="${u.id_usuario}" data-nombre="${escapeHtml(u.nombre_usuario)}">Desactivar</button>`
                                    : `<button type="button" class="btn btn-secundario btn-chico" data-activo="1" data-id="${u.id_usuario}" data-nombre="${escapeHtml(u.nombre_usuario)}">Reactivar</button>`)}
                                ${!soyYo && Number(u.torneos_creados) === 0 ? `<button type="button" class="btn btn-texto btn-chico boton-borrar" data-eliminar="${u.id_usuario}" data-nombre="${escapeHtml(u.nombre_usuario)}" aria-label="Eliminar a ${escapeHtml(u.nombre_usuario)}">${nlIcono('basura')}</button>` : ''}
                            </td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
        </div>
    `;
    const porId = Object.fromEntries(data.usuarios.map(u => [String(u.id_usuario), u]));

    contenedor.querySelectorAll('[data-rol]').forEach(select => select.addEventListener('change', async () => {
        const ok = await nlConfirmar(`${select.dataset.nombre} pasa a ser ${NL_NOMBRE_ROL[select.value].toLowerCase()}. Los permisos cambian en su próxima acción.`,
            { titulo: '¿Cambiar el rol?', aceptar: 'Cambiar rol' });
        if (!ok) {
            select.value = porId[select.dataset.rol].rol;
            return;
        }
        actualizarUsuario(select.dataset.rol, { rol: select.value });
    }));
    contenedor.querySelectorAll('[data-activo]').forEach(boton => boton.addEventListener('click', async () => {
        const activar = boton.dataset.activo === '1';
        if (!activar) {
            const ok = await nlConfirmar(`${boton.dataset.nombre} no va a poder entrar. Sus torneos, equipos y resultados se conservan; podés reactivarlo cuando quieras.`,
                { titulo: '¿Desactivar la cuenta?', aceptar: 'Desactivar', peligro: true });
            if (!ok) return;
        }
        actualizarUsuario(boton.dataset.id, { activo: activar });
    }));
    contenedor.querySelectorAll('[data-eliminar]').forEach(boton => boton.addEventListener('click', async () => {
        const ok = await nlConfirmar(`Se borra la cuenta de ${boton.dataset.nombre}. Solo se puede con cuentas sin actividad; si tiene historial, desactivala.`,
            { titulo: '¿Eliminar el usuario?', aceptar: 'Eliminar', peligro: true });
        if (!ok) return;
        const data = await nlApi.delete(`/admin/usuarios/${boton.dataset.eliminar}`);
        avisar(data.mensaje, data.exito ? 'exito' : 'error');
        cargarUsuarios();
    }));
    contenedor.querySelectorAll('[data-editar]').forEach(boton => boton.addEventListener('click', () =>
        abrirModalUsuario(porId[boton.dataset.editar])));

    nlPaginacion('paginacionUsuarios', data.paginacion, (pagina) => {
        filtrosUsuarios.pagina = pagina;
        cargarUsuarios();
    });
}

async function actualizarUsuario(id, cambios) {
    const data = await nlApi.put(`/admin/usuarios/${id}`, cambios);
    avisar(data.errores ? Object.values(data.errores)[0] : data.mensaje, data.exito ? 'exito' : 'error');
    cargarUsuarios(); // si la API lo rechazó, vuelve a mostrar el valor real
}

alEscribir(campo('buscarUsuario'), () => { filtrosUsuarios.buscar = campo('buscarUsuario').value.trim(); filtrosUsuarios.pagina = 1; cargarUsuarios(); });
campo('filtroRol').addEventListener('change', (e) => { filtrosUsuarios.rol = e.target.value; filtrosUsuarios.pagina = 1; cargarUsuarios(); });
campo('filtroActivo').addEventListener('change', (e) => { filtrosUsuarios.activo = e.target.value; filtrosUsuarios.pagina = 1; cargarUsuarios(); });
campo('filtrosUsuarios').addEventListener('submit', (e) => e.preventDefault());

// ── Ventana para crear o editar un usuario ──
const modalUsuario = campo('modalUsuario');
let usuarioEditado = null;
let claveUsuarioValida = () => false;

function abrirModalUsuario(usuario = null) {
    usuarioEditado = usuario;
    const form = campo('formUsuario');
    form.reset();
    nlLimpiarErrores(form);
    campo('tituloModalUsuario').textContent = usuario ? `Editar a ${usuario.nombre_usuario}` : 'Nuevo usuario';
    campo('guardarUsuario').textContent = usuario ? 'Guardar cambios' : 'Crear usuario';
    // Al editar solo se cambian nombre y correo (el rol y el estado se cambian en la tabla)
    campo('campoUsuarioClave').classList.toggle('oculto', Boolean(usuario));
    campo('campoUsuarioRol').classList.toggle('oculto', Boolean(usuario));
    campo('usuario_nombre').value = usuario?.nombre_usuario || '';
    campo('usuario_correo').value = usuario?.correo || '';
    campo('usuario_clave').dispatchEvent(new Event('input'));
    modalUsuario.showModal();
    campo('usuario_nombre').focus();
}

campo('botonNuevoUsuario').addEventListener('click', () => abrirModalUsuario());
campo('cancelarUsuario').addEventListener('click', () => modalUsuario.close());

campo('formUsuario').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    nlLimpiarErrores(form);
    const mapa = { nombre_usuario: 'usuario_nombre', correo: 'usuario_correo', contrasena: 'usuario_clave', rol: 'usuario_rol' };
    const datos = { nombre_usuario: campo('usuario_nombre').value.trim(), correo: campo('usuario_correo').value.trim() };
    if (!usuarioEditado) {
        if (!claveUsuarioValida()) {
            nlMostrarErrores({ contrasena: 'La contraseña no cumple todos los requisitos.' }, mapa);
            return;
        }
        datos.contrasena = campo('usuario_clave').value;
        datos.rol = campo('usuario_rol').value;
    }
    const boton = campo('guardarUsuario');
    nlBotonOcupado(boton, true);
    const data = usuarioEditado
        ? await nlApi.put(`/admin/usuarios/${usuarioEditado.id_usuario}`, datos)
        : await nlApi.post('/admin/usuarios', datos);
    nlBotonOcupado(boton, false);
    if (!data.exito) {
        if (data.errores) nlMostrarErrores(data.errores, mapa);
        else campo('usuario_nombreError').textContent = data.mensaje;
        return;
    }
    modalUsuario.close();
    avisar(usuarioEditado ? 'Usuario actualizado.' : `${data.mensaje} Al entrar le vamos a pedir que cambie la contraseña.`, 'exito');
    cargarUsuarios();
});

// ═════════════════════════════ TORNEOS ═════════════════════════════
const filtrosTorneos = { buscar: '', estado: '', modulo: '', pagina: 1 };

async function cargarTorneos() {
    const contenedor = campo('tablaTorneos');
    const numero = ++ultimoPedido.torneos;
    contenedor.innerHTML = nlCargando('Cargando torneos…');
    const data = await nlApi.get(nlConParametros('/admin/torneos', { ...filtrosTorneos, por_pagina: 20 }));
    if (numero !== ultimoPedido.torneos) return; // llegó la respuesta de un filtro anterior
    if (!data.exito) {
        contenedor.innerHTML = nlVacio(data.mensaje);
        return;
    }
    const total = data.paginacion.total;
    campo('cantidadTorneos').textContent = total === 1 ? '1 torneo' : `${total} torneos`;
    if (data.torneos.length === 0) {
        contenedor.innerHTML = nlVacio('No hay torneos con esos filtros.');
        nlPaginacion('paginacionTorneos', null);
        return;
    }
    contenedor.innerHTML = `
        <div class="tabla-scroll panel panel--tabla">
            <table class="tabla tabla-admin">
                <caption class="solo-lectores">Torneos</caption>
                <thead><tr>
                    <th scope="col">Torneo</th><th scope="col">Formato</th><th scope="col">Organizador</th><th scope="col">Estado</th>
                    <th scope="col" class="numero">Inscriptos</th><th scope="col">Inicio</th><th scope="col"><span class="solo-lectores">Acciones</span></th>
                </tr></thead>
                <tbody>
                    ${data.torneos.map(t => `
                        <tr>
                            <td>
                                <a class="celda-torneo" href="../torneos/torneo?id=${t.id_torneo}">${escapeHtml(t.nombre)}</a>
                                ${Number(t.publicado) ? '' : '<span class="chip chip-aviso">Oculto</span>'}
                                <span class="celda-sub">${escapeHtml(t.juego)}</span>
                            </td>
                            <td>${escapeHtml(nombreModulo(t.modulo))}<span class="celda-sub">${escapeHtml(NOMBRE_MODALIDAD[t.modalidad] || '')}</span></td>
                            <td><a href="${nlEnlaceJugador(t.id_organizador)}">${escapeHtml(t.organizador)}</a></td>
                            <td>${nlChipEstadoTorneo(t.estado)}</td>
                            <td class="numero">${t.inscriptos} / ${t.cupo}</td>
                            <td>${formatearFecha(t.fecha_inicio)}</td>
                            <td class="acciones-tabla">
                                ${t.estado !== 'cancelado' ? `<a class="btn btn-secundario btn-chico" href="../torneos/gestionar?id=${t.id_torneo}">Gestionar</a>` : ''}
                                ${t.estado === 'en_curso' ? `<button type="button" class="btn btn-texto btn-chico boton-borrar" data-cancelar="${t.id_torneo}" data-nombre="${escapeHtml(t.nombre)}">Cancelar</button>` : ''}
                                ${t.estado === 'pendiente' ? `<button type="button" class="btn btn-texto btn-chico boton-borrar" data-eliminar-torneo="${t.id_torneo}" data-nombre="${escapeHtml(t.nombre)}" aria-label="Eliminar ${escapeHtml(t.nombre)}">${nlIcono('basura')}</button>` : ''}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    contenedor.querySelectorAll('[data-cancelar]').forEach(boton => boton.addEventListener('click', async () => {
        const ok = await nlConfirmar(`"${boton.dataset.nombre}" deja de jugarse. Sus partidos y resultados se conservan como historial.`,
            { titulo: '¿Cancelar el torneo?', aceptar: 'Cancelar torneo', peligro: true });
        if (!ok) return;
        const data = await nlApi.post(`/torneos/${boton.dataset.cancelar}/cancelar`);
        avisar(data.mensaje, data.exito ? 'exito' : 'error');
        cargarTorneos();
    }));
    contenedor.querySelectorAll('[data-eliminar-torneo]').forEach(boton => boton.addEventListener('click', async () => {
        const ok = await nlConfirmar(`Se borran "${boton.dataset.nombre}" y sus inscripciones. Si ya tiene calendario no se puede: hay que cancelarlo.`,
            { titulo: '¿Eliminar el torneo?', aceptar: 'Eliminar', peligro: true });
        if (!ok) return;
        const data = await nlApi.delete(`/torneos/${boton.dataset.eliminarTorneo}`);
        avisar(data.mensaje, data.exito ? 'exito' : 'error');
        cargarTorneos();
    }));
    nlPaginacion('paginacionTorneos', data.paginacion, (pagina) => {
        filtrosTorneos.pagina = pagina;
        cargarTorneos();
    });
}

alEscribir(campo('buscarTorneo'), () => { filtrosTorneos.buscar = campo('buscarTorneo').value.trim(); filtrosTorneos.pagina = 1; cargarTorneos(); });
campo('filtroEstadoTorneo').addEventListener('change', (e) => { filtrosTorneos.estado = e.target.value; filtrosTorneos.pagina = 1; cargarTorneos(); });
campo('filtroModuloTorneo').addEventListener('change', (e) => { filtrosTorneos.modulo = e.target.value; filtrosTorneos.pagina = 1; cargarTorneos(); });
campo('filtrosTorneos').addEventListener('submit', (e) => e.preventDefault());

// ═════════════════════════════ MÓDULOS ═════════════════════════════
const ICONO_MODULO = { liga: 'podio', eliminacion_directa: 'llaves', sistema_suizo: 'modulos' };

async function cargarModulos() {
    const contenedor = campo('listaModulos');
    contenedor.innerHTML = nlCargando();
    const data = await nlApi.get('/admin/modulos');
    if (!data.exito) {
        contenedor.innerHTML = nlVacio(data.mensaje);
        return;
    }
    contenedor.innerHTML = data.modulos.map(m => `
        <div class="panel modulo ${Number(m.habilitado) ? '' : 'modulo--apagado'}">
            <span class="modulo__icono">${nlIcono(ICONO_MODULO[m.codigo] || 'trofeo', 'icono-grande')}</span>
            <div class="modulo__datos">
                <h2>${escapeHtml(m.nombre)}</h2>
                <p class="texto-secundario">${escapeHtml(m.descripcion || '')}</p>
                <p class="texto-tenue">${m.torneos} ${Number(m.torneos) === 1 ? 'torneo lo usa' : 'torneos lo usan'}</p>
            </div>
            <label class="interruptor">
                <input type="checkbox" data-modulo="${m.id_modulo}" ${Number(m.habilitado) ? 'checked' : ''}>
                <span>${Number(m.habilitado) ? 'Habilitado' : 'Deshabilitado'}</span>
            </label>
        </div>
    `).join('');
    contenedor.querySelectorAll('[data-modulo]').forEach(interruptor => interruptor.addEventListener('change', async () => {
        interruptor.disabled = true;
        const data = await nlApi.put(`/admin/modulos/${interruptor.dataset.modulo}`, { habilitado: interruptor.checked });
        avisar(data.mensaje, data.exito ? 'exito' : 'error');
        cargarModulos(); // si no se pudo (era el último habilitado), vuelve a como estaba
    }));
}

// ═════════════════════════════ CONFIGURACIÓN ═════════════════════════════
// Nombres claros y grupos para cada clave (las claves nuevas van a "Otros")
const CONFIGURACION = {
    nombre_sitio: ['General', 'Nombre del sitio'],
    registro_abierto: ['General', 'Registro abierto a nuevos usuarios'],
    torneos_por_pagina: ['General', 'Torneos por página en los listados'],
    max_intentos_login: ['Seguridad', 'Intentos de ingreso antes de bloquear'],
    minutos_bloqueo_login: ['Seguridad', 'Minutos de bloqueo tras los intentos fallidos'],
    minutos_inactividad_sesion: ['Seguridad', 'Minutos sin actividad para cerrar la sesión'],
    dias_recordarme: ['Seguridad', 'Días que dura "Recordarme"'],
    minutos_token_recuperacion: ['Seguridad', 'Minutos que vale el enlace para recuperar la contraseña'],
    max_tamano_imagen_mb: ['Archivos', 'Tamaño máximo de las imágenes (MB)'],
    max_tamano_reglas_mb: ['Archivos', 'Tamaño máximo del PDF de reglas (MB)'],
};
let configuracionOriginal = {};

async function cargarConfiguracion() {
    const contenedor = campo('camposConfiguracion');
    contenedor.innerHTML = nlCargando();
    const data = await nlApi.get('/admin/configuracion');
    if (!data.exito) {
        contenedor.innerHTML = nlVacio(data.mensaje);
        return;
    }
    configuracionOriginal = Object.fromEntries(data.configuracion.map(c => [c.clave, c.valor]));
    const grupos = {};
    data.configuracion.forEach(c => {
        const [grupo] = CONFIGURACION[c.clave] || ['Otros'];
        (grupos[grupo] ??= []).push(c);
    });
    contenedor.innerHTML = ['General', 'Seguridad', 'Archivos', 'Otros'].filter(g => grupos[g]).map(grupo => `
        <fieldset class="panel grupo-config">
            <legend class="grupo-config__titulo">${grupo}</legend>
            ${grupos[grupo].map(campoConfiguracion).join('')}
        </fieldset>
    `).join('');
}

function campoConfiguracion(c) {
    const id = `cfg-${c.clave}`;
    const etiqueta = escapeHtml(CONFIGURACION[c.clave]?.[1] || c.clave);
    const ayuda = c.descripcion ? `<span class="ayuda">${escapeHtml(c.descripcion)}</span>` : '';
    if (c.tipo === 'booleano') {
        return `
            <div class="campo">
                <label class="interruptor"><input type="checkbox" id="${id}" data-clave="${c.clave}" ${c.valor === '1' ? 'checked' : ''}><span>${etiqueta}</span></label>
                ${ayuda}<span class="error-campo" id="${id}Error"></span>
            </div>`;
    }
    return `
        <div class="campo">
            <label for="${id}">${etiqueta}</label>
            <input type="${c.tipo === 'numero' ? 'number' : 'text'}" id="${id}" data-clave="${c.clave}" value="${escapeHtml(c.valor)}"
                   ${c.tipo === 'numero' ? 'min="1" max="100000" inputmode="numeric"' : 'maxlength="255"'}>
            ${ayuda}<span class="error-campo" id="${id}Error"></span>
        </div>`;
}

campo('formConfiguracion').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    nlLimpiarErrores(form);
    // Solo se mandan los valores que cambiaron
    const cambios = {};
    form.querySelectorAll('[data-clave]').forEach(input => {
        const valor = input.type === 'checkbox' ? (input.checked ? '1' : '0') : input.value.trim();
        if (valor !== configuracionOriginal[input.dataset.clave]) cambios[input.dataset.clave] = valor;
    });
    if (!Object.keys(cambios).length) {
        avisar('No hay cambios para guardar.', 'info');
        return;
    }
    const boton = form.querySelector('[type="submit"]');
    nlBotonOcupado(boton, true);
    const data = await nlApi.put('/admin/configuracion', cambios);
    nlBotonOcupado(boton, false);
    if (!data.exito) {
        const mapa = Object.fromEntries(Object.keys(data.errores || {}).map(clave => [clave, `cfg-${clave}`]));
        nlMostrarErrores(data.errores, mapa);
        avisar(data.errores ? 'No se guardó nada: revisá los valores marcados.' : data.mensaje, 'error');
        return;
    }
    configuracionOriginal = Object.fromEntries(data.configuracion.map(c => [c.clave, c.valor]));
    const cantidad = Object.keys(cambios).length;
    avisar(cantidad === 1 ? 'Se guardó 1 cambio.' : `Se guardaron ${cantidad} cambios.`, 'exito');
});

// ═════════════════════════════ AUDITORÍA ═════════════════════════════
const NOMBRE_ACCION = {
    crear: 'Alta', editar: 'Cambio', eliminar: 'Baja',
    iniciar_sesion: 'Inicio de sesión', cerrar_sesion: 'Cierre de sesión', bloqueo_login: 'Bloqueo por intentos',
    solicitar_recuperacion: 'Pidió recuperar la contraseña', restablecer_contrasena: 'Restableció la contraseña',
    generar_calendario: 'Generó el calendario', generar_ronda: 'Generó una ronda',
    cancelar_torneo: 'Canceló un torneo', finalizar_torneo: 'Torneo finalizado',
};
const CLASE_ACCION = { crear: 'chip-exito', editar: '', eliminar: 'chip-peligro', bloqueo_login: 'chip-aviso' };
const NOMBRE_TABLA = {
    roles: 'Roles', usuarios: 'Usuarios', juegos: 'Juegos', modulos_competencia: 'Módulos', torneos: 'Torneos',
    configuracion_torneo: 'Configuración de torneo', equipos: 'Equipos', equipo_miembros: 'Integrantes de equipo',
    equipo_solicitudes: 'Solicitudes de equipo', participantes: 'Participantes', rondas: 'Rondas',
    enfrentamientos: 'Partidos', resultados: 'Resultados', configuracion_sistema: 'Configuración del sistema',
};
const filtrosAuditoria = { buscar: '', accion: '', tabla: '', origen: '', desde: '', hasta: '', pagina: 1 };

function textoValor(valor) {
    if (valor === null || valor === undefined || valor === '') return '—';
    if (valor === true) return 'sí';
    if (valor === false) return 'no';
    const texto = String(valor);
    return texto.length > 40 ? `${texto.slice(0, 40)}…` : texto;
}

// Campos que cambiaron entre antes y después ("contrasena_cambiada" es una
// marca: la base nunca guarda la contraseña en la auditoría).
function camposCambiados(r) {
    return Object.keys(r.valores_nuevos || {}).filter(c => (c === 'contrasena_cambiada'
        ? r.valores_nuevos[c] === true
        : JSON.stringify(r.valores_anteriores?.[c]) !== JSON.stringify(r.valores_nuevos[c])));
}

function resumenCambio(r) {
    if (r.detalle) return escapeHtml(r.detalle);
    if (r.accion === 'editar' && r.valores_anteriores && r.valores_nuevos) {
        const campos = camposCambiados(r);
        const texto = campos.slice(0, 3).map(c => (c === 'contrasena_cambiada'
            ? 'contraseña cambiada'
            : `${escapeHtml(c)}: ${escapeHtml(textoValor(r.valores_anteriores[c]))} → ${escapeHtml(textoValor(r.valores_nuevos[c]))}`)).join('<br>');
        return campos.length > 3 ? `${texto}<br><span class="texto-tenue">y ${campos.length - 3} más</span>` : texto;
    }
    const valores = r.valores_nuevos || r.valores_anteriores || {};
    const nombre = valores.nombre || valores.nombre_usuario || valores.clave
        || (valores.puntaje_local !== undefined ? `${valores.puntaje_local} - ${valores.puntaje_visitante}` : '');
    return escapeHtml(textoValor(nombre) === '—' ? '' : textoValor(nombre));
}

// Detalle completo: cada campo con su valor de antes y de después
function detalleCambio(r) {
    const antes = r.valores_anteriores || {};
    const despues = r.valores_nuevos || {};
    const campos = r.accion === 'editar' ? camposCambiados(r) : Object.keys({ ...antes, ...despues });
    if (!campos.length) return '';
    return `
        <details class="detalle-auditoria">
            <summary>Ver detalle</summary>
            <table class="tabla tabla-detalle">
                <thead><tr><th scope="col">Campo</th>${r.accion !== 'crear' ? '<th scope="col">Antes</th>' : ''}${r.accion !== 'eliminar' ? '<th scope="col">Después</th>' : ''}</tr></thead>
                <tbody>${campos.map(c => `
                    <tr>
                        <td>${escapeHtml(c)}</td>
                        ${r.accion !== 'crear' ? `<td>${escapeHtml(textoValor(antes[c]))}</td>` : ''}
                        ${r.accion !== 'eliminar' ? `<td>${escapeHtml(textoValor(despues[c]))}</td>` : ''}
                    </tr>`).join('')}
                </tbody>
            </table>
        </details>
    `;
}

function quienHizo(r) {
    if (r.nombre_usuario) return `<a href="${nlEnlaceJugador(r.id_usuario)}">${escapeHtml(r.nombre_usuario)}</a>`;
    if (r.id_usuario) return `<span class="texto-tenue">Usuario #${escapeHtml(String(r.id_usuario))} (eliminado)</span>`;
    return '<span class="texto-tenue">Sistema / visitante</span>';
}

// compacta: para el resumen (sin detalle ni IP)
function tablaAuditoria(registros, compacta = false) {
    return `
        <div class="tabla-scroll ${compacta ? '' : 'panel panel--tabla'}">
            <table class="tabla tabla-admin tabla-auditoria">
                <caption class="solo-lectores">Registro de auditoría</caption>
                <thead><tr>
                    <th scope="col">Fecha</th><th scope="col">Quién</th><th scope="col">Acción</th><th scope="col">Sobre</th><th scope="col">Cambio</th>
                </tr></thead>
                <tbody>
                    ${registros.map(r => `
                        <tr>
                            <td>${formatearFechaHora(r.fecha)}</td>
                            <td>${quienHizo(r)}${!compacta && r.ip ? `<span class="celda-sub">IP ${escapeHtml(r.ip)}</span>` : ''}</td>
                            <td><span class="chip ${CLASE_ACCION[r.accion] ?? 'chip-gris'}">${escapeHtml(NOMBRE_ACCION[r.accion] || r.accion)}</span></td>
                            <td>${escapeHtml(NOMBRE_TABLA[r.tabla] || r.tabla || '—')}${r.id_registro ? ` <span class="texto-tenue">#${escapeHtml(String(r.id_registro))}</span>` : ''}</td>
                            <td class="celda-cambio">${resumenCambio(r)}${compacta ? '' : detalleCambio(r)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function cargarAuditoria() {
    const contenedor = campo('tablaAuditoria');
    const numero = ++ultimoPedido.auditoria;
    contenedor.innerHTML = nlCargando('Cargando registros…');
    const data = await nlApi.get(nlConParametros('/admin/auditoria', { ...filtrosAuditoria, por_pagina: 25 }));
    if (numero !== ultimoPedido.auditoria) return; // llegó la respuesta de un filtro anterior
    if (!data.exito) {
        contenedor.innerHTML = nlVacio(data.errores ? Object.values(data.errores)[0] : data.mensaje);
        nlPaginacion('paginacionAuditoria', null);
        return;
    }
    const total = data.paginacion.total;
    campo('cantidadAuditoria').textContent = total === 1 ? '1 registro' : `${total.toLocaleString('es-UY')} registros`;
    contenedor.innerHTML = data.registros.length ? tablaAuditoria(data.registros) : nlVacio('No hay registros con esos filtros.');
    nlPaginacion('paginacionAuditoria', data.paginacion, (pagina) => {
        filtrosAuditoria.pagina = pagina;
        cargarAuditoria();
        nlDesplazar(campo('tituloAuditoria'));
    });
}

function prepararFiltrosAuditoria() {
    const eventos = Object.keys(NOMBRE_ACCION).filter(a => !['crear', 'editar', 'eliminar'].includes(a));
    campo('filtroAccion').innerHTML = `
        <option value="">Todas las acciones</option>
        <optgroup label="Cambios en la base">
            <option value="crear">Altas</option><option value="editar">Cambios</option><option value="eliminar">Bajas</option>
        </optgroup>
        <optgroup label="Eventos de la aplicación">
            ${eventos.map(a => `<option value="${a}">${NOMBRE_ACCION[a]}</option>`).join('')}
        </optgroup>`;
    campo('filtroTabla').innerHTML = '<option value="">Todas las tablas</option>'
        + Object.entries(NOMBRE_TABLA).map(([tabla, nombre]) => `<option value="${tabla}">${nombre}</option>`).join('');

    alEscribir(campo('buscarAuditoria'), () => { filtrosAuditoria.buscar = campo('buscarAuditoria').value.trim(); filtrosAuditoria.pagina = 1; cargarAuditoria(); });
    [['filtroAccion', 'accion'], ['filtroTabla', 'tabla'], ['filtroOrigen', 'origen'], ['filtroDesde', 'desde'], ['filtroHasta', 'hasta']].forEach(([id, filtro]) => {
        campo(id).addEventListener('change', () => {
            filtrosAuditoria[filtro] = campo(id).value;
            filtrosAuditoria.pagina = 1;
            cargarAuditoria();
        });
    });
    campo('filtrosAuditoria').addEventListener('submit', (e) => e.preventDefault());
}

// ═════════════════════════════ ARRANQUE ═════════════════════════════
const CARGAR = {
    resumen: cargarResumen,
    usuarios: cargarUsuarios,
    torneos: cargarTorneos,
    modulos: cargarModulos,
    configuracion: cargarConfiguracion,
    auditoria: cargarAuditoria,
};

async function iniciar() {
    sesion = await nextleagueExigirSesion();
    if (!sesion.logueado) return;
    // Solo cambia lo que se muestra: la seguridad real está en la API, que
    // vuelve a controlar el rol en cada petición a /api/admin/...
    if (sesion.usuario.rol !== 'administrador') {
        campo('estadoCarga').innerHTML = nlVacio('Esta sección es solo para administradores.', { texto: 'Volver al inicio', href: '../home/' });
        return;
    }
    claveUsuarioValida = nlRequisitosContrasena('usuario_clave', 'requisitosUsuario');
    nlBotonVerContrasena('verClaveUsuario', 'usuario_clave');
    prepararFiltrosAuditoria();
    campo('estadoCarga').classList.add('oculto');
    campo('panelAdmin').classList.remove('oculto');
    mostrarSeccion(window.location.hash.slice(1));
    window.addEventListener('hashchange', () => mostrarSeccion(window.location.hash.slice(1)));
}

iniciar();
