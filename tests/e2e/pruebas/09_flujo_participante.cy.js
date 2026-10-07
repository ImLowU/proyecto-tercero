// =====================================================================
// 9. FLUJO DEL PARTICIPANTE: desde que una persona se registra hasta que
//    juega su torneo (inscripción, baja, cupo, calendario, resultados y
//    posiciones). En cada paso se mira lo que ven ella, el organizador y
//    el público. Las pruebas van en orden: cada una sigue a la anterior.
//    Cada paso queda anotado en el registro (cy.paso).
// =====================================================================

import { fechaHora, inscribirPorApi, partidosDeRonda, registrarPorApi } from '../soporte/ayudas';

const JUGADORA = 'lucia_flujo';
const CORREO = `${JUGADORA}@e2e.uy`;

// El partido de la ronda 1 en el que juega la jugadora
const suPartido = (id) => partidosDeRonda(id, 1).then((partidos) =>
    partidos.find((p) => [p.nombre_local, p.nombre_visitante].includes(JUGADORA)));

describe('Flujo 1: del registro a jugar un torneo', () => {
    before(() => cy.prepararBase());

    it('1.1 Una visitante crea su cuenta y queda con la sesión iniciada', () => {
        cy.paso('Entra a la portada sin cuenta y elige "Registrarse"');
        cy.visit('home/');
        cy.contains('#authArea a', 'Registrarse').should('be.visible').click();

        cy.paso('Completa el formulario de registro como participante y acepta las condiciones');
        cy.location('pathname').should('eq', '/public/register/register');
        cy.get('#nombre_usuario').type(JUGADORA);
        cy.get('#correo').type(CORREO);
        cy.get('#contrasena').type(Cypress.expose('clave'));
        cy.get('#contrasena_repetida').type(Cypress.expose('clave'));
        cy.contains('.opcion-rol', 'Participar').click();
        cy.get('#acepto').check();
        cy.contains('button', 'Crear mi cuenta').click();

        cy.paso('Vuelve a la portada con la sesión iniciada: su menú dice "Participante" y no ofrece "Crear torneo"');
        cy.location('pathname').should('eq', '/public/home/');
        cy.get('#botonUsuario').should('contain', JUGADORA).click();
        cy.get('#listaUsuario').should('contain', 'Participante').and('not.contain', 'Crear torneo');

        cy.paso('En su perfil todavía no tiene torneos');
        cy.visit('perfil/perfil');
        cy.get('#listaJuego').should('contain', 'Todavía no te anotaste');
    });

    it('1.2 Vuelve a entrar con su contraseña y se inscribe en un torneo abierto', () => {
        cy.paso('Ingresa con su correo y su contraseña');
        cy.visit('home/login');
        cy.get('#correo').type(CORREO);
        cy.get('#contrasena').type(Cypress.expose('clave'));
        cy.contains('button', 'Iniciar sesión').click();
        cy.get('#botonUsuario').should('contain', JUGADORA);

        cy.paso('En "Explorar" busca torneos con inscripción abierta y abre "Abierto E2E de Póker"');
        cy.visit('torneos/explorar?estado=pendiente');
        cy.contains('.tarjeta-torneo', 'Abierto E2E de Póker').should('contain', 'Inscripciones abiertas').click();

        cy.paso('La página del torneo muestra el cupo (0 de 4) y el botón para inscribirse');
        cy.location('pathname').should('eq', '/public/torneos/torneo');
        cy.get('#cupoTexto').should('have.text', '0 / 4');

        cy.paso('Se inscribe: el cupo pasa a 1 de 4 y aparece en "Participantes"');
        cy.get('#botonInscribirme').click();
        cy.get('#alertaTorneo').should('contain', 'Ya estás inscripto');
        cy.get('#cupoTexto').should('have.text', '1 / 4');
        cy.contains('#pestanasTorneo button', 'Participantes').click();
        cy.get('#listaParticipantes').should('contain', JUGADORA);

        cy.paso('El torneo aparece en su perfil');
        cy.visit('perfil/perfil');
        cy.get('#listaJuego').should('contain', 'Abierto E2E de Póker');

        cy.paso('El organizador la ve en "Gestionar" (1 de 4)');
        cy.clearCookies();
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('abierto').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        cy.get('#listaInscriptos').should('contain', JUGADORA);
        cy.get('#cupoChip').should('have.text', '1 / 4');
    });

    it('1.3 Mientras el torneo no empieza, puede darse de baja y volver a anotarse', () => {
        cy.ingresarComo(JUGADORA);
        cy.idTorneo('abierto').then((id) => cy.visit(`torneos/torneo?id=${id}`));

        cy.paso('Se da de baja desde la página del torneo (con confirmación): el cupo vuelve a 0 de 4');
        cy.get('#botonBaja').click();
        cy.confirmar('Darme de baja');
        cy.get('#alertaTorneo').should('contain', 'Te diste de baja');
        cy.get('#cupoTexto').should('have.text', '0 / 4');

        cy.paso('Se vuelve a inscribir');
        cy.get('#botonInscribirme').click();
        cy.get('#cupoTexto').should('have.text', '1 / 4');

        cy.paso('No puede inscribirse dos veces: la API lo rechaza');
        cy.idTorneo('abierto').then((id) => cy.api('POST', `/torneos/${id}/inscripcion`)).then((r) => {
            expect(r.status).to.eq(409);
            expect(r.body.mensaje).to.contain('Ya estás inscripto');
        });
    });

    it('1.4 Con el cupo completo, la siguiente persona ya no puede anotarse', () => {
        cy.paso('Se inscriben otros tres jugadores: el torneo queda con 4 de 4');
        cy.idTorneo('abierto').then((id) => inscribirPorApi(id, ['jugador_e2e1', 'jugador_e2e2', 'jugador_e2e3']));

        cy.paso('Otra persona crea su cuenta y entra al torneo: ve "Cupo completo" y no tiene botón para inscribirse');
        registrarPorApi('pedro_flujo');
        cy.ingresarComo('pedro_flujo');
        cy.idTorneo('abierto').then((id) => cy.visit(`torneos/torneo?id=${id}`));
        cy.get('#cupoTexto').should('have.text', '4 / 4');
        cy.get('#torneoChips').should('contain', 'Cupo completo');
        cy.get('#botonInscribirme').should('not.exist');

        cy.paso('Tampoco puede por la API');
        cy.idTorneo('abierto').then((id) => cy.api('POST', `/torneos/${id}/inscripcion`)).then((r) => {
            expect(r.status).to.eq(409);
            expect(r.body.mensaje).to.contain('cupo');
        });
    });

    it('1.5 El organizador arma el calendario: ella ve su próximo partido y ya no puede darse de baja', () => {
        cy.paso('El organizador genera el calendario, lo publica y programa el partido de ella para el día de inicio (en 7 días, en "Club Social E2E")');
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('abierto').then((id) => {
            cy.api('POST', `/torneos/${id}/calendario`).its('status').should('eq', 201);
            cy.api('POST', `/torneos/${id}/rondas/publicar`).its('status').should('eq', 200);
            suPartido(id).then((partido) => {
                cy.api('PUT', `/enfrentamientos/${partido.id_enfrentamiento}`, { fecha_hora: fechaHora(7, '19:00'), lugar: 'Club Social E2E' })
                    .its('status').should('eq', 200);
            });
        });

        cy.paso('Ella entra al torneo: dice "En curso", "Participás de este torneo" y ya no está el botón para darse de baja');
        cy.clearCookies();
        cy.ingresarComo(JUGADORA);
        cy.idTorneo('abierto').then((id) => cy.visit(`torneos/torneo?id=${id}`));
        cy.get('#torneoChips').should('contain', 'En curso');
        cy.get('#inscripcionContenido').should('contain', 'Participás de este torneo');
        cy.get('#botonBaja').should('not.exist');

        cy.paso('En su perfil, pestaña "Partidos", aparece su próximo partido con el lugar');
        cy.visit('perfil/perfil');
        cy.get('#pestanaPartidos').click();
        cy.get('#misProximos').should('contain', 'Club Social E2E').and('contain', 'Abierto E2E de Póker');

        cy.paso('Si intenta darse de baja por la API, se rechaza porque el torneo ya empezó');
        cy.idTorneo('abierto').then((id) => cy.api('DELETE', `/torneos/${id}/inscripcion`)).then((r) => {
            expect(r.status).to.eq(409);
            expect(r.body.mensaje).to.contain('ya empezó');
        });
    });

    it('1.6 Con su resultado cargado, ve cómo le fue, su posición y su perfil público', () => {
        cy.paso('Antes del partido, su resultado no se puede cargar: todavía no se jugó');
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('abierto').then((id) => suPartido(id).then((partido) =>
            cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`, { puntaje_local: 1, puntaje_visitante: 0 })))
            .its('body.mensaje').should('contain', 'cambiá primero la fecha');

        cy.idTorneo('abierto').then((id) => cy.simularTiempo('Pasan 8 días: llega el día del partido y se juega',
            { accion: 'pasan_dias', id, dias: 8 }));
        cy.paso('El organizador carga el resultado del partido de ella: gana 3 a 1');
        cy.idTorneo('abierto').then((id) => suPartido(id).then((partido) => {
            const esLocal = partido.nombre_local === JUGADORA;
            cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`,
                { puntaje_local: esLocal ? 3 : 1, puntaje_visitante: esLocal ? 1 : 3 }).its('status').should('eq', 200);
        }));

        cy.paso('En su perfil, el partido pasó a "Resultados" y dejó de figurar entre los próximos');
        cy.clearCookies();
        cy.ingresarComo(JUGADORA);
        cy.visit('perfil/perfil');
        cy.get('#pestanaPartidos').click();
        cy.get('#misResultados').should('contain', 'Abierto E2E de Póker');
        cy.get('#misProximos').should('not.contain', 'Club Social E2E');

        cy.paso('En la tabla de posiciones tiene 3 puntos');
        cy.idTorneo('abierto').then((id) => cy.visit(`torneos/torneo?id=${id}#posiciones`));
        cy.contains('#tablaPosiciones tbody tr', JUGADORA).find('td.destacada').should('have.text', '3');

        cy.paso('Su perfil público muestra sus números pero no su correo');
        cy.api('GET', '/perfil').its('body.usuario.id_usuario').then((idUsuario) => {
            cy.clearCookies();
            cy.api('GET', `/usuarios/${idUsuario}`).its('body.usuario').then((perfil) => {
                expect(perfil.nombre_usuario).to.eq(JUGADORA);
                expect(perfil, 'el perfil público no tiene el correo').not.to.have.property('correo');
            });
            cy.visit(`perfil/jugador?id=${idUsuario}`);
            cy.get('body').should('contain', JUGADORA).and('not.contain', CORREO);
        });

        cy.paso('Como participante no puede cargar ni cambiar resultados (la API responde 403)');
        cy.ingresarComo(JUGADORA);
        cy.idTorneo('abierto').then((id) => suPartido(id).then((partido) => {
            cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`, { puntaje_local: 9, puntaje_visitante: 0 })
                .its('status').should('eq', 403);
        }));
    });
});
