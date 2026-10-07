<?php
// Verifica las vistas, en especial la tabla de posiciones calculada.

final class VistasTest extends CasoBaseDeDatos
{
    // Liga individual de 3 jugadores, ronda publicada. Devuelve [torneo, ronda, [ana, beto, caro]].
    private function ligaDeTres(array $configuracion = []): array
    {
        $torneo = $this->crearTorneo(['configuracion' => $configuracion]);
        $jugadores = [];
        foreach (['ana', 'beto', 'caro'] as $nombre) {
            $jugadores[] = $this->inscribirUsuario($torneo, $this->crearUsuario($nombre));
        }
        return [$torneo, $this->crearRonda($torneo, 1), $jugadores];
    }

    // ── Tabla de posiciones ──

    public function testLaTablaCuentaVictoriasEmpatesDerrotasYGoles(): void
    {
        [$torneo, $ronda, [$ana, $beto, $caro]] = $this->ligaDeTres();
        $this->cargarResultado($this->crearPartido($ronda, 0, $ana, $beto), 3, 1);
        $ronda2 = $this->crearRonda($torneo, 2);
        $this->cargarResultado($this->crearPartido($ronda2, 0, $beto, $caro), 2, 2);
        $ronda3 = $this->crearRonda($torneo, 3);
        $this->cargarResultado($this->crearPartido($ronda3, 0, $caro, $ana), 0, 1);

        // Beto y Caro empatan en puntos: desempata la diferencia de goles.
        $this->assertSame([
            ['nombre' => 'ana',  'pj' => 2, 'pg' => 2, 'pe' => 0, 'pp' => 0, 'gf' => 4, 'gc' => 1, 'diferencia' => 3,  'puntos' => 6],
            ['nombre' => 'caro', 'pj' => 2, 'pg' => 0, 'pe' => 1, 'pp' => 1, 'gf' => 2, 'gc' => 3, 'diferencia' => -1, 'puntos' => 1],
            ['nombre' => 'beto', 'pj' => 2, 'pg' => 0, 'pe' => 1, 'pp' => 1, 'gf' => 3, 'gc' => 5, 'diferencia' => -2, 'puntos' => 1],
        ], $this->tablaDe($torneo));
    }

    public function testUsaLosPuntosConfiguradosEnCadaTorneo(): void
    {
        [$torneo, $ronda, [$ana, $beto]] = $this->ligaDeTres(['puntos_victoria' => 2, 'puntos_empate' => 1, 'puntos_derrota' => 0]);
        $this->cargarResultado($this->crearPartido($ronda, 0, $ana, $beto), 1, 0);
        $this->assertSame(2, $this->tablaDe($torneo)[0]['puntos']);
    }

    public function testElPaseLibreCuentaComoVictoriaSinGoles(): void
    {
        [$torneo, $ronda, [, , $caro]] = $this->ligaDeTres();
        $this->crearPaseLibre($ronda, 0, $caro);
        $fila = $this->filaDe($torneo, 'caro');
        $this->assertSame([1, 1, 0, 0, 3], [$fila['pj'], $fila['pg'], $fila['gf'], $fila['gc'], $fila['puntos']]);
    }

    public function testAlCorregirUnResultadoSoloCuentaElVigente(): void
    {
        [$torneo, $ronda, [$ana, $beto]] = $this->ligaDeTres();
        $partido = $this->crearPartido($ronda, 0, $ana, $beto);
        $this->cargarResultado($partido, 0, 1);  // se cargó mal: ganaba beto
        $this->cargarResultado($partido, 2, 1);  // corrección: ganó ana

        $this->assertSame(3, $this->filaDe($torneo, 'ana')['puntos']);
        $this->assertSame(0, $this->filaDe($torneo, 'beto')['puntos']);
        $this->assertSame(1, $this->filaDe($torneo, 'ana')['pj'], 'El partido corregido no se cuenta dos veces');
        $this->assertSame(2, (int)$this->valor("SELECT COUNT(*) FROM resultados WHERE id_enfrentamiento = ?", [$partido]), 'Se guarda el historial');
    }

    public function testUnPartidoPendienteNoSuma(): void
    {
        [$torneo, $ronda, [$ana, $beto]] = $this->ligaDeTres();
        $this->crearPartido($ronda, 0, $ana, $beto);
        foreach ($this->tablaDe($torneo) as $fila) {
            $this->assertSame([0, 0], [$fila['pj'], $fila['puntos']]);
        }
    }

    public function testEnTorneosPorEquiposMuestraElNombreDelEquipo(): void
    {
        $torneo = $this->crearTorneo(['modalidad' => 'equipos']);
        $a = $this->inscribirEquipo($torneo, 'Tigres');
        $b = $this->inscribirEquipo($torneo, 'Leones');
        $this->cargarResultado($this->crearPartido($this->crearRonda($torneo, 1), 0, $a, $b), 2, 0);
        $this->assertSame(['Tigres', 'Leones'], array_column($this->tablaDe($torneo), 'nombre'));
    }

    // ── Calendario: qué ve el público ──

    public function testElPublicoSoloVeRondasPublicadasDeTorneosPublicados(): void
    {
        [$torneo, , [$ana, $beto]] = $this->ligaDeTres();
        $borrador = $this->crearPartido($this->crearRonda($torneo, 2, 'borrador'), 0, $ana, $beto);
        $publicada = $this->crearPartido($this->crearRonda($torneo, 3, 'publicada'), 0, $beto, $ana);
        $cerrada = $this->crearPartido($this->crearRonda($torneo, 4, 'cerrada'), 0, $ana, $beto);

        $this->assertSame(0, $this->esPublico($borrador));
        $this->assertSame(1, $this->esPublico($publicada));
        $this->assertSame(1, $this->esPublico($cerrada));

        $this->pdo->prepare("UPDATE torneos SET publicado = 0 WHERE id_torneo = ?")->execute([$torneo]);
        $this->assertSame(0, $this->esPublico($publicada), 'Torneo sin publicar');

        // Un torneo cancelado conserva su calendario a la vista, como historial
        // (las listas de próximos partidos lo dejan afuera con estado_torneo)
        $this->pdo->prepare("UPDATE torneos SET publicado = 1, estado = 'cancelado' WHERE id_torneo = ?")->execute([$torneo]);
        $this->assertSame(1, $this->esPublico($publicada), 'Torneo cancelado');
        $this->assertSame('cancelado', $this->filas("SELECT estado_torneo FROM vista_calendario WHERE id_enfrentamiento = ?", [$publicada])[0]['estado_torneo']);
    }

    public function testElCalendarioTraeNombresYResultadoVigente(): void
    {
        [, $ronda, [$ana, $beto]] = $this->ligaDeTres();
        $partido = $this->crearPartido($ronda, 0, $ana, $beto);
        $this->cargarResultado($partido, 1, 1);
        $this->cargarResultado($partido, 4, 2);
        $fila = $this->filas("SELECT nombre_local, nombre_visitante, puntaje_local, puntaje_visitante FROM vista_calendario WHERE id_enfrentamiento = ?", [$partido])[0];
        $this->assertSame(['ana', 'beto', 4, 2], [$fila['nombre_local'], $fila['nombre_visitante'], (int)$fila['puntaje_local'], (int)$fila['puntaje_visitante']]);
    }

    // ── Torneos y estadísticas ──

    public function testLosTorneosSinPublicarOCanceladosNoSonPublicos(): void
    {
        $visible = $this->crearTorneo(['nombre' => 'Visible']);
        $oculto = $this->crearTorneo(['nombre' => 'Oculto', 'publicado' => 0]);
        $cancelado = $this->crearTorneo(['nombre' => 'Cancelado', 'estado' => 'cancelado']);
        $publicos = array_column($this->filas("SELECT id_torneo FROM vista_torneos_publicos"), 'id_torneo');
        $this->assertContains((string)$visible, array_map('strval', $publicos));
        $this->assertNotContains((string)$oculto, array_map('strval', $publicos));
        $this->assertNotContains((string)$cancelado, array_map('strval', $publicos));
    }

    public function testLaVistaDeTorneosCuentaLosInscriptos(): void
    {
        [$torneo] = $this->ligaDeTres();
        $fila = $this->filas("SELECT inscriptos, cupo, modulo, juego FROM vista_torneos WHERE id_torneo = ?", [$torneo])[0];
        $this->assertSame([3, 8, 'liga', 'Ajedrez'], [(int)$fila['inscriptos'], (int)$fila['cupo'], $fila['modulo'], $fila['juego']]);
    }

    public function testLasEstadisticasReflejanLosDatosReales(): void
    {
        $antes = $this->filas("SELECT * FROM vista_estadisticas")[0];
        [$torneo, $ronda, [$ana, $beto]] = $this->ligaDeTres();
        $this->cargarResultado($this->crearPartido($ronda, 0, $ana, $beto), 1, 0);
        $this->crearTorneo(['id_juego' => 33]); // Valorant: electrónico
        $despues = $this->filas("SELECT * FROM vista_estadisticas")[0];

        $this->assertSame(3, $despues['usuarios'] - $antes['usuarios']);
        $this->assertSame(2, $despues['torneos'] - $antes['torneos']);
        $this->assertSame(3, $despues['participantes'] - $antes['participantes']);
        $this->assertSame(1, $despues['partidos_jugados'] - $antes['partidos_jugados']);
        $this->assertSame(1, $despues['torneos_electronicos'] - $antes['torneos_electronicos']);
    }

    public function testUnEquipoInformaCuantosMiembrosTiene(): void
    {
        $torneo = $this->crearTorneo(['modalidad' => 'equipos']);
        $participante = $this->inscribirEquipo($torneo, 'Pumas');
        $equipo = $this->valor("SELECT id_equipo FROM participantes WHERE id_participante = ?", [$participante]);
        foreach (['uno', 'dos'] as $nombre) {
            $this->insertar("INSERT INTO equipo_miembros (id_equipo, id_usuario) VALUES (?, ?)", [$equipo, $this->crearUsuario($nombre)]);
        }
        $this->assertSame(2, (int)$this->valor("SELECT cantidad_miembros FROM vista_participantes WHERE id_participante = ?", [$participante]));
    }

    // ── Ayudas ──

    private function filaDe(int $torneo, string $nombre): array
    {
        foreach ($this->tablaDe($torneo) as $fila) {
            if ($fila['nombre'] === $nombre) {
                return $fila;
            }
        }
        $this->fail("$nombre no está en la tabla");
    }

    private function esPublico(int $partido): int
    {
        return (int)$this->valor("SELECT es_publico FROM vista_calendario WHERE id_enfrentamiento = ?", [$partido]);
    }
}
