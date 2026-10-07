// =====================================================================
// 6. ADMINISTRACIÓN: permisos, usuarios (alta, rol, desactivar),
//    módulos, configuración, auditoría y reportes CSV.
// =====================================================================

describe('Administración', () => {
    before(() => cy.prepararBase());

    it('Un participante no entra al panel (ni por la página ni por la API)', () => {
        cy.ingresarComo('jugador_e2e1');
        cy.visit('admin/admin');
        cy.get('#estadoCarga').should('contain', 'solo para administradores');
        cy.get('#panelAdmin').should('not.be.visible');
        cy.api('GET', '/admin/usuarios').its('status').should('eq', 403);
        cy.api('PUT', '/admin/modulos/1', { habilitado: false }).its('status').should('eq', 403);
    });

    it('Resumen con los números del sistema y la actividad reciente', () => {
        cy.ingresarComo('admin_e2e');
        cy.visit('admin/admin');
        cy.get('#tarjetasResumen li').should('have.length.greaterThan', 3);
        cy.get('#barrasRoles').should('contain', 'Participante');
        cy.get('#actividadReciente tbody tr').should('have.length.greaterThan', 0);
        cy.get('#menuAdmin a[data-seccion="resumen"]').should('have.attr', 'aria-current');
    });

    it('Deshabilitar un formato: deja de ofrecerse al crear torneos', () => {
        cy.ingresarComo('admin_e2e');
        cy.visit('admin/admin#modulos');
        cy.contains('.modulo', 'Sistema suizo').find('input[type="checkbox"]').should('be.checked').uncheck({ force: true });
        cy.get('#alertaAdmin .alerta-exito').should('be.visible');
        cy.contains('.modulo', 'Sistema suizo').should('have.class', 'modulo--apagado');

        cy.clearCookies();
        cy.ingresarComo('orga_e2e');
        cy.visit('createtournament/createtournament');
        cy.get('#modulo .opcion--formato').should('have.length', 2).and('not.contain', 'Sistema suizo');
        cy.api('POST', '/torneos', { nombre: 'Suizo no permitido', id_juego: 28, modulo: 'sistema_suizo', modalidad: 'individual',
            cupo: 4, fecha_inicio: '2099-01-01', fecha_fin: '2099-01-02' }).its('status').should('eq', 422);

        // Volver a habilitarlo
        cy.clearCookies();
        cy.ingresarComo('admin_e2e');
        cy.visit('admin/admin#modulos');
        cy.contains('.modulo', 'Sistema suizo').find('input[type="checkbox"]').check({ force: true });
        cy.contains('.modulo', 'Sistema suizo').should('not.have.class', 'modulo--apagado');
    });

    it('Desactivar un usuario lo deja afuera en su siguiente acción (B5)', () => {
        // jugador_e2e3 tiene la sesión abierta
        cy.ingresarComo('jugador_e2e3');
        cy.getCookie('NLSESION').then((cookieJugador) => {
            cy.clearCookies();
            cy.ingresarComo('admin_e2e');
            cy.visit('admin/admin#usuarios');
            cy.get('#buscarUsuario').type('jugador_e2e3');
            cy.get('#tablaUsuarios tbody tr').should('have.length', 1);
            cy.contains('#tablaUsuarios button', 'Desactivar').click();
            cy.confirmar('Desactivar');
            cy.get('#tablaUsuarios').should('contain', 'Inactivo');

            // Con la misma sesión de antes, ya no está logueado
            cy.clearCookies();
            cy.setCookie('NLSESION', cookieJugador.value);
            cy.request('api/auth/sesion').its('body.logueado').should('eq', false);
            cy.visit('perfil/perfil');
            cy.location('pathname').should('eq', '/public/home/login');
            // Y no puede volver a entrar
            cy.get('#correo').type('jugador_e2e3@e2e.uy');
            cy.get('#contrasena').type(Cypress.expose('clave'));
            cy.contains('button', 'Iniciar sesión').click();
            cy.get('#alertaAcceso').should('contain', 'desactivada');
        });
    });

    it('Cambiar el rol: un participante pasa a organizador y ya puede crear torneos', () => {
        cy.ingresarComo('admin_e2e');
        cy.visit('admin/admin#usuarios');
        cy.get('#buscarUsuario').type('jugador_e2e4');
        cy.get('#tablaUsuarios tbody tr').should('have.length', 1);
        cy.get('#tablaUsuarios select[aria-label="Rol de jugador_e2e4"]').select('organizador');
        cy.confirmar('Cambiar rol');
        cy.get('#alertaAdmin .alerta-exito').should('be.visible');

        cy.clearCookies();
        cy.ingresarComo('jugador_e2e4');
        cy.visit('home/');
        cy.get('#botonUsuario').click();
        cy.get('#listaUsuario').should('contain', 'Organizador').and('contain', 'Crear torneo');
    });

    it('Alta de un usuario con contraseña temporal: la tiene que cambiar al entrar', () => {
        cy.ingresarComo('admin_e2e');
        cy.visit('admin/admin#usuarios');
        cy.get('#botonNuevoUsuario').click();
        cy.get('#modalUsuario').should('be.visible');
        cy.get('#usuario_nombre').type('alta_e2e');
        cy.get('#usuario_correo').type('alta_e2e@e2e.uy');
        cy.get('#usuario_clave').type('Temporal#2026');
        cy.get('#usuario_rol').select('organizador');
        cy.get('#guardarUsuario').click();
        cy.get('#modalUsuario').should('not.be.visible');
        cy.get('#tablaUsuarios').should('contain', 'alta_e2e').and('contain', 'Clave temporal');

        cy.clearCookies();
        cy.visit('home/login');
        cy.get('#correo').type('alta_e2e@e2e.uy');
        cy.get('#contrasena').type('Temporal#2026');
        cy.contains('button', 'Iniciar sesión').click();
        // La página después cambia su dirección a #cuenta: se revisa el aviso, no la dirección
        cy.location('pathname').should('eq', '/public/perfil/perfil');
        cy.get('#alertaPerfil').should('contain', 'tenés que elegir una contraseña nueva');
    });

    it('Configuración: un valor inválido no guarda nada; cerrar el registro oculta el formulario', () => {
        cy.ingresarComo('admin_e2e');
        cy.visit('admin/admin#configuracion');
        cy.get('[data-clave="max_intentos_login"]').clear().type('0');
        cy.get('[data-clave="nombre_sitio"]').clear().type('Otro nombre');
        cy.get('#formConfiguracion').submit();
        cy.get('#alertaAdmin .alerta-error').should('contain', 'No se guardó nada');
        cy.get('#cfg-max_intentos_loginError').should('not.be.empty');
        cy.request('api/admin/configuracion').its('body.configuracion').then((lista) => {
            const valor = (clave) => lista.find((c) => c.clave === clave).valor;
            expect(valor('nombre_sitio'), 'no se guardó a medias').to.eq('NextLeague');
            expect(valor('max_intentos_login')).to.eq('5');
        });

        cy.reload();
        cy.get('[data-clave="registro_abierto"]').should('be.checked').uncheck({ force: true });
        cy.get('#formConfiguracion').submit();
        cy.get('#alertaAdmin').should('contain', 'Se guardó 1 cambio');

        cy.clearCookies();
        cy.visit('register/register');
        cy.get('.acceso__tarjeta').should('contain', 'El registro está cerrado');
        cy.get('#formRegistro').should('not.exist');
        cy.visit('home/login');
        cy.get('#pieRegistro').should('not.be.visible');
        cy.get('#authArea').should('not.contain', 'Registrarse');
    });

    it('Auditoría: registra los cambios con quién los hizo y se puede filtrar', () => {
        cy.ingresarComo('admin_e2e');
        cy.visit('admin/admin#auditoria');
        // Solo las filas de la auditoría (cada una tiene adentro su propia tabla de detalle)
        const filas = '#tablaAuditoria table.tabla-auditoria > tbody > tr';
        cy.get(filas).should('have.length.greaterThan', 5);
        cy.get('#filtroTabla').select('usuarios');
        cy.get('#filtroAccion').select('editar');
        // Se reintenta hasta que se vea el resultado de los DOS filtros (no el intermedio)
        cy.get(filas).should(($filas) => {
            expect($filas.length).to.be.greaterThan(0);
            $filas.each((_, fila) => {
                expect(Cypress.$(fila).find('td').eq(2).text().trim(), 'acción').to.eq('Cambio');
                expect(Cypress.$(fila).find('td').eq(3).text(), 'tabla').to.contain('Usuarios');
            });
        });
        // La búsqueda espera a que se deje de escribir: se espera su respuesta
        cy.intercept('GET', '**/api/admin/auditoria?*buscar=jugador_e2e3*').as('busqueda');
        cy.get('#buscarAuditoria').type('jugador_e2e3');
        cy.wait('@busqueda');
        cy.get('#tablaAuditoria .cargando').should('not.exist');
        cy.get('#tablaAuditoria').should('contain', 'admin_e2e'); // quién lo desactivó
        cy.get('#tablaAuditoria details summary').first().click();
        cy.get('#tablaAuditoria details[open]').should('contain', 'activo');
    });

    it('Si se cambian los filtros rápido, queda el resultado del último (no el de una respuesta que llegó tarde)', () => {
        cy.ingresarComo('admin_e2e');
        // La respuesta del filtro "Organizadores" llega tarde a propósito
        cy.intercept('GET', '**/api/admin/usuarios?*rol=organizador*', (req) => {
            req.on('response', (res) => { res.setDelay(1500); });
        }).as('lenta');
        cy.visit('admin/admin#usuarios');
        cy.get('#tablaUsuarios table').should('exist');
        cy.get('#filtroRol').select('organizador');
        cy.get('#filtroRol').select('administrador');
        cy.wait('@lenta');
        cy.wait(300); // margen para que una respuesta vieja, si no se descartara, pise la tabla
        cy.get('#tablaUsuarios tbody tr').should('have.length.greaterThan', 0).each(($fila) => {
            expect($fila.find('select[data-rol]').val(), 'rol de cada fila').to.eq('administrador');
        });
        cy.get('#cantidadUsuarios').should('contain', '2 usuarios');
    });

    it('En la tabla de usuarios, los chips quedan alineados con el nombre y sin taparlo', () => {
        cy.viewport(375, 812); // en el celular la columna es más angosta
        cy.ingresarComo('admin_e2e');
        cy.visit('admin/admin#usuarios');
        // "Vos" (admin_e2e) y "Clave temporal" (admin, alta_e2e)
        cy.get('#tablaUsuarios .fila-usuario .chip').should('have.length.at.least', 2).each(($chip) => {
            const fila = $chip[0].closest('tr');
            const nombre = fila.querySelector('.celda-usuario span').getBoundingClientRect();
            const correo = fila.querySelectorAll('td')[1].getBoundingClientRect();
            const chip = $chip[0].getBoundingClientRect();
            const texto = $chip.text();
            expect(Math.abs((chip.top + chip.height / 2) - (nombre.top + nombre.height / 2)), `"${texto}": diferencia vertical en px`).to.be.at.most(2);
            expect(chip.left, `"${texto}" empieza después del nombre`).to.be.at.least(nombre.right);
            expect(chip.right, `"${texto}" termina antes del correo`).to.be.at.most(correo.left);
        });
    });

    it('Reportes: los CSV se descargan solo como administrador', () => {
        cy.ingresarComo('admin_e2e');
        ['usuarios', 'torneos', 'auditoria'].forEach((tipo) => {
            cy.request(`api/admin/reportes/${tipo}`).then((r) => {
                expect(r.status).to.eq(200);
                expect(r.headers['content-type']).to.contain('text/csv');
                expect(r.headers['content-disposition']).to.contain('attachment');
                expect(r.body.split('\n').length).to.be.greaterThan(2);
            });
        });
        cy.clearCookies();
        cy.ingresarComo('orga_e2e');
        cy.request({ url: 'api/admin/reportes/usuarios', failOnStatusCode: false }).its('status').should('eq', 403);
    });
});
