// =====================================================================
// 11. FLUJO DEL ORGANIZADOR: desde que el administrador lo da de alta
//     hasta que su torneo tiene campeón. Recorre lo que la letra le pide
//     al organizador (punto 5.2): configurar torneos asignados, inscribir
//     participantes, generar rondas o llaves, publicar y cerrar rondas,
//     cargar resultados, corregirlos si cuenta con autorización y
//     consultar el reporte. Después, los otros formatos con 5
//     participantes (pases libres) y la cancelación.
//     Las pruebas 3.1 a 3.13 van en orden sobre la misma liga.
// =====================================================================

import { crearTorneoPorApi, fecha, fechaHora, inscribirPorApi, jugarRondaPorApi, partidosDeRonda } from '../soporte/ayudas';

const ORGA = 'orga_flujo';
const CORREO_ORGA = `${ORGA}@e2e.uy`;
const TEMPORAL = 'Temporal#2026';
const LIGA = 'Liga Flujo de Ajedrez';
let idLiga = null; // se completa en la prueba 3.4

// Id de un usuario (como administrador)
const idDe = (nombre) => cy.api('GET', `/admin/usuarios?buscar=${nombre}`).its('body.usuarios.0.id_usuario');

// Abre "Gestionar" de la liga y elige una ronda (1, 2, 3)
function gestionarRonda(numero) {
    cy.visit(`torneos/gestionar?id=${idLiga}`);
    cy.get('#selectorRondas [data-ronda]').eq(numero - 1).click();
}

// Completa los marcadores de las filas indicadas del editor de la ronda y guarda
function cargarMarcadores(filas, local, visitante) {
    cy.get(`#editorRonda ${filas}`).each(($fila) => {
        cy.wrap($fila).find('[name="local"]').clear().type(String(local));
        cy.wrap($fila).find('[name="visitante"]').clear().type(String(visitante));
    });
    cy.get('#formRonda').contains('button', 'Guardar cambios').click();
}

describe('Flujo 3: del alta del organizador al campeón', () => {
    before(() => cy.prepararBase());

    it('3.1 El administrador da de alta al organizador con una contraseña temporal', () => {
        cy.paso('El administrador abre "Usuarios" en el panel y elige "Nuevo usuario"');
        cy.ingresarComo('admin_e2e');
        cy.visit('admin/admin#usuarios');
        cy.get('#botonNuevoUsuario').click();
        cy.get('#modalUsuario').should('be.visible');

        cy.paso(`Carga a ${ORGA} con rol "Organizador" y una contraseña temporal`);
        cy.get('#usuario_nombre').type(ORGA);
        cy.get('#usuario_correo').type(CORREO_ORGA);
        cy.get('#usuario_clave').type(TEMPORAL);
        cy.get('#usuario_rol').select('organizador');
        cy.get('#guardarUsuario').click();
        cy.get('#modalUsuario').should('not.be.visible');

        cy.paso('La tabla lo muestra como organizador y con "Clave temporal"');
        cy.get(`#tablaUsuarios select[aria-label="Rol de ${ORGA}"]`).should('have.value', 'organizador');
        cy.contains('#tablaUsuarios tbody tr', ORGA).should('contain', 'Clave temporal');
    });

    it('3.2 En su primer ingreso tiene que cambiar la contraseña; después ya puede crear torneos', () => {
        cy.paso('Ingresa con la contraseña temporal: lo lleva a cambiarla');
        cy.visit('home/login');
        cy.get('#correo').type(CORREO_ORGA);
        cy.get('#contrasena').type(TEMPORAL);
        cy.contains('button', 'Iniciar sesión').click();
        // La página después cambia su dirección a #cuenta: se revisa el aviso, no la dirección
        cy.location('pathname').should('eq', '/public/perfil/perfil');
        cy.get('#alertaPerfil').should('contain', 'tenés que elegir una contraseña nueva');

        cy.paso('Si intenta ir a "Crear torneo" antes de cambiarla, vuelve al perfil');
        cy.visit('createtournament/createtournament');
        cy.location('pathname').should('eq', '/public/perfil/perfil');

        cy.paso('Elige su contraseña definitiva');
        cy.get('#contrasena_actual').type(TEMPORAL);
        cy.get('#contrasena_nueva').type(Cypress.expose('clave'));
        cy.get('#contrasena_repetida').type(Cypress.expose('clave'));
        cy.get('#formClave').submit();
        cy.get('#alertaPerfil').should('contain', 'ontraseña');

        cy.paso('Ahora su menú dice "Organizador" y ofrece "Crear torneo"');
        cy.visit('home/');
        cy.get('#botonUsuario').click();
        cy.get('#listaUsuario').should('contain', 'Organizador').and('contain', 'Crear torneo');
    });

    it('3.3 El administrador le asigna un torneo para organizar (letra 5.1 y 5.2: "configurar torneos asignados")', () => {
        cy.paso('El administrador abre "Crear torneo": además de los datos, elige quién lo organiza');
        cy.ingresarComo('admin_e2e');
        cy.visit('createtournament/createtournament');
        cy.get('#botonGuardar').should('not.be.disabled');
        cy.get('#campoOrganizador').should('be.visible');
        cy.get('#nombre').type('Copa Asignada E2E');
        cy.get('#id_juego').select('Ajedrez');
        cy.contains('.opcion--formato', 'Liga').click();
        cy.get('#fecha_fin').clear().type(fecha(30));
        cy.get('#id_organizador').select(ORGA);
        cy.get('#botonGuardar').click();
        cy.get('#alertaGestion').should('contain', '¡Torneo creado!');
        cy.location('search').then((busqueda) => {
            const idTorneo = Number(new URLSearchParams(busqueda).get('id'));
            cy.paso(`El torneo queda a cargo de ${ORGA}, no del administrador que lo creó, y la asignación queda en la auditoría`);
            cy.api('GET', `/torneos/${idTorneo}`).its('body.torneo.organizador')
                .then((organizador) => expect(organizador, 'organizador a cargo del torneo asignado').to.eq(ORGA));
            cy.api('GET', '/admin/auditoria?accion=asignar_organizador').its('body.registros.0.detalle').should('contain', ORGA);

            cy.paso(`${ORGA} lo ve entre sus torneos y lo puede gestionar`);
            cy.clearCookies();
            cy.ingresarComo(ORGA);
            cy.api('GET', '/perfil/torneos?tipo=creados').its('body.torneos')
                .then((torneos) => expect(torneos.map((x) => x.nombre)).to.include('Copa Asignada E2E'));
            cy.visit(`torneos/gestionar?id=${idTorneo}`);
            cy.get('#nombreTorneo').should('have.text', 'Copa Asignada E2E');

            cy.paso('Al editarlo, el organizador no tiene la opción de cambiar quién está a cargo');
            cy.visit(`createtournament/createtournament?id=${idTorneo}`);
            cy.get('#botonGuardar').should('not.be.disabled');
            cy.get('#campoOrganizador').should('not.be.visible');
        });
    });

    it('3.4 Crea su liga (primero oculta) y la publica', () => {
        cy.paso('Completa "Crear torneo": liga de ajedrez, 4 lugares, empieza en 3 días, sin publicar todavía');
        cy.ingresarComo(ORGA);
        cy.visit('createtournament/createtournament');
        cy.get('#id_juego option').should('have.length.greaterThan', 10);
        cy.get('#nombre').type(LIGA);
        cy.get('#id_juego').select('Ajedrez');
        cy.contains('.opcion--formato', 'Liga').click();
        cy.get('#cupo').clear().type('4');
        cy.get('#fecha_inicio').clear().type(fecha(3));
        cy.get('#fecha_fin').clear().type(fecha(30));
        cy.paso('No elige quién lo organiza ni se autoriza a corregir resultados: eso lo decide el administrador');
        cy.get('#campoOrganizador').should('not.be.visible');
        cy.get('#permitir_correcciones').should('be.disabled').and('not.be.checked');
        cy.get('#ayudaCorrecciones').should('contain', 'lo autoriza el administrador');
        cy.get('#publicado').uncheck({ force: true });
        cy.get('#botonGuardar').click();
        cy.location('pathname').should('eq', '/public/torneos/gestionar');
        cy.get('#alertaGestion').should('contain', '¡Torneo creado!');
        cy.location('search').then((busqueda) => { idLiga = Number(new URLSearchParams(busqueda).get('id')); });

        cy.paso('Mientras está oculta, el público no la encuentra');
        cy.clearCookies();
        cy.api('GET', '/torneos?buscar=Liga%20Flujo').its('body.paginacion.total').should('eq', 0);

        cy.paso('La publica desde "Gestionar" y ya aparece en el listado público');
        cy.ingresarComo(ORGA);
        cy.then(() => cy.visit(`torneos/gestionar?id=${idLiga}`));
        cy.get('#interruptorPublicado').should('not.be.checked').check({ force: true });
        cy.get('#alertaGestion').should('contain', 'ahora es público');
        cy.clearCookies();
        cy.api('GET', '/torneos?buscar=Liga%20Flujo').its('body.paginacion.total').should('eq', 1);
    });

    it('3.5 Inscribe participantes; otros se anotan solos hasta completar el cupo', () => {
        cy.ingresarComo(ORGA);
        cy.visit(`torneos/gestionar?id=${idLiga}`);

        cy.paso('Intenta inscribirse a sí mismo: el organizador no puede jugar su propio torneo');
        cy.get('#inscribirUsuario').type(ORGA);
        cy.get('#formInscribir').submit();
        cy.get('#inscribirUsuarioError').should('contain', 'organizador');

        cy.paso('Inscribe a jugador_e2e1 por su nombre de usuario');
        cy.get('#inscribirUsuario').clear().type('jugador_e2e1');
        cy.get('#formInscribir').submit();
        cy.get('#listaInscriptos').should('contain', 'jugador_e2e1');
        cy.get('#cupoChip').should('have.text', '1 / 4');

        cy.paso('jugador_e2e2 y jugador_e2e3 se anotan solos');
        inscribirPorApi(idLiga, ['jugador_e2e2', 'jugador_e2e3']);

        cy.paso('Inscribe a jugador_e2e4: cupo completo y el formulario de inscripción desaparece');
        cy.ingresarComo(ORGA);
        cy.visit(`torneos/gestionar?id=${idLiga}`);
        cy.get('#cupoChip').should('have.text', '3 / 4');
        cy.get('#inscribirUsuario').type('jugador_e2e4');
        cy.get('#formInscribir').submit();
        cy.get('#cupoChip').should('have.text', '4 / 4');
        cy.get('#formInscribir').should('not.be.visible');
    });

    it('3.6 Genera el calendario: las rondas quedan en borrador y el público todavía no las ve', () => {
        cy.ingresarComo(ORGA);
        cy.visit(`torneos/gestionar?id=${idLiga}`);

        cy.paso('"Gestionar" le indica que ya puede generar el calendario; lo genera');
        cy.get('#textoProximoPaso').should('contain', 'Ya podés generar el calendario');
        cy.get('#botonGenerar').click();
        cy.confirmar('Generar calendario');
        cy.get('#alertaGestion').should('contain', 'Calendario generado');

        cy.paso('Quedan 3 rondas (todos contra todos con 4 jugadores), las 3 en borrador');
        cy.get('#selectorRondas [data-ronda]').should('have.length', 3);
        cy.get('#selectorRondas .punto--borrador').should('have.length', 3);

        cy.paso('El público todavía no ve ninguna ronda');
        cy.clearCookies();
        cy.api('GET', `/torneos/${idLiga}/calendario`).its('body.rondas').should('have.length', 0);

        cy.paso('Las inscripciones se cerraron: jugador_e2e5 ya no puede anotarse');
        cy.ingresarComo('jugador_e2e5');
        cy.api('POST', `/torneos/${idLiga}/inscripcion`).then((r) => {
            expect(r.status).to.eq(409);
            expect(r.body.mensaje).to.contain('cerradas');
        });
    });

    it('3.7 Programa fecha y lugar, publica la ronda 1 y el público la ve', () => {
        cy.ingresarComo(ORGA);
        cy.paso('En la ronda 1 programa los dos partidos (en 3 y 4 días, en "Sede Flujo")');
        gestionarRonda(1);
        cy.get('#editorRonda .editor-partido[data-id]').each(($fila, i) => {
            cy.wrap($fila).find('[name="fecha_hora"]').type(fechaHora(3 + i, '18:00'));
            cy.wrap($fila).find('[name="lugar"]').type('Sede Flujo');
        });
        cy.get('#formRonda').contains('button', 'Guardar cambios').click();
        cy.get('#alertaGestion').should('contain', 'Se guardaron 2 cambios');

        cy.paso('Publica solo la ronda 1');
        cy.get('#editorRonda [data-estado-ronda="publicada"]').click();
        cy.get('#alertaGestion').should('contain', 'Ronda publicada');

        cy.paso('Los resultados todavía no se pueden cargar: los partidos son en 3 y 4 días');
        cy.get('#editorRonda .editor-partido__nota').first().should('contain', 'Se juega el');
        cy.get('#editorRonda [name="local"]').first().should('be.disabled');

        cy.paso('El público ve la ronda 1 con su lugar, pero no las rondas 2 y 3');
        cy.clearCookies();
        cy.api('GET', `/torneos/${idLiga}/calendario`).its('body.rondas').should('have.length', 1);
        cy.visit(`torneos/torneo?id=${idLiga}#calendario`);
        cy.get('#listaRondas').should('contain', 'Sede Flujo');
    });

    it('3.8 Carga los resultados de la ronda 1, la tabla se actualiza y cierra la ronda', () => {
        cy.simularTiempo('Pasan 5 días: se juegan los dos partidos de la ronda 1', { accion: 'pasan_dias', id: idLiga, dias: 5 });
        cy.ingresarComo(ORGA);
        cy.paso('Carga los dos resultados de la ronda 1 (gana el local 2 a 1)');
        gestionarRonda(1);
        cargarMarcadores('.editor-partido[data-id]', 2, 1);
        cy.get('#alertaGestion').should('contain', 'Se guardaron 2 cambios');
        cy.get('#pasos').should('contain', '2 de 6 jugados');

        cy.paso('El público ve la tabla actualizada: dos jugadores con 3 puntos y dos con 0');
        cy.clearCookies();
        cy.visit(`torneos/torneo?id=${idLiga}#posiciones`);
        cy.get('#tablaPosiciones tbody tr').should('have.length', 4);
        cy.get('#tablaPosiciones tbody td.destacada').then(($puntos) => {
            expect([...$puntos].map((td) => td.textContent.trim())).to.deep.eq(['3', '3', '0', '0']);
        });

        cy.paso('Cierra la ronda 1: sus resultados quedan firmes');
        cy.ingresarComo(ORGA);
        gestionarRonda(1);
        cy.get('#editorRonda [data-estado-ronda="cerrada"]').click();
        cy.get('#alertaGestion').should('contain', 'Ronda cerrada');

        cy.paso('Con la ronda cerrada no se puede cargar un resultado en ella');
        partidosDeRonda(idLiga, 1).then(([partido]) =>
            cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`, { puntaje_local: 0, puntaje_visitante: 5 }))
            .then((r) => {
                expect(r.status).to.eq(409);
                expect(r.body.mensaje).to.contain('cerrada');
            });
    });

    it('3.9 Con la autorización del administrador, corrige un resultado: reabre la ronda, corrige con motivo y queda el historial', () => {
        cy.paso('El administrador autoriza al organizador a corregir resultados en esta liga (letra 5.2)');
        cy.ingresarComo('admin_e2e');
        cy.visit(`createtournament/createtournament?id=${idLiga}`);
        cy.get('#avisoBloqueo').should('contain', 'ya tiene calendario');
        cy.get('#permitir_correcciones').should('not.be.disabled').check({ force: true });
        cy.get('#botonGuardar').click();
        cy.get('#alertaGestion').should('contain', 'Cambios guardados');

        cy.clearCookies();
        cy.ingresarComo(ORGA);
        gestionarRonda(1);
        cy.paso('El organizador reabre la ronda 1 para corregir');
        cy.get('#editorRonda [data-estado-ronda="publicada"]').should('contain', 'Reabrir').click();
        cy.get('#alertaGestion').should('contain', 'Ronda publicada');

        cy.paso('Corrige el primer partido (2-1 pasa a 1-2) y escribe el motivo');
        cy.get('#editorRonda .editor-partido[data-finalizado="1"]').first().within(() => {
            cy.get('[name="local"]').clear().type('1');
            cy.get('[name="visitante"]').clear().type('2');
            cy.get('.motivo').should('be.visible').find('input').type('Planilla mal leída');
        });
        cy.get('#formRonda').contains('button', 'Guardar cambios').click();
        cy.get('#alertaGestion').should('contain', 'Se guardó 1 cambio');

        cy.paso('El historial del partido muestra el resultado anterior y el vigente, con el motivo');
        cy.get('#editorRonda .editor-partido[data-finalizado="1"]').first().contains('button', 'Historial').click();
        cy.get('#modalHistorial').should('be.visible').within(() => {
            cy.get('.historial li').should('have.length', 2);
            cy.get('.historial--vigente').should('contain', '1 - 2').and('contain', 'Planilla mal leída');
            cy.contains('li', 'Reemplazado').should('contain', '2 - 1');
            cy.contains('button', 'Cerrar').click();
        });

        cy.paso('Vuelve a cerrar la ronda 1');
        cy.get('#editorRonda [data-estado-ronda="cerrada"]').click();
        cy.get('#alertaGestion').should('contain', 'Ronda cerrada');
    });

    it('3.10 Si el administrador le quita la autorización, el organizador no puede corregir; el administrador sí', () => {
        cy.paso('El administrador le quita la autorización para corregir');
        cy.ingresarComo('admin_e2e');
        cy.api('POST', `/torneos/${idLiga}`, { permitir_correcciones: 0 }).its('status').should('eq', 200);

        cy.paso('En "Editar torneo", el organizador ve la opción sin marcar y no la puede cambiar');
        cy.clearCookies();
        cy.ingresarComo(ORGA);
        cy.visit(`createtournament/createtournament?id=${idLiga}`);
        cy.get('#avisoBloqueo').should('contain', 'ya tiene calendario');
        cy.get('#permitir_correcciones').should('be.disabled').and('not.be.checked');

        cy.paso('Publica la ronda 2 y carga el resultado de su primer partido (3 a 0)');
        gestionarRonda(2);
        cy.get('#editorRonda [data-estado-ronda="publicada"]').click();
        cy.get('#alertaGestion').should('contain', 'Ronda publicada');
        cargarMarcadores('.editor-partido[data-id]:first', 3, 0);
        cy.get('#alertaGestion').should('contain', 'Se guardó 1 cambio');

        cy.paso('Intenta corregir ese resultado: la API lo rechaza');
        partidosDeRonda(idLiga, 2).then(([partido]) => {
            cy.wrap(partido.id_enfrentamiento).as('partidoRonda2');
            cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`, { puntaje_local: 0, puntaje_visitante: 3 })
                .then((r) => {
                    expect(r.status).to.eq(403);
                    expect(r.body.mensaje).to.contain('no permite corregir');
                });
        });

        cy.paso('Tampoco puede reabrir la ronda 1: la página no ofrece "Reabrir" y la API lo rechaza');
        gestionarRonda(1);
        cy.get('#editorRonda').should('contain', 'solo un administrador puede reabrirla');
        cy.get('#editorRonda [data-estado-ronda="publicada"]').should('not.exist');
        cy.api('GET', `/torneos/${idLiga}/calendario`).its('body.rondas.0.id_ronda')
            .then((idRonda) => cy.api('PUT', `/rondas/${idRonda}`, { estado: 'publicada' })).its('status').should('eq', 403);

        cy.paso('El administrador sí puede corregirlo (letra 5.1: "registrar y modificar resultados")');
        cy.clearCookies();
        cy.ingresarComo('admin_e2e');
        cy.get('@partidoRonda2').then((idPartido) => {
            cy.api('POST', `/enfrentamientos/${idPartido}/resultado`, { puntaje_local: 1, puntaje_visitante: 1, observacion: 'Corregido por el administrador' })
                .its('body.mensaje').should('contain', 'Resultado corregido');
            cy.paso('El historial registra que la corrección la hizo el administrador');
            cy.api('GET', `/enfrentamientos/${idPartido}/resultados`).its('body.resultados.0').then((vigente) => {
                expect(vigente.cargado_por).to.eq('admin_e2e');
                expect(Number(vigente.vigente)).to.eq(1);
            });
        });

        cy.paso('El organizador tampoco se puede autorizar a sí mismo: la API lo rechaza');
        cy.clearCookies();
        cy.ingresarComo(ORGA);
        cy.api('POST', `/torneos/${idLiga}`, { permitir_correcciones: 1 }).then((r) => {
            expect(r.status).to.eq(403);
            expect(r.body.mensaje).to.contain('la da el administrador');
        });
    });

    it('3.11 Juega las rondas que faltan: con el último resultado el torneo termina solo', () => {
        cy.ingresarComo(ORGA);
        cy.visit(`torneos/gestionar?id=${idLiga}`);
        cy.paso('Publica la ronda que falta (la 3)');
        cy.get('#botonPublicarTodas').click();
        cy.get('#alertaGestion').should('contain', 'Se publicaron 1 ronda(s)');

        cy.paso('Carga el resultado que falta de la ronda 2');
        gestionarRonda(2);
        cargarMarcadores('.editor-partido[data-id]:not([data-finalizado="1"])', 2, 0);
        cy.get('#alertaGestion').should('contain', 'Se guardó 1 cambio');

        cy.paso('Carga los dos de la ronda 3: era lo último y el torneo termina solo');
        gestionarRonda(3);
        cargarMarcadores('.editor-partido[data-id]', 1, 0);
        cy.get('#alertaGestion').should('contain', 'el torneo terminó');
        cy.get('#pasos').should('contain', 'Finalizado');

        cy.paso('La página del torneo dice "Finalizado" y el campeón es el primero de la tabla');
        cy.visit(`torneos/torneo?id=${idLiga}#posiciones`);
        cy.get('#torneoChips').should('contain', 'Finalizado');
        cy.get('#tablaPosiciones tbody tr').first().find('.tabla-posiciones__nombre').invoke('text').then((primero) => {
            cy.get('#avisosTorneo .campeon strong').should('have.text', primero.trim());
        });
    });

    it('3.12 Con el torneo terminado ya no se programa ni se cancela; el reporte tiene todo', () => {
        cy.ingresarComo(ORGA);
        cy.paso('No se puede programar un partido, ni cancelar ni borrar el torneo');
        partidosDeRonda(idLiga, 3).then(([partido]) =>
            cy.api('PUT', `/enfrentamientos/${partido.id_enfrentamiento}`, { fecha_hora: fechaHora(5), lugar: 'Otra sede' }))
            .its('status').should('eq', 409);
        cy.api('POST', `/torneos/${idLiga}/cancelar`).its('body.mensaje').should('contain', 'finalizado');
        cy.api('DELETE', `/torneos/${idLiga}`).its('status').should('eq', 409);

        cy.paso('Descarga el reporte CSV: tiene las 4 posiciones y los 6 partidos');
        cy.request(`api/torneos/${idLiga}/reporte`).then((r) => {
            expect(r.headers['content-type']).to.contain('text/csv');
            const lineas = r.body.split('\n');
            expect(lineas.filter((l) => /^"?Posiciones/.test(l)), 'filas de posiciones').to.have.length(4);
            expect(lineas.filter((l) => /^"?Partido/.test(l)), 'filas de partidos').to.have.length(6);
        });

        cy.paso('En su perfil, el torneo figura como "Finalizado"');
        cy.visit('perfil/perfil');
        cy.contains('#listaOrganizo .fila-torneo', LIGA).should('contain', 'Finalizado');
    });

    it('3.13 La auditoría registró lo que hizo el organizador', () => {
        cy.paso('El administrador filtra la auditoría por el organizador');
        cy.ingresarComo('admin_e2e');
        idDe(ORGA).then((idOrga) => {
            const cantidad = (filtros) => cy.api('GET', `/admin/auditoria?id_usuario=${idOrga}&${filtros}`).its('body.paginacion.total');
            cy.paso('Están la creación del torneo, el calendario, los resultados y el final');
            cantidad('tabla=torneos&accion=crear').should('be.at.least', 1);
            cantidad('accion=generar_calendario').should('eq', 1);
            cantidad('tabla=resultados&accion=crear').should('be.at.least', 6);
            cantidad('accion=finalizar_torneo').should('eq', 1);
        });
        cy.paso('La autorización para corregir (darla y quitarla) figura como cambio del administrador');
        cy.idUsuario('admin_e2e').then((idAdmin) =>
            cy.api('GET', `/admin/auditoria?id_usuario=${idAdmin}&tabla=configuracion_torneo&accion=editar`))
            .its('body.paginacion.total').should('be.at.least', 2);

        cy.paso('En el panel, la búsqueda "Liga Flujo" muestra esos registros');
        cy.visit('admin/admin#auditoria');
        cy.intercept('GET', '**/api/admin/auditoria?*buscar=Liga*').as('busqueda');
        cy.get('#buscarAuditoria').type('Liga Flujo');
        cy.wait('@busqueda');
        cy.get('#tablaAuditoria table.tabla-auditoria > tbody > tr').should('have.length.greaterThan', 0);
    });

    it('3.14 Eliminación directa con 5 participantes: pases libres, sin empates y el ganador avanza', () => {
        cy.paso('Crea una copa de eliminación directa con 8 lugares y se anotan 5 jugadores');
        cy.ingresarComo(ORGA);
        crearTorneoPorApi({ nombre: 'Copa Flujo de Damas', id_juego: 29, modulo: 'eliminacion_directa', cupo: 8 }).then((id) => {
            inscribirPorApi(id, ['jugador_e2e1', 'jugador_e2e2', 'jugador_e2e3', 'jugador_e2e4', 'jugador_e2e5']);
            cy.paso('El administrador lo autoriza a corregir resultados en esta copa');
            cy.ingresarComo('admin_e2e');
            cy.api('POST', `/torneos/${id}`, { permitir_correcciones: 1 }).its('status').should('eq', 200);
            cy.clearCookies();

            cy.paso('Genera las llaves desde "Gestionar" y las publica');
            cy.ingresarComo(ORGA);
            cy.visit(`torneos/gestionar?id=${id}`);
            cy.get('#botonGenerar').click();
            cy.confirmar('Generar calendario');
            cy.get('#alertaGestion').should('contain', 'Calendario generado');
            cy.get('#botonPublicarTodas').click();
            cy.get('#alertaGestion').should('contain', 'Se publicaron');

            cy.paso('Hay 3 rondas; en la primera, 3 pases libres y un solo partido; los que pasaron libres ya esperan en la segunda');
            cy.api('GET', `/torneos/${id}/calendario`).its('body.rondas').then((rondas) => {
                expect(rondas, 'rondas').to.have.length(3);
                expect(rondas[0].partidos.filter((p) => Number(p.es_pase_libre)), 'pases libres').to.have.length(3);
                const ubicados = rondas[1].partidos.flatMap((p) => [p.id_local, p.id_visitante]).filter(Boolean);
                expect(ubicados, 'lugares ocupados en la segunda ronda').to.have.length(3);
            });

            partidosDeRonda(id, 1).then(([partido]) => {
                cy.paso('Un empate no se acepta: alguien tiene que avanzar');
                cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`, { puntaje_local: 1, puntaje_visitante: 1 })
                    .then((r) => {
                        expect(r.status).to.eq(422);
                        expect(JSON.stringify(r.body)).to.contain('empatado');
                    });

                cy.paso('Carga el partido de la ronda 1 (2 a 0): el ganador pasa a la ronda 2');
                cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`, { puntaje_local: 2, puntaje_visitante: 0 })
                    .its('status').should('eq', 200);
                partidosDeRonda(id, 2).then((semis) => {
                    expect(semis, 'semifinales con los dos rivales').to.have.length(2);
                    const suya = semis.find((s) => [s.id_local, s.id_visitante].map(Number).includes(Number(partido.id_local)));
                    expect(suya, 'el ganador está en una semifinal').to.exist;

                    cy.paso('Se juega la semifinal de ese ganador; después, aun con autorización, ya no se puede corregir el partido de la ronda 1');
                    cy.api('POST', `/enfrentamientos/${suya.id_enfrentamiento}/resultado`, { puntaje_local: 3, puntaje_visitante: 1 })
                        .its('status').should('eq', 200);
                    cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`, { puntaje_local: 0, puntaje_visitante: 2 })
                        .then((r) => {
                            expect(r.status).to.eq(409);
                            expect(r.body.mensaje).to.contain('ya jugó el partido siguiente');
                        });

                    cy.paso('Se juegan la otra semifinal y la final: el torneo termina');
                    const otra = semis.find((s) => s.id_enfrentamiento !== suya.id_enfrentamiento);
                    cy.api('POST', `/enfrentamientos/${otra.id_enfrentamiento}/resultado`, { puntaje_local: 1, puntaje_visitante: 0 })
                        .its('status').should('eq', 200);
                });
            });
            partidosDeRonda(id, 3).then(([final]) => {
                cy.api('POST', `/enfrentamientos/${final.id_enfrentamiento}/resultado`, { puntaje_local: 4, puntaje_visitante: 2 })
                    .its('body.mensaje').should('contain', 'el torneo terminó');

                cy.paso('Las llaves muestran los pases libres y al campeón (el ganador de la final)');
                cy.clearCookies();
                cy.visit(`torneos/torneo?id=${id}#llaves`);
                cy.get('#llaves').should('contain', 'Pase libre');
                cy.get('.llave--campeon').should('contain', final.nombre_local);
            });
        });
    });

    it('3.15 Sistema suizo con 5 participantes: de a una ronda, sin revanchas y con un libre distinto cada vez', () => {
        cy.paso('Crea un torneo suizo y se anotan 5 jugadores (corresponden 3 rondas)');
        cy.ingresarComo(ORGA);
        crearTorneoPorApi({ nombre: 'Suizo Flujo', modulo: 'sistema_suizo', cupo: 8 }).then((id) => {
            inscribirPorApi(id, ['jugador_e2e4', 'jugador_e2e5', 'jugador_e2e6', 'jugador_e2e7', 'jugador_e2e8']);
            cy.ingresarComo(ORGA);
            cy.api('POST', `/torneos/${id}/calendario`).its('body.total_rondas').should('eq', 3);

            [1, 2, 3].forEach((numero) => {
                cy.paso(`Ronda ${numero}: la publica y carga sus resultados`);
                cy.api('POST', `/torneos/${id}/rondas/publicar`).its('status').should('eq', 200);
                if (numero === 1) {
                    cy.paso('Antes de cargar los resultados no se puede pedir la ronda siguiente');
                    cy.api('POST', `/torneos/${id}/rondas`).its('body.mensaje').should('contain', 'Faltan resultados');
                }
                jugarRondaPorApi(id, numero, 1, 0);
                if (numero < 3) {
                    cy.api('POST', `/torneos/${id}/rondas`).its('body.mensaje').should('contain', `Ronda ${numero + 1} generada`);
                }
            });

            cy.paso('Nadie se cruzó dos veces con el mismo rival y el libre (pase libre) le tocó a uno distinto en cada ronda');
            cy.api('GET', `/torneos/${id}/calendario`).its('body.rondas').then((rondas) => {
                const partidos = rondas.flatMap((r) => r.partidos);
                const cruces = partidos.filter((p) => !Number(p.es_pase_libre)).map((p) => [p.id_local, p.id_visitante].map(Number).sort().join('-'));
                expect(new Set(cruces).size, 'cruces sin repetir').to.eq(cruces.length);
                const libres = partidos.filter((p) => Number(p.es_pase_libre)).map((p) => Number(p.id_local));
                expect(libres, 'un libre por ronda').to.have.length(3);
                expect(new Set(libres).size, 'libres distintos').to.eq(3);
            });

            cy.paso('El torneo terminó solo y la tabla usa el desempate Buchholz');
            cy.api('GET', `/torneos/${id}`).its('body.torneo.estado').should('eq', 'finalizado');
            cy.api('POST', `/torneos/${id}/rondas`).its('status').should('eq', 409);
            cy.clearCookies();
            cy.visit(`torneos/torneo?id=${id}#posiciones`);
            cy.get('#tablaPosiciones thead').should('contain', 'Buch.');
            cy.get('#avisosTorneo .campeon').should('be.visible');
        });
    });

    it('3.16 Cancelar un torneo en curso: se conserva lo jugado y ya no se puede cargar nada', () => {
        cy.paso('Una liga de 3 jugadores en curso, con un resultado cargado');
        cy.ingresarComo(ORGA);
        crearTorneoPorApi({ nombre: 'Liga Flujo Cancelada' }).then((id) => {
            inscribirPorApi(id, ['jugador_e2e6', 'jugador_e2e7', 'jugador_e2e8']);
            cy.ingresarComo(ORGA);
            cy.api('POST', `/torneos/${id}/calendario`).its('status').should('eq', 201);
            cy.api('POST', `/torneos/${id}/rondas/publicar`).its('status').should('eq', 200);
            jugarRondaPorApi(id, 1, 2, 0);

            cy.paso('La cancela desde la zona de peligro de "Gestionar"');
            cy.visit(`torneos/gestionar?id=${id}`);
            cy.get('#botonEliminar').should('not.exist');
            cy.get('#botonCancelar').click();
            cy.confirmar('Cancelar torneo');
            cy.get('#alertaGestion').should('contain', 'Torneo cancelado');

            cy.paso('Ya no se pueden cargar resultados, programar partidos ni cambiar rondas');
            partidosDeRonda(id, 2).then(([partido]) => {
                cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`, { puntaje_local: 1, puntaje_visitante: 0 })
                    .its('body.mensaje').should('contain', 'cancelado');
                cy.api('PUT', `/enfrentamientos/${partido.id_enfrentamiento}`, { fecha_hora: fechaHora(2) }).its('status').should('eq', 409);
                cy.api('PUT', `/rondas/${partido.id_ronda}`, { estado: 'cerrada' }).its('status').should('eq', 409);
            });

            cy.paso('No se puede borrar: el historial se conserva');
            cy.api('DELETE', `/torneos/${id}`).its('status').should('eq', 409);

            cy.paso('El público ve el aviso de cancelación y el resultado que se jugó');
            cy.clearCookies();
            cy.visit(`torneos/torneo?id=${id}#calendario`);
            cy.get('#avisosTorneo').should('contain', 'fue cancelado');
            cy.get('#listaRondas .partido__marcador').should('contain', '2 - 0');
        });
    });

    it('3.17 Mientras "Editar torneo" carga los datos, no se puede guardar con los valores por defecto', () => {
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('liga_lista').then((id) => {
            cy.paso('"Liga E2E Lista" todavía no empezó, tiene descripción, 4 lugares y está oculta');
            cy.api('PUT', `/torneos/${id}/publicacion`, { publicado: false }).its('status').should('eq', 200);
            cy.api('GET', `/torneos/${id}`).its('body.torneo').as('antes');

            cy.paso('Abre "Editar torneo" con la conexión lenta (la lista de juegos tarda 2,5 segundos)');
            cy.intercept('GET', '**/api/torneos/*').as('torneo');
            cy.intercept('GET', '**/api/juegos', (req) => { req.on('response', (res) => { res.setDelay(2500); }); }).as('juegos');
            cy.intercept('POST', /\/api\/torneos(\/\d+)?$/).as('guardar');
            cy.visit(`createtournament/createtournament?id=${id}`);
            cy.wait('@torneo');

            cy.paso('Mientras el formulario carga, "Guardar" está deshabilitado');
            cy.get('#botonGuardar').should('be.disabled');
            cy.paso('Aunque se fuerce el envío del formulario (por ejemplo, con Enter en un campo), no se manda nada');
            cy.get('#formTorneo').submit();
            cy.wait('@juegos');
            cy.get('#botonGuardar').should('not.be.disabled');
            cy.wait(1000); // margen para que termine un guardado, si se hubiera mandado
            cy.get('@guardar.all').then((pedidos) => {
                cy.paso(`Pedidos de guardado que se mandaron mientras cargaba: ${pedidos.length}`);
                cy.then(() => expect(pedidos, 'pedidos de guardado mientras cargaba').to.have.length(0));
            });

            cy.paso('El torneo tiene que seguir igual: mismo cupo, misma descripción y oculto');
            const resumen = (t) => `cupo ${t.cupo}, descripción "${t.descripcion ?? ''}", ${Number(t.publicado) ? 'público' : 'oculto'}`;
            cy.get('@antes').then((antes) => cy.api('GET', `/torneos/${id}`).its('body.torneo').then((despues) => {
                cy.paso(`Antes: ${resumen(antes)}. Después: ${resumen(despues)}`);
                // Se compara después de anotar el paso, así queda en el registro aunque falle
                cy.then(() => expect(resumen(despues), 'datos del torneo después del clic').to.eq(resumen(antes)));
            }));
        });
    });
});
