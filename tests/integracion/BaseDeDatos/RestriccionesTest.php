<?php
// Verifica que la base rechaza datos inválidos por sí misma (CHECK,
// UNIQUE, claves foráneas y modo estricto), aunque la aplicación falle.

final class RestriccionesTest extends CasoBaseDeDatos
{
    // ── Participantes ──

    public function testUnParticipanteNoPuedeSerPersonaYEquipoALaVez(): void
    {
        $torneo = $this->crearTorneo(['modalidad' => 'equipos']);
        $equipo = $this->insertar("INSERT INTO equipos (id_torneo, nombre) VALUES (?, 'Los Pumas')", [$torneo]);
        $this->assertFallaSql(self::ERROR_CHECK,
            "INSERT INTO participantes (id_torneo, id_usuario, id_equipo) VALUES (?, 1, ?)", [$torneo, $equipo]);
    }

    public function testUnParticipanteTieneQueSerPersonaOEquipo(): void
    {
        $torneo = $this->crearTorneo();
        $this->assertFallaSql(self::ERROR_CHECK, "INSERT INTO participantes (id_torneo) VALUES (?)", [$torneo]);
    }

    public function testUnaPersonaNoSePuedeInscribirDosVecesAlMismoTorneo(): void
    {
        $torneo = $this->crearTorneo();
        $ana = $this->crearUsuario('ana');
        $this->inscribirUsuario($torneo, $ana);
        $this->assertFallaSql(self::ERROR_DUPLICADO, "INSERT INTO participantes (id_torneo, id_usuario) VALUES (?, ?)", [$torneo, $ana]);
    }

    public function testLaMismaPersonaSePuedeInscribirAOtroTorneo(): void
    {
        $ana = $this->crearUsuario('ana');
        $this->inscribirUsuario($this->crearTorneo(), $ana);
        $this->assertGreaterThan(0, $this->inscribirUsuario($this->crearTorneo(), $ana));
    }

    public function testUnEquipoParticipaUnaSolaVez(): void
    {
        $torneo = $this->crearTorneo(['modalidad' => 'equipos']);
        $equipo = $this->insertar("INSERT INTO equipos (id_torneo, nombre) VALUES (?, 'Los Pumas')", [$torneo]);
        $this->insertar("INSERT INTO participantes (id_torneo, id_equipo) VALUES (?, ?)", [$torneo, $equipo]);
        $this->assertFallaSql(self::ERROR_DUPLICADO, "INSERT INTO participantes (id_torneo, id_equipo) VALUES (?, ?)", [$torneo, $equipo]);
    }

    public function testNoHayDosEquiposConElMismoNombreEnUnTorneo(): void
    {
        $torneo = $this->crearTorneo(['modalidad' => 'equipos']);
        $this->inscribirEquipo($torneo, 'Los Pumas');
        $this->assertFallaSql(self::ERROR_DUPLICADO, "INSERT INTO equipos (id_torneo, nombre) VALUES (?, 'Los Pumas')", [$torneo]);
        // En otro torneo el nombre está libre
        $this->assertGreaterThan(0, $this->inscribirEquipo($this->crearTorneo(['modalidad' => 'equipos']), 'Los Pumas'));
    }

    // ── Torneos y configuración ──

    public function testLaFechaDeFinNoPuedeSerAnteriorALaDeInicio(): void
    {
        $this->expectException(PDOException::class);
        $this->crearTorneo(['fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-11-10']);
    }

    public function testElCupoTieneQueEstarEntre2Y256(): void
    {
        foreach ([1, 257] as $cupo) {
            try {
                $this->crearTorneo(['cupo' => $cupo]);
                $this->fail("Aceptó un cupo de $cupo");
            } catch (PDOException $e) {
                $this->assertContains((int)$e->errorInfo[1], self::ERROR_CHECK);
            }
        }
        $this->assertGreaterThan(0, $this->crearTorneo(['cupo' => 2]));
        $this->assertGreaterThan(0, $this->crearTorneo(['cupo' => 256]));
    }

    public function testElPremioNoPuedeSerNegativo(): void
    {
        $torneo = $this->crearTorneo();
        $this->assertFallaSql(self::ERROR_CHECK, "UPDATE torneos SET premio = -1 WHERE id_torneo = ?", [$torneo]);
    }

    public function testLosPuntosPorEmpateNoPuedenSuperarALosDeVictoria(): void
    {
        $torneo = $this->crearTorneo();
        $this->assertFallaSql(self::ERROR_CHECK,
            "UPDATE configuracion_torneo SET puntos_victoria = 1, puntos_empate = 2 WHERE id_torneo = ?", [$torneo]);
    }

    public function testElModoEstrictoRechazaUnEstadoQueNoExiste(): void
    {
        $torneo = $this->crearTorneo();
        $this->assertFallaSql(self::ERROR_DATO_INVALIDO, "UPDATE torneos SET estado = 'jugando' WHERE id_torneo = ?", [$torneo]);
    }

    public function testUnTorneoNecesitaUnJuegoQueExista(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionCode('23000');
        $this->crearTorneo(['id_juego' => 9999]);
    }

    // ── Rondas, partidos y resultados ──

    public function testNoSePuedeGenerarDosVecesLaMismaRonda(): void
    {
        $torneo = $this->crearTorneo();
        $this->crearRonda($torneo, 1);
        $this->assertFallaSql(self::ERROR_DUPLICADO, "INSERT INTO rondas (id_torneo, numero) VALUES (?, 1)", [$torneo]);
    }

    public function testLaRondaEmpiezaEnUno(): void
    {
        $torneo = $this->crearTorneo();
        $this->assertFallaSql(self::ERROR_CHECK, "INSERT INTO rondas (id_torneo, numero) VALUES (?, 0)", [$torneo]);
    }

    public function testDosPartidosNoOcupanLaMismaPosicionDeUnaRonda(): void
    {
        $ronda = $this->crearRonda($this->crearTorneo(), 1);
        $this->crearPartido($ronda, 0, null, null);
        $this->assertFallaSql(self::ERROR_DUPLICADO,
            "INSERT INTO enfrentamientos (id_ronda, posicion) VALUES (?, 0)", [$ronda]);
    }

    public function testUnResultadoNoPuedeSerNegativo(): void
    {
        $partido = $this->crearPartido($this->crearRonda($this->crearTorneo(), 1), 0, null, null);
        $this->assertFallaSql(self::ERROR_CHECK,
            "INSERT INTO resultados (id_enfrentamiento, puntaje_local, puntaje_visitante) VALUES (?, -1, 2)", [$partido]);
    }

    // ── Usuarios ──

    public function testElNombreDeUsuarioYElCorreoSonUnicos(): void
    {
        $this->crearUsuario('ana');
        $this->assertFallaSql(self::ERROR_DUPLICADO,
            "INSERT INTO usuarios (nombre_usuario, correo, contrasena_hash) VALUES ('ana', 'otro@x.com', 'h')");
        $this->assertFallaSql(self::ERROR_DUPLICADO,
            "INSERT INTO usuarios (nombre_usuario, correo, contrasena_hash) VALUES ('otra', 'ana@prueba.uy', 'h')");
    }

    public function testElNombreDeUsuarioTieneAlMenosTresLetras(): void
    {
        $this->assertFallaSql(self::ERROR_CHECK,
            "INSERT INTO usuarios (nombre_usuario, correo, contrasena_hash) VALUES ('ab', 'ab@x.com', 'h')");
    }

    public function testNoSePuedeBorrarUnUsuarioQueOrganizaTorneos(): void
    {
        $organizador = $this->crearUsuario('orga', 2);
        $this->crearTorneo(['id_organizador' => $organizador]);
        $this->assertFallaSql(self::ERROR_FK_PADRE, "DELETE FROM usuarios WHERE id_usuario = ?", [$organizador]);
    }

    // ── Borrado en cascada ──

    public function testBorrarUnTorneoBorraTodoLoQueDependeDeEl(): void
    {
        $torneo = $this->crearTorneo(['modalidad' => 'equipos']);
        $a = $this->inscribirEquipo($torneo, 'Azules');
        $b = $this->inscribirEquipo($torneo, 'Blancos');
        $ronda = $this->crearRonda($torneo, 1);
        $partido = $this->crearPartido($ronda, 0, $a, $b);
        $this->cargarResultado($partido, 1, 0);

        $this->pdo->prepare("DELETE FROM torneos WHERE id_torneo = ?")->execute([$torneo]);

        foreach (['configuracion_torneo' => 'id_torneo', 'equipos' => 'id_torneo', 'participantes' => 'id_torneo', 'rondas' => 'id_torneo'] as $tabla => $columna) {
            $this->assertSame(0, (int)$this->valor("SELECT COUNT(*) FROM $tabla WHERE $columna = ?", [$torneo]), $tabla);
        }
        $this->assertSame(0, (int)$this->valor("SELECT COUNT(*) FROM enfrentamientos WHERE id_enfrentamiento = ?", [$partido]));
        $this->assertSame(0, (int)$this->valor("SELECT COUNT(*) FROM resultados WHERE id_enfrentamiento = ?", [$partido]));
    }
}
