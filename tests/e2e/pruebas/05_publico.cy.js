// =====================================================================
// 5. CONSULTA PÚBLICA (sin iniciar sesión): portada con datos reales,
//    explorar con filtros, página del torneo, calendario y resultados,
//    y perfiles públicos de jugadores y equipos.
// =====================================================================

describe('Consulta pública', () => {
    before(() => cy.prepararBase());

    it('Desde la raíz del sitio se llega a la portada, sin "index.html" en la dirección', () => {
        cy.visit(Cypress.config('baseUrl').replace(/public\/$/, ''));
        cy.location('pathname').should('eq', '/public/home/');
        cy.get('#listaTorneos .tarjeta-torneo').should('have.length.greaterThan', 0);
        // El logo y "Inicio" también llevan a la carpeta, no al archivo
        cy.get('.sitio-nav__marca').should('have.attr', 'href', '../home/');
        cy.get('#menuPrincipal a[data-clave="inicio"]').should('have.attr', 'href', '../home/')
            .and('have.attr', 'aria-current', 'page');
    });

    it('Las páginas van sin ".html", y una dirección vieja con ".html" lleva a la nueva', () => {
        // Un enlace guardado o un correo con la dirección vieja: redirige y conserva los parámetros
        cy.request({ url: 'torneos/explorar.html?categoria=mental', followRedirect: false }).then((r) => {
            expect(r.status).to.eq(302);
            expect(r.redirectedToUrl).to.match(/\/public\/torneos\/explorar\?categoria=mental$/);
        });
        cy.request({ url: 'home/index.html', followRedirect: false }).its('redirectedToUrl').should('match', /\/public\/home\/$/);
        cy.request({ url: 'torneos/no-existe', failOnStatusCode: false }).its('status').should('eq', 404);

        // Recorriendo el sitio, la barra nunca muestra el archivo
        cy.visit('home/');
        cy.get('#menuPrincipal a[data-clave="torneos"]').should('have.attr', 'href', '../torneos/explorar').click();
        cy.location('pathname').should('eq', '/public/torneos/explorar');
        cy.get('.tarjeta-torneo').first().should('have.attr', 'href').and('match', /^\.\.\/torneos\/torneo\?id=\d+$/);
        cy.get('.tarjeta-torneo').first().click();
        cy.location('pathname').should('eq', '/public/torneos/torneo');
        cy.location('search').should('match', /^\?id=\d+$/);
        // "Ingresar" recuerda esta página para volver después, también sin ".html"
        cy.contains('#authArea a', 'Ingresar').should('have.attr', 'href').and('match', /^\.\.\/home\/login\?volver=\.\.%2Ftorneos%2Ftorneo%3Fid%3D\d+$/);
    });

    it('La portada muestra números reales (M1) y los torneos activos', () => {
        cy.request('api/estadisticas').its('body.estadisticas').then((e) => {
            cy.visit('home/');
            cy.get('#cantidadUsuarios').should('have.text', String(e.usuarios));
            cy.get('#cantidadTorneos').should('have.text', String(e.torneos));
            cy.get('#cantidadEquipos').should('have.text', String(e.equipos));
            // Con un solo equipo, el rótulo va en singular
            expect(e.equipos, 'equipos en los datos de prueba').to.eq(1);
            cy.get('[data-numero="cantidadEquipos"]').should('have.text', 'Equipo registrado');
            cy.get('[data-numero="cantidadUsuarios"]').should('have.text', 'Usuarios registrados');
        });
        // El destacado tiene lugares libres y, sin premio, dice qué se juega
        cy.get('#torneoDestacado').should('have.attr', 'href').and('match', /torneo\?id=\d+/);
        cy.get('#destacadoEstado').should('contain', 'Inscripciones abiertas');
        cy.get('#destacadoPremio').should('not.be.visible');
        cy.get('#destacadoRotulo').should('not.contain', 'Premio').and('contain', '·');
        cy.get('#listaTorneos .tarjeta-torneo').should('have.length.greaterThan', 0).and('have.length.at.most', 6);
        // Filtrar por categoría cambia la lista y el "Ver todos"
        cy.contains('#filtroCategoria button', 'Mental').click();
        cy.get('#listaTorneos .tarjeta-torneo').each(($t) => {
            expect($t.text()).to.match(/Ajedrez|Damas|Truco|Póker|Dominó/);
        });
        cy.get('#verTodos').should('have.attr', 'href').and('contain', 'categoria=mental');
        // Los próximos partidos y los últimos resultados vienen de torneos publicados
        cy.get('#proximosPartidos').should('contain', 'Sala E2E');
        cy.get('#ultimosResultados .partido').should('have.length.greaterThan', 0);
        // Un partido ya jugado no dice "Fecha a confirmar"
        cy.get('#ultimosResultados').should('not.contain', 'Fecha a confirmar');
        // No hay "Desliza hacia abajo" ni una portada que ocupe toda la pantalla
        cy.contains('Desliza hacia abajo').should('not.exist');
    });

    it('Explorar: buscar, filtrar, y los filtros quedan en la dirección', () => {
        cy.visit('torneos/explorar');
        cy.get('#listaTorneos .tarjeta-torneo').should('have.length.greaterThan', 3);
        cy.get('#buscar').type('Damas');
        cy.get('#listaTorneos .tarjeta-torneo').should('have.length', 1).and('contain', 'Copa E2E de Damas');
        cy.location('search').should('contain', 'buscar=Damas');

        cy.reload();
        cy.get('#buscar').should('have.value', 'Damas');
        cy.get('#listaTorneos .tarjeta-torneo').should('have.length', 1);

        cy.get('#botonLimpiar').click();
        cy.get('#buscar').should('have.value', '');
        cy.get('#botonFiltros').then(($b) => { if ($b.is(':visible')) cy.wrap($b).click(); });
        cy.get('#filtroEstado').select('finalizado');
        cy.get('#listaTorneos .tarjeta-torneo').should('have.length', 1).and('contain', 'Finalizado');
        cy.location('search').should('contain', 'estado=finalizado');

        // Una búsqueda sin resultados lo dice
        cy.get('#buscar').type('zzz no existe');
        cy.get('#listaTorneos .vacio').should('be.visible');
    });

    it('Página del torneo: pestañas, la pestaña queda en la dirección (#) y la tabla enlaza a los perfiles', () => {
        cy.idTorneo('liga_en_curso').then((id) => cy.visit(`torneos/torneo?id=${id}`));
        cy.get('#torneoNombre').should('have.text', 'Liga E2E de Ajedrez');
        cy.get('#torneoChips').should('contain', 'En curso').and('contain', 'Liga');
        cy.get('#pestanaLlaves').should('not.be.visible'); // la liga no tiene llaves
        cy.get('#proximosDelTorneo').should('contain', 'Sala E2E');

        cy.contains('#pestanasTorneo button', 'Posiciones').click();
        cy.location('hash').should('eq', '#posiciones');
        cy.get('#panelPosiciones').should('be.visible');
        cy.get('#tablaPosiciones tbody tr').should('have.length', 4);
        cy.get('#tablaPosiciones tbody tr a').first().should('have.attr', 'href').and('match', /jugador\?id=\d+/);

        cy.contains('#pestanasTorneo button', 'Calendario').click();
        cy.get('#listaRondas .ronda').should('have.length', 3);
        cy.get('#listaRondas .partido__marcador').should('have.length', 2); // la ronda 1 ya se jugó

        // Teclado: la flecha saltea "Llaves", que en una liga está oculta
        cy.get('#pestanaCalendario').focus().type('{rightArrow}');
        cy.focused().should('have.id', 'pestanaPosiciones').and('have.attr', 'aria-selected', 'true');
        cy.get('#panelPosiciones').should('be.visible');
        cy.location('hash').should('eq', '#posiciones');
        // Fin, Inicio, y la flecha izquierda desde la primera vuelve a la última
        cy.focused().type('{end}');
        cy.focused().should('have.id', 'pestanaParticipantes').and('have.attr', 'aria-selected', 'true');
        cy.focused().type('{home}');
        cy.focused().should('have.id', 'pestanaResumen').and('have.attr', 'aria-selected', 'true');
        cy.focused().type('{leftArrow}');
        cy.focused().should('have.id', 'pestanaParticipantes');
    });

    it('Eliminación directa terminada: llaves por ronda y el campeón', () => {
        cy.idTorneo('copa_final').then((id) => cy.visit(`torneos/torneo?id=${id}#llaves`));
        cy.get('#pestanaLlaves').should('have.attr', 'aria-selected', 'true');
        cy.get('.llaves__ronda').should('have.length', 3); // semifinal, final y campeón
        cy.get('.llave--campeon').should('not.contain', 'Por definir');
        // En PC las llaves entran: no se pide deslizar de costado
        cy.get('.nota-deslizar').should('not.be.visible');
        cy.get('#avisosTorneo .campeon').should('be.visible');
        cy.get('#pestanaPosiciones').should('not.be.visible');
    });

    it('El perfil público de un jugador no muestra su correo (S2)', () => {
        cy.idUsuario('jugador_e2e1').then((id) => {
            cy.visit(`perfil/jugador?id=${id}`);
            cy.get('#nombreJugador').should('have.text', 'jugador_e2e1');
            cy.get('#numerosJugador').should('contain', 'Partidos');
            cy.get('#torneosJuega').should('contain', 'Liga E2E de Ajedrez');
            cy.get('body').should('not.contain', '@e2e.uy');
            cy.request(`api/usuarios/${id}`).its('body').then((cuerpo) => {
                expect(JSON.stringify(cuerpo)).not.to.contain('@e2e.uy');
            });
        });
    });

    it('Un nombre con código HTML se muestra como texto y no se ejecuta', () => {
        cy.visit('torneos/explorar?buscar=XSS', {
            onBeforeLoad: (win) => cy.stub(win, 'alert').as('alerta'),
        });
        cy.get('#listaTorneos .tarjeta-torneo__titulo').should('contain', '<img src=x onerror=alert(1)> Copa XSS');
        cy.get('#listaTorneos img[src="x"]').should('not.exist');
        cy.idTorneo('con_codigo').then((id) => cy.visit(`torneos/torneo?id=${id}`, {
            onBeforeLoad: (win) => cy.stub(win, 'alert').as('alerta'),
        }));
        cy.get('#torneoNombre').should('contain', '<img src=x onerror=alert(1)>');
        cy.get('@alerta').should('not.have.been.called');
    });

    it('Calendario y resultados: próximos partidos por día y resultados', () => {
        cy.visit('torneos/partidos');
        cy.get('#listaProximos').should('contain', 'Sala E2E');
        cy.get('#listaResultados .partido__marcador').should('have.length.greaterThan', 0);
        // "Fecha a confirmar" solo en los que faltan jugar sin fecha programada
        cy.get('#listaProximos').should('contain', 'Fecha a confirmar');
        cy.get('#listaResultados').should('not.contain', 'Fecha a confirmar');
        cy.visit('torneos/partidos?vista=resultados');
        cy.get('#pestanaResultados').should('have.attr', 'aria-selected', 'true');
    });

    it('Una página que no existe o un torneo inexistente muestran un mensaje claro', () => {
        cy.visit('torneos/torneo?id=999999');
        cy.get('#estadoCarga .vacio').should('be.visible').find('a').should('have.attr', 'href', 'explorar');
        cy.visit('perfil/jugador?id=999999');
        cy.get('#estadoCarga').should('contain', 'no existe');
    });
});
