// =====================================================================
// 12. FLUJOS DEL ADMINISTRADOR Y DEL PÚBLICO (letra, puntos 5.1 y 5.4)
//     El administrador actúa sobre torneos de otros (participantes,
//     equipos y resultados), deshabilita un formato y desactiva usuarios
//     sin romper los torneos en curso. El público ve solo lo publicado
//     y lo oficial, y no puede cambiar nada.
// =====================================================================

import { fecha, registrarPorApi } from '../soporte/ayudas';

// Partidos de una ronda tal como los ve quien está logueado
const ronda = (idTorneo, numero) => cy.api('GET', `/torneos/${idTorneo}/calendario`)
    .its('body.rondas').then((rondas) => rondas.find((r) => Number(r.numero) === numero));

describe('Flujo 4: administrador y público', () => {
    before(() => cy.prepararBase());

    it('4.1 El administrador crea un torneo desde el formulario y lo gestiona (5.1: "crear y configurar torneos")', () => {
        cy.paso('Completa "Crear torneo" como administrador');
        cy.ingresarComo('admin_e2e');
        cy.visit('createtournament/createtournament');
        cy.get('#id_juego option').should('have.length.greaterThan', 10);
        cy.get('#nombre').type('Liga del Administrador');
        cy.get('#id_juego').select('Ajedrez');
        cy.contains('.opcion--formato', 'Liga').click();
        cy.get('#fecha_fin').clear().type(fecha(20));
        cy.get('#botonGuardar').click();

        cy.paso('Queda creado y lo puede gestionar');
        cy.location('pathname').should('eq', '/public/torneos/gestionar');
        cy.get('#alertaGestion').should('contain', '¡Torneo creado!');
        cy.get('#nombreTorneo').should('have.text', 'Liga del Administrador');
    });

    it('4.2 Gestiona participantes y equipos de torneos de otro organizador (5.1)', () => {
        cy.ingresarComo('admin_e2e');
        cy.paso('En "Abierto E2E de Póker" (de orga_e2e) inscribe a jugador_e2e1 y después lo quita');
        cy.idTorneo('abierto').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        cy.get('#inscribirUsuario').type('jugador_e2e1');
        cy.get('#formInscribir').submit();
        cy.get('#listaInscriptos').should('contain', 'jugador_e2e1');
        cy.get('#listaInscriptos [aria-label="Quitar a jugador_e2e1"]').click();
        cy.confirmar('Quitar');
        cy.get('#listaInscriptos').should('not.contain', 'jugador_e2e1');

        cy.paso('En el torneo por equipos inscribe un equipo con jugador_e2e2 como capitán');
        cy.idTorneo('equipos').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        cy.get('#inscribirEquipo').type('Equipo del Admin');
        cy.get('#inscribirCapitan').type('jugador_e2e2');
        cy.get('#formInscribir').submit();
        cy.get('#listaInscriptos').should('contain', 'Equipo del Admin');

        cy.paso('El organizador del torneo ve el equipo nuevo en su "Gestionar"');
        cy.clearCookies();
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('equipos').then((id) => cy.visit(`torneos/gestionar?id=${id}`));
        cy.get('#listaInscriptos').should('contain', 'Equipo del Admin');
    });

    it('4.3 Carga y corrige resultados en un torneo de otro organizador (5.1)', () => {
        cy.ingresarComo('admin_e2e');
        cy.idTorneo('liga_en_curso').then((id) => {
            cy.paso('Carga un resultado de la ronda 2 de "Liga E2E de Ajedrez" (de un partido sin fecha: el programado todavía no se jugó)');
            ronda(id, 2).then((r) => {
                const partido = r.partidos.find((p) => p.estado === 'pendiente' && p.id_visitante && !p.fecha_hora);
                cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`, { puntaje_local: 1, puntaje_visitante: 0 })
                    .its('body.mensaje').should('contain', 'Resultado guardado');
            });

            cy.paso('Corrige un resultado de la ronda 1 que había cargado el organizador');
            ronda(id, 1).then((r) => {
                const partido = r.partidos.find((p) => p.estado === 'finalizado' && p.id_visitante);
                cy.api('POST', `/enfrentamientos/${partido.id_enfrentamiento}/resultado`, { puntaje_local: 0, puntaje_visitante: 0, observacion: 'Revisión del administrador' })
                    .its('body.mensaje').should('contain', 'Resultado corregido');

                cy.paso('El historial muestra quién cargó cada uno: primero orga_e2e, después admin_e2e');
                cy.api('GET', `/enfrentamientos/${partido.id_enfrentamiento}/resultados`).its('body.resultados').then((historial) => {
                    expect(historial.map((h) => h.cargado_por)).to.deep.eq(['admin_e2e', 'orga_e2e']);
                    expect(historial.map((h) => Number(h.vigente))).to.deep.eq([1, 0]);
                });
            });
        });
    });

    it('4.4 Deshabilitar un formato impide crear torneos nuevos con él, pero los que ya lo usan siguen', () => {
        cy.paso('El administrador deshabilita "Sistema suizo" en "Módulos"');
        cy.ingresarComo('admin_e2e');
        cy.visit('admin/admin#modulos');
        cy.contains('.modulo', 'Sistema suizo').find('input[type="checkbox"]').uncheck({ force: true });
        cy.contains('.modulo', 'Sistema suizo').should('have.class', 'modulo--apagado');

        cy.paso('Un organizador ya no puede crear un torneo suizo');
        cy.clearCookies();
        cy.ingresarComo('orga_e2e');
        cy.api('POST', '/torneos', { nombre: 'Suizo nuevo', id_juego: 28, modulo: 'sistema_suizo', modalidad: 'individual',
            cupo: 4, fecha_inicio: fecha(5), fecha_fin: fecha(20) }).its('status').should('eq', 422);

        cy.paso('El suizo que ya tenía inscriptos se puede seguir jugando: genera, publica, carga y pide la ronda 2');
        cy.idTorneo('suizo_listo').then((id) => {
            cy.api('POST', `/torneos/${id}/calendario`).its('status').should('eq', 201);
            cy.api('POST', `/torneos/${id}/rondas/publicar`).its('status').should('eq', 200);
            ronda(id, 1).then((r) => r.partidos.filter((p) => !Number(p.es_pase_libre)).forEach((p) => {
                cy.api('POST', `/enfrentamientos/${p.id_enfrentamiento}/resultado`, { puntaje_local: 1, puntaje_visitante: 0 })
                    .its('status').should('eq', 200);
            }));
            cy.api('POST', `/torneos/${id}/rondas`).its('status').should('eq', 201);
        });

        cy.paso('El administrador lo vuelve a habilitar');
        cy.clearCookies();
        cy.ingresarComo('admin_e2e');
        cy.visit('admin/admin#modulos');
        cy.contains('.modulo', 'Sistema suizo').find('input[type="checkbox"]').check({ force: true });
        cy.contains('.modulo', 'Sistema suizo').should('not.have.class', 'modulo--apagado');
    });

    it('4.5 Desactivar a un jugador que está compitiendo: no entra más, pero sus partidos y su lugar en la tabla quedan', () => {
        cy.paso('El administrador desactiva a jugador_e2e2, que juega "Liga E2E de Ajedrez"');
        cy.ingresarComo('admin_e2e');
        cy.visit('admin/admin#usuarios');
        cy.get('#buscarUsuario').type('jugador_e2e2');
        cy.get('#tablaUsuarios tbody tr').should('have.length', 1);
        cy.contains('#tablaUsuarios button', 'Desactivar').click();
        cy.confirmar('Desactivar');
        cy.get('#tablaUsuarios').should('contain', 'Inactivo');

        cy.paso('jugador_e2e2 ya no puede ingresar');
        cy.clearCookies();
        cy.api('POST', '/auth/login', { correo: 'jugador_e2e2@e2e.uy', contrasena: Cypress.expose('clave') })
            .its('body.mensaje').should('contain', 'desactivada');

        cy.paso('El público sigue viéndolo en la tabla de posiciones del torneo');
        cy.idTorneo('liga_en_curso').then((id) => cy.api('GET', `/torneos/${id}/posiciones`)).its('body.posiciones')
            .then((tabla) => expect(tabla.map((f) => f.nombre)).to.include('jugador_e2e2'));

        cy.paso('El organizador puede cargar el resultado de un partido pendiente de jugador_e2e2');
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('liga_en_curso').then((id) => cy.api('GET', `/torneos/${id}/calendario`)).its('body.rondas').then((rondas) => {
            const suyo = rondas.flatMap((r) => r.partidos)
                .find((p) => p.estado === 'pendiente' && !p.fecha_hora && [p.nombre_local, p.nombre_visitante].includes('jugador_e2e2'));
            expect(suyo, 'partido pendiente de jugador_e2e2').to.exist;
            cy.api('POST', `/enfrentamientos/${suyo.id_enfrentamiento}/resultado`, { puntaje_local: 2, puntaje_visitante: 2 })
                .its('status').should('eq', 200);
        });
    });

    it('4.6 Un usuario con historial no se puede eliminar (se desactiva); uno sin historial sí', () => {
        cy.ingresarComo('admin_e2e');
        cy.paso('Intenta eliminar a jugador_e2e3, que tiene torneos y resultados: se rechaza');
        cy.idUsuario('jugador_e2e3').then((id) => cy.api('DELETE', `/admin/usuarios/${id}`)).then((r) => {
            expect(r.status).to.eq(409);
            expect(r.body.mensaje).to.contain('desactivalo');
        });

        cy.paso('Una cuenta nueva sin actividad se puede eliminar');
        registrarPorApi('borrable_flujo').then((id) => {
            cy.api('DELETE', `/admin/usuarios/${id}`).its('status').should('eq', 200);
        });
    });

    it('4.7 El público ve solo lo publicado y lo oficial, y no puede cambiar nada (5.4)', () => {
        cy.paso('Sin cuenta, el listado de torneos no incluye el torneo oculto');
        cy.api('GET', '/torneos?por_pagina=50').its('body.torneos')
            .then((torneos) => expect(torneos.map((t) => t.nombre)).not.to.include('Torneo E2E Oculto'));
        cy.idTorneo('oculto').then((id) => cy.api('GET', `/torneos/${id}`)).its('status').should('eq', 404);

        cy.paso('El organizador genera el calendario de "Liga E2E Lista": mientras está en borrador, el público no ve sus partidos');
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('liga_lista').then((id) => cy.api('POST', `/torneos/${id}/calendario`)).its('status').should('eq', 201);
        cy.clearCookies();
        cy.idTorneo('liga_lista').then((id) => cy.api('GET', `/torneos/${id}/calendario`)).its('body.rondas').should('have.length', 0);
        cy.api('GET', '/partidos/proximos?por_pagina=50').its('body.partidos')
            .then((partidos) => expect(partidos.map((p) => p.torneo)).not.to.include('Liga E2E Lista'));

        cy.paso('Cuando las publica, el público ve el calendario');
        cy.ingresarComo('orga_e2e');
        cy.idTorneo('liga_lista').then((id) => cy.api('POST', `/torneos/${id}/rondas/publicar`)).its('status').should('eq', 200);
        cy.clearCookies();
        cy.idTorneo('liga_lista').then((id) => cy.api('GET', `/torneos/${id}/calendario`)).its('body.rondas').should('have.length', 3);

        cy.paso('Ve los resultados oficiales, las llaves con el campeón y la tabla de posiciones');
        cy.api('GET', '/partidos/recientes?por_pagina=50').its('body.partidos')
            .then((partidos) => expect(partidos.map((p) => p.torneo)).to.include('Copa E2E de Damas'));
        cy.idTorneo('copa_final').then((id) => cy.visit(`torneos/torneo?id=${id}#llaves`));
        cy.get('.llave--campeon').should('be.visible');
        cy.idTorneo('liga_en_curso').then((id) => cy.visit(`torneos/torneo?id=${id}#posiciones`));
        cy.get('#tablaPosiciones tbody tr').should('have.length', 4);

        cy.paso('Ve perfiles públicos de jugadores (sin correo) y de equipos');
        cy.idUsuario('jugador_e2e1').then((id) => cy.api('GET', `/usuarios/${id}`)).its('body.usuario')
            .should('not.have.property', 'correo');
        cy.wrap(null).then(() => cy.visit(`perfil/equipo?id=${Cypress.expose('datos').equipo}`));
        cy.get('#miembrosEquipo').should('contain', 'capitan_e2e');

        cy.paso('No puede inscribirse, cargar resultados, crear torneos ni tocar la administración');
        cy.idTorneo('abierto').then((id) => cy.api('POST', `/torneos/${id}/inscripcion`)).its('status').should('be.oneOf', [401, 403]);
        cy.wrap(null).then(() => cy.api('POST', `/enfrentamientos/${Cypress.expose('datos').partido_programado}/resultado`,
            { puntaje_local: 1, puntaje_visitante: 0 })).its('status').should('be.oneOf', [401, 403]);
        cy.api('POST', '/torneos', { nombre: 'Del público' }).its('status').should('be.oneOf', [401, 403]);
        cy.api('PUT', '/admin/modulos/1', { habilitado: false }).its('status').should('be.oneOf', [401, 403]);
    });
});
