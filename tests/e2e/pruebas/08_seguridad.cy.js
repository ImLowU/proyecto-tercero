// =====================================================================
// 8. SEGURIDAD vista desde el navegador: cabeceras, CSRF, cookies,
//    archivos privados, redirecciones, permisos e inyección SQL.
//    (La CSP y los errores de consola se revisan en todas las pruebas.)
// =====================================================================

describe('Seguridad', () => {
    before(() => cy.prepararBase());

    it('Las páginas mandan las cabeceras de seguridad, con una CSP sin "unsafe-inline"', () => {
        ['home/', 'torneos/explorar', 'shared/base.css', 'api/juegos'].forEach((ruta) => {
            cy.request(ruta).then(({ headers }) => {
                expect(headers['content-security-policy'], ruta).to.contain("script-src 'self'").and.not.contain('unsafe-inline');
                expect(headers['x-frame-options'], ruta).to.eq('DENY');
                expect(headers['x-content-type-options'], ruta).to.eq('nosniff');
                expect(headers['referrer-policy'], ruta).to.eq('same-origin');
            });
        });
    });

    it('La cookie de sesión no la puede leer JavaScript', () => {
        cy.ingresarComo('jugador_e2e1');
        cy.getCookie('NLSESION').should('have.property', 'httpOnly', true);
        cy.getCookie('NLSESION').should('have.property', 'sameSite', 'lax');
        cy.visit('home/');
        cy.document().its('cookie').should('not.contain', 'NLSESION');
    });

    it('Sin el token CSRF, la API no hace cambios', () => {
        cy.ingresarComo('jugador_e2e5');
        cy.idTorneo('abierto').then((id) => {
            cy.request({ method: 'POST', url: `api/torneos/${id}/inscripcion`, failOnStatusCode: false }).then((r) => {
                expect(r.status).to.eq(403);
                expect(r.body.mensaje).to.contain('desactualizada');
            });
            cy.api('GET', `/torneos/${id}`).its('body.torneo.permisos.estoy_inscripto').should('eq', false);
        });
        // Cerrar sesión solo por POST
        cy.request({ url: 'api/auth/logout', failOnStatusCode: false }).its('status').should('be.oneOf', [404, 405]);
    });

    it('Los permisos se controlan en la API, no solo en la página', () => {
        cy.ingresarComo('jugador_e2e1');
        cy.idTorneo('liga_en_curso').then((id) => {
            cy.api('GET', `/torneos/${id}/calendario`).its('body.rondas.1.partidos.0.id_enfrentamiento').then((idPartido) => {
                cy.api('POST', `/enfrentamientos/${idPartido}/resultado`, { puntaje_local: 9, puntaje_visitante: 0 }).its('status').should('eq', 403);
                cy.api('PUT', `/enfrentamientos/${idPartido}`, { lugar: 'Otro lugar' }).its('status').should('eq', 403);
            });
            cy.api('POST', `/torneos/${id}/cancelar`).its('status').should('eq', 403);
        });
        // Nadie puede registrarse como administrador
        cy.clearCookies();
        cy.api('POST', '/auth/registro', { nombre_usuario: 'quiero_ser_admin', correo: 'quiero@e2e.uy',
            contrasena: Cypress.expose('clave'), rol: 'administrador' }).its('status').should('eq', 422);
    });

    it('Los archivos privados no se pueden descargar', () => {
        const base = Cypress.config('baseUrl').replace(/\/public\/$/, '');
        [
            '/config.ejemplo.php', '/app/core/Config.php', '/database/02_datos_base.sql',
            '/tests/e2e/preparar_base.php', '/tools/servidor_dev.php', '/package.json', '/cypress.config.js',
            '/public/uploads/prueba.php', '/README.md',
            // Docker: la configuración, las claves (.env) y el certificado HTTPS
            '/docker/web/config.php', '/docker/ssl/clave.key', '/docker-compose.yml', '/Dockerfile', '/.env', '/scripts/instalar_almalinux.sh',
            // Archivos ocultos: .git tiene el repositorio entero (con el historial)
            '/.git/HEAD', '/.git/config', '/public/.htaccess',
            // cy.request normaliza "/public/../app" a "/app" (403); el servidor, si le llega tal cual, responde 400
            '/public/../app/Aplicacion.php',
        ].forEach((ruta) => {
            cy.request({ url: base + ruta, failOnStatusCode: false }).its('status').should('be.oneOf', [400, 403]);
        });
    });

    it('El "volver" del login no lleva a otro sitio', () => {
        cy.visit('home/login?volver=' + encodeURIComponent('https://ejemplo.com/robar'));
        cy.get('#correo').type('jugador_e2e1@e2e.uy');
        cy.get('#contrasena').type(Cypress.expose('clave'));
        cy.contains('button', 'Iniciar sesión').click();
        cy.location('host').should('eq', '127.0.0.1:8081');
        cy.location('pathname').should('eq', '/public/home/');
    });

    it('Una búsqueda con comillas o SQL no rompe nada ni devuelve de más', () => {
        ["' OR '1'='1", '"; DROP TABLE torneos; --', '%_%'].forEach((texto) => {
            cy.request({ url: `api/torneos?buscar=${encodeURIComponent(texto)}`, failOnStatusCode: false }).then((r) => {
                expect(r.status, texto).to.eq(200);
                expect(r.body.torneos, texto).to.have.length(0);
            });
        });
        cy.request('api/torneos').its('body.torneos').should('have.length.greaterThan', 0);
    });
});
