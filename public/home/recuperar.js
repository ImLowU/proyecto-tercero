// =====================================================================
// NEXTLEAGUE - RECUPERAR LA CONTRASEÑA
// La API responde siempre lo mismo, exista o no el correo (así no se
// puede averiguar quién tiene cuenta). El enlace viaja por correo; solo
// en modo desarrollo (XAMPP no manda correos) se muestra en pantalla.
// Antes este script estaba escrito dentro del HTML.
// =====================================================================

const formRecuperar = document.getElementById('formRecuperar');

formRecuperar.addEventListener('submit', async (e) => {
    e.preventDefault();
    nlLimpiarErrores(formRecuperar);
    nlAlerta('avisoDemo', '');
    const correo = document.getElementById('correo').value.trim();
    if (!nlCorreoValido(correo)) {
        nlMostrarErrores({ correo: correo ? 'Ese correo no parece válido.' : 'Escribí tu correo.' });
        document.getElementById('correo').focus();
        return;
    }

    const boton = formRecuperar.querySelector('[type="submit"]');
    nlBotonOcupado(boton, true, 'Enviando…');
    const data = await nlApi.post('/auth/recuperar', { correo });
    nlBotonOcupado(boton, false);
    if (!data.exito) {
        if (data.errores) nlMostrarErrores(data.errores);
        nlAlerta('alertaAcceso', data.errores ? '' : data.mensaje, 'error');
        return;
    }
    nlAlerta('alertaAcceso', `${data.mensaje} Revisá también la carpeta de spam.`, 'exito');

    // Solo en desarrollo: el enlace se arma con elementos y textContent,
    // nunca se inserta como HTML lo que viene del servidor.
    if (data.link_demo) {
        const aviso = document.getElementById('avisoDemo');
        aviso.innerHTML = `<div class="alerta alerta-aviso" role="status">${nlIcono('info')}<span>Modo desarrollo (sin servidor de correo): </span></div>`;
        const enlace = document.createElement('a');
        enlace.href = data.link_demo;
        enlace.textContent = 'abrir el enlace para restablecer la contraseña';
        aviso.querySelector('span').appendChild(enlace);
        aviso.classList.remove('oculto');
    }
});

nlSiYaIngresoIrA('../perfil/perfil#cuenta');
