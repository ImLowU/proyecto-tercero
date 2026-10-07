<?php
// Tests de inscripciones individuales, equipos y solicitudes.

final class InscripcionApiTest extends CasoApi
{
    private ClienteApi $organizador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizador = $this->cliente('orga', Rol::ORGANIZADOR);
    }

    public function testUnaPersonaSeInscribeAUnTorneoIndividual(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $ana = $this->cliente('ana');
        $this->assertEstado(201, $ana->post("/torneos/$id/inscripcion"));
        $this->assertSame(['ana'], array_column($ana->get("/torneos/$id/participantes")->cuerpo['participantes'], 'nombre'));
        $this->assertTrue($ana->get("/torneos/$id")->cuerpo['torneo']['permisos']['estoy_inscripto']);
    }

    public function testNoSeSuperaElCupoDeUnTorneoIndividual(): void
    {
        $id = $this->crearTorneo($this->organizador, ['cupo' => 2]);
        $this->inscribirJugadores($id, 2);
        $this->assertEstado(409, $this->cliente('tercero')->post("/torneos/$id/inscripcion"));
    }

    public function testElOrganizadorNoJuegaSuPropioTorneo(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $this->assertEstado(409, $this->organizador->post("/torneos/$id/inscripcion"));
    }

    public function testNoSePuedeInscribirDosVeces(): void
    {
        $id = $this->crearTorneo($this->organizador);
        [$ana] = $this->inscribirJugadores($id, 1);
        $this->assertEstado(409, $ana->post("/torneos/$id/inscripcion"));
    }

    public function testEnUnTorneoPorEquiposSeEntraConUnEquipo(): void
    {
        $id = $this->crearTorneo($this->organizador, ['id_juego' => 1, 'modalidad' => 'equipos']);
        $ana = $this->cliente('ana');
        $this->assertEstado(409, $ana->post("/torneos/$id/inscripcion"));

        $this->assertEstado(201, $ana->post("/torneos/$id/equipos", ['nombre' => 'Los Pumas']));
        $participantes = $ana->get("/torneos/$id/participantes")->cuerpo['participantes'];
        $this->assertSame([['Los Pumas', 'equipo', 1]], array_map(fn($p) => [$p['nombre'], $p['tipo'], $p['cantidad_miembros']], $participantes));
    }

    public function testElCapitanAceptaSolicitudesYSoloEl(): void
    {
        $id = $this->crearTorneo($this->organizador, ['id_juego' => 1, 'modalidad' => 'equipos']);
        $capitan = $this->cliente('capitan');
        $idEquipo = $capitan->post("/torneos/$id/equipos", ['nombre' => 'Los Pumas'])->cuerpo['id_equipo'];

        $beto = $this->cliente('beto');
        $idSolicitud = $this->assertEstado(201, $beto->post("/equipos/$idEquipo/solicitudes"))->cuerpo['id_solicitud'];
        $this->assertEstado(409, $beto->post("/equipos/$idEquipo/solicitudes"), 'No se duplican las solicitudes');

        $this->assertEstado(403, $beto->get("/equipos/$idEquipo/solicitudes"));
        $this->assertEstado(403, $this->cliente('intruso')->post("/solicitudes/$idSolicitud/respuesta", ['accion' => 'aceptar']));

        $this->assertCount(1, $capitan->get("/equipos/$idEquipo/solicitudes")->cuerpo['solicitudes']);
        $this->assertOk($capitan->post("/solicitudes/$idSolicitud/respuesta", ['accion' => 'aceptar']));
        $this->assertSame(['capitan', 'beto'], array_column((new ClienteApi())->get("/equipos/$idEquipo")->cuerpo['equipo']['miembros'], 'nombre_usuario'));
        $this->assertEstado(409, $capitan->post("/solicitudes/$idSolicitud/respuesta", ['accion' => 'rechazar']), 'Ya fue respondida');
    }

    public function testNoSePuedePedirUnirseAUnEquipoSinCapitan(): void
    {
        $id = $this->crearTorneo($this->organizador, ['id_juego' => 1, 'modalidad' => 'equipos']);
        $idEquipo = $this->organizador->post("/torneos/$id/participantes", ['nombre_equipo' => 'Sin capitán'])->cuerpo['id_equipo'];
        $this->assertEstado(409, $this->cliente('beto')->post("/equipos/$idEquipo/solicitudes"));
    }

    public function testNadieJuegaEnDosEquiposDelMismoTorneo(): void
    {
        $id = $this->crearTorneo($this->organizador, ['id_juego' => 1, 'modalidad' => 'equipos']);
        $ana = $this->cliente('ana');
        $ana->post("/torneos/$id/equipos", ['nombre' => 'Los Pumas']);
        $otro = $this->cliente('otro')->post("/torneos/$id/equipos", ['nombre' => 'Los Teros'])->cuerpo['id_equipo'];
        $this->assertEstado(409, $ana->post("/equipos/$otro/solicitudes"));
        $this->assertEstado(409, $ana->post("/torneos/$id/equipos", ['nombre' => 'Tercer equipo']));
    }

    public function testRespetaElMaximoDeIntegrantes(): void
    {
        $id = $this->crearTorneo($this->organizador, ['id_juego' => 1, 'modalidad' => 'equipos', 'max_integrantes_equipo' => 1]);
        $idEquipo = $this->cliente('capitan')->post("/torneos/$id/equipos", ['nombre' => 'Solitarios'])->cuerpo['id_equipo'];
        $this->assertEstado(409, $this->cliente('beto')->post("/equipos/$idEquipo/solicitudes"));
    }

    public function testElOrganizadorInscribeYQuitaParticipantes(): void
    {
        $individual = $this->crearTorneo($this->organizador);
        $this->crearUsuario('ana');
        $this->assertEstado(201, $this->organizador->post("/torneos/$individual/participantes", ['nombre_usuario' => 'ana']));
        $this->assertErrorEnCampo('nombre_usuario', $this->organizador->post("/torneos/$individual/participantes", ['nombre_usuario' => 'no_existe']));

        $porEquipos = $this->crearTorneo($this->organizador, ['id_juego' => 1, 'modalidad' => 'equipos']);
        $this->crearUsuario('capi');
        $respuesta = $this->assertEstado(201, $this->organizador->post("/torneos/$porEquipos/participantes", ['nombre_equipo' => 'Halcones', 'capitan' => 'capi']));
        $idParticipante = $respuesta->cuerpo['id_participante'];

        $this->assertOk($this->organizador->delete("/torneos/$porEquipos/participantes/$idParticipante"));
        $this->assertSame(0, (int)BaseDatos::valor("SELECT COUNT(*) FROM equipos WHERE id_torneo = ?", [$porEquipos]), 'Se borra también el equipo');
    }

    public function testDarseDeBaja(): void
    {
        $individual = $this->crearTorneo($this->organizador);
        [$ana] = $this->inscribirJugadores($individual, 1);
        $this->assertOk($ana->delete("/torneos/$individual/inscripcion"));
        $this->assertSame(0, Participante::cantidad($individual));

        $porEquipos = $this->crearTorneo($this->organizador, ['id_juego' => 1, 'modalidad' => 'equipos']);
        $capitan = $this->cliente('capitan');
        $idEquipo = $capitan->post("/torneos/$porEquipos/equipos", ['nombre' => 'Pumas'])->cuerpo['id_equipo'];
        $beto = $this->cliente('beto');
        $solicitud = $beto->post("/equipos/$idEquipo/solicitudes")->cuerpo['id_solicitud'];
        $capitan->post("/solicitudes/$solicitud/respuesta", ['accion' => 'aceptar']);

        $this->assertEstado(409, $capitan->delete("/torneos/$porEquipos/inscripcion"), 'El capitán no abandona un equipo con integrantes');
        $this->assertOk($beto->delete("/torneos/$porEquipos/inscripcion"));
        $this->assertSame(1, Equipo::cantidadMiembros($idEquipo));
    }

    // Se puede anotar hasta el día de inicio inclusive; al día siguiente, si
    // el organizador no generó el calendario, la inscripción se cierra sola.
    public function testLaInscripcionSeCierraSolaDespuesDeLaFechaDeInicio(): void
    {
        $id = $this->crearTorneo($this->organizador, ['cupo' => 8]);
        $this->fechasDelTorneo($id, 0, 20);
        // El mismo día de inicio todavía se puede
        $this->assertOk($this->cliente('hoy1')->post("/torneos/$id/inscripcion"));
        $this->assertOk($this->cliente('hoy2')->post("/torneos/$id/inscripcion"));

        $this->fechasDelTorneo($id, -1, 20);
        $tarde = $this->cliente('tarde');
        $respuesta = $this->assertEstado(409, $tarde->post("/torneos/$id/inscripcion"));
        $this->assertStringContainsString('se cerraron', $respuesta->cuerpo['mensaje']);
        $this->assertFalse($tarde->get("/torneos/$id")->cuerpo['torneo']['permisos']['inscribirse']);
        $this->crearUsuario('otro');
        $this->assertEstado(409, $this->organizador->post("/torneos/$id/participantes", ['nombre_usuario' => 'otro']));

        // El público ya no lo ve como "inscripción abierta" ni se destaca en la portada
        $publico = new ClienteApi();
        $this->assertSame(0, $publico->get('/torneos?estado=pendiente')->cuerpo['paginacion']['total']);
        $this->assertNull($publico->get('/torneos/destacado')->cuerpo['torneo']);

        // Con los que ya estaban anotados, el organizador puede empezar
        $this->assertOk($this->organizador->post("/torneos/$id/calendario"));
    }

    // Si el organizador corre la fecha de inicio, la inscripción se abre de nuevo.
    public function testCambiarLaFechaDeInicioReabreLaInscripcion(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $this->fechasDelTorneo($id, -2, 20);
        $this->assertEstado(409, $this->cliente('ana')->post("/torneos/$id/inscripcion"));
        $this->assertOk($this->organizador->post("/torneos/$id", ['fecha_inicio' => date('Y-m-d', strtotime('+3 days'))]));
        $this->assertOk($this->cliente('beto')->post("/torneos/$id/inscripcion"));
    }

    // En los torneos por equipos, tampoco se crean equipos ni se suman integrantes.
    public function testDespuesDeLaFechaDeInicioNoSeArmanEquipos(): void
    {
        $id = $this->crearTorneo($this->organizador, ['modalidad' => 'equipos', 'id_juego' => 2]);
        $capitan = $this->cliente('capitan');
        $idEquipo = $this->assertOk($capitan->post("/torneos/$id/equipos", ['nombre' => 'Tigres']))->cuerpo['id_equipo'];
        $idSolicitud = $this->assertOk($this->cliente('ana')->post("/equipos/$idEquipo/solicitudes"))->cuerpo['id_solicitud'];

        $this->fechasDelTorneo($id, -1, 20);
        $this->assertEstado(409, $this->cliente('beto')->post("/torneos/$id/equipos", ['nombre' => 'Leones']));
        $this->assertEstado(409, $this->cliente('carla')->post("/equipos/$idEquipo/solicitudes"));
        $this->assertEstado(409, $capitan->post("/solicitudes/$idSolicitud/respuesta", ['accion' => 'aceptar']));
    }

    public function testDespuesDeGenerarElCalendarioSeCierranLasInscripciones(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 2);
        $this->assertEstado(409, $this->cliente('tarde')->post("/torneos/$id/inscripcion"));
        $this->assertEstado(409, $this->organizador->post("/torneos/$id/participantes", ['nombre_usuario' => 'jugador1']));
    }
}
