// =====================================================================
// 2. TORNEOS: crear uno de cada formato, validaciones, archivos (logo y
//    reglas), editar, quitar el logo, visibilidad y permisos.
// =====================================================================

// Fecha AAAA-MM-DD a "dias" de hoy
const fecha = (dias) => {
    const d = new Date(Date.now() + dias * 86400000);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

// Completa lo mínimo del formulario de crear torneo
function completarFormulario({ nombre, juego = 'Ajedrez', formato = 'Liga' }) {
    cy.get('#id_juego option').should('have.length.greaterThan', 10);
    cy.get('#nombre').type(nombre);
    cy.get('#id_juego').select(juego);
    cy.contains('.opcion--formato', formato).click();
    cy.get('#fecha_fin').type(fecha(30));
}

describe('Torneos', () => {
    before(() => cy.prepararBase());

    it('El organizador crea un torneo de cada formato', () => {
        cy.ingresarComo('orga_e2e');
        [
            ['Liga', 'Liga E2E Creada', 'Ajedrez'],
            ['Eliminación directa', 'Copa E2E Creada', 'Damas'],
            ['Sistema suizo', 'Suizo E2E Creado', 'Ajedrez'],
        ].forEach(([formato, nombre, juego]) => {
            cy.visit('createtournament/createtournament');
            completarFormulario({ nombre, juego, formato });
            // La vista previa muestra la tarjeta como va a quedar
            cy.get('#vistaPrevia .tarjeta-torneo__titulo').should('have.text', nombre);
            cy.get('#botonGuardar').click();

            cy.location('pathname').should('eq', '/public/torneos/gestionar');
            cy.get('#alertaGestion').should('contain', '¡Torneo creado!');
            cy.get('#nombreTorneo').should('have.text', nombre);
            cy.get('#chipsTorneo').should('contain', formato);
        });
        // Aparecen en el listado público
        cy.visit('torneos/explorar?buscar=E2E%20Cread');
        cy.get('#listaTorneos .tarjeta-torneo').should('have.length', 3);
    });

    it('Muestra los errores junto a cada campo (B10)', () => {
        cy.ingresarComo('orga_e2e');
        cy.visit('createtournament/createtournament');
        cy.get('#id_juego option').should('have.length.greaterThan', 10);
        cy.get('#botonGuardar').click();
        cy.get('#alertaFormulario').should('contain', 'Revisá');
        cy.get('#nombreError').should('not.be.empty');
        cy.get('#id_juegoError').should('not.be.empty');
        cy.get('#fecha_finError').should('not.be.empty');
        cy.focused().should('have.id', 'nombre');

        // Fecha de fin anterior a la de inicio
        cy.get('#nombre').type('Torneo con fechas al revés');
        cy.get('#id_juego').select('Ajedrez');
        cy.get('#fecha_inicio').clear().type(fecha(10));
        cy.get('#fecha_fin').type(fecha(5));
        cy.get('#botonGuardar').click();
        cy.get('#fecha_finError').should('contain', 'anterior');
        cy.location('pathname').should('eq', '/public/createtournament/createtournament');
    });

    it('Guarda la moneda elegida: un premio en dólares se muestra en US$ (B4)', () => {
        cy.ingresarComo('orga_e2e');
        cy.visit('createtournament/createtournament');
        completarFormulario({ nombre: 'Torneo E2E en Dólares' });
        cy.get('#premio').clear().type('1500');
        cy.get('#moneda').select('USD');
        cy.get('#vistaPrevia').should('contain', 'US$');
        cy.get('#botonGuardar').click();
        cy.get('#alertaGestion').should('contain', '¡Torneo creado!');
        cy.get('#enlaceVolver').click();
        cy.get('#torneoMeta').should('contain', 'US$ 1.500');
    });

    it('Logo y reglas: se suben, se ven en la página, y el logo se puede quitar al editar', () => {
        cy.ingresarComo('orga_e2e');
        cy.visit('createtournament/createtournament');
        completarFormulario({ nombre: 'Torneo E2E con Logo' });
        cy.get('#logo').selectFile('tests/e2e/archivos/logo.png', { force: true });
        cy.get('#logoElegido').should('contain', 'logo.png').find('img').should('have.attr', 'src').and('match', /^blob:/);
        cy.get('#reglas').selectFile('tests/e2e/archivos/reglas.pdf', { force: true });
        cy.get('#reglasElegido').should('contain', 'reglas.pdf');
        cy.get('#botonGuardar').click();
        cy.get('#alertaGestion').should('contain', '¡Torneo creado!');

        cy.get('#enlaceVolver').click();
        cy.get('#torneoPortada img').should('have.attr', 'src').and('contain', 'uploads/torneos/logos/');
        cy.get('#torneoPortada img').should(($img) => expect($img[0].naturalWidth, 'el logo carga').to.be.greaterThan(0));
        cy.contains('#torneoAcciones a', 'Reglas (PDF)').should('have.attr', 'href').and('contain', 'uploads/torneos/reglas/');

        // Editar: quitar el logo
        cy.contains('#torneoAcciones a', 'Gestionar torneo').click();
        cy.get('#enlaceEditar').click();
        cy.get('#tituloFormulario').should('have.text', 'Editar torneo');
        cy.get('#logoElegido').should('contain', 'Logo actual');
        cy.get('#logoElegido [data-accion="quitar"]').click();
        cy.get('#logoElegido').should('contain', 'Se va a quitar al guardar');
        cy.get('#botonGuardar').should('have.text', 'Guardar cambios').click();
        cy.get('#alertaGestion').should('contain', 'Cambios guardados');
        cy.get('#enlaceVolver').click();
        cy.get('#torneoPortada .portada-generica').should('be.visible');
    });

    it('Rechaza un archivo que no es una imagen aunque se llame .png', () => {
        cy.ingresarComo('orga_e2e');
        cy.visit('createtournament/createtournament');
        completarFormulario({ nombre: 'Torneo E2E con archivo falso' });
        cy.get('#logo').selectFile('tests/e2e/archivos/no_es_imagen.png', { force: true });
        cy.get('#botonGuardar').click();
        cy.get('#logoError').should('not.be.empty');
        cy.location('pathname').should('eq', '/public/createtournament/createtournament');
    });

    it('Con el calendario armado, lo que cambiaría los partidos queda bloqueado', () => {
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('liga_en_curso').then((id) => cy.visit(`createtournament/createtournament?id=${id}`));
        cy.get('#avisoBloqueo').should('contain', 'ya tiene calendario');
        cy.get('#id_juego').should('be.disabled');
        cy.get('#cupo').should('be.disabled');
        cy.get('[name="modulo"]').should('be.disabled');
        // El nombre sí se puede cambiar
        cy.get('#nombre').should('not.be.disabled').clear().type('Liga E2E de Ajedrez (renombrada)');
        cy.get('#botonGuardar').click();
        cy.get('#nombreTorneo').should('have.text', 'Liga E2E de Ajedrez (renombrada)');
    });

    it('Un participante no puede crear torneos', () => {
        cy.ingresarComo('jugador_e2e1');
        cy.visit('createtournament/createtournament');
        cy.get('#alertaFormulario').should('contain', 'participante');
        cy.get('.formulario-torneo').should('not.be.visible');
        cy.api('POST', '/torneos', { nombre: 'No debería crearse' }).its('status').should('eq', 403);
    });

    it('Un organizador no puede gestionar el torneo de otro', () => {
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('ajeno').then((id) => {
            cy.visit(`torneos/gestionar?id=${id}`);
            cy.get('#estadoCarga').should('contain', 'Solo el organizador del torneo');
            cy.visit(`torneos/torneo?id=${id}`);
            cy.get('#torneoNombre').should('have.text', 'Torneo E2E Ajeno');
            cy.contains('#torneoAcciones a', 'Gestionar').should('not.exist');
            cy.api('POST', `/torneos/${id}/calendario`).its('status').should('eq', 403);
            cy.api('DELETE', `/torneos/${id}`).its('status').should('eq', 403);
        });
    });

    it('Un torneo oculto no lo ve el público; su organizador sí, con un aviso', () => {
        cy.idTorneo('oculto').then((id) => {
            cy.visit(`torneos/torneo?id=${id}`);
            cy.get('#estadoCarga .vacio').should('be.visible');
            cy.get('#contenidoTorneo').should('not.be.visible');
            cy.visit('torneos/explorar?buscar=Oculto');
            cy.get('#listaTorneos .tarjeta-torneo').should('have.length', 0);

            cy.ingresarComo('orga_e2e');
            cy.visit(`torneos/torneo?id=${id}`);
            cy.get('#avisosTorneo').should('contain', 'Este torneo está oculto');

            // Publicarlo desde "Gestionar" lo hace visible
            cy.visit(`torneos/gestionar?id=${id}`);
            cy.get('#interruptorPublicado').should('not.be.checked').check({ force: true });
            cy.get('#alertaGestion').should('contain', 'ahora es público');
            cy.clearCookies();
            cy.visit(`torneos/torneo?id=${id}`);
            cy.get('#torneoNombre').should('have.text', 'Torneo E2E Oculto');
        });
    });

    it('Eliminar un torneo sin calendario desde la zona de peligro', () => {
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('abierto').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        cy.get('#botonEliminar').click();
        cy.confirmar('Eliminar');
        cy.location('pathname').should('eq', '/public/perfil/perfil');
        cy.get('#listaOrganizo').should('not.contain', 'Abierto E2E de Póker');
    });
});
