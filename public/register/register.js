// =====================================================================
// NEXTLEAGUE - CREAR CUENTA
// Antes, después de registrarse había que volver a escribir el correo y
// la contraseña en el login, la casilla de términos enlazaba a páginas
// que no existían y, si el administrador cerraba el registro, el
// formulario se mostraba igual y fallaba al enviarlo. Ahora:
//   - al crear la cuenta se inicia sesión sola y se vuelve a la página
//     desde la que se vino (por ejemplo, el torneo al que se quería anotar);
//   - los requisitos de la contraseña se marcan mientras se escribe;
//   - con el registro cerrado se avisa en lugar de mostrar el formulario.
// La API vuelve a validar todo (y no deja registrarse como administrador).
// =====================================================================

const formRegistro = document.getElementById('formRegistro');
const claveValida = nlRequisitosContrasena('contrasena', 'requisitosClave');
nlBotonVerContrasena('verContrasena', 'contrasena');

// Si se vino de una página (?volver=), el enlace a "Iniciá sesión" también la conserva
if (nlRutaDeVuelta(nlParametro('volver'))) {
    document.getElementById('enlaceIngresar').href = `../home/login?volver=${encodeURIComponent(nlParametro('volver'))}`;
}

function validar() {
    const errores = {};
    const nombre = document.getElementById('nombre_usuario').value.trim();
    const correo = document.getElementById('correo').value.trim();
    if (!/^[a-zA-Z0-9_]{3,50}$/.test(nombre)) {
        errores.nombre_usuario = nombre ? 'De 3 a 50 caracteres: letras, números o guion bajo (sin espacios).' : 'Elegí un nombre de usuario.';
    }
    if (!nlCorreoValido(correo)) errores.correo = correo ? 'Ese correo no parece válido.' : 'Escribí tu correo.';
    if (!claveValida()) errores.contrasena = 'La contraseña no cumple todos los requisitos.';
    else if (document.getElementById('contrasena').value !== document.getElementById('contrasena_repetida').value) {
        errores.contrasena_repetida = 'Las dos contraseñas no coinciden.';
    }
    if (!formRegistro.querySelector('[name="rol"]:checked')) errores.rol = 'Elegí si querés participar u organizar.';
    if (!document.getElementById('acepto').checked) errores.acepto = 'Para crear la cuenta tenés que aceptarlo.';
    return errores;
}

formRegistro.addEventListener('submit', async (e) => {
    e.preventDefault();
    nlLimpiarErrores(formRegistro);
    nlAlerta('alertaAcceso', '');
    const errores = validar();
    if (Object.keys(errores).length) {
        nlMostrarErrores(errores);
        const primero = document.getElementById(Object.keys(errores)[0]);
        (primero.matches('input') ? primero : primero.querySelector('input')).focus();
        return;
    }

    const boton = formRegistro.querySelector('[type="submit"]');
    nlBotonOcupado(boton, true, 'Creando tu cuenta…');
    const datos = {
        nombre_usuario: document.getElementById('nombre_usuario').value.trim(),
        correo: document.getElementById('correo').value.trim(),
        contrasena: document.getElementById('contrasena').value,
        rol: formRegistro.querySelector('[name="rol"]:checked').value,
    };
    const respuesta = await nlApi.post('/auth/registro', datos);
    if (!respuesta.exito) {
        nlBotonOcupado(boton, false);
        // Por ejemplo "Ese nombre de usuario ya está en uso", al lado del campo
        if (respuesta.errores) nlMostrarErrores(respuesta.errores);
        nlAlerta('alertaAcceso', respuesta.errores ? 'Revisá los campos marcados.' : respuesta.mensaje, 'error');
        return;
    }

    // Cuenta creada: se inicia sesión sola, sin volver a escribir los datos
    const ingreso = await nlApi.post('/auth/login', { correo: datos.correo, contrasena: datos.contrasena });
    window.location.href = ingreso.exito ? nlDestinoDespuesDeIngresar() : '../home/login';
});

async function iniciar() {
    const sesion = await nlSiYaIngresoIrA(nlDestinoDespuesDeIngresar());
    nlCargarNumerosAcceso();
    if (sesion.registro_abierto === false) {
        document.querySelector('.acceso__tarjeta').innerHTML = `
            <div class="acceso__mensaje">
                ${nlIcono('candado')}
                <h1>El registro está cerrado</h1>
                <p class="texto-secundario">Por el momento no se aceptan cuentas nuevas. Si ya tenés una, podés ingresar.</p>
                <a class="btn btn-primario" href="../home/login">Ingresar</a>
            </div>
        `;
    }
}

iniciar();
