// =====================================================================
// 1. ACCESO: registro, ingreso, "recordarme", recuperar la contraseña,
//    cambio obligatorio y bloqueo por intentos fallidos.
// =====================================================================

describe('Acceso', () => {
    before(() => cy.prepararBase());

    it('Registro de un participante: entra con la sesión ya iniciada', () => {
        cy.visit('register/register');
        cy.get('#nombre_usuario').type('nuevo_e2e');
        cy.get('#correo').type('nuevo_e2e@e2e.uy');
        cy.get('#contrasena').type(Cypress.expose('clave'));
        cy.get('#requisitosClave li.cumple').should('have.length', 4);
        cy.get('#contrasena_repetida').type(Cypress.expose('clave'));
        cy.contains('.opcion-rol', 'Participar').click();
        cy.get('#acepto').check();
        cy.contains('button', 'Crear mi cuenta').click();

        cy.location('pathname').should('eq', '/public/home/');
        cy.get('#botonUsuario').should('contain', 'nuevo_e2e');
        // Un participante no ve "Crear torneo" en su menú
        cy.get('#botonUsuario').click();
        cy.get('#listaUsuario').should('contain', 'Participante').and('not.contain', 'Crear torneo');
    });

    it('Registro: marca los errores en cada campo y no repite nombres', () => {
        cy.visit('register/register');
        cy.contains('button', 'Crear mi cuenta').click();
        cy.get('#nombre_usuarioError').should('not.be.empty');
        cy.get('#correoError').should('not.be.empty');
        cy.get('#contrasenaError').should('not.be.empty');
        cy.get('#rolError').should('not.be.empty');
        cy.get('#aceptoError').should('not.be.empty');

        // Un nombre que ya existe lo rechaza la API, junto al campo
        cy.get('#nombre_usuario').type('jugador_e2e1');
        cy.get('#correo').type('otro_correo@e2e.uy');
        cy.get('#contrasena').type(Cypress.expose('clave'));
        cy.get('#contrasena_repetida').type(Cypress.expose('clave'));
        cy.contains('.opcion-rol', 'Participar').click();
        cy.get('#acepto').check();
        cy.contains('button', 'Crear mi cuenta').click();
        cy.get('#nombre_usuarioError').should('contain', 'en uso');
        cy.location('pathname').should('eq', '/public/register/register');
    });

    it('Ingreso con datos incorrectos: avisa y no entra', () => {
        cy.visit('home/login');
        cy.get('#correo').type('jugador_e2e1@e2e.uy');
        cy.get('#contrasena').type('Equivocada#1');
        cy.contains('button', 'Iniciar sesión').click();
        cy.get('#alertaAcceso').should('contain', 'Correo o contraseña incorrectos');
        cy.get('#contrasena').should('have.value', '');
        cy.location('pathname').should('eq', '/public/home/login');

        // Al reintentar, el aviso anterior desaparece mientras se espera la respuesta
        cy.intercept('POST', '**/api/auth/login', (req) => {
            req.on('response', (res) => { res.setDelay(800); });
        }).as('lento');
        cy.get('#contrasena').type('Equivocada#2');
        cy.contains('button', 'Iniciar sesión').click();
        cy.get('#alertaAcceso').should('not.be.visible');
        cy.wait('@lento');
        cy.get('#alertaAcceso').should('be.visible').and('contain', 'incorrectos');
    });

    it('Si el sistema no responde, lo avisa en vez de mandar a ingresar', () => {
        cy.intercept('GET', '**/api/auth/sesion', {
            statusCode: 503,
            body: { exito: false, mensaje: 'El sistema no está disponible en este momento (no hay conexión con la base de datos).' },
        });
        cy.visit('perfil/perfil');
        cy.get('.sin-servicio').should('contain', 'El sistema no está disponible');
        cy.contains('.sin-servicio button', 'Reintentar').should('be.visible');
        cy.location('pathname').should('eq', '/public/perfil/perfil');
    });

    it('Ingreso desde un torneo: vuelve al torneo después de ingresar', () => {
        cy.idTorneo('abierto').then((id) => cy.visit(`torneos/torneo?id=${id}`));
        cy.contains('a', 'Ingresá para inscribirte').click();
        cy.get('#alertaAcceso').should('contain', 'Ingresá para continuar');
        cy.get('#correo').type('jugador_e2e5@e2e.uy');
        cy.get('#contrasena').type(Cypress.expose('clave'));
        cy.contains('button', 'Iniciar sesión').click();
        cy.location('pathname').should('eq', '/public/torneos/torneo');
        cy.get('#botonInscribirme').should('be.visible');
    });

    it('"Recordarme" mantiene la sesión al cerrar el navegador; sin marcarlo, no', () => {
        const ingresar = (recordar) => {
            cy.visit('home/login');
            cy.get('#correo').type('jugador_e2e2@e2e.uy');
            cy.get('#contrasena').type(Cypress.expose('clave'));
            if (recordar) cy.get('#recordar').check();
            cy.contains('button', 'Iniciar sesión').click();
            cy.get('#botonUsuario').should('contain', 'jugador_e2e2');
        };

        ingresar(true);
        cy.getCookie('nl_recordarme').should('exist');
        cy.clearCookie('NLSESION'); // como cerrar el navegador: se pierde la cookie de sesión
        cy.visit('perfil/perfil');
        cy.get('#nombrePerfil').should('contain', 'jugador_e2e2');

        cy.clearCookies();
        ingresar(false);
        cy.getCookie('nl_recordarme').should('not.exist');
        cy.clearCookie('NLSESION');
        cy.visit('perfil/perfil');
        cy.location('pathname').should('eq', '/public/home/login');
    });

    it('Cerrar sesión desde el menú del usuario', () => {
        cy.ingresarComo('jugador_e2e3');
        cy.visit('home/');
        cy.get('#botonUsuario').click();
        cy.contains('#listaUsuario button', 'Cerrar sesión').click();
        cy.contains('#authArea a', 'Ingresar').should('be.visible');
        cy.request('api/auth/sesion').its('body.logueado').should('eq', false);
    });

    it('Recuperar la contraseña con el enlace (en desarrollo se muestra en pantalla)', () => {
        const nueva = 'Nueva#Clave2026';
        cy.visit('home/recuperar');
        cy.get('#correo').type('jugador_e2e4@e2e.uy');
        cy.contains('button', 'Enviar').click();
        cy.get('#alertaAcceso').should('contain', 'Si el correo está registrado');
        cy.contains('#avisoDemo a', 'restablecer').invoke('attr', 'href').then((enlace) => {
            expect(enlace).to.match(/restablecer\?token=/);
            cy.visit(enlace.replace(/^https?:\/\/[^/]+\/public\//, ''));
        });
        cy.get('#contrasena').type(nueva);
        cy.get('#contrasena_repetida').type(nueva);
        cy.contains('button', 'Cambiar').click();

        cy.location('search').should('contain', 'restablecida=1');
        cy.get('#alertaAcceso').should('contain', 'tu contraseña cambió');
        cy.get('#correo').type('jugador_e2e4@e2e.uy');
        cy.get('#contrasena').type(nueva);
        cy.contains('button', 'Iniciar sesión').click();
        cy.get('#botonUsuario').should('contain', 'jugador_e2e4');
    });

    it('El administrador inicial tiene que cambiar la contraseña antes de usar el sitio', () => {
        // Usuario de 02_datos_base.sql, con la contraseña publicada en el script
        cy.visit('home/login');
        cy.get('#correo').type('admin@nextleague.com');
        cy.get('#contrasena').type('Admin123!');
        cy.contains('button', 'Iniciar sesión').click();
        // La página después cambia su dirección a #cuenta: se revisa el aviso, no la dirección
        cy.location('pathname').should('eq', '/public/perfil/perfil');
        cy.get('#alertaPerfil').should('contain', 'tenés que elegir una contraseña nueva');
        cy.get('#formClave').should('be.visible');
        cy.get('#pestanaTorneos').should('not.be.visible');

        // Si intenta ir a otra página, vuelve al perfil
        cy.visit('torneos/explorar');
        cy.location('pathname').should('eq', '/public/perfil/perfil');
        // La API tampoco deja hacer otra cosa
        cy.api('GET', '/admin/usuarios').its('status').should('eq', 403);

        cy.get('#contrasena_actual').type('Admin123!');
        cy.get('#contrasena_nueva').type('Admin#Nueva2026');
        cy.get('#contrasena_repetida').type('Admin#Nueva2026');
        cy.get('#formClave').submit();
        cy.get('#alertaPerfil').should('contain', 'ontraseña');
        cy.visit('admin/admin');
        cy.get('#tituloResumen').should('be.visible');
    });

    it('Bloqueo temporal después de 5 intentos fallidos, avisando antes', () => {
        cy.intercept('POST', '**/api/auth/login').as('login');
        cy.visit('home/login');
        cy.get('#correo').type('jugador_e2e6@e2e.uy');
        const intentar = (clave, estado, texto) => {
            cy.get('#contrasena').type(clave);
            cy.contains('button', 'Iniciar sesión').click();
            cy.wait('@login').its('response.statusCode').should('eq', estado);
            cy.get('#alertaAcceso').should('contain', texto);
        };
        for (let i = 1; i <= 3; i++) intentar(`Equivocada#${i}`, 401, 'incorrectos');
        // El 4.º avisa que queda uno; el 5.º es el que bloquea y ya lo dice
        intentar('Equivocada#4', 401, 'Te queda 1 intento');
        intentar('Equivocada#5', 429, 'Demasiados intentos');
        cy.get('#alertaAcceso .alerta-aviso').should('be.visible');
        // Ni con la contraseña correcta: hay que esperar
        intentar(Cypress.expose('clave'), 429, 'Demasiados intentos');
    });
});
