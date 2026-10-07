// =====================================================================
// 4. COMPETENCIA (organizador): generar el calendario, programar fecha
//    y lugar, publicar rondas, cargar y corregir resultados, y jugar los
//    tres formatos hasta el final (liga, eliminación directa y suizo).
// =====================================================================

// Fecha y hora "AAAA-MM-DDTHH:MM" a "dias" de hoy, para un datetime-local
const fechaHora = (dias, hora = '20:00') => {
    const d = new Date(Date.now() + dias * 86400000);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}T${hora}`;
};

// Completa todos los marcadores de la ronda elegida (gana el local) y guarda
function cargarRondaElegida(golesLocal = 2, golesVisitante = 1) {
    cy.get('#editorRonda .editor-partido[data-id]').each(($fila) => {
        cy.wrap($fila).find('[name="local"]').clear().type(String(golesLocal));
        cy.wrap($fila).find('[name="visitante"]').clear().type(String(golesVisitante));
    });
    cy.get('#formRonda').contains('button', 'Guardar cambios').click();
}

// Las acciones de "Gestionar" recargan los datos y RECIÉN ENTONCES avisan:
// en el mismo instante en que aparece el aviso, la pantalla ya tiene que
// mostrar el estado nuevo (se revisa con Cypress.$, sin reintentar). Antes
// avisaban primero y por un momento quedaba la pantalla vieja.
function generar() {
    cy.get('#botonGenerar').click();
    cy.confirmar('Generar calendario');
    cy.get('#alertaGestion').should('contain', 'Calendario generado').then(() => {
        expect(Cypress.$('#selectorRondas [data-ronda]').length, 'rondas dibujadas al aparecer el aviso').to.be.greaterThan(0);
    });
}

function publicarTodas() {
    cy.get('#botonPublicarTodas').click();
    cy.get('#alertaGestion').should('contain', 'Se publicaron').then(() => {
        expect(Cypress.$('#botonPublicarTodas').length, 'botón "Publicar" al aparecer el aviso').to.eq(0);
        expect(Cypress.$('#selectorRondas .punto--borrador').length, 'rondas en borrador al aparecer el aviso').to.eq(0);
    });
}

function generarYPublicar() {
    generar();
    publicarTodas();
}

describe('Competencia', () => {
    before(() => cy.prepararBase());
    beforeEach(() => cy.ingresarComo('orga_e2e'));

    it('Liga: generar, programar, publicar, cargar resultados y ver la tabla', () => {
        cy.idTorneo('liga_lista').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        cy.get('#textoProximoPaso').should('contain', 'Ya podés generar el calendario');
        generar();
        // 4 jugadores en liga: 3 rondas de 2 partidos, en borrador
        cy.get('#selectorRondas [data-ronda]').should('have.length', 3);
        cy.get('#editorRonda .chip').should('contain', 'Borrador');
        cy.get('#editorRonda [name="local"]').first().should('be.disabled');

        // Programar fecha y lugar del primer partido de la ronda 2 (se puede en
        // borrador), dentro de las fechas del torneo (empieza en 7 días)
        cy.get('#selectorRondas [data-ronda]').eq(1).click();
        cy.get('#editorRonda .editor-partido[data-id]').first().within(() => {
            cy.get('[name="fecha_hora"]').type(fechaHora(10));
            cy.get('[name="lugar"]').type('Cancha Central E2E');
        });
        cy.get('#formRonda').contains('button', 'Guardar cambios').click();
        cy.get('#alertaGestion').should('contain', 'Se guardó 1 cambio');

        // Publicar y cargar la ronda 1
        publicarTodas();
        cy.get('#alertaGestion').should('contain', 'Se publicaron 3 ronda(s)');
        cy.get('#selectorRondas [data-ronda]').first().click();
        cargarRondaElegida(2, 1);
        cy.get('#alertaGestion').should('contain', 'Se guardaron 2 cambios');
        cy.get('#pasos').should('contain', '2 de 6 jugados');

        // El público ve la tabla actualizada y el partido programado
        cy.clearCookies();
        cy.idTorneo('liga_lista').then((id) => cy.visit(`torneos/torneo?id=${id}#posiciones`));
        cy.get('#pestanaPosiciones').should('have.attr', 'aria-selected', 'true');
        cy.get('#tablaPosiciones tbody tr').should('have.length', 4);
        cy.get('#tablaPosiciones tbody tr').first().find('td.destacada').should('have.text', '3');
        cy.contains('#pestanasTorneo button', 'Calendario').click();
        cy.get('#listaRondas').should('contain', 'Cancha Central E2E');
    });

    it('Una fecha fuera de las del torneo no se guarda y se avisa en el partido', () => {
        cy.idTorneo('liga_lista').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        cy.get('#selectorRondas [data-ronda]').eq(2).click();
        cy.get('#editorRonda .editor-partido[data-id]').first().find('[name="fecha_hora"]').type(fechaHora(40));
        cy.get('#formRonda').contains('button', 'Guardar cambios').click();
        cy.get('#editorRonda .editor-partido--error').should('contain', 'las fechas del torneo');
        cy.get('#alertaGestion').should('contain', 'no se pudo guardar');
    });

    it('Con la autorización del administrador, corregir un resultado deja el anterior en el historial', () => {
        // La autorización para corregir resultados la da el administrador (letra, 5.2)
        cy.clearCookies();
        cy.ingresarComo('admin_e2e');
        cy.idTorneo('liga_lista').then((id) => cy.api('POST', `/torneos/${id}`, { permitir_correcciones: 1 })).its('status').should('eq', 200);
        cy.clearCookies();
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('liga_lista').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        cy.get('#selectorRondas [data-ronda]').first().click();
        cy.get('#editorRonda .editor-partido[data-finalizado="1"]').first().within(() => {
            cy.get('[name="local"]').clear().type('0');
            cy.get('[name="visitante"]').clear().type('3');
            cy.get('.motivo').should('be.visible').find('input').type('Se cargó al revés');
        });
        cy.get('#formRonda').contains('button', 'Guardar cambios').click();
        cy.get('#alertaGestion').should('contain', 'Se guardó 1 cambio');

        cy.get('#editorRonda .editor-partido[data-finalizado="1"]').first().contains('button', 'Historial').click();
        cy.get('#modalHistorial').should('be.visible').within(() => {
            cy.get('.historial li').should('have.length', 2);
            cy.get('.historial--vigente').should('contain', '0 - 3').and('contain', 'Vigente').and('contain', 'Se cargó al revés');
            cy.contains('li', 'Reemplazado').should('contain', '2 - 1');
            cy.contains('button', 'Cerrar').click();
        });
    });

    it('Un marcador a medio completar no se guarda y se avisa', () => {
        cy.idTorneo('liga_lista').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        // Ronda 3: sus partidos no tienen fecha (el primero de la ronda 2 está
        // programado para dentro de 10 días y su marcador espera a que se juegue)
        cy.get('#selectorRondas [data-ronda]').eq(2).click();
        cy.get('#editorRonda .editor-partido[data-id]').first().find('[name="local"]').type('4');
        cy.get('#formRonda').contains('button', 'Guardar cambios').click();
        cy.get('#alertaGestion').should('contain', 'a medio completar');
        cy.get('#editorRonda .editor-partido--error').should('have.length', 1);
        // Lo escrito no se pierde
        cy.get('#editorRonda .editor-partido[data-id]').first().find('[name="local"]').should('have.value', '4');
    });

    it('Eliminación directa: el ganador avanza y al terminar aparece el campeón', () => {
        cy.idTorneo('copa_lista').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        generarYPublicar();
        // Semifinales: la final todavía no tiene rivales
        cy.get('#selectorRondas [data-ronda]').should('have.length', 2);
        cy.get('#selectorRondas [data-ronda]').last().click();
        cy.get('#editorRonda').should('contain', 'Por definir');
        cy.get('#selectorRondas [data-ronda]').first().click();
        cargarRondaElegida(3, 0);
        cy.get('#alertaGestion').should('contain', 'Se guardaron 2 cambios');

        // En la final ya están los dos ganadores
        cy.get('#selectorRondas [data-ronda]').last().click();
        cy.get('#editorRonda').should('not.contain', 'Por definir');
        cargarRondaElegida(2, 1);
        cy.get('#alertaGestion').should('contain', 'el torneo terminó');
        cy.get('#pasos').should('contain', 'Finalizado');

        cy.get('#enlaceVolver').click();
        cy.get('#torneoChips').should('contain', 'Finalizado');
        cy.get('#avisosTorneo .campeon strong').invoke('text').should('match', /^jugador_e2e[1-4]$/).then((campeon) => {
            cy.contains('#pestanasTorneo button', 'Llaves').click();
            cy.get('.llave--campeon').should('contain', campeon);
        });
    });

    it('Sistema suizo: aparece "Generar ronda siguiente" y el torneo termina solo (B2)', () => {
        cy.idTorneo('suizo_listo').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        generarYPublicar();
        // El suizo arma de a una ronda: con 4 jugadores se juegan 2
        cy.get('#selectorRondas [data-ronda]').should('have.length', 1);
        cy.get('#pasos').should('contain', 'Ronda 1 de 2');
        cargarRondaElegida(1, 0);
        cy.get('#botonSiguiente').should('be.visible').click();
        cy.get('#alertaGestion').should('contain', 'Ronda 2 generada');
        cy.get('#selectorRondas [data-ronda]').should('have.length', 2);
        publicarTodas();
        cargarRondaElegida(1, 0);
        cy.get('#alertaGestion').should('contain', 'el torneo terminó');

        // La tabla final tiene la columna de desempate Buchholz
        cy.get('#enlaceVolver').click();
        cy.get('#avisosTorneo .campeon').should('be.visible');
        cy.contains('#pestanasTorneo button', 'Posiciones').click();
        cy.get('#tablaPosiciones thead').should('contain', 'Buch.');
    });

    it('Cancelar un torneo en curso conserva sus partidos (M9)', () => {
        cy.idTorneo('liga_en_curso').then((id) => {
            cy.visit(`torneos/gestionar?id=${id}`);
            cy.get('#botonEliminar').should('not.exist'); // ya tiene calendario: solo se cancela
            cy.get('#botonCancelar').click();
            cy.confirmar('Cancelar torneo');
            cy.get('#alertaGestion').should('contain', 'Torneo cancelado');
            cy.get('#chipsTorneo').should('contain', 'Cancelado');
            cy.get('#enlaceEditar').should('not.be.visible');

            cy.visit(`torneos/torneo?id=${id}#calendario`);
            cy.get('#avisosTorneo').should('contain', 'fue cancelado');
            cy.get('#listaRondas .partido').should('have.length.greaterThan', 0);
        });
    });
});
