<?php
// Tests del sistema suizo: sin revanchas, pase libre rotativo y rondas.

use PHPUnit\Framework\TestCase;

final class ModuloSuizoTest extends TestCase
{
    // Simula un torneo suizo completo: en cada partido gana el de menor id
    // (así la tabla cambia ronda a ronda). Devuelve [cruces, pases libres].
    private function jugarTorneo(int $n, int $rondas): array
    {
        $modulo = new ModuloSuizo();
        $puntos = array_fill_keys(range(1, $n), 0);
        $cruces = [];
        $pasesLibres = [];

        for ($r = 1; $r <= $rondas; $r++) {
            $clasificacion = array_keys($puntos);
            usort($clasificacion, fn($a, $b) => [$puntos[$b], $a] <=> [$puntos[$a], $b]);
            $partidos = $r === 1
                ? $modulo->generarCalendario(range(1, $n), [])[0]['partidos']
                : $modulo->generarRonda($clasificacion, $cruces, $pasesLibres);

            foreach ($partidos as $p) {
                if ($p['pase_libre']) {
                    $pasesLibres[] = $p['local'];
                    $puntos[$p['local']] += 3;
                } else {
                    $cruces[] = [$p['local'], $p['visitante']];
                    $puntos[min($p['local'], $p['visitante'])] += 3;
                }
            }
        }
        return [$cruces, $pasesLibres];
    }

    public function testNoSeRepitenRivalesEnTodoElTorneo(): void
    {
        foreach ([[4, 3], [6, 5], [8, 3], [8, 7], [10, 4], [16, 4], [7, 5], [9, 4]] as [$n, $rondas]) {
            [$cruces] = $this->jugarTorneo($n, $rondas);
            $pares = array_map(fn($c) => min($c) . '-' . max($c), $cruces);
            $this->assertSame(count($pares), count(array_unique($pares)), "Hubo revancha con $n participantes y $rondas rondas");
        }
    }

    public function testElPaseLibreNoLeTocaDosVecesAlMismo(): void
    {
        [, $pasesLibres] = $this->jugarTorneo(7, 5);
        $this->assertCount(5, $pasesLibres, 'Un pase libre por ronda');
        $this->assertSame(count($pasesLibres), count(array_unique($pasesLibres)));
    }

    public function testElPaseLibreLoRecibeElPeorUbicadoQueNoTuvoUno(): void
    {
        $partidos = (new ModuloSuizo())->generarRonda([1, 2, 3, 4, 5], [], [5]);
        $libre = array_values(array_filter($partidos, fn($p) => $p['pase_libre']))[0];
        $this->assertSame(4, $libre['local'], 'El 5 ya tuvo pase libre: le toca al 4');
    }

    public function testEmparejaAParticipantesConPuntajesParecidos(): void
    {
        // Sin cruces previos, el 1º juega con el 2º, el 3º con el 4º...
        $partidos = (new ModuloSuizo())->generarRonda([1, 2, 3, 4, 5, 6], [], []);
        $this->assertSame([[1, 2], [3, 4], [5, 6]], array_map(fn($p) => [$p['local'], $p['visitante']], $partidos));
    }

    public function testSiYaSeEnfrentaronBuscaElSiguienteRivalPosible(): void
    {
        $partidos = (new ModuloSuizo())->generarRonda([1, 2, 3, 4], [[1, 2], [3, 4]], []);
        $pares = array_map(fn($p) => [$p['local'], $p['visitante']], $partidos);
        $this->assertSame([[1, 3], [2, 4]], $pares);
    }

    public function testCantidadDeRondasPorDefectoYConfigurada(): void
    {
        $modulo = new ModuloSuizo();
        $this->assertSame(3, $modulo->totalRondas(8, []), 'log2(8) = 3');
        $this->assertSame(4, $modulo->totalRondas(9, []), 'log2(9) redondeado para arriba = 4');
        $this->assertSame(5, $modulo->totalRondas(8, ['rondas_suizo' => 5]));
        $this->assertSame(3, $modulo->totalRondas(4, ['rondas_suizo' => 10]), 'Con 4 no se pueden jugar más de 3 rondas sin repetir');
        $this->assertSame(1, $modulo->totalRondas(2, []));
    }
}
