// =====================================================================
// COMANDOS COMUNES DE LAS PRUEBAS DE PUNTA A PUNTA
// Además, en TODAS las páginas que se visitan se revisa que:
//   - la CSP no haya bloqueado nada (un estilo o script en línea);
//   - ningún script haya escrito un error en la consola.
// Si pasa, la prueba falla aunque la pantalla "se vea bien".
// =====================================================================

const problemas = [];

Cypress.on('window:before:load', (win) => {
    win.document.addEventListener('securitypolicyviolation', (e) => {
        problemas.push(`CSP bloqueó ${e.violatedDirective} (${e.blockedURI || 'en línea'}) en ${win.location.pathname}`);
    });
    const original = win.console.error;
    win.console.error = (...args) => {
        problemas.push(`Error en la consola de ${win.location.pathname}: ${args.map(String).join(' ')}`);
        original.apply(win.console, args);
    };
});

// ── Registro de pasos ──
// Las pruebas de los flujos anotan lo que hacen con cy.paso('...'), lo que
// conviene revisar con cy.observacion('...') y el tiempo que simulan con
// cy.simularTiempo(). Al terminar cada prueba todo eso se guarda, con el
// resultado, en tests/e2e/registro/pasos.json (de ahí sale el registro).
let pasos = [];

function anotar(tipo, texto) {
    pasos.push({ tipo, texto, hora: new Date().toISOString() });
    const numero = pasos.filter((p) => p.tipo === 'paso').length;
    Cypress.log({ name: { paso: `Paso ${numero}`, observacion: 'Observación', tiempo: 'Tiempo' }[tipo], message: texto });
}

Cypress.Commands.add('paso', (texto) => anotar('paso', texto));
Cypress.Commands.add('observacion', (texto) => anotar('observacion', texto));

beforeEach(() => {
    problemas.length = 0;
    pasos = [];
});

afterEach(function () {
    const prueba = this.currentTest;
    cy.task('guardarPasos', {
        archivo: Cypress.spec.name,
        titulo: prueba.titlePath().join(' › '),
        estado: prueba.state,
        error: prueba.err ? String(prueba.err.message).split('\n')[0] : null,
        pasos,
    }, { log: false });
    cy.then(() => {
        expect(problemas, 'violaciones de CSP y errores de consola').to.deep.equal([]);
    });
});

// ── Paso del tiempo ──
// Simula que pasó el tiempo atrasando fechas guardadas en la base de
// pruebas (ver tests/e2e/simular_tiempo.php). Ej:
//   cy.simularTiempo('Pasan 31 minutos', { accion: 'token_recuperacion', correo, minutos: 31 })
Cypress.Commands.add('simularTiempo', (descripcion, pedido) => {
    anotar('tiempo', descripcion);
    cy.task('simularTiempo', pedido, { log: false })
        .its('afectados', { log: false }).should('be.greaterThan', 0);
});

// ── Datos de la base de pruebas ──
// Vuelve a crear la base y guarda los ids en Cypress.expose('datos').
Cypress.Commands.add('prepararBase', () => {
    cy.task('prepararBase', null, { log: false }).then((datos) => {
        Cypress.expose('datos', datos);
        cy.log(`Base de pruebas lista: ${Object.keys(datos.torneos).length} torneos`);
    });
});

// Id de un torneo de los datos conocidos (ver tests/e2e/preparar_base.php)
const torneo = (clave) => Cypress.expose('datos').torneos[clave];
const usuario = (nombre) => Cypress.expose('datos').usuarios[nombre];
Cypress.Commands.add('idTorneo', (clave) => cy.wrap(torneo(clave), { log: false }));
Cypress.Commands.add('idUsuario', (nombre) => cy.wrap(usuario(nombre), { log: false }));

// ── API (para preparar el escenario de una prueba sin pasar por la página) ──
// Pide el token CSRF como lo hace api.js y lo manda en la petición.
Cypress.Commands.add('api', (metodo, ruta, cuerpo = null, opciones = {}) => {
    return cy.request({ url: 'api/auth/sesion', log: false }).then(({ body }) => cy.request({
        method: metodo,
        url: `api${ruta}`,
        headers: { 'X-CSRF-Token': body.csrf },
        body: cuerpo,
        failOnStatusCode: false,
        ...opciones,
    }));
});

// Inicia sesión por la API (rápido); el login por la página se prueba aparte.
Cypress.Commands.add('ingresarComo', (nombre, clave = Cypress.expose('clave')) => {
    cy.api('POST', '/auth/login', { correo: `${nombre}@e2e.uy`, contrasena: clave }, { log: false })
        .its('status', { log: false }).should('eq', 200);
    cy.log(`Sesión iniciada como **${nombre}**`);
});

// ── Ventanas de confirmación (nlConfirmar) ──
Cypress.Commands.add('confirmar', (textoBoton) => {
    cy.get('dialog.modal[open]').should('be.visible').contains('button', textoBoton).click();
    cy.get('dialog.modal[open]').should('not.exist');
});

// ── Diseño ──
// Falla si la página se puede deslizar de costado y dice qué elementos la
// estiran: los que terminan justo donde termina la página (los que se salen
// dentro de una tabla o de una fila que se desliza no cuentan, porque esas
// se recortan solas).
Cypress.Commands.add('sinDeslizamientoHorizontal', () => {
    cy.document({ log: false }).then((doc) => {
        const raiz = doc.documentElement;
        const ancho = raiz.clientWidth;
        const total = raiz.scrollWidth;
        const nombre = (el) => `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}${typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\s+/).join('.') : ''}`;
        const culpables = total <= ancho ? [] : [...doc.body.querySelectorAll('*')]
            .filter((el) => Math.abs(el.getBoundingClientRect().right - total) < 2)
            .slice(0, 5)
            .map((el) => `${nombre(el)} dentro de ${nombre(el.parentElement)}`);
        expect(total, `ancho de la página (la estiran: ${culpables.join('; ') || 'nada'})`).to.be.at.most(ancho);
    });
});
