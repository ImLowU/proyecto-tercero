// =====================================================================
// 7. DISEÑO RESPONSIVO: cada pantalla en celular (375), tableta (768) y
//    PC (1280). En todas se revisa que:
//      - no se pueda deslizar de costado (nada se sale de la pantalla);
//      - todas las imágenes tengan texto alternativo (alt);
//      - no haya ids repetidos (rompen etiquetas y lectores de pantalla).
//    Se guarda una captura de cada una en tests/e2e/screenshots/ para
//    compararlas con la preview. Además se anotan (sin fallar) los
//    botones y enlaces demasiado chicos para tocarlos con el dedo.
// =====================================================================

const TAMANOS = [
    { nombre: 'celular', ancho: 375, alto: 812 },
    { nombre: 'tableta', ancho: 768, alto: 1024 },
    { nombre: 'pc', ancho: 1280, alto: 800 },
];

// Cada pantalla: quién entra (sin usuario = visitante), la dirección y qué
// esperar para saber que terminó de cargar TODO (las partes que llegan por
// separado también: si no, la captura de página completa sale mezclada).
const PANTALLAS = [
    { nombre: 'inicio', ruta: () => 'home/', listo: ['#listaTorneos .tarjeta-torneo', '#proximosPartidos a', '#ultimosResultados .partido'] },
    { nombre: 'explorar', ruta: () => 'torneos/explorar', listo: ['#listaTorneos .tarjeta-torneo'] },
    { nombre: 'calendario', ruta: () => 'torneos/partidos', listo: ['#listaProximos a', '#listaResultados .partido'] },
    { nombre: 'torneo-liga', ruta: (d) => `torneos/torneo?id=${d.torneos.liga_en_curso}#posiciones`, listo: ['#tablaPosiciones table'] },
    { nombre: 'torneo-llaves', ruta: (d) => `torneos/torneo?id=${d.torneos.copa_final}#llaves`, listo: ['.llaves'] },
    { nombre: 'torneo-equipos', ruta: (d) => `torneos/torneo?id=${d.torneos.equipos}#participantes`, listo: ['.participante--equipo'] },
    { nombre: 'jugador', ruta: (d) => `perfil/jugador?id=${d.usuarios.jugador_e2e1}`, listo: ['#contenidoJugador:visible', '#torneosJuega .tarjeta-torneo'] },
    { nombre: 'equipo', ruta: (d) => `perfil/equipo?id=${d.equipo}`, listo: ['#contenidoEquipo:visible', '#miembrosEquipo a'] },
    { nombre: 'ingresar', ruta: () => 'home/login', listo: ['#formIngresar'] },
    { nombre: 'registro', ruta: () => 'register/register', listo: ['#formRegistro'] },
    { nombre: 'recuperar', ruta: () => 'home/recuperar', listo: ['#formRecuperar'] },
    { nombre: 'mi-perfil', usuario: 'jugador_e2e1', ruta: () => 'perfil/perfil', listo: ['#contenidoPerfil:visible', '#listaJuego .tarjeta-torneo'] },
    { nombre: 'crear-torneo', usuario: 'orga_e2e', ruta: () => 'createtournament/createtournament', listo: ['#modulo .opcion--formato', '#vistaPrevia .tarjeta-torneo'] },
    { nombre: 'gestionar', usuario: 'orga_e2e', ruta: (d) => `torneos/gestionar?id=${d.torneos.liga_en_curso}`, listo: ['#contenidoGestion:visible', '#editorRonda .editor-partido'] },
    // Liga de 8 jugadores: 7 rondas en el selector (en el celular no entran y se desliza)
    { nombre: 'gestionar-7-rondas', usuario: 'orga_e2e', ruta: (d) => `torneos/gestionar?id=${d.torneos.liga_grande}`, listo: ['#contenidoGestion:visible', '#selectorRondas [data-ronda]'] },
    { nombre: 'admin-resumen', usuario: 'admin_e2e', ruta: () => 'admin/admin#resumen', listo: ['#actividadReciente table', '#barrasRoles li'] },
    { nombre: 'admin-usuarios', usuario: 'admin_e2e', ruta: () => 'admin/admin#usuarios', listo: ['#tablaUsuarios table'] },
    { nombre: 'admin-torneos', usuario: 'admin_e2e', ruta: () => 'admin/admin#torneos', listo: ['#tablaTorneos table'] },
    { nombre: 'admin-auditoria', usuario: 'admin_e2e', ruta: () => 'admin/admin#auditoria', listo: ['#tablaAuditoria table'] },
];

const observaciones = [];

describe('Diseño responsivo', () => {
    before(() => cy.prepararBase());

    after(() => {
        cy.writeFile('tests/e2e/screenshots/observaciones.json', observaciones);
    });

    TAMANOS.forEach(({ nombre: tamano, ancho, alto }) => {
        describe(`${tamano} (${ancho}px)`, () => {
            beforeEach(() => cy.viewport(ancho, alto));

            PANTALLAS.forEach((pantalla) => {
                it(pantalla.nombre, () => {
                    if (pantalla.usuario) cy.ingresarComo(pantalla.usuario);
                    cy.visit(pantalla.ruta(Cypress.expose('datos')));
                    pantalla.listo.forEach((selector) => cy.get(selector).should('exist'));
                    cy.get('#pie .sitio-pie__contenido').should('exist');
                    cy.get('.cargando:visible').should('not.exist');

                    cy.sinDeslizamientoHorizontal();
                    cy.get('img:not([alt])').should('have.length', 0);
                    cy.document().then((doc) => {
                        const ids = [...doc.querySelectorAll('[id]')].map((el) => el.id);
                        const repetidos = [...new Set(ids.filter((id, i) => ids.indexOf(id) !== i))];
                        expect(repetidos, 'ids repetidos').to.deep.equal([]);

                        // En celular, lo que se toca tiene que medir al menos 32 px de alto (WCAG 2.2
                        // pide 24 como mínimo; lo cómodo son 44). En una casilla o un interruptor
                        // lo que se toca es su etiqueta, así que se mide la etiqueta. Los enlaces
                        // dentro de una oración ("¿No tenés cuenta? Registrate") no cuentan:
                        // WCAG los exceptúa porque su alto lo fija el renglón.
                        if (ancho <= 375) {
                            const dentroDeOracion = (el) => el.tagName === 'A'
                                && [...el.parentNode.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim());
                            const chicos = [...doc.querySelectorAll('#contenido a, button, input:not([type="hidden"]), select, .pestana')]
                                .map((el) => ({ el, zona: el.closest('label') || el }))
                                .filter(({ el, zona }) => {
                                    const r = zona.getBoundingClientRect();
                                    return r.width > 0 && r.height > 0 && r.height < 32 && !dentroDeOracion(el)
                                        && !el.closest('.solo-lectores, .sitio-pie, .tabla, .tarjeta-torneo, .cita, .partido');
                                })
                                .map(({ el, zona }) => `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''} "${(zona.textContent || el.getAttribute('aria-label') || el.type || '').trim().replace(/\s+/g, ' ').slice(0, 35)}" (${Math.round(zona.getBoundingClientRect().height)} px de alto)`);
                            if (chicos.length) observaciones.push({ pantalla: pantalla.nombre, tipo: 'zonas para tocar de menos de 32 px', detalle: [...new Set(chicos)].slice(0, 8) });
                            expect([...new Set(chicos)], 'zonas para tocar de menos de 32 px').to.deep.equal([]);
                        }
                    });
                    // Solo para la captura de página completa (que se arma en tramos):
                    // sin desplazamiento suave y sin elementos "pegajosos", que si no
                    // se repiten en cada tramo. Ya se hicieron todas las comprobaciones.
                    cy.document().then((doc) => {
                        doc.documentElement.style.scrollBehavior = 'auto';
                        doc.querySelectorAll('body *').forEach((el) => {
                            if (doc.defaultView.getComputedStyle(el).position === 'sticky') el.style.position = 'static';
                        });
                    });
                    cy.screenshot(`${tamano}/${pantalla.nombre}`, { capture: 'fullPage', overwrite: true });
                });
            });
        });
    });

    it('En el celular el menú se abre y se cierra con el botón', () => {
        cy.viewport(375, 812);
        cy.visit('home/');
        cy.get('#menuPrincipal').should('not.be.visible');
        cy.get('#botonMenu').should('have.attr', 'aria-expanded', 'false').click();
        cy.get('#menuPrincipal').should('be.visible').and('contain', 'Torneos');
        cy.get('#botonMenu').should('have.attr', 'aria-expanded', 'true');
        cy.get('body').type('{esc}');
        cy.get('#menuPrincipal').should('not.be.visible');
        cy.focused().should('have.id', 'botonMenu');
    });

    it('En el celular la tabla de posiciones muestra los puntos sin deslizarla', () => {
        cy.viewport(375, 812);
        cy.visit(`torneos/torneo?id=${Cypress.expose('datos').torneos.liga_en_curso}#posiciones`);
        cy.contains('#tablaPosiciones th', 'Pts').then(($th) => {
            expect($th[0].getBoundingClientRect().right, 'borde derecho de la columna Pts').to.be.at.most(375);
        });
        cy.get('#tablaPosiciones th.col-goles').should('not.be.visible'); // GF y GC, solo desde 640 px
        cy.viewport(768, 1024);
        cy.get('#tablaPosiciones th.col-goles').should('be.visible');
    });

    it('"Deslizá de costado" aparece en las llaves solo cuando no entran en la pantalla', () => {
        const llaves = () => `torneos/torneo?id=${Cypress.expose('datos').torneos.copa_final}#llaves`;
        cy.viewport(375, 812);
        cy.visit(llaves());
        cy.get('.nota-deslizar').should('be.visible');
        cy.viewport(768, 1024); // en tableta, las tres columnas entran
        cy.get('.nota-deslizar').should('not.be.visible');
        cy.viewport(375, 812); // al achicar la ventana vuelve a aparecer
        cy.get('.nota-deslizar').should('be.visible');
    });

    it('Con "reducir movimiento" en el sistema, no hay desplazamiento suave', () => {
        // El estilo: base.css lo desactiva para quien lo pidió en su sistema
        cy.visit('home/');
        cy.document().then((doc) => {
            const reglas = [...doc.styleSheets].flatMap((hoja) => [...hoja.cssRules]);
            const regla = reglas.find((r) => r.media && r.media.mediaText.includes('prefers-reduced-motion: reduce'));
            expect(regla, 'regla @media (prefers-reduced-motion: reduce)').to.exist;
            expect(regla.cssText).to.contain('scroll-behavior: auto');
        });
        // Los desplazamientos que hace la página por código también lo respetan
        // (se simula que el sistema lo pide antes de que cargue la página)
        cy.visit('home/', {
            onBeforeLoad(win) {
                const original = win.matchMedia.bind(win);
                win.matchMedia = (consulta) => (consulta.includes('prefers-reduced-motion')
                    ? { matches: true, media: consulta, addEventListener() {}, removeEventListener() {} }
                    : original(consulta));
            },
        });
        cy.window().then((win) => {
            const destino = win.document.getElementById('pie');
            const espia = cy.spy(destino, 'scrollIntoView');
            win.nlDesplazar(destino, { block: 'nearest' });
            expect(espia).to.have.been.calledWith({ behavior: 'auto', block: 'nearest' });
        });
    });

    it('En PC la portada entra sin la flecha de "deslizá" y sin ocupar toda la pantalla de alto', () => {
        cy.viewport(1280, 800);
        cy.visit('home/');
        cy.get('#tituloHero').should('be.visible');
        // Las estadísticas se ven sin bajar
        cy.get('#cantidadUsuarios').then(($n) => expect($n[0].getBoundingClientRect().top).to.be.lessThan(800));
    });
});
