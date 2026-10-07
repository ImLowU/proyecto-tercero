// =====================================================================
// NEXTLEAGUE - MI PERFIL
// Antes el perfil era un solo formulario que mezclaba los datos con el
// cambio de contraseña, y la lista de torneos mostraba los creados o los
// jugados según el rol (un organizador no veía los torneos en los que
// juega). Ahora:
//   - encabezado con foto (se cambia con la cámara), rol y números;
//   - "Mis torneos": los que organizo (con acceso a Gestionar) y en los
//     que juego (un organizador también puede jugar torneos ajenos);
//   - "Mis partidos": próximos y últimos resultados (con Gané/Perdí);
//   - "Cuenta": datos por un lado y contraseña por otro, con los
//     requisitos marcados mientras se escribe.
// Si la cuenta tiene que cambiar la contraseña (por ejemplo el admin
// inicial), solo se muestra "Cuenta" hasta que la cambie: la API no deja
// usar el resto del sitio mientras tanto.
// =====================================================================

let usuario = null;
let partidosCargados = false;
let pestanas = null;
let claveNuevaValida = () => false;

const campo = (id) => document.getElementById(id);
const esOrganizador = () => ['organizador', 'administrador'].includes(usuario?.rol);
const debeCambiarClave = () => Boolean(Number(usuario?.debe_cambiar_contrasena));

function avisar(mensaje, tipo) {
    nlAlerta('alertaPerfil', mensaje, tipo);
    nlDesplazar(campo('alertaPerfil'), { block: 'nearest' });
}

// ── Encabezado y números ──
function pintarCabecera() {
    const u = usuario;
    document.title = `${u.nombre_usuario} · Mi perfil · NextLeague`;
    campo('fotoPerfil').src = nlAvatar(u.foto_perfil);
    campo('botonQuitarFoto').classList.toggle('oculto', !u.foto_perfil);
    campo('nombrePerfil').textContent = u.nombre_usuario;
    campo('chipsPerfil').innerHTML = `<span class="chip">${escapeHtml(u.nombre_rol || NL_NOMBRE_ROL[u.rol] || u.rol)}</span>`;
    campo('metaPerfil').innerHTML = `
        <span>${nlIcono('correo')}${escapeHtml(u.correo)}</span>
        <span>${nlIcono('calendario')}En NextLeague desde ${escapeHtml(formatearMesAnio(u.fecha_registro))}</span>
    `;
    campo('bioPerfil').textContent = u.biografia || '';
    campo('enlacePublico').href = nlEnlaceJugador(u.id_usuario);

    // El nombre y la foto también se ven en el menú de arriba
    const botonMenu = document.getElementById('botonUsuario');
    if (botonMenu) {
        botonMenu.querySelector('img').src = nlAvatar(u.foto_perfil);
        botonMenu.querySelector('span').textContent = u.nombre_usuario;
    }
}

function pintarNumeros() {
    const e = usuario.estadisticas || {};
    const partidos = e.partidos || { pj: 0, pg: 0 };
    const numeros = [
        [e.torneos_jugados ?? 0, 'Torneos jugados'],
        [partidos.pj, 'Partidos jugados'],
        [partidos.pg, 'Victorias'],
    ];
    if (esOrganizador()) numeros.push([e.torneos_organizados ?? 0, 'Torneos organizados']);
    campo('numerosPerfil').innerHTML = numeros.map(([valor, texto]) =>
        `<li class="numero-perfil"><strong>${Number(valor).toLocaleString('es-UY')}</strong><span>${texto}</span></li>`).join('');
}

// ── Mis torneos ──
async function cargarTorneos() {
    const juego = campo('listaJuego');
    juego.innerHTML = nlCargando();
    const pedidos = [nlApi.get('/perfil/torneos?tipo=participo')];
    if (esOrganizador()) {
        campo('bloqueOrganizo').classList.remove('oculto');
        campo('listaOrganizo').innerHTML = `<li>${nlCargando()}</li>`;
        pedidos.push(nlApi.get('/perfil/torneos?tipo=creados'));
    }
    const [participo, creados] = await Promise.all(pedidos);

    if (!participo.exito) juego.innerHTML = nlVacio(participo.mensaje);
    else if (participo.torneos.length === 0) juego.innerHTML = nlVacio('Todavía no te anotaste en ningún torneo.', { texto: 'Ver torneos con inscripción abierta', href: '../torneos/explorar?estado=pendiente' });
    else juego.innerHTML = participo.torneos.map(nlTarjetaTorneo).join('');

    if (creados) pintarOrganizo(creados);
}

function pintarOrganizo(data) {
    const lista = campo('listaOrganizo');
    if (!data.exito) {
        lista.innerHTML = `<li>${nlVacio(data.mensaje)}</li>`;
        return;
    }
    if (data.torneos.length === 0) {
        lista.innerHTML = `<li>${nlVacio('Todavía no creaste ningún torneo.')}</li>`;
        return;
    }
    lista.innerHTML = data.torneos.map(t => `
        <li class="fila-torneo">
            <div class="fila-torneo__portada">${nlPortadaTorneo(t, 56, 56)}</div>
            <div class="fila-torneo__datos">
                <strong>${escapeHtml(t.nombre)}</strong>
                <div class="fila-torneo__meta">
                    ${nlChipTorneo(t)}
                    ${Number(t.publicado) ? '' : '<span class="chip chip-aviso">Oculto</span>'}
                    <span>${escapeHtml(t.juego)} · ${escapeHtml(nombreModulo(t.modulo))}</span>
                    <span>${t.inscriptos} / ${t.cupo} ${t.modalidad === 'equipos' ? 'equipos' : 'jugadores'}</span>
                </div>
            </div>
            <div class="fila-torneo__acciones">
                ${t.estado === 'cancelado' ? '' : `<a class="btn btn-secundario btn-chico" href="../torneos/gestionar?id=${t.id_torneo}">${nlIcono('ajustes')}Gestionar</a>`}
                <a class="btn btn-texto btn-chico" href="../torneos/torneo?id=${t.id_torneo}">Ver</a>
            </div>
        </li>
    `).join('');
}

// ── Mis partidos (se cargan la primera vez que se abre la pestaña) ──
async function cargarPartidos() {
    if (partidosCargados) return;
    partidosCargados = true;
    const proximos = campo('misProximos');
    const resultados = campo('misResultados');
    proximos.innerHTML = nlCargando();
    resultados.innerHTML = nlCargando();

    const data = await nlApi.get('/perfil/partidos');
    if (!data.exito) {
        proximos.innerHTML = nlVacio(data.mensaje);
        resultados.innerHTML = '';
        return;
    }
    proximos.innerHTML = data.partidos.length
        ? data.partidos.map(p => nlCita(p)).join('')
        : nlVacio('No tenés partidos programados.');
    resultados.innerHTML = data.recientes.length
        ? data.recientes.map(p => nlPartido(p, { torneo: true, enlace: true, etiqueta: nlChipResultado(p, p.id_propio, 'vos') })).join('')
        : nlVacio('Todavía no jugaste partidos.');
}

// ── Foto de perfil ──
campo('inputFoto').addEventListener('change', async (e) => {
    const archivo = e.target.files[0];
    if (!archivo) return;
    const datos = new FormData();
    datos.append('foto', archivo);
    campo('fotoPerfil').classList.add('cargando-foto');
    const data = await nlApi.post('/perfil/foto', datos);
    campo('fotoPerfil').classList.remove('cargando-foto');
    e.target.value = '';
    if (!data.exito) {
        avisar(data.errores?.foto || data.mensaje, 'error');
        return;
    }
    usuario.foto_perfil = data.foto_perfil;
    pintarCabecera();
    avisar('Foto actualizada.', 'exito');
});

campo('botonQuitarFoto').addEventListener('click', async (e) => {
    const boton = e.currentTarget;
    const ok = await nlConfirmar('Tu perfil vuelve a mostrar el ícono por defecto.', { titulo: '¿Quitar tu foto?', aceptar: 'Quitar foto', peligro: true });
    if (!ok) return;
    nlBotonOcupado(boton, true, 'Quitando…');
    const data = await nlApi.delete('/perfil/foto');
    nlBotonOcupado(boton, false);
    if (data.exito) {
        usuario.foto_perfil = null;
        pintarCabecera();
    }
    avisar(data.mensaje, data.exito ? 'exito' : 'error');
});

// ── Cuenta: datos ──
function completarDatos() {
    campo('nombre_usuario').value = usuario.nombre_usuario;
    campo('correo').value = usuario.correo;
    campo('biografia').value = usuario.biografia || '';
    campo('contadorBio').textContent = campo('biografia').value.length;
    campo('campoClaveCorreo').classList.add('oculto');
    campo('clave_para_correo').value = '';
}

// La contraseña actual se pide solo si cambia el correo
campo('correo').addEventListener('input', () => {
    const cambia = campo('correo').value.trim().toLowerCase() !== String(usuario.correo).toLowerCase();
    campo('campoClaveCorreo').classList.toggle('oculto', !cambia);
});
campo('biografia').addEventListener('input', () => { campo('contadorBio').textContent = campo('biografia').value.length; });

campo('formDatos').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    nlLimpiarErrores(form);
    const datos = {
        nombre_usuario: campo('nombre_usuario').value.trim(),
        correo: campo('correo').value.trim(),
        biografia: campo('biografia').value.trim(),
    };
    if (!campo('campoClaveCorreo').classList.contains('oculto')) datos.contrasena_actual = campo('clave_para_correo').value;

    const boton = form.querySelector('[type="submit"]');
    nlBotonOcupado(boton, true);
    const data = await nlApi.put('/perfil', datos);
    nlBotonOcupado(boton, false);
    if (!data.exito) {
        nlMostrarErrores(data.errores, { contrasena_actual: 'clave_para_correo' });
        avisar(data.errores ? 'Revisá los campos marcados.' : data.mensaje, 'error');
        return;
    }
    usuario = { ...usuario, nombre_usuario: datos.nombre_usuario, correo: datos.correo.toLowerCase(), biografia: datos.biografia || null };
    completarDatos();
    pintarCabecera();
    avisar('Datos guardados.', 'exito');
});

// ── Cuenta: contraseña ──
campo('formClave').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    nlLimpiarErrores(form);
    const errores = {};
    if (!campo('contrasena_actual').value) errores.contrasena_actual = 'Escribí tu contraseña actual.';
    if (!claveNuevaValida()) errores.contrasena_nueva = 'La contraseña nueva no cumple todos los requisitos.';
    else if (campo('contrasena_nueva').value !== campo('contrasena_repetida').value) errores.contrasena_repetida = 'Las dos contraseñas no coinciden.';
    if (Object.keys(errores).length) {
        nlMostrarErrores(errores);
        return;
    }

    const boton = form.querySelector('[type="submit"]');
    nlBotonOcupado(boton, true, 'Cambiando…');
    const data = await nlApi.put('/perfil', {
        contrasena_actual: campo('contrasena_actual').value,
        contrasena_nueva: campo('contrasena_nueva').value,
    });
    nlBotonOcupado(boton, false);
    if (!data.exito) {
        nlMostrarErrores(data.errores);
        avisar(data.errores ? 'Revisá los campos marcados.' : data.mensaje, 'error');
        return;
    }
    form.reset();
    campo('contrasena_nueva').dispatchEvent(new Event('input')); // vuelve a pintar los requisitos
    const estabaObligado = debeCambiarClave();
    usuario.debe_cambiar_contrasena = false;
    if (estabaObligado) {
        mostrarTodo();
        avisar('Contraseña actualizada. Ya podés usar NextLeague con normalidad.', 'exito');
    } else {
        avisar('Contraseña actualizada.', 'exito');
    }
});

// ── Pestañas y modo "tenés que cambiar la contraseña" ──
function abrirPestanaDeLaDireccion() {
    const pedida = window.location.hash.slice(1);
    if (['mis-torneos', 'mis-partidos', 'cuenta'].includes(pedida)) pestanas.activar(pedida);
}

function modoCambioObligatorio() {
    ['pestanaTorneos', 'pestanaPartidos', 'numerosPerfil'].forEach(id => campo(id).classList.add('oculto'));
    pestanas.activar('cuenta');
    nlAlerta('alertaPerfil', 'Por seguridad tenés que elegir una contraseña nueva antes de seguir usando NextLeague.', 'aviso');
    campo('contrasena_actual').focus();
}

function mostrarTodo() {
    ['pestanaTorneos', 'pestanaPartidos', 'numerosPerfil'].forEach(id => campo(id).classList.remove('oculto'));
    cargarTorneos();
}

async function iniciar() {
    const sesion = await nextleagueExigirSesion();
    if (!sesion.logueado) return;

    const data = await nlApi.get('/perfil');
    if (!data.exito) {
        campo('estadoCarga').innerHTML = nlVacio(data.mensaje);
        return;
    }
    usuario = data.usuario;
    pintarCabecera();
    pintarNumeros();
    completarDatos();
    claveNuevaValida = nlRequisitosContrasena('contrasena_nueva', 'requisitosClave');
    nlBotonVerContrasena('verClaveActual', 'contrasena_actual');
    nlBotonVerContrasena('verClaveNueva', 'contrasena_nueva');
    nlBotonVerContrasena('verClaveCorreo', 'clave_para_correo');

    campo('estadoCarga').classList.add('oculto');
    campo('contenidoPerfil').classList.remove('oculto');

    pestanas = nlPestanas('pestanasPerfil', (clave) => {
        history.replaceState(null, '', `perfil#${clave}`);
        if (clave === 'mis-partidos') cargarPartidos();
    });
    if (debeCambiarClave()) {
        modoCambioObligatorio();
        return;
    }
    cargarTorneos();
    abrirPestanaDeLaDireccion();
    window.addEventListener('hashchange', abrirPestanaDeLaDireccion);
}

iniciar();
