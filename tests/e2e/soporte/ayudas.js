// =====================================================================
// AYUDAS PARA LAS PRUEBAS DE LOS FLUJOS (09 a 13)
// Fechas relativas a hoy y atajos por la API para preparar lo que no es
// el foco de cada prueba (lo que sí es el foco se hace por la página).
// =====================================================================

// Fecha AAAA-MM-DD a "dias" de hoy (hora local, como los campos de fecha)
export function fecha(dias = 0) {
    const d = new Date(Date.now() + dias * 86400000);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// "AAAA-MM-DDTHH:MM" a "dias" de hoy, para un campo datetime-local
export const fechaHora = (dias, hora = '20:00') => `${fecha(dias)}T${hora}`;

// Crea una cuenta por la API (el formulario de registro se prueba en el flujo 1)
export function registrarPorApi(nombre, rol = 'participante') {
    return cy.api('POST', '/auth/registro', {
        nombre_usuario: nombre, correo: `${nombre}@e2e.uy`, contrasena: Cypress.expose('clave'), rol,
    }).then((r) => {
        expect(r.status, `crear la cuenta ${nombre}`).to.eq(201);
        return r.body.id_usuario;
    });
}

// Crea un torneo por la API con el usuario que tiene la sesión iniciada
export function crearTorneoPorApi(datos) {
    return cy.api('POST', '/torneos', {
        id_juego: 28, modulo: 'liga', modalidad: 'individual', cupo: 4,
        fecha_inicio: fecha(7), fecha_fin: fecha(30), ...datos,
    }).then((r) => {
        expect(r.status, `crear el torneo ${datos.nombre}: ${r.body.mensaje}`).to.eq(201);
        return r.body.id_torneo;
    });
}

// Inscribe a varios usuarios (cada uno se inscribe solo, con su sesión)
export function inscribirPorApi(idTorneo, nombres) {
    nombres.forEach((nombre) => {
        cy.clearCookies();
        cy.ingresarComo(nombre);
        cy.api('POST', `/torneos/${idTorneo}/inscripcion`).its('status').should('eq', 201);
    });
    cy.clearCookies();
}

// Partidos jugables (con dos rivales y que no son pase libre) de una ronda
export function partidosDeRonda(idTorneo, numero) {
    return cy.api('GET', `/torneos/${idTorneo}/calendario`).then((r) => {
        const ronda = r.body.rondas.find((x) => Number(x.numero) === numero);
        return (ronda ? ronda.partidos : []).filter((p) => p.id_local && p.id_visitante && !Number(p.es_pase_libre));
    });
}

// Carga el resultado de todos los partidos de una ronda (gana el local)
export function jugarRondaPorApi(idTorneo, numero, local = 2, visitante = 1) {
    partidosDeRonda(idTorneo, numero).then((partidos) => {
        partidos.forEach((p) => {
            cy.api('POST', `/enfrentamientos/${p.id_enfrentamiento}/resultado`, { puntaje_local: local, puntaje_visitante: visitante })
                .its('status').should('eq', 200);
        });
    });
}
