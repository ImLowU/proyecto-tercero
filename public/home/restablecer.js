// =====================================================================
// NEXTLEAGUE - ELEGIR UNA CONTRASEÑA NUEVA (desde el enlace del correo)
// Antes había un solo campo, sin repetir la contraseña ni ver sus
// requisitos, y el script estaba escrito dentro del HTML.
// =====================================================================

const token = nlParametro('token');
const formRestablecer = document.getElementById('formRestablecer');
const claveValida = nlRequisitosContrasena('contrasena', 'requisitosClave');
nlBotonVerContrasena('verContrasena', 'contrasena');

if (!token) {
    nlAlerta('alertaAcceso', 'El enlace está incompleto. Pedí uno nuevo desde "¿Olvidaste tu contraseña?".', 'error');
    formRestablecer.classList.add('oculto');
}

formRestablecer.addEventListener('submit', async (e) => {
    e.preventDefault();
    nlLimpiarErrores(formRestablecer);
    const contrasena = document.getElementById('contrasena').value;
    if (!claveValida()) {
        nlMostrarErrores({ contrasena: 'La contraseña no cumple todos los requisitos.' });
        return;
    }
    if (contrasena !== document.getElementById('contrasena_repetida').value) {
        nlMostrarErrores({ contrasena_repetida: 'Las dos contraseñas no coinciden.' });
        return;
    }

    const boton = formRestablecer.querySelector('[type="submit"]');
    nlBotonOcupado(boton, true, 'Cambiando…');
    const data = await nlApi.post('/auth/restablecer', { token, contrasena });
    nlBotonOcupado(boton, false);
    if (!data.exito) {
        if (data.errores?.contrasena) nlMostrarErrores({ contrasena: data.errores.contrasena });
        else nlAlerta('alertaAcceso', data.errores ? Object.values(data.errores)[0] : data.mensaje, 'error');
        return;
    }
    window.location.href = 'login?restablecida=1';
});
