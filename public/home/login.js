// =====================================================================
// NEXTLEAGUE - INICIAR SESIÓN
// Antes, después de ingresar siempre se volvía a la portada, aunque se
// viniera de un torneo; los números de la izquierda eran inventados y
// los íconos eran emojis. Ahora:
//   - vuelve a la página desde la que se vino (?volver=), solo si es del sitio;
//   - si la cuenta tiene que cambiar la contraseña, va directo al perfil;
//   - los errores se marcan en cada campo y los bloqueos por intentos
//     fallidos se explican en un aviso.
// =====================================================================

const formIngresar = document.getElementById('formIngresar');
const campoCorreo = document.getElementById('correo');
const campoContrasena = document.getElementById('contrasena');

nlBotonVerContrasena('verContrasena', 'contrasena');

if (nlParametro('restablecida')) {
    nlAlerta('alertaAcceso', 'Listo: tu contraseña cambió. Ya podés ingresar con la nueva.', 'exito');
} else if (nlParametro('volver')) {
    nlAlerta('alertaAcceso', 'Ingresá para continuar.', 'info');
}

formIngresar.addEventListener('submit', async (e) => {
    e.preventDefault();
    nlLimpiarErrores(formIngresar);
    nlAlerta('alertaAcceso', ''); // el aviso del intento anterior no queda mientras se espera la respuesta
    const correo = campoCorreo.value.trim();
    const errores = {};
    if (!correo) errores.correo = 'Escribí tu correo.';
    else if (!nlCorreoValido(correo)) errores.correo = 'Ese correo no parece válido.';
    if (!campoContrasena.value) errores.contrasena = 'Escribí tu contraseña.';
    if (Object.keys(errores).length) {
        nlMostrarErrores(errores);
        document.getElementById(Object.keys(errores)[0]).focus();
        return;
    }

    const boton = formIngresar.querySelector('[type="submit"]');
    nlBotonOcupado(boton, true, 'Ingresando…');
    const respuesta = await nlApi.post('/auth/login', {
        correo,
        contrasena: campoContrasena.value,
        recordar: document.getElementById('recordar').checked,
    });

    if (!respuesta.exito) {
        nlBotonOcupado(boton, false);
        if (respuesta.errores) nlMostrarErrores(respuesta.errores);
        // 401: datos incorrectos · 403: cuenta desactivada · 429: bloqueo por intentos
        nlAlerta('alertaAcceso', respuesta.mensaje, respuesta.estado === 429 ? 'aviso' : 'error');
        campoContrasena.value = '';
        campoContrasena.focus();
        return;
    }

    // Si la cuenta tiene que cambiar la contraseña (por ejemplo la del admin inicial), va al perfil.
    window.location.href = respuesta.usuario.debe_cambiar_contrasena
        ? '../perfil/perfil?cambiar_contrasena=1'
        : nlDestinoDespuesDeIngresar();
});

async function iniciar() {
    const sesion = await nlSiYaIngresoIrA(nlDestinoDespuesDeIngresar());
    if (sesion.registro_abierto === false) document.getElementById('pieRegistro').classList.add('oculto');
    nlCargarNumerosAcceso();
    campoCorreo.focus();
}

iniciar();
