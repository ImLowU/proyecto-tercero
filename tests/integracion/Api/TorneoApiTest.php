<?php
// Tests de creación, edición, listado, visibilidad, borrado y cancelación de torneos.

final class TorneoApiTest extends CasoApi
{
    private ClienteApi $organizador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizador = $this->cliente('orga', Rol::ORGANIZADOR);
    }

    public function testUnParticipanteNoPuedeCrearTorneos(): void
    {
        $this->assertEstado(403, $this->cliente('ana')->post('/torneos', ['nombre' => 'X']));
    }

    public function testLaMonedaElegidaSeGuarda(): void
    {
        $id = $this->crearTorneo($this->organizador, ['premio' => '1500.50', 'moneda' => 'USD']);
        $torneo = Torneo::buscar($id);
        $this->assertSame('USD', $torneo['moneda'], 'Antes quedaba siempre en UYU');
        $this->assertEquals(1500.50, $torneo['premio']);
    }

    public function testLosErroresSeMarcanCampoPorCampo(): void
    {
        $respuesta = $this->organizador->post('/torneos', [
            'nombre' => 'X', 'id_juego' => 9999, 'modulo' => 'no_existe', 'cupo' => 1,
            'fecha_inicio' => '2030-05-10', 'fecha_fin' => '2030-05-01',
        ]);
        $this->assertEstado(422, $respuesta);
        $this->assertEqualsCanonicalizing(['nombre', 'id_juego', 'modulo', 'cupo', 'fecha_fin'], array_keys($respuesta->cuerpo['errores']));
    }

    public function testNoSePuedeCrearUnTorneoQueEmpiezaEnElPasado(): void
    {
        $respuesta = $this->organizador->post('/torneos', ['nombre' => 'Viejo', 'id_juego' => 1, 'modulo' => 'liga', 'cupo' => 4,
                                                           'fecha_inicio' => '2020-01-01', 'fecha_fin' => '2020-01-02']);
        $this->assertErrorEnCampo('fecha_inicio', $respuesta);
    }

    public function testSiNoSeEligeModalidadSeUsaLaSugeridaPorElJuego(): void
    {
        $ajedrez = $this->crearTorneo($this->organizador, ['id_juego' => 28, 'modalidad' => '']);
        $futbol = $this->crearTorneo($this->organizador, ['id_juego' => 1, 'modalidad' => '']);
        $this->assertSame('individual', Torneo::buscar($ajedrez)['modalidad']);
        $this->assertSame('equipos', Torneo::buscar($futbol)['modalidad']);
    }

    public function testElLogoYLasReglasSonOpcionales(): void
    {
        $torneo = Torneo::buscar($this->crearTorneo($this->organizador));
        $this->assertNull($torneo['logo']);
        $this->assertNull($torneo['reglas_pdf']);
    }

    public function testSeGuardanElLogoYElPdfDeReglas(): void
    {
        $respuesta = $this->organizador->post('/torneos', [
            'nombre' => 'Con archivos', 'id_juego' => 1, 'modulo' => 'liga', 'cupo' => 4,
            'fecha_inicio' => date('Y-m-d', strtotime('+1 day')), 'fecha_fin' => date('Y-m-d', strtotime('+2 days')),
        ], ['logo' => PerfilApiTest::archivo('logo.png', PerfilApiTest::png()),
            'reglas' => PerfilApiTest::archivo('reglas.pdf', "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF")]);
        $torneo = Torneo::buscar($this->assertEstado(201, $respuesta)->cuerpo['id_torneo']);
        $this->assertFileExists(Archivo::carpeta('torneos/logos') . '/' . $torneo['logo']);
        $this->assertFileExists(Archivo::carpeta('torneos/reglas') . '/' . $torneo['reglas_pdf']);
    }

    public function testAlEditarElLogoSeReemplazaOSeQuita(): void
    {
        $logo = fn() => ['logo' => PerfilApiTest::archivo('logo.png', PerfilApiTest::png())];
        $respuesta = $this->organizador->post('/torneos', [
            'nombre' => 'Con logo', 'id_juego' => 1, 'modulo' => 'liga', 'cupo' => 4,
            'fecha_inicio' => date('Y-m-d', strtotime('+1 day')), 'fecha_fin' => date('Y-m-d', strtotime('+2 days')),
        ], $logo());
        $id = $this->assertEstado(201, $respuesta)->cuerpo['id_torneo'];
        $carpeta = Archivo::carpeta('torneos/logos');
        $primero = Torneo::buscar($id)['logo'];

        $this->assertOk($this->organizador->post("/torneos/$id", ['nombre' => 'Con logo nuevo'], $logo()));
        $segundo = Torneo::buscar($id)['logo'];
        $this->assertNotSame($primero, $segundo);
        $this->assertFileDoesNotExist("$carpeta/$primero", 'El logo reemplazado se borra');

        $this->assertOk($this->organizador->post("/torneos/$id", ['quitar_logo' => '1']));
        $this->assertNull(Torneo::buscar($id)['logo']);
        $this->assertFileDoesNotExist("$carpeta/$segundo");
    }

    public function testUnModuloDeshabilitadoNoSeOfreceNiSePuedeUsar(): void
    {
        $this->assertOk($this->admin()->put('/admin/modulos/3', ['habilitado' => false]));
        $this->assertNotContains('sistema_suizo', array_column((new ClienteApi())->get('/modulos')->cuerpo['modulos'], 'codigo'));
        $respuesta = $this->organizador->post('/torneos', ['nombre' => 'Suizo', 'id_juego' => 28, 'modulo' => 'sistema_suizo', 'cupo' => 8,
            'fecha_inicio' => date('Y-m-d', strtotime('+1 day')), 'fecha_fin' => date('Y-m-d', strtotime('+2 days'))]);
        $this->assertErrorEnCampo('modulo', $respuesta);
    }

    public function testElListadoFiltraYPaginaEnElServidor(): void
    {
        $this->crearTorneo($this->organizador, ['nombre' => 'Copa de Ajedrez', 'id_juego' => 28]);
        $this->crearTorneo($this->organizador, ['nombre' => 'Liga de Fútbol', 'id_juego' => 1, 'modalidad' => 'equipos']);
        $this->crearTorneo($this->organizador, ['nombre' => 'Torneo de Valorant', 'id_juego' => 33, 'modalidad' => 'equipos']);
        $publico = new ClienteApi();

        $this->assertSame(['Copa de Ajedrez'], array_column($publico->get('/torneos?id_juego=28')->cuerpo['torneos'], 'nombre'));
        $this->assertSame(['Torneo de Valorant'], array_column($publico->get('/torneos?categoria=electronico')->cuerpo['torneos'], 'nombre'));
        $this->assertSame(['Liga de Fútbol'], array_column($publico->get('/torneos?buscar=fútbol')->cuerpo['torneos'], 'nombre'));

        $pagina = $publico->get('/torneos?por_pagina=2&pagina=2')->cuerpo;
        $this->assertCount(1, $pagina['torneos']);
        $this->assertSame(['pagina' => 2, 'por_pagina' => 2, 'total' => 3, 'paginas' => 2], $pagina['paginacion']);
    }

    public function testElFiltroActivosDejaAfueraLosCanceladosYFinalizados(): void
    {
        $abierto = $this->crearTorneo($this->organizador, ['nombre' => 'Abierto']);
        $cancelado = $this->crearTorneo($this->organizador, ['nombre' => 'Cancelado']);
        $this->assertOk($this->organizador->post("/torneos/$cancelado/cancelar"));

        $activos = (new ClienteApi())->get('/torneos?estado=activos')->cuerpo;
        $this->assertSame([$abierto], array_column($activos['torneos'], 'id_torneo'));
        $this->assertSame(1, $activos['paginacion']['total']);
    }

    public function testUnTorneoOcultoSoloLoVenSuOrganizadorYElAdmin(): void
    {
        $id = $this->crearTorneo($this->organizador, ['publicado' => '0']);
        $this->assertEstado(404, (new ClienteApi())->get("/torneos/$id"));
        $this->assertEstado(404, $this->cliente('otro', Rol::ORGANIZADOR)->get("/torneos/$id"));
        $this->assertOk($this->organizador->get("/torneos/$id"));
        $this->assertOk($this->admin()->get("/torneos/$id"));
        $this->assertSame(0, (new ClienteApi())->get('/torneos')->cuerpo['paginacion']['total']);

        $this->assertOk($this->organizador->put("/torneos/$id/publicacion", ['publicado' => true]));
        $this->assertOk((new ClienteApi())->get("/torneos/$id"));
    }

    public function testLaPaginaRecibeLosPermisosDelUsuario(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $this->assertTrue($this->organizador->get("/torneos/$id")->cuerpo['torneo']['permisos']['gestionar']);
        $this->assertFalse($this->organizador->get("/torneos/$id")->cuerpo['torneo']['permisos']['inscribirse'], 'El organizador no juega su torneo');
        $ana = $this->cliente('ana');
        $this->assertTrue($ana->get("/torneos/$id")->cuerpo['torneo']['permisos']['inscribirse']);
        $this->assertFalse($ana->get("/torneos/$id")->cuerpo['torneo']['permisos']['gestionar']);
    }

    public function testDespuesDeGenerarElCalendarioElFormatoNoSePuedeCambiar(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 4);
        $this->assertErrorEnCampo('id_modulo', $this->organizador->post("/torneos/$id", ['modulo' => 'eliminacion_directa']));
        $this->assertErrorEnCampo('cupo', $this->organizador->post("/torneos/$id", ['cupo' => 20]));
        // La descripción sí
        $this->assertOk($this->organizador->post("/torneos/$id", ['descripcion' => 'Se juega los sábados']));
        $this->assertSame('Se juega los sábados', Torneo::buscar($id)['descripcion']);
    }

    public function testConInscriptosLaModalidadNoSeCambia(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $this->assertOk($this->organizador->post("/torneos/$id", ['modalidad' => 'equipos']), 'Sin inscriptos se puede');
        $this->assertOk($this->organizador->post("/torneos/$id", ['modalidad' => 'individual']));
        $this->inscribirJugadores($id, 1);
        $this->assertErrorEnCampo('modalidad', $this->organizador->post("/torneos/$id", ['modalidad' => 'equipos']));
    }

    public function testElCupoNoPuedeQuedarPorDebajoDeLosInscriptos(): void
    {
        $id = $this->crearTorneo($this->organizador, ['cupo' => 8]);
        $this->inscribirJugadores($id, 3);
        $this->assertErrorEnCampo('cupo', $this->organizador->post("/torneos/$id", ['cupo' => 2]));
    }

    // Compromiso con el grupo: lo que se borra queda auditado fila por fila,
    // porque MySQL no dispara triggers en los borrados en cascada.
    public function testAlBorrarUnTorneoPendienteQuedaAuditadoCadaRegistro(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $this->inscribirJugadores($id, 3);
        $antes = (int)BaseDatos::valor("SELECT MAX(id_auditoria) FROM auditoria");

        $this->assertOk($this->organizador->delete("/torneos/$id"));

        $borrados = BaseDatos::filas(
            "SELECT tabla, COUNT(*) AS cantidad FROM auditoria WHERE id_auditoria > ? AND accion = 'eliminar' GROUP BY tabla",
            [$antes]);
        $this->assertEquals(
            ['configuracion_torneo' => 1, 'participantes' => 3, 'torneos' => 1],
            array_column($borrados, 'cantidad', 'tabla'));
    }

    public function testUnTorneoEnCursoNoSeBorraSeCancela(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 4);
        $this->assertEstado(409, $this->organizador->delete("/torneos/$id"));

        $this->assertOk($this->organizador->post("/torneos/$id/cancelar"));
        $this->assertSame('cancelado', $this->estadoTorneo($id));
        $this->assertSame(6, (int)BaseDatos::valor(
            "SELECT COUNT(*) FROM enfrentamientos e JOIN rondas r ON r.id_ronda = e.id_ronda WHERE r.id_torneo = ?", [$id]),
            'Los partidos se conservan como historial');
        $this->assertNotNull($this->ultimaAuditoria('cancelar_torneo'));
    }

    public function testUnTorneoCanceladoNoAparecePublicamenteNiSeEdita(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $this->assertOk($this->organizador->post("/torneos/$id/cancelar"));
        $this->assertSame(0, (new ClienteApi())->get('/torneos')->cuerpo['paginacion']['total']);
        $this->assertEstado(409, $this->organizador->post("/torneos/$id", ['nombre' => 'Otro nombre']));
    }

    public function testElReporteCsvTraeLasPosicionesYLosPartidos(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 3);
        $respuesta = $this->assertOk($this->organizador->get("/torneos/$id/reporte"));
        $this->assertSame('text/csv; charset=UTF-8', $respuesta->cabeceras['Content-Type']);
        $this->assertStringContainsString('jugador1', $respuesta->contenidoCrudo);
        $this->assertSame(3 + 3 + 1, substr_count($respuesta->contenidoCrudo, "\n"), 'Encabezado + 3 posiciones + 3 partidos');
    }

    public function testElDestacadoEsElActivoConMayorPremio(): void
    {
        $this->crearTorneo($this->organizador, ['nombre' => 'Chico', 'premio' => 100]);
        $this->crearTorneo($this->organizador, ['nombre' => 'Grande', 'premio' => 5000]);
        $this->assertSame('Grande', (new ClienteApi())->get('/torneos/destacado')->cuerpo['torneo']['nombre']);
    }

    // Uno lleno no invita a inscribirse: se prefiere uno con lugares aunque tenga menos premio.
    public function testElDestacadoPrefiereUnTorneoConLugares(): void
    {
        $this->crearTorneo($this->organizador, ['nombre' => 'Con lugares', 'premio' => 100]);
        $lleno = $this->crearTorneo($this->organizador, ['nombre' => 'Lleno', 'premio' => 5000, 'cupo' => 2]);
        $this->inscribirJugadores($lleno, 2);
        $this->assertSame('Con lugares', (new ClienteApi())->get('/torneos/destacado')->cuerpo['torneo']['nombre']);
    }

    // ── Organizador a cargo (letra, 5.1 y 5.2) ──

    private function datosDeTorneo(): array
    {
        return ['nombre' => 'Torneo', 'id_juego' => 28, 'modulo' => 'liga', 'modalidad' => 'individual', 'cupo' => 4,
                'fecha_inicio' => date('Y-m-d', strtotime('+7 days')), 'fecha_fin' => date('Y-m-d', strtotime('+30 days'))];
    }

    public function testElAdministradorCreaUnTorneoYSeLoAsignaAUnOrganizador(): void
    {
        $idOrga = (int)Usuario::buscarPorNombre('orga')['id_usuario'];
        $id = $this->crearTorneo($this->admin(), ['nombre' => 'Asignado', 'id_organizador' => $idOrga]);
        $torneo = $this->organizador->get("/torneos/$id")->cuerpo['torneo'];
        $this->assertSame('orga', $torneo['organizador']);
        $this->assertTrue($torneo['permisos']['gestionar']);
        $this->assertContains('Asignado', array_column($this->organizador->get('/perfil/torneos?tipo=creados')->cuerpo['torneos'], 'nombre'));
        $this->assertStringContainsString('asignado a orga', $this->ultimaAuditoria('asignar_organizador')['detalle']);
    }

    public function testElAdministradorPasaUnTorneoAOtroOrganizador(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $otro = $this->cliente('otro_orga', Rol::ORGANIZADOR);
        $this->assertOk($this->admin()->post("/torneos/$id", ['id_organizador' => (int)Usuario::buscarPorNombre('otro_orga')['id_usuario']]));
        $this->assertTrue($otro->get("/torneos/$id")->cuerpo['torneo']['permisos']['gestionar']);
        $this->assertFalse($this->organizador->get("/torneos/$id")->cuerpo['torneo']['permisos']['gestionar']);
        $this->assertEstado(403, $this->organizador->post("/torneos/$id", ['nombre' => 'Ya no es mío']));
    }

    public function testSoloElAdministradorAsignaYSoloAOrganizadoresActivos(): void
    {
        $otro = $this->crearUsuario('otro_orga', Rol::ORGANIZADOR);
        $this->assertEstado(403, $this->organizador->post('/torneos', $this->datosDeTorneo() + ['id_organizador' => $otro]));
        $participante = $this->crearUsuario('jugador_x');
        $this->assertErrorEnCampo('id_organizador', $this->admin()->post('/torneos', $this->datosDeTorneo() + ['id_organizador' => $participante]));
        Usuario::actualizar($otro, ['activo' => 0]);
        $this->assertErrorEnCampo('id_organizador', $this->admin()->post('/torneos', $this->datosDeTorneo() + ['id_organizador' => $otro]));
    }

    // ── Autorización para corregir resultados (letra, 5.2) ──

    public function testLaAutorizacionParaCorregirLaDaElAdministrador(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $this->assertSame(0, (int)Torneo::configuracion($id)['permitir_correcciones'], 'Empieza sin autorización');
        $this->assertEstado(403, $this->organizador->post("/torneos/$id", ['permitir_correcciones' => 1]));
        $this->assertEstado(403, $this->organizador->post('/torneos', $this->datosDeTorneo() + ['permitir_correcciones' => 1]));
        $this->assertOk($this->admin()->post("/torneos/$id", ['permitir_correcciones' => 1]));
        $this->assertSame(1, (int)Torneo::configuracion($id)['permitir_correcciones']);
        // Un formulario que manda el valor que ya tiene no es un cambio: se acepta
        $this->assertOk($this->organizador->post("/torneos/$id", ['nombre' => 'Renombrado', 'permitir_correcciones' => 1]));
    }

    // ── Fechas coherentes al editar ──

    public function testAlEditarUnTorneoQueNoEmpezoElInicioNoQuedaEnElPasado(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $this->assertErrorEnCampo('fecha_inicio', $this->organizador->post("/torneos/$id", ['fecha_inicio' => date('Y-m-d', strtotime('-5 days'))]));
        // Si la fecha ya había vencido, se pueden cambiar otras cosas sin tocarla
        $this->fechasDelTorneo($id, -3, 20);
        $this->assertOk($this->organizador->post("/torneos/$id", ['nombre' => 'Atrasado', 'fecha_inicio' => date('Y-m-d', strtotime('-3 days'))]));
    }

    public function testLasFechasDelTorneoNoDejanAfueraPartidosProgramados(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 2); // del día +7 al +30
        $partido = $this->partidosJugables($this->organizador, $id, 1)[0]['id_enfrentamiento'];
        $this->assertOk($this->organizador->put("/enfrentamientos/$partido", ['fecha_hora' => date('Y-m-d', strtotime('+10 days')) . 'T20:00']));
        $this->assertErrorEnCampo('fecha_fin', $this->organizador->post("/torneos/$id", ['fecha_fin' => date('Y-m-d', strtotime('+8 days'))]));
        $this->assertErrorEnCampo('fecha_inicio', $this->organizador->post("/torneos/$id", ['fecha_inicio' => date('Y-m-d', strtotime('+12 days'))]));
        $this->assertOk($this->organizador->post("/torneos/$id", ['fecha_fin' => date('Y-m-d', strtotime('+12 days'))]));
    }
}
