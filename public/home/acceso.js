// =====================================================================
// NEXTLEAGUE - FUNCIONES COMPARTIDAS POR LAS PÁGINAS DE ACCESO
// (login, registro, recuperar y restablecer la contraseña)
// =====================================================================

// Adónde ir después de ingresar: la página desde la que se vino
// (login?volver=../torneos/torneo?id=3) o la portada.
// nlRutaDeVuelta solo acepta páginas del propio sitio: un enlace armado
// por otro no puede mandar a la persona a un sitio externo.
function nlDestinoDespuesDeIngresar() {
    return nlRutaDeVuelta(nlParametro('volver')) || '../home/';
}

// Si ya hay una sesión abierta, estas páginas no tienen sentido.
async function nlSiYaIngresoIrA(destino) {
    const sesion = await nextleagueObtenerSesion();
    if (sesion.logueado) window.location.replace(destino);
    return sesion;
}

// Números reales para la bienvenida (los que existan en la página), con el
// rótulo en singular cuando hay uno ("1 equipo registrado")
async function nlCargarNumerosAcceso() {
    const data = await nlApi.get('/estadisticas');
    if (!data.exito) return;
    const e = data.estadisticas;
    const valores = { numeroTorneos: e.torneos_activos, numeroEquipos: e.equipos, numeroPartidos: e.partidos_jugados, numeroUsuarios: e.usuarios };
    Object.entries(valores).forEach(([id, valor]) => nlPintarNumero(id, valor));
}

// Validación rápida del correo antes de llamar a la API
function nlCorreoValido(correo) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo);
}
