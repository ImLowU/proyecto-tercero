// =====================================================================
// 3. INSCRIPCIONES: individual (con baja y cupo), por equipos (crear
//    equipo, pedir unirse, aceptar) y desde "Gestionar" (organizador).
// =====================================================================

describe('Inscripciones', () => {
    before(() => cy.prepararBase());

    it('Un jugador se inscribe a un torneo individual y se da de baja', () => {
        cy.ingresarComo('jugador_e2e5');
        cy.idTorneo('abierto').then((id) => cy.visit(`torneos/torneo?id=${id}`));
        cy.get('#cupoTexto').should('have.text', '0 / 4');
        cy.get('#botonInscribirme').click();
        cy.get('#alertaTorneo').should('contain', 'Ya estás inscripto');
        cy.get('#inscripcionContenido').should('contain', 'Ya estás inscripto');
        cy.get('#cupoTexto').should('have.text', '1 / 4');
        cy.contains('#pestanasTorneo button', 'Participantes').click();
        cy.get('#listaParticipantes').should('contain', 'jugador_e2e5');

        cy.contains('#pestanasTorneo button', 'Resumen').click();
        cy.get('#botonBaja').click();
        cy.confirmar('Darme de baja');
        cy.get('#alertaTorneo').should('contain', 'Te diste de baja');
        cy.get('#botonInscribirme').should('be.visible');
        cy.get('#cupoTexto').should('have.text', '0 / 4');
    });

    it('Respeta el cupo: con el torneo lleno ya no se puede inscribir nadie más (B8)', () => {
        cy.idTorneo('abierto').then((id) => {
            ['jugador_e2e1', 'jugador_e2e2', 'jugador_e2e3', 'jugador_e2e4'].forEach((nombre) => {
                cy.clearCookies();
                cy.ingresarComo(nombre);
                cy.api('POST', `/torneos/${id}/inscripcion`).its('status').should('be.lessThan', 300);
            });
            cy.clearCookies();
            cy.ingresarComo('jugador_e2e5');
            cy.visit(`torneos/torneo?id=${id}`);
            cy.get('#cupoTexto').should('have.text', '4 / 4');
            cy.get('#inscripcionContenido').should('contain', 'El cupo está completo');
            cy.get('#botonInscribirme').should('not.exist');
            // El chip ya no dice "Inscripciones abiertas", ni acá ni en Explorar
            cy.get('#torneoChips').should('contain', 'Cupo completo').and('not.contain', 'Inscripciones abiertas');
            cy.visit('torneos/explorar?buscar=P%C3%B3ker');
            cy.contains('.tarjeta-torneo', 'Abierto E2E de Póker').should('contain', 'Cupo completo');
            // Tampoco por la API
            cy.api('POST', `/torneos/${id}/inscripcion`).then((r) => {
                expect(r.status).to.be.within(400, 499);
                expect(r.body.mensaje).to.contain('cupo');
            });
        });
    });

    it('Por equipos: un jugador crea su equipo y queda como capitán', () => {
        cy.ingresarComo('jugador_e2e6');
        cy.idTorneo('equipos').then((id) => cy.visit(`torneos/torneo?id=${id}`));
        cy.get('#botonCrearEquipo').click();
        cy.get('#modalEquipo').should('be.visible');
        cy.get('#nombreEquipo').type('Las Águilas E2E');
        cy.get('#formEquipo').contains('button', 'Crear equipo').click();
        cy.get('#modalEquipo').should('not.be.visible');
        cy.get('#alertaTorneo').should('contain', 'Equipo creado');
        cy.contains('#pestanasTorneo button', 'Participantes').click();
        cy.contains('.participante--equipo', 'Las Águilas E2E').should('contain', 'Sos el capitán');
    });

    it('Por equipos: otro jugador pide unirse y el capitán lo acepta', () => {
        cy.idTorneo('equipos').then((id) => {
            cy.ingresarComo('jugador_e2e7');
            cy.visit(`torneos/torneo?id=${id}#participantes`);
            cy.contains('.participante--equipo', 'Los Toros E2E').contains('button', 'Pedir unirme').click();
            cy.get('#alertaTorneo').should('contain', 'Solicitud enviada');

            // Otra persona en la misma página: hay que recargarla (visitar la misma
            // dirección con el mismo #participantes no la vuelve a cargar)
            cy.clearCookies();
            cy.ingresarComo('capitan_e2e');
            cy.reload();
            cy.get('#botonUsuario').should('contain', 'capitan_e2e');
            cy.contains('.participante--equipo', 'Los Toros E2E').within(() => {
                cy.contains('Sos el capitán');
                cy.contains('button', 'Solicitudes').click();
                cy.get('.solicitud').should('contain', 'jugador_e2e7');
                cy.contains('button', 'Aceptar').click();
            });
            cy.get('#alertaTorneo').should('contain', 'Solicitud aceptada');
            cy.contains('.participante--equipo', 'Los Toros E2E').should('contain', '2 jugadores');

            cy.clearCookies();
            cy.ingresarComo('jugador_e2e7');
            cy.visit(`torneos/torneo?id=${id}`);
            cy.get('#botonUsuario').should('contain', 'jugador_e2e7');
            cy.get('#inscripcionContenido').should('contain', 'Ya estás inscripto');
        });
        // El perfil del equipo muestra a los dos integrantes
        cy.wrap(null).then(() => cy.visit(`perfil/equipo?id=${Cypress.expose('datos').equipo}`));
        cy.get('#nombreEquipo').should('have.text', 'Los Toros E2E');
        cy.get('#miembrosEquipo').should('contain', 'capitan_e2e').and('contain', 'jugador_e2e7');
    });

    it('El organizador inscribe y quita participantes desde "Gestionar"', () => {
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('liga_lista').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        // liga_lista ya tiene 4 inscriptos de 4: se ve lleno
        cy.get('#cupoChip').should('have.text', '4 / 4');
        cy.get('#formInscribir').should('not.be.visible');

        cy.idTorneo('oculto').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        cy.get('#inscribirUsuario').type('no_existe_e2e');
        cy.get('#formInscribir').submit();
        cy.get('#inscribirUsuarioError').should('not.be.empty');

        cy.get('#inscribirUsuario').clear().type('jugador_e2e8');
        cy.get('#formInscribir').submit();
        cy.get('#alertaGestion .alerta-exito').should('be.visible');
        cy.get('#listaInscriptos').should('contain', 'jugador_e2e8');
        cy.get('#cupoChip').should('have.text', '1 / 4');

        cy.get('#listaInscriptos [aria-label="Quitar a jugador_e2e8"]').click();
        cy.confirmar('Quitar');
        cy.get('#listaInscriptos').should('not.contain', 'jugador_e2e8');
        cy.get('#cupoChip').should('have.text', '0 / 4');
    });

    it('El organizador inscribe un equipo con su capitán', () => {
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('equipos').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        cy.get('#campoUsuario').should('not.be.visible');
        cy.get('#inscribirEquipo').type('Equipo Organizado E2E');
        cy.get('#inscribirCapitan').type('jugador_e2e8');
        cy.get('#formInscribir').submit();
        cy.get('#alertaGestion .alerta-exito').should('be.visible');
        cy.get('#listaInscriptos').should('contain', 'Equipo Organizado E2E');
    });

    it('El organizador no puede jugar su propio torneo', () => {
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('oculto').then((id) => {
            cy.visit(`torneos/torneo?id=${id}`);
            cy.get('#inscripcionContenido').should('contain', 'Sos el organizador');
            cy.api('POST', `/torneos/${id}/inscripcion`).its('status').should('be.within', 400, 499);
        });
    });
});
