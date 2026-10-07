// =====================================================================
// 10. FLUJO DE LOS EQUIPOS: una jugadora nueva arma su equipo, otros
//     piden unirse (se aceptan o se rechazan), alguien sale, se llega al
//     máximo de integrantes y, cuando el torneo empieza, el equipo queda
//     cerrado y todos sus integrantes ven sus partidos y resultados.
//     Usa "Torneo E2E de Fútbol 5" (por equipos, máximo 5 integrantes),
//     que ya tiene el equipo "Los Toros E2E" de capitan_e2e.
// =====================================================================

import { fechaHora, partidosDeRonda, registrarPorApi } from '../soporte/ayudas';

const CAPITANA = 'capitana_flujo';
const EQUIPO = 'Las Panteras';

// Id del equipo "Las Panteras" (se crea en la prueba 2.1)
const idPanteras = () => cy.idTorneo('equipos').then((id) => cy.api('GET', `/torneos/${id}/participantes`))
    .its('body.participantes').then((lista) => lista.find((p) => p.nombre === EQUIPO).id_equipo);

// Un jugador pide unirse y la capitana lo acepta (por la API)
function sumar(nombre) {
    idPanteras().then((idEquipo) => {
        cy.clearCookies();
        cy.ingresarComo(nombre);
        cy.api('POST', `/equipos/${idEquipo}/solicitudes`).then((r) => {
            expect(r.status, `${nombre} pide unirse`).to.eq(201);
            cy.clearCookies();
            cy.ingresarComo(CAPITANA);
            cy.api('POST', `/solicitudes/${r.body.id_solicitud}/respuesta`, { accion: 'aceptar' }).its('status').should('eq', 200);
        });
    });
    cy.clearCookies();
}

describe('Flujo 2: armar un equipo y jugar por equipos', () => {
    before(() => cy.prepararBase());

    it('2.1 Una jugadora nueva crea su equipo y queda como capitana', () => {
        cy.paso(`Crea su cuenta (${CAPITANA}) e ingresa`);
        registrarPorApi(CAPITANA);
        cy.ingresarComo(CAPITANA);

        cy.paso('En un torneo por equipos no hay "Inscribirme": se entra creando un equipo o uniéndose a uno');
        cy.idTorneo('equipos').then((id) => cy.visit(`torneos/torneo?id=${id}`));
        cy.get('#botonInscribirme').should('not.exist');
        cy.get('#botonCrearEquipo').should('be.visible');

        cy.paso(`Crea el equipo "${EQUIPO}"`);
        cy.get('#botonCrearEquipo').click();
        cy.get('#nombreEquipo').type(EQUIPO);
        cy.get('#formEquipo').contains('button', 'Crear equipo').click();
        cy.get('#alertaTorneo').should('contain', 'Equipo creado');

        cy.paso('En "Participantes" figura su equipo y ella como capitana');
        cy.contains('#pestanasTorneo button', 'Participantes').click();
        cy.contains('.participante--equipo', EQUIPO).should('contain', 'Sos el capitán');

        cy.paso('No puede crear un segundo equipo ni pedir unirse a otro del mismo torneo');
        cy.idTorneo('equipos').then((id) => cy.api('POST', `/torneos/${id}/equipos`, { nombre: 'Otro equipo' }))
            .its('status').should('eq', 409);
        cy.wrap(null).then(() => cy.api('POST', `/equipos/${Cypress.expose('datos').equipo}/solicitudes`))
            .its('body.mensaje').should('contain', 'Ya formás parte');
    });

    it('2.2 Dos jugadores piden unirse: la capitana acepta a uno y rechaza al otro', () => {
        cy.paso('jugador_e2e1 pide unirse desde la página del torneo');
        cy.ingresarComo('jugador_e2e1');
        cy.idTorneo('equipos').then((id) => cy.visit(`torneos/torneo?id=${id}#participantes`));
        cy.contains('.participante--equipo', EQUIPO).contains('button', 'Pedir unirme').click();
        cy.get('#alertaTorneo').should('contain', 'Solicitud enviada');

        cy.paso('No puede mandar la misma solicitud dos veces');
        idPanteras().then((idEquipo) => cy.api('POST', `/equipos/${idEquipo}/solicitudes`))
            .its('body.mensaje').should('contain', 'pendiente');

        cy.paso('jugador_e2e2 también pide unirse');
        cy.clearCookies();
        cy.ingresarComo('jugador_e2e2');
        idPanteras().then((idEquipo) => cy.api('POST', `/equipos/${idEquipo}/solicitudes`)).its('status').should('eq', 201);

        cy.paso('La capitana abre "Solicitudes" y ve las dos');
        cy.clearCookies();
        cy.ingresarComo(CAPITANA);
        cy.reload();
        cy.get('#botonUsuario').should('contain', CAPITANA);
        cy.contains('.participante--equipo', EQUIPO).contains('button', 'Solicitudes').click();
        cy.get('.solicitud').should('have.length', 2);

        cy.paso('Acepta a jugador_e2e1');
        cy.contains('.solicitud', 'jugador_e2e1').contains('button', 'Aceptar').click();
        cy.get('#alertaTorneo').should('contain', 'Solicitud aceptada');
        cy.contains('.participante--equipo', EQUIPO).should('contain', '2 jugadores');

        cy.paso('Rechaza a jugador_e2e2');
        cy.contains('.participante--equipo', EQUIPO).contains('button', 'Solicitudes').click();
        cy.get('.solicitud').should('have.length', 1);
        cy.contains('.solicitud', 'jugador_e2e2').contains('button', 'Rechazar').click();
        cy.get('#alertaTorneo').should('contain', 'Solicitud rechazada');
        cy.contains('.participante--equipo', EQUIPO).should('contain', '2 jugadores');

        cy.paso('jugador_e2e1 ya figura inscripto; jugador_e2e2 no');
        cy.clearCookies();
        cy.ingresarComo('jugador_e2e1');
        cy.idTorneo('equipos').then((id) => cy.visit(`torneos/torneo?id=${id}`));
        cy.get('#inscripcionContenido').should('contain', 'Ya estás inscripto');
        cy.clearCookies();
        cy.ingresarComo('jugador_e2e2');
        cy.idTorneo('equipos').then((id) => cy.api('GET', `/torneos/${id}`)).its('body.torneo.permisos.estoy_inscripto').should('eq', false);
    });

    it('2.3 Un integrante puede salir del equipo; la capitana no, mientras tenga compañeros', () => {
        cy.paso('La capitana intenta darse de baja: no puede, porque el equipo tiene otro integrante');
        cy.ingresarComo(CAPITANA);
        cy.idTorneo('equipos').then((id) => cy.api('DELETE', `/torneos/${id}/inscripcion`)).then((r) => {
            expect(r.status).to.eq(409);
            expect(r.body.mensaje).to.contain('Sos el capitán');
        });

        cy.paso('jugador_e2e1 sale del equipo desde la página del torneo');
        cy.clearCookies();
        cy.ingresarComo('jugador_e2e1');
        cy.idTorneo('equipos').then((id) => cy.visit(`torneos/torneo?id=${id}`));
        cy.get('#botonBaja').click();
        cy.confirmar('Darme de baja');
        cy.get('#alertaTorneo').should('contain', 'Saliste del equipo');
        cy.contains('#pestanasTorneo button', 'Participantes').click();
        cy.contains('.participante--equipo', EQUIPO).should('contain', '1 jugador');

        cy.paso('Lo piensa mejor: vuelve a pedir unirse y la capitana lo acepta');
        sumar('jugador_e2e1');
        idPanteras().then((idEquipo) => cy.api('GET', `/equipos/${idEquipo}`))
            .its('body.equipo.miembros').should('have.length', 2);
    });

    it('2.4 El equipo no puede pasar el máximo de 5 integrantes', () => {
        cy.paso('Se suman jugador_e2e3, jugador_e2e4 y jugador_e2e5: el equipo llega a 5');
        ['jugador_e2e3', 'jugador_e2e4', 'jugador_e2e5'].forEach(sumar);

        cy.paso('jugador_e2e6 pide unirse y se rechaza porque el equipo está completo');
        cy.ingresarComo('jugador_e2e6');
        idPanteras().then((idEquipo) => cy.api('POST', `/equipos/${idEquipo}/solicitudes`)).then((r) => {
            expect(r.status).to.eq(409);
            expect(r.body.mensaje).to.contain('máximo de 5');
        });

        cy.paso('El perfil público del equipo muestra a los 5 integrantes');
        cy.clearCookies();
        idPanteras().then((idEquipo) => cy.visit(`perfil/equipo?id=${idEquipo}`));
        cy.get('#nombreEquipo').should('have.text', EQUIPO);
        cy.get('#miembrosEquipo li').should('have.length', 5);
    });

    it('2.5 Cuando el torneo empieza, el equipo queda cerrado y todos sus integrantes ven sus partidos', () => {
        cy.paso('Antes de que empiece, jugador_e2e7 pide unirse a "Los Toros E2E" (queda pendiente)');
        cy.ingresarComo('jugador_e2e7');
        cy.wrap(null).then(() => cy.api('POST', `/equipos/${Cypress.expose('datos').equipo}/solicitudes`))
            .then((r) => {
                expect(r.status).to.eq(201);
                cy.wrap(r.body.id_solicitud).as('pendiente');
            });

        cy.paso('El organizador genera el calendario (2 equipos: un partido), lo publica y lo programa');
        cy.clearCookies();
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('equipos').then((id) => {
            cy.api('POST', `/torneos/${id}/calendario`).its('status').should('eq', 201);
            cy.api('POST', `/torneos/${id}/rondas/publicar`).its('status').should('eq', 200);
            partidosDeRonda(id, 1).then(([partido]) => {
                cy.wrap(partido.id_enfrentamiento).as('partido');
                cy.api('PUT', `/enfrentamientos/${partido.id_enfrentamiento}`, { fecha_hora: fechaHora(8, '21:00'), lugar: 'Cancha 5 E2E' })
                    .its('status').should('eq', 200);
            });
        });

        cy.paso('El capitán de "Los Toros E2E" ya no puede aceptar la solicitud: el torneo empezó');
        cy.clearCookies();
        cy.ingresarComo('capitan_e2e');
        cy.get('@pendiente').then((idSolicitud) => cy.api('POST', `/solicitudes/${idSolicitud}/respuesta`, { accion: 'aceptar' }))
            .then((r) => {
                expect(r.status).to.eq(409);
                expect(r.body.mensaje).to.contain('ya empezó');
            });

        cy.paso('Nadie más puede pedir unirse ni crear equipos');
        cy.clearCookies();
        cy.ingresarComo('jugador_e2e8');
        idPanteras().then((idEquipo) => cy.api('POST', `/equipos/${idEquipo}/solicitudes`)).its('status').should('eq', 409);
        cy.idTorneo('equipos').then((id) => cy.api('POST', `/torneos/${id}/equipos`, { nombre: 'Tarde' })).its('status').should('eq', 409);

        cy.paso('Una integrante que no es capitana (jugador_e2e3) ve el partido de su equipo en su perfil');
        cy.clearCookies();
        cy.ingresarComo('jugador_e2e3');
        cy.visit('perfil/perfil');
        cy.get('#pestanaPartidos').click();
        cy.get('#misProximos').should('contain', 'Cancha 5 E2E').and('contain', EQUIPO);

        cy.idTorneo('equipos').then((id) => cy.simularTiempo('Pasan 9 días: se juega el partido', { accion: 'pasan_dias', id, dias: 9 }));
        cy.paso('El organizador carga el resultado y otro integrante (jugador_e2e4) lo ve en sus resultados');
        cy.clearCookies();
        cy.ingresarComo('orga_e2e');
        cy.get('@partido').then((idPartido) => cy.api('POST', `/enfrentamientos/${idPartido}/resultado`, { puntaje_local: 4, puntaje_visitante: 2 }))
            .its('body.mensaje').should('contain', 'el torneo terminó');
        cy.clearCookies();
        cy.ingresarComo('jugador_e2e4');
        cy.visit('perfil/perfil');
        cy.get('#pestanaPartidos').click();
        cy.get('#misResultados').should('contain', 'Torneo E2E de Fútbol 5');
        cy.get('#misProximos').should('not.contain', 'Cancha 5 E2E');
    });
});
