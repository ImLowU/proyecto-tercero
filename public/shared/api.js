// =====================================================================
// NEXTLEAGUE - CLIENTE DE LA API (compartido por todas las páginas)
// Antes cada página repetía su propio fetch() con la URL de un archivo
// PHP distinto. Ahora todas las llamadas pasan por nlApi, que:
//   - arma la URL (todas las páginas están un nivel abajo de public/),
//   - manda el token CSRF en las acciones que modifican datos,
//   - devuelve siempre un objeto { exito, mensaje, estado, ... }, incluso
//     si se cae la conexión, así cada página maneja los errores igual.
// =====================================================================

const NEXTLEAGUE_API = '../api';
const NEXTLEAGUE_SUBIDAS = '../uploads';
let nextleagueCsrf = null;

async function nlActualizarSesion() {
    try {
        const respuesta = await fetch(NEXTLEAGUE_API + '/auth/sesion', { credentials: 'same-origin' });
        const datos = await respuesta.json();
        nextleagueCsrf = datos.csrf;
        return datos;
    } catch (error) {
        return { exito: false, logueado: false };
    }
}

async function nlPedir(metodo, ruta, datos = null, reintentar = true) {
    const opciones = { method: metodo, headers: {}, credentials: 'same-origin' };
    if (metodo !== 'GET') {
        if (!nextleagueCsrf) await nlActualizarSesion();
        opciones.headers['X-CSRF-Token'] = nextleagueCsrf;
        if (datos instanceof FormData) {
            opciones.body = datos;
        } else if (datos) {
            opciones.headers['Content-Type'] = 'application/json';
            opciones.body = JSON.stringify(datos);
        }
    }

    let respuesta;
    try {
        respuesta = await fetch(NEXTLEAGUE_API + ruta, opciones);
    } catch (error) {
        return { exito: false, estado: 0, mensaje: 'No se pudo conectar con el servidor. Revisá tu conexión y probá de nuevo.' };
    }

    let cuerpo;
    try {
        cuerpo = await respuesta.json();
    } catch (error) {
        cuerpo = { exito: false, mensaje: 'El servidor respondió algo inesperado. Probá de nuevo en un momento.' };
    }
    cuerpo.estado = respuesta.status;

    // Si la página quedó abierta mucho tiempo, el token puede haber cambiado:
    // se pide uno nuevo y se reintenta una sola vez.
    if (respuesta.status === 403 && reintentar && metodo !== 'GET' && /desactualizada/.test(cuerpo.mensaje || '')) {
        await nlActualizarSesion();
        return nlPedir(metodo, ruta, datos, false);
    }
    return cuerpo;
}

const nlApi = {
    get: (ruta) => nlPedir('GET', ruta),
    post: (ruta, datos) => nlPedir('POST', ruta, datos),
    put: (ruta, datos) => nlPedir('PUT', ruta, datos),
    delete: (ruta, datos) => nlPedir('DELETE', ruta, datos),
};

// Arma una URL con parámetros, salteando los vacíos: nlConParametros('/torneos', {pagina: 2})
function nlConParametros(ruta, parametros) {
    const query = new URLSearchParams();
    Object.entries(parametros).forEach(([clave, valor]) => {
        if (valor !== null && valor !== undefined && valor !== '') query.append(clave, valor);
    });
    const texto = query.toString();
    return texto ? `${ruta}?${texto}` : ruta;
}

// URL de un archivo subido (fotos, logos, reglas) o null si no hay.
function nlRutaSubida(carpeta, archivo) {
    return archivo ? `${NEXTLEAGUE_SUBIDAS}/${carpeta}/${encodeURIComponent(archivo)}` : null;
}

// Muestra al lado de cada campo los errores de una respuesta 422.
// "mapa" traduce el nombre del campo en la API al id del input en la página.
function nlMostrarErrores(errores, mapa = {}) {
    Object.entries(errores || {}).forEach(([campo, mensaje]) => {
        const id = mapa[campo] || campo;
        const input = document.getElementById(id);
        const span = document.getElementById(id + 'Error');
        if (input) input.classList.add('input-error');
        if (span) span.textContent = mensaje;
    });
}

function nlLimpiarErrores(contenedor) {
    contenedor.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
    contenedor.querySelectorAll('.field-error, .error-campo').forEach(el => { el.textContent = ''; });
}
