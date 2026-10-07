<?php
// Tests de calendario, rondas, resultados y finalización de torneos.
// Incluye la regresión de los bugs B1, B2, B3, B7 y B9.

final class CompetenciaApiTest extends CasoApi
{
    private ClienteApi $organizador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizador = $this->cliente('orga', Rol::ORGANIZADOR);
    }

    // Carga resultados a todos los partidos jugables de la ronda: gana el local.
    private function jugarRonda(int $idTorneo, int $numero): void
    {
        foreach ($this->partidosJugables($this->organizador, $idTorneo, $numero) as $partido) {
            $this->assertOk($this->cargar($this->organizador, $partido['id_enfrentamiento'], 2, 1));
        }
    }

    // ── B1: los torneos individuales se pueden jugar ──

    public function testUnTorneoIndividualSeJuegaDePuntaAPunta(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 3);
        $rondas = $this->calendario(new ClienteApi(), $id);
        $this->assertCount(3, $rondas, 'Liga de 3: 3 fechas (uno descansa en cada una)');

        foreach ($rondas as $ronda) {
            $this->jugarRonda($id, (int)$ronda['numero']);
        }
        $this->assertSame('finalizado', $this->estadoTorneo($id), 'B9: la liga se finaliza sola al jugarse todo');

        $tabla = (new ClienteApi())->get("/torneos/$id/posiciones")->cuerpo['posiciones'];
        $this->assertSame([2, 2, 2], array_column($tabla, 'pj'));
        $this->assertSame(9, array_sum(array_column($tabla, 'puntos')), '3 partidos x 3 puntos');
    }

    // ── B3: el calendario no se duplica ──

    public function testElCalendarioNoSePuedeGenerarDosVeces(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $this->inscribirJugadores($id, 4);
        $this->assertOk($this->organizador->post("/torneos/$id/calendario"));
        $this->assertEstado(409, $this->organizador->post("/torneos/$id/calendario"));
        $this->assertSame(3, Ronda::cantidad($id));
    }

    public function testHacenFaltaAlMenosDosParticipantes(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $this->inscribirJugadores($id, 1);
        $this->assertEstado(409, $this->organizador->post("/torneos/$id/calendario"));
    }

    // ── Rondas: borrador, publicada, cerrada ──

    public function testElPublicoNoVeLasRondasHastaQueSePublican(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $this->inscribirJugadores($id, 4);
        $this->assertOk($this->organizador->post("/torneos/$id/calendario"));

        $this->assertCount(0, $this->calendario(new ClienteApi(), $id));
        $this->assertCount(3, $this->calendario($this->organizador, $id), 'El organizador ve los borradores');

        $primera = $this->calendario($this->organizador, $id)[0];
        $this->assertOk($this->organizador->put("/rondas/{$primera['id_ronda']}", ['estado' => 'publicada']));
        $this->assertCount(1, $this->calendario(new ClienteApi(), $id));
    }

    public function testNoSeCarganResultadosEnRondasEnBorradorNiCerradas(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $this->inscribirJugadores($id, 2);
        $this->assertOk($this->organizador->post("/torneos/$id/calendario"));
        $ronda = $this->calendario($this->organizador, $id)[0];
        $partido = $ronda['partidos'][0]['id_enfrentamiento'];

        $this->assertEstado(409, $this->cargar($this->organizador, $partido, 1, 0));

        $this->organizador->put("/rondas/{$ronda['id_ronda']}", ['estado' => 'publicada']);
        $this->assertEstado(409, $this->organizador->put("/rondas/{$ronda['id_ronda']}", ['estado' => 'cerrada']), 'Faltan resultados');
        $this->assertOk($this->cargar($this->organizador, $partido, 1, 0));
        $this->assertOk($this->organizador->put("/rondas/{$ronda['id_ronda']}", ['estado' => 'cerrada']));
        $this->assertEstado(409, $this->cargar($this->organizador, $partido, 3, 0), 'Ronda cerrada: resultados firmes');
    }

    public function testUnaRondaConResultadosNoVuelveABorrador(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 2);
        $ronda = $this->calendario($this->organizador, $id)[0];
        $this->cargar($this->organizador, $ronda['partidos'][0]['id_enfrentamiento'], 1, 1);
        $this->assertEstado(409, $this->organizador->put("/rondas/{$ronda['id_ronda']}", ['estado' => 'borrador']));
    }

    // ── Eliminación directa ──

    public function testEnLaLlaveLosPasesLibresYLosGanadoresAvanzan(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'eliminacion_directa', 6);
        [$cuartos, $semis, $final] = $this->calendario($this->organizador, $id);
        $this->assertSame(['Cuartos de final', 'Semifinal', 'Final'], [$cuartos['nombre'], $semis['nombre'], $final['nombre']]);
        $this->assertCount(2, array_filter($cuartos['partidos'], fn($p) => $p['es_pase_libre']));
        // Los dos que pasaron directo ya están en la primera semifinal
        $this->assertSame(['jugador1', 'jugador2'], [$semis['partidos'][0]['nombre_local'], $semis['partidos'][0]['nombre_visitante']]);

        $this->jugarRonda($id, 1); // ganan los locales: jugador3 y jugador5
        $semis = $this->calendario($this->organizador, $id)[1];
        $this->assertSame(['jugador3', 'jugador5'], [$semis['partidos'][1]['nombre_local'], $semis['partidos'][1]['nombre_visitante']]);

        $this->jugarRonda($id, 2);
        $this->assertSame('en_curso', $this->estadoTorneo($id));
        $this->jugarRonda($id, 3);
        $this->assertSame('finalizado', $this->estadoTorneo($id));
        $final = $this->calendario(new ClienteApi(), $id)[2]['partidos'][0];
        $this->assertSame('jugador1', $final['id_ganador'] == $final['id_local'] ? $final['nombre_local'] : $final['nombre_visitante']);
    }

    public function testEnLaLlaveNoHayEmpates(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'eliminacion_directa', 2);
        $partido = $this->partidosJugables($this->organizador, $id, 1)[0];
        $this->assertErrorEnCampo('puntaje_local', $this->cargar($this->organizador, $partido['id_enfrentamiento'], 1, 1));
    }

    public function testEnLaLlaveNoSeCorrigeSiElGanadorYaJugoElSiguientePartido(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'eliminacion_directa', 8);
        $this->autorizarCorrecciones($id);
        $cuarto = $this->partidosJugables($this->organizador, $id, 1)[0];
        $this->jugarRonda($id, 1);
        // Corregir antes de que el ganador juegue: se puede, y cambia quién pasa a la semifinal
        $this->assertOk($this->cargar($this->organizador, $cuarto['id_enfrentamiento'], 0, 3));
        $semi = $this->partidosJugables($this->organizador, $id, 2)[0];
        $this->assertSame($cuarto['nombre_visitante'], $semi['nombre_local']);
        // Después de jugar la semifinal, ya no
        $this->jugarRonda($id, 2);
        $respuesta = $this->assertEstado(409, $this->cargar($this->organizador, $cuarto['id_enfrentamiento'], 3, 0));
        $this->assertStringContainsString('ya jugó el partido siguiente', $respuesta->cuerpo['mensaje']);
    }

    // ── Sistema suizo (B2 y B7) ──

    public function testUnSuizoCompletoSinRevanchasYConPaseLibreRotativo(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'sistema_suizo', 5); // log2(5) → 3 rondas
        for ($ronda = 1; $ronda <= 3; $ronda++) {
            $this->jugarRonda($id, $ronda);
            if ($ronda < 3) {
                $estado = $this->calendario($this->organizador, $id);
                $this->assertTrue($this->organizador->get("/torneos/$id/calendario")->cuerpo['puede_generar_siguiente'],
                    'B2: la página tiene que ofrecer generar la ronda siguiente aunque el torneo ya esté en curso');
                $this->assertEstado(201, $this->organizador->post("/torneos/$id/rondas"));
                $this->assertOk($this->organizador->post("/torneos/$id/rondas/publicar"));
            }
        }
        $this->assertSame('finalizado', $this->estadoTorneo($id));
        $this->assertEstado(409, $this->organizador->post("/torneos/$id/rondas"), 'Ya se jugaron todas las rondas');

        $partidos = array_merge(...array_column($this->calendario(new ClienteApi(), $id), 'partidos'));
        $cruces = array_map(fn($p) => min($p['id_local'], $p['id_visitante']) . '-' . max($p['id_local'], $p['id_visitante']),
                            array_filter($partidos, fn($p) => !$p['es_pase_libre']));
        $this->assertSame(count($cruces), count(array_unique($cruces)), 'Nadie se enfrenta dos veces');
        $libres = array_column(array_filter($partidos, fn($p) => $p['es_pase_libre']), 'id_local');
        $this->assertCount(3, $libres);
        $this->assertSame(3, count(array_unique($libres)), 'B7: el pase libre le toca a uno distinto cada ronda');

        $tabla = (new ClienteApi())->get("/torneos/$id/posiciones")->cuerpo;
        $this->assertTrue($tabla['buchholz']);
        $this->assertArrayHasKey('buchholz', $tabla['posiciones'][0]);
        $this->assertSame(range(1, 5), array_column($tabla['posiciones'], 'posicion'));
    }

    public function testElSuizoNoAvanzaConResultadosPendientes(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'sistema_suizo', 4);
        $this->assertFalse($this->organizador->get("/torneos/$id/calendario")->cuerpo['puede_generar_siguiente']);
        $this->assertEstado(409, $this->organizador->post("/torneos/$id/rondas"));
    }

    // ── Correcciones ──

    public function testUnaCorreccionGuardaElHistorialYActualizaLaTabla(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 3);
        $this->autorizarCorrecciones($id);
        $partido = $this->partidosJugables($this->organizador, $id, 1)[0]['id_enfrentamiento'];
        $this->assertOk($this->cargar($this->organizador, $partido, 0, 2));
        $respuesta = $this->assertOk($this->cargar($this->organizador, $partido, 3, 2));
        $this->assertTrue($respuesta->cuerpo['es_correccion']);

        $historial = $this->organizador->get("/enfrentamientos/$partido/resultados")->cuerpo['resultados'];
        $this->assertSame([[3, 2, 1], [0, 2, 0]], array_map(fn($r) => [$r['puntaje_local'], $r['puntaje_visitante'], $r['vigente']], $historial));
        $this->assertSame(3, (new ClienteApi())->get("/torneos/$id/posiciones")->cuerpo['posiciones'][0]['puntos']);
    }

    public function testSiElTorneoNoPermiteCorreccionesSoloElAdminCorrige(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 3, ['permitir_correcciones' => '0']);
        $partido = $this->partidosJugables($this->organizador, $id, 1)[0]['id_enfrentamiento'];
        $this->assertOk($this->cargar($this->organizador, $partido, 1, 0));
        $this->assertEstado(403, $this->cargar($this->organizador, $partido, 0, 1));
        $this->assertOk($this->cargar($this->admin(), $partido, 0, 1));
    }

    public function testUnOrganizadorAjenoNoCargaResultados(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 2);
        $partido = $this->partidosJugables($this->organizador, $id, 1)[0]['id_enfrentamiento'];
        $this->assertEstado(403, $this->cargar($this->cliente('ajeno', Rol::ORGANIZADOR), $partido, 1, 0));
        $this->assertEstado(403, $this->cargar($this->cliente('jugadorx'), $partido, 1, 0));
    }

    // ── Programación y portada ──

    public function testSeProgramaFechaHoraYLugarDeUnPartido(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 2); // del día +7 al +30
        $partido = $this->partidosJugables($this->organizador, $id, 1)[0]['id_enfrentamiento'];
        $this->assertErrorEnCampo('fecha_hora', $this->organizador->put("/enfrentamientos/$partido", ['fecha_hora' => '32/13/2026']));
        $dia = date('Y-m-d', strtotime('+10 days'));
        $this->assertOk($this->organizador->put("/enfrentamientos/$partido", ['fecha_hora' => "{$dia}T19:30", 'lugar' => 'Cancha 2']));

        $programado = $this->partidosJugables(new ClienteApi(), $id, 1)[0];
        $this->assertSame(["$dia 19:30:00", 'Cancha 2'], [$programado['fecha_hora'], $programado['lugar']]);
        $this->assertSame('Cancha 2', (new ClienteApi())->get('/partidos/proximos')->cuerpo['partidos'][0]['lugar']);
    }

    // Un partido (o una ronda) se programa dentro de las fechas del torneo.
    public function testNoSeProgramaFueraDeLasFechasDelTorneo(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 2); // del día +7 al +30
        $partido = $this->partidosJugables($this->organizador, $id, 1)[0];
        foreach (['+2 days', '+40 days'] as $cuando) {
            $fecha = date('Y-m-d', strtotime($cuando)) . 'T20:00';
            $this->assertErrorEnCampo('fecha_hora', $this->organizador->put("/enfrentamientos/{$partido['id_enfrentamiento']}", ['fecha_hora' => $fecha]));
        }
        $idRonda = $this->calendario($this->organizador, $id)[0]['id_ronda'];
        $this->assertErrorEnCampo('fecha', $this->organizador->put("/rondas/$idRonda", ['fecha' => date('Y-m-d', strtotime('+40 days'))]));
        $this->assertOk($this->organizador->put("/rondas/$idRonda", ['fecha' => date('Y-m-d', strtotime('+8 days'))]));
    }

    // Un partido que según su fecha todavía no se jugó no puede tener resultado.
    public function testNoSeCargaElResultadoDeUnPartidoQueTodaviaNoSeJugo(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 3);
        $partido = $this->partidosJugables($this->organizador, $id, 1)[0]['id_enfrentamiento'];
        $this->assertOk($this->organizador->put("/enfrentamientos/$partido", ['fecha_hora' => date('Y-m-d', strtotime('+10 days')) . 'T20:00']));
        $respuesta = $this->assertEstado(409, $this->cargar($this->organizador, $partido, 2, 0));
        $this->assertStringContainsString('cambiá primero la fecha', $respuesta->cuerpo['mensaje']);

        // Si el torneo ya empezó y el partido fue ayer, sí
        $this->fechasDelTorneo($id, -3, 20);
        $this->assertOk($this->organizador->put("/enfrentamientos/$partido", ['fecha_hora' => date('Y-m-d', strtotime('-1 day')) . 'T20:00']));
        $this->assertOk($this->cargar($this->organizador, $partido, 2, 0));
    }

    // Al terminar, las rondas quedan cerradas (resultados firmes). Corregir
    // después pide reabrir la ronda, y el mensaje no vuelve a decir que terminó.
    public function testAlTerminarElTorneoSeCierranSusRondas(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 2);
        $partido = $this->partidosJugables($this->organizador, $id, 1)[0];
        $this->assertTrue($this->assertOk($this->cargar($this->organizador, $partido['id_enfrentamiento'], 2, 1))->cuerpo['torneo_finalizado']);
        $this->assertSame(['cerrada'], array_column($this->calendario($this->organizador, $id), 'estado'));
        $this->assertEstado(409, $this->cargar($this->organizador, $partido['id_enfrentamiento'], 1, 2));

        $admin = $this->admin();
        $this->assertOk($admin->put("/rondas/{$partido['id_ronda']}", ['estado' => 'publicada']));
        $correccion = $this->assertOk($this->cargar($admin, $partido['id_enfrentamiento'], 1, 2));
        $this->assertTrue($correccion->cuerpo['es_correccion']);
        $this->assertFalse($correccion->cuerpo['torneo_finalizado']);
        $this->assertStringNotContainsString('terminó', $correccion->cuerpo['mensaje']);
        $this->assertSame('finalizado', $this->estadoTorneo($id));
    }

    // "Próximos" son los partidos de hoy en adelante (o sin fecha) de torneos
    // no cancelados. Un torneo cancelado conserva su calendario como historial.
    public function testLosProximosSonDesdeHoyYSinTorneosCancelados(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 4);
        $this->fechasDelTorneo($id, -5, 20);
        [$atrasado, $futuro] = $this->partidosJugables($this->organizador, $id, 1);
        $this->assertOk($this->organizador->put("/enfrentamientos/{$atrasado['id_enfrentamiento']}", ['fecha_hora' => date('Y-m-d', strtotime('-2 days')) . 'T20:00', 'lugar' => 'Atrasado']));
        $this->assertOk($this->organizador->put("/enfrentamientos/{$futuro['id_enfrentamiento']}", ['fecha_hora' => date('Y-m-d', strtotime('+2 days')) . 'T20:00', 'lugar' => 'Futuro']));

        $lugares = fn(array $partidos) => array_column($partidos, 'lugar');
        $proximos = (new ClienteApi())->get('/partidos/proximos?por_pagina=50')->cuerpo['partidos'];
        $this->assertContains('Futuro', $lugares($proximos));
        $this->assertNotContains('Atrasado', $lugares($proximos));
        $jugador = ClienteApi::como((int)Usuario::buscarPorNombre($atrasado['nombre_local'])['id_usuario']);
        $this->assertNotContains('Atrasado', $lugares($jugador->get('/perfil/partidos')->cuerpo['partidos']));

        // Cancelado: sale de los próximos, pero su calendario sigue siendo público
        $this->assertOk($this->organizador->put("/enfrentamientos/{$atrasado['id_enfrentamiento']}", ['fecha_hora' => date('Y-m-d', strtotime('-2 days')) . 'T20:00']));
        $this->assertOk($this->cargar($this->organizador, $atrasado['id_enfrentamiento'], 1, 0));
        $this->assertOk($this->organizador->post("/torneos/$id/cancelar"));
        $publico = new ClienteApi();
        $this->assertNotContains('Futuro', $lugares($publico->get('/partidos/proximos?por_pagina=50')->cuerpo['partidos']));
        $this->assertSame([], array_filter($publico->get('/partidos/recientes?por_pagina=50')->cuerpo['partidos'], fn($p) => (int)$p['id_torneo'] === $id));
        $this->assertCount(2, $this->partidosJugables($publico, $id, 1), 'El calendario del torneo cancelado sigue a la vista');
    }

    public function testLaPortadaSoloMuestraPartidosPublicos(): void
    {
        $id = $this->crearTorneo($this->organizador);
        $this->inscribirJugadores($id, 2);
        $this->assertOk($this->organizador->post("/torneos/$id/calendario"));
        $this->assertCount(0, (new ClienteApi())->get('/partidos/proximos')->cuerpo['partidos']);
        $this->assertOk($this->organizador->post("/torneos/$id/rondas/publicar"));
        $this->assertCount(1, (new ClienteApi())->get('/partidos/proximos')->cuerpo['partidos']);

        $this->jugarRonda($id, 1);
        $this->assertCount(1, (new ClienteApi())->get('/partidos/recientes')->cuerpo['partidos']);
    }

    public function testElCalendarioPublicoSePaginaYSeFiltraPorJuego(): void
    {
        $ajedrez = $this->torneoEnJuego($this->organizador, 'liga', 4, ['id_juego' => 28]);  // 6 partidos
        $futbol = $this->crearTorneo($this->organizador, ['id_juego' => 1]);                  // 1 partido
        $this->inscribirJugadores($futbol, 2, 'futbolista');
        $this->assertOk($this->organizador->post("/torneos/$futbol/calendario"));
        $this->assertOk($this->organizador->post("/torneos/$futbol/rondas/publicar"));
        $publico = new ClienteApi();

        $pagina = $publico->get('/partidos/proximos?por_pagina=4&pagina=2')->cuerpo;
        $this->assertCount(3, $pagina['partidos']);
        $this->assertSame(['pagina' => 2, 'por_pagina' => 4, 'total' => 7, 'paginas' => 2], $pagina['paginacion']);

        $soloAjedrez = $publico->get('/partidos/proximos?id_juego=28&por_pagina=50')->cuerpo;
        $this->assertSame(6, $soloAjedrez['paginacion']['total']);
        $this->assertSame([$ajedrez], array_values(array_unique(array_column($soloAjedrez['partidos'], 'id_torneo'))));
    }

    public function testGenerarElCalendarioQuedaRegistradoEnLaAuditoria(): void
    {
        $id = $this->torneoEnJuego($this->organizador, 'liga', 4);
        $registro = $this->ultimaAuditoria('generar_calendario');
        $this->assertSame((string)$id, $registro['id_registro']);
        $this->assertSame((int)$this->organizador->sesion['id_usuario'], (int)$registro['id_usuario']);
    }
}
