// =====================================================================
// 13. EL SISTEMA SEGÚN EL MOMENTO: qué pasa antes, durante y después de
//     un torneo, cómo se ordenan y se muestran los partidos según su
//     fecha, y todo lo que vence (enlace de recuperación, bloqueo por
//     intentos fallidos, sesión inactiva y "Recordarme").
//     El paso del tiempo se simula atrasando las fechas guardadas en la
//     base de pruebas (cy.simularTiempo, ver tests/e2e/simular_tiempo.php).
//     Reglas (decididas con el grupo):
//       - se puede anotar hasta el día de inicio inclusive; después, si no
//         se generó el calendario, la inscripción se cierra sola;
//       - los partidos se programan dentro de las fechas del torneo;
//       - un partido que todavía no se jugó (según su fecha) no tiene resultado;
//       - "próximos" son los partidos de hoy en adelante;
//       - al terminar el torneo las rondas se cierran y los resultados quedan firmes.
//     Las pruebas 5.2 a 5.9 siguen la vida de un mismo torneo.
// =====================================================================

import { crearTorneoPorApi, fecha, fechaHora, inscribirPorApi, partidosDeRonda, registrarPorApi } from '../soporte/ayudas';

let idTorneo = null;     // "Liga del Tiempo" (se crea en la 5.2)
let partidoSalaA = null; // ronda 1, programado en "Sala A" (5.4)
let partidoSalaB = null; // ronda 1, programado en "Sala B" (5.4)

// "AAAA-MM-DD HH:MM:SS" (hora de Uruguay, igual que el navegador de las pruebas) a milisegundos
const momento = (fechaHoraTexto) => new Date(String(fechaHoraTexto).replace(' ', 'T')).getTime();
const vencidos = (partidos) => partidos.filter((p) => p.fecha_hora && momento(p.fecha_hora) < new Date().setHours(0, 0, 0, 0))
    .map((p) => `${p.torneo}: ${p.nombre_local} vs ${p.nombre_visitante} (${p.fecha_hora})`);

// Carga un resultado en todos los partidos pendientes del torneo (gana el local)
function jugarLoQueFalta(id) {
    cy.api('GET', `/torneos/${id}/calendario`).its('body.rondas').then((rondas) => {
        rondas.flatMap((r) => r.partidos)
            .filter((p) => p.estado === 'pendiente' && p.id_local && p.id_visitante && !Number(p.es_pase_libre))
            .forEach((p) => cy.api('POST', `/enfrentamientos/${p.id_enfrentamiento}/resultado`, { puntaje_local: 2, puntaje_visitante: 1 })
                .its('status').should('eq', 200));
    });
}

describe('Flujo 5: el sistema según el momento', () => {
    before(() => cy.prepararBase());

    it('5.1 Fechas del torneo: puede empezar hoy, no ayer, no termina antes de empezar, y al editarlo tampoco vuelve al pasado', () => {
        cy.ingresarComo('orga_e2e');
        cy.paso('En el formulario, la fecha de inicio no deja elegir días anteriores a hoy');
        cy.visit('createtournament/createtournament');
        cy.get('#fecha_inicio').should('have.attr', 'min', fecha(0));

        cy.paso('Un torneo que empieza hoy se crea');
        crearTorneoPorApi({ nombre: 'Empieza Hoy E2E', fecha_inicio: fecha(0), fecha_fin: fecha(10) });

        cy.paso('Uno que empezaba ayer se rechaza');
        const datos = { id_juego: 28, modulo: 'liga', modalidad: 'individual', cupo: 4 };
        cy.api('POST', '/torneos', { ...datos, nombre: 'Empezaba Ayer E2E', fecha_inicio: fecha(-1), fecha_fin: fecha(10) }).then((r) => {
            expect(r.status).to.eq(422);
            expect(r.body.errores.fecha_inicio).to.contain('anterior a hoy');
        });

        cy.paso('Uno que termina antes de empezar también se rechaza');
        cy.api('POST', '/torneos', { ...datos, nombre: 'Al Revés E2E', fecha_inicio: fecha(10), fecha_fin: fecha(5) }).then((r) => {
            expect(r.status).to.eq(422);
            expect(r.body.errores.fecha_fin).to.contain('anterior a la de inicio');
        });

        cy.paso('Al editar uno que no empezó, tampoco se puede mover su inicio a hace 5 días');
        crearTorneoPorApi({ nombre: 'Editable E2E' }).then((id) => cy.api('POST', `/torneos/${id}`, { fecha_inicio: fecha(-5), fecha_fin: fecha(10) }))
            .then((r) => {
                cy.paso(`La API responde ${r.status}: "${r.body.mensaje}"`);
                expect(r.status).to.eq(422);
                expect(r.body.errores.fecha_inicio).to.contain('anterior a hoy');
            });
    });

    it('5.2 Faltan 7 días para el inicio: hay inscripción, se puede dar de baja y se puede editar todo', () => {
        cy.paso('orga_e2e crea "Liga del Tiempo": 6 lugares, empieza en 7 días y termina en 30');
        cy.ingresarComo('orga_e2e');
        crearTorneoPorApi({ nombre: 'Liga del Tiempo', cupo: 6, fecha_inicio: fecha(7), fecha_fin: fecha(30) }).then((id) => { idTorneo = id; });

        cy.paso('Se anotan 4 jugadores; uno se da de baja y se vuelve a anotar');
        cy.then(() => inscribirPorApi(idTorneo, ['jugador_e2e1', 'jugador_e2e2', 'jugador_e2e3', 'jugador_e2e4']));
        cy.ingresarComo('jugador_e2e3');
        cy.then(() => cy.api('DELETE', `/torneos/${idTorneo}/inscripcion`)).its('status').should('eq', 200);
        cy.then(() => cy.api('POST', `/torneos/${idTorneo}/inscripcion`)).its('status').should('eq', 201);

        cy.paso('El organizador todavía puede cambiar el cupo y las fechas');
        cy.clearCookies();
        cy.ingresarComo('orga_e2e');
        cy.then(() => cy.api('POST', `/torneos/${idTorneo}`, { cupo: 8, fecha_inicio: fecha(8) })).its('status').should('eq', 200);
        cy.then(() => cy.api('POST', `/torneos/${idTorneo}`, { cupo: 6, fecha_inicio: fecha(7) })).its('status').should('eq', 200);
    });

    it('5.3 Pasa la fecha de inicio sin calendario: la inscripción se cierra sola y el organizador lo ve', () => {
        cy.simularTiempo('Pasan 8 días: la fecha de inicio fue ayer y el calendario sigue sin generarse',
            { accion: 'torneo', id: idTorneo, inicio: -1, fin: 22 });

        cy.paso('El público lo ve con "Inscripciones cerradas" y sin botón para anotarse');
        // Se espera a que la página termine de pedir sus datos: si una petición
        // suya llegara después del ingreso de abajo, cambiaría la sesión.
        cy.intercept('GET', '**/api/torneos/*/participantes').as('participantes');
        cy.visit(`torneos/torneo?id=${idTorneo}`);
        cy.wait('@participantes');
        cy.get('#torneoChips').should('contain', 'Inscripciones cerradas').and('not.contain', 'Inscripciones abiertas');
        cy.get('#inscripcionContenido').should('contain', 'Las inscripciones se cerraron');

        cy.paso('jugador_e2e5 intenta anotarse igual: se rechaza');
        cy.clearCookies();
        cy.ingresarComo('jugador_e2e5');
        cy.api('POST', `/torneos/${idTorneo}/inscripcion`).then((r) => {
            cy.paso(`La API responde ${r.status}: "${r.body.mensaje}"`);
            expect(r.status).to.eq(409);
            expect(r.body.mensaje).to.contain('se cerraron');
        });

        cy.paso('En "Gestionar", el organizador ve el aviso y no puede inscribir a nadie más');
        cy.clearCookies();
        cy.ingresarComo('orga_e2e');
        cy.visit(`torneos/gestionar?id=${idTorneo}`);
        cy.get('#textoProximoPaso').should('contain', 'las inscripciones se cerraron solas');
        cy.get('#formInscribir').should('not.be.visible');
        cy.api('POST', `/torneos/${idTorneo}/participantes`, { nombre_usuario: 'jugador_e2e5' }).its('status').should('eq', 409);

        cy.paso('Si corre la fecha de inicio a dentro de 2 días, la inscripción se vuelve a abrir');
        cy.api('POST', `/torneos/${idTorneo}`, { fecha_inicio: fecha(2) }).its('status').should('eq', 200);
        cy.clearCookies();
        cy.ingresarComo('jugador_e2e5');
        cy.api('GET', `/torneos/${idTorneo}`).its('body.torneo.permisos.inscribirse').should('eq', true);
    });

    it('5.4 Generado el calendario, los próximos partidos se ordenan por fecha y no se cargan antes de jugarse', () => {
        cy.paso('El organizador genera el calendario (4 jugadores) y publica las 3 rondas: el torneo pasa a "En curso"');
        cy.ingresarComo('orga_e2e');
        cy.api('POST', `/torneos/${idTorneo}/calendario`).its('status').should('eq', 201);
        cy.api('POST', `/torneos/${idTorneo}/rondas/publicar`).its('status').should('eq', 200);
        cy.api('GET', `/torneos/${idTorneo}`).its('body.torneo.estado').should('eq', 'en_curso');

        cy.paso('Programa los dos partidos de la ronda 1: uno en 4 días a las 18:00 ("Sala A") y otro en 3 días a las 21:00 ("Sala B")');
        partidosDeRonda(idTorneo, 1).then(([a, b]) => {
            partidoSalaA = a;
            partidoSalaB = b;
            cy.api('PUT', `/enfrentamientos/${a.id_enfrentamiento}`, { fecha_hora: fechaHora(4, '18:00'), lugar: 'Sala A' }).its('status').should('eq', 200);
            cy.api('PUT', `/enfrentamientos/${b.id_enfrentamiento}`, { fecha_hora: fechaHora(3, '21:00'), lugar: 'Sala B' }).its('status').should('eq', 200);
        });

        cy.paso('Su resultado todavía no se puede cargar: no se jugaron');
        cy.then(() => cy.api('POST', `/enfrentamientos/${partidoSalaB.id_enfrentamiento}/resultado`, { puntaje_local: 1, puntaje_visitante: 0 }))
            .then((r) => {
                expect(r.status).to.eq(409);
                expect(r.body.mensaje).to.contain('cambiá primero la fecha');
            });

        cy.paso('En la lista pública de próximos partidos, primero va el de "Sala B", después el de "Sala A", y los que no tienen fecha van al final');
        cy.clearCookies();
        cy.api('GET', '/partidos/proximos?por_pagina=50').its('body.partidos').then((partidos) => {
            expect(partidos.slice(0, 2).map((p) => p.lugar), 'los dos primeros').to.deep.eq(['Sala B', 'Sala A']);
            const conFecha = partidos.map((p) => Boolean(p.fecha_hora));
            expect(conFecha.lastIndexOf(true), 'los que tienen fecha van antes que los que no').to.be.lessThan(conFecha.indexOf(false));
        });

        cy.paso('En la portada también aparece primero el de "Sala B"');
        cy.visit('home/');
        cy.get('#proximosPartidos').should('contain', 'Sala B').invoke('text').then((texto) => {
            expect(texto.indexOf('Sala B'), 'Sala B antes que Sala A').to.be.lessThan(texto.indexOf('Sala A'));
        });
    });

    it('5.5 Un partido cuya fecha ya pasó y no tiene resultado no figura entre los "próximos"; el organizador lo ve atrasado', () => {
        cy.simularTiempo('Pasan 5 días: los partidos de "Sala B" y "Sala A" fueron hace 2 días y ayer, y nadie cargó los resultados',
            { accion: 'pasan_dias', id: idTorneo, dias: 5 });

        const encontrados = {};
        cy.paso('Se revisa la lista pública de próximos partidos');
        cy.api('GET', '/partidos/proximos?por_pagina=50').its('body.partidos').then((partidos) => { encontrados.publico = vencidos(partidos); });

        cy.paso(`Se revisan los próximos partidos en el perfil de ${partidoSalaB.nombre_local}, que jugaba en "Sala B"`);
        cy.then(() => cy.ingresarComo(partidoSalaB.nombre_local));
        cy.api('GET', '/perfil/partidos').its('body.partidos').then((partidos) => { encontrados.perfil = vencidos(partidos); });

        cy.then(() => cy.paso(`Partidos con la fecha ya pasada que figuran como próximos: ${encontrados.publico.length} en la lista pública y ${encontrados.perfil.length} en el perfil`));
        cy.then(() => {
            expect([...encontrados.publico, ...encontrados.perfil].join(' | '), 'partidos vencidos que se muestran como próximos').to.eq('');
        });

        cy.paso('En "Gestionar", el organizador ve que hay 2 partidos atrasados');
        cy.clearCookies();
        cy.ingresarComo('orga_e2e');
        cy.visit(`torneos/gestionar?id=${idTorneo}`);
        cy.get('#textoProximoPaso').should('contain', 'Hay 2 partidos con la fecha vencida y sin resultado');
    });

    it('5.6 No se puede programar un partido fuera de las fechas del torneo', () => {
        const respuestas = {};
        cy.paso('El torneo va de hace 3 días a dentro de 17');
        cy.ingresarComo('orga_e2e');
        partidosDeRonda(idTorneo, 2).then(([partido]) => {
            const programar = (dias, lugar) => cy.api('PUT', `/enfrentamientos/${partido.id_enfrentamiento}`, { fecha_hora: fechaHora(dias), lugar });

            cy.paso('Intenta programar un partido de la ronda 2 para dentro de 40 días (después del fin)');
            programar(40, 'Muy tarde').then((r) => {
                respuestas.despuesDelFin = r.status;
                cy.paso(`La API responde ${r.status}: "${r.body.mensaje}"`);
            });

            cy.paso('Intenta programarlo para hace 5 días (antes del inicio)');
            programar(-5, 'Muy temprano').then((r) => {
                respuestas.antesDelInicio = r.status;
                cy.paso(`La API responde ${r.status}: "${r.body.mensaje}"`);
            });

            cy.paso('Lo programa dentro de las fechas del torneo (en 5 días, en "Sala C"): se acepta');
            programar(5, 'Sala C').its('status').should('eq', 200);
        });
        cy.then(() => {
            expect(`después del fin: ${respuestas.despuesDelFin}, antes del inicio: ${respuestas.antesDelInicio}`,
                'respuesta al programar fuera de las fechas del torneo (422 = rechazado)')
                .to.eq('después del fin: 422, antes del inicio: 422');
        });
    });

    it('5.7 Un resultado se carga recién cuando el partido se jugó', () => {
        cy.ingresarComo('orga_e2e');
        cy.paso('El partido de "Sala C" es en 5 días: su resultado se rechaza, con la indicación de cambiar la fecha si ya se jugó');
        partidosDeRonda(idTorneo, 2).then(([partido]) => cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`,
            { puntaje_local: 1, puntaje_visitante: 0 })).then((r) => {
            cy.paso(`La API responde ${r.status}: "${r.body.mensaje}"`);
            expect(r.status).to.eq(409);
            expect(r.body.mensaje).to.contain('Si ya se jugó, cambiá primero la fecha');
        });

        cy.paso('El de "Sala A" fue ayer: su resultado se carga');
        cy.then(() => cy.api('POST', `/enfrentamientos/${partidoSalaA.id_enfrentamiento}/resultado`, { puntaje_local: 1, puntaje_visitante: 0 }))
            .its('status').should('eq', 200);
    });

    it('5.8 Pasa la fecha de fin con partidos sin jugar: el organizador lo ve, y el torneo termina con el último resultado', () => {
        cy.simularTiempo('Pasan 18 días: la fecha de fin fue ayer y todavía faltan partidos',
            { accion: 'pasan_dias', id: idTorneo, dias: 18 });

        cy.paso('El torneo sigue "En curso"');
        cy.api('GET', `/torneos/${idTorneo}`).its('body.torneo.estado').should('eq', 'en_curso');
        cy.visit(`torneos/torneo?id=${idTorneo}`);
        cy.get('#torneoChips').should('contain', 'En curso');

        cy.paso('En "Gestionar", el organizador ve que la fecha de fin ya pasó y que faltan partidos');
        cy.ingresarComo('orga_e2e');
        cy.visit(`torneos/gestionar?id=${idTorneo}`);
        cy.get('#textoProximoPaso').should('contain', 'todavía faltan partidos');

        cy.paso('Carga todos los resultados que faltan: con el último, el torneo pasa a "Finalizado"');
        jugarLoQueFalta(idTorneo);
        cy.api('GET', `/torneos/${idTorneo}`).its('body.torneo.estado').should('eq', 'finalizado');
    });

    it('5.9 Con el torneo terminado, los resultados quedan firmes', () => {
        cy.paso('Al terminar, todas las rondas quedaron cerradas');
        cy.ingresarComo('orga_e2e');
        cy.api('GET', `/torneos/${idTorneo}/calendario`).its('body.rondas')
            .then((rondas) => expect(rondas.map((r) => r.estado)).to.deep.eq(['cerrada', 'cerrada', 'cerrada']));

        cy.paso('El organizador intenta invertir un resultado de la ronda 3: se rechaza porque la ronda está cerrada');
        cy.api('GET', `/torneos/${idTorneo}/posiciones`).its('body.posiciones.0.nombre').as('lider');
        partidosDeRonda(idTorneo, 3).then(([partido]) => {
            cy.wrap(partido).as('partidoRonda3');
            cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`,
                { puntaje_local: Number(partido.puntaje_visitante), puntaje_visitante: Number(partido.puntaje_local) + 3 }).then((r) => {
                cy.paso(`La API responde ${r.status}: "${r.body.mensaje}"`);
                expect(r.status).to.eq(409);
                expect(r.body.mensaje).to.contain('cerrada');
            });
        });

        cy.paso('El administrador sí puede reabrir la ronda y corregir (letra 5.1): el mensaje no repite que el torneo terminó');
        cy.clearCookies();
        cy.ingresarComo('admin_e2e');
        cy.get('@partidoRonda3').then((partido) => {
            cy.api('PUT', `/rondas/${partido.id_ronda}`, { estado: 'publicada' }).its('status').should('eq', 200);
            cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`,
                { puntaje_local: Number(partido.puntaje_visitante), puntaje_visitante: Number(partido.puntaje_local) + 3, observacion: 'Revisión del administrador' })
                .then((r) => {
                    cy.paso(`La API responde ${r.status}: "${r.body.mensaje}"`);
                    expect(r.body.mensaje).to.eq('Resultado corregido.');
                });
        });
        cy.api('GET', `/torneos/${idTorneo}`).its('body.torneo.estado').should('eq', 'finalizado');
        cy.get('@lider').then((lider) => cy.api('GET', `/torneos/${idTorneo}/posiciones`).its('body.posiciones.0.nombre')
            .then((ahora) => cy.paso(`Primero de la tabla antes: ${lider}; después de la corrección: ${ahora}`)));
    });

    it('5.10 El enlace para recuperar la contraseña vale 30 minutos y sirve una sola vez', () => {
        const correo = 'olvido_flujo@e2e.uy';
        registrarPorApi('olvido_flujo');
        const pedirEnlace = () => {
            cy.visit('home/recuperar');
            cy.get('#correo').type(correo);
            cy.contains('button', 'Enviar').click();
            return cy.contains('#avisoDemo a', 'restablecer').invoke('attr', 'href')
                .then((enlace) => enlace.replace(/^https?:\/\/[^/]+\/public\//, ''));
        };
        const usar = (enlace, clave) => {
            cy.visit(enlace);
            cy.get('#contrasena').type(clave);
            cy.get('#contrasena_repetida').type(clave);
            cy.contains('button', 'Cambiar').click();
        };

        cy.paso('Pide el enlace para recuperar la contraseña');
        pedirEnlace().then((enlace) => {
            cy.simularTiempo('Pasan 31 minutos sin usarlo', { accion: 'token_recuperacion', correo, minutos: 31 });
            cy.paso('Lo usa: se rechaza porque venció');
            usar(enlace, 'Nueva#Clave2026');
            cy.get('#alertaAcceso').should('contain', 'venció o ya se usó');
        });

        cy.paso('Pide otro enlace');
        pedirEnlace().then((enlace) => {
            cy.simularTiempo('Pasan 29 minutos', { accion: 'token_recuperacion', correo, minutos: 29 });
            cy.paso('Lo usa: todavía vale y la contraseña cambia');
            usar(enlace, 'Nueva#Clave2026');
            cy.location('search').should('contain', 'restablecida=1');
            cy.paso('Lo vuelve a usar: ya no sirve');
            usar(enlace, 'Otra#Clave2026');
            cy.get('#alertaAcceso').should('contain', 'venció o ya se usó');
        });
    });

    it('5.11 El bloqueo por intentos fallidos dura 15 minutos', () => {
        const correo = 'bloqueo_flujo@e2e.uy';
        registrarPorApi('bloqueo_flujo');
        const ingresar = (clave) => cy.api('POST', '/auth/login', { correo, contrasena: clave });

        cy.paso('Se equivoca 5 veces seguidas: ni con la contraseña correcta puede entrar');
        for (let i = 1; i <= 5; i++) ingresar(`Equivocada#${i}`);
        ingresar(Cypress.expose('clave')).its('status').should('eq', 429);

        cy.simularTiempo('Pasan 14 minutos', { accion: 'intentos_login', correo, minutos: 14 });
        cy.paso('Sigue bloqueado y el aviso dice que falta 1 minuto');
        ingresar(Cypress.expose('clave')).then((r) => {
            expect(r.status).to.eq(429);
            expect(r.body.mensaje).to.contain('1 minuto');
        });

        cy.simularTiempo('Pasan 2 minutos más (16 en total)', { accion: 'intentos_login', correo, minutos: 2 });
        cy.paso('Ya puede entrar con su contraseña');
        ingresar(Cypress.expose('clave')).its('status').should('eq', 200);
    });

    it('5.12 La sesión se cierra sola después de 2 horas sin actividad', () => {
        cy.paso('jugador_e2e5 inicia sesión');
        cy.ingresarComo('jugador_e2e5');
        cy.getCookie('NLSESION').its('value').then((idSesion) => {
            cy.simularTiempo('Pasan 119 minutos sin actividad', { accion: 'sesion', id: idSesion, minutos: 119 });
            cy.paso('Sigue con la sesión iniciada (y el tiempo vuelve a contar desde ahora)');
            cy.request('api/auth/sesion').its('body.logueado').should('eq', true);

            cy.simularTiempo('Pasan 121 minutos sin actividad', { accion: 'sesion', id: idSesion, minutos: 121 });
            cy.paso('La sesión venció: la API ya no lo reconoce');
            cy.request('api/auth/sesion').its('body.logueado').should('eq', false);
        });
        cy.paso('Si entra a su perfil, lo manda a ingresar');
        cy.visit('perfil/perfil');
        cy.location('pathname').should('eq', '/public/home/login');
    });

    it('5.13 "Recordarme" mantiene la sesión hasta 30 días', () => {
        const correo = 'jugador_e2e6@e2e.uy';
        cy.paso('Ingresa marcando "Recordarme"');
        cy.visit('home/login');
        cy.get('#correo').type(correo);
        cy.get('#contrasena').type(Cypress.expose('clave'));
        cy.get('#recordar').check();
        cy.contains('button', 'Iniciar sesión').click();
        cy.get('#botonUsuario').should('contain', 'jugador_e2e6');

        cy.simularTiempo('Pasan 29 días y cierra el navegador', { accion: 'recordarme', correo, dias: 29 });
        cy.clearCookie('NLSESION');
        cy.paso('Vuelve a entrar: sigue con la sesión iniciada (y el "Recordarme" se renueva)');
        cy.visit('perfil/perfil');
        cy.get('#nombrePerfil').should('contain', 'jugador_e2e6');

        cy.simularTiempo('Pasan 31 días sin entrar y cierra el navegador', { accion: 'recordarme', correo, dias: 31 });
        cy.clearCookie('NLSESION');
        cy.paso('Vuelve a entrar: el "Recordarme" venció y tiene que ingresar de nuevo');
        cy.visit('perfil/perfil');
        cy.location('pathname').should('eq', '/public/home/login');
    });

    it('5.14 El resumen del administrador cuenta solo los partidos de los próximos 7 días', () => {
        let antes = 0;
        cy.idTorneo('liga_grande').then((id) => cy.simularTiempo('Pasan 7 días: empieza la "Liga E2E Grande"', { accion: 'pasan_dias', id, dias: 7 }));
        cy.ingresarComo('admin_e2e');
        cy.api('GET', '/admin/resumen').its('body.resumen.partidos_esta_semana').then((cantidad) => {
            antes = cantidad;
            cy.paso(`El resumen dice que hay ${cantidad} partido(s) en los próximos 7 días`);
        });

        cy.paso('El organizador programa un partido para dentro de 2 días y otro para dentro de 9');
        cy.clearCookies();
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('liga_grande').then((id) => partidosDeRonda(id, 1)).then(([a, b]) => {
            cy.api('PUT', `/enfrentamientos/${a.id_enfrentamiento}`, { fecha_hora: fechaHora(2) }).its('status').should('eq', 200);
            cy.api('PUT', `/enfrentamientos/${b.id_enfrentamiento}`, { fecha_hora: fechaHora(9) }).its('status').should('eq', 200);
        });

        cy.paso('El resumen suma solo el de dentro de 2 días');
        cy.clearCookies();
        cy.ingresarComo('admin_e2e');
        cy.api('GET', '/admin/resumen').its('body.resumen.partidos_esta_semana').then((cantidad) => expect(cantidad).to.eq(antes + 1));
    });
});
