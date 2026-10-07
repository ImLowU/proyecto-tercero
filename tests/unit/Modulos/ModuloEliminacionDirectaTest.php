<?php
// Tests del módulo eliminación directa (llaves).

use PHPUnit\Framework\TestCase;

final class ModuloEliminacionDirectaTest extends TestCase
{
    public function testConCualquierCantidadLaLlaveTerminaEnUnaFinal(): void
    {
        $modulo = new ModuloEliminacionDirecta();
        for ($n = 2; $n <= 17; $n++) {
            $rondas = $modulo->generarCalendario(range(1, $n), []);
            $ultima = end($rondas);
            $this->assertSame('Final', $ultima['nombre'], "Con $n participantes");
            $this->assertCount(1, $ultima['partidos'], "Con $n participantes");
            $this->assertCount($modulo->totalRondas($n, []), $rondas, "Con $n participantes");
        }
    }

    public function testCadaRondaTieneLaMitadDePartidosQueLaAnterior(): void
    {
        $rondas = (new ModuloEliminacionDirecta())->generarCalendario(range(1, 12), []);
        $this->assertSame([8, 4, 2, 1], array_map(fn($r) => count($r['partidos']), $rondas));
        $this->assertSame(['Octavos de final', 'Cuartos de final', 'Semifinal', 'Final'], array_column($rondas, 'nombre'));
    }

    public function testTodosLosParticipantesEstanEnLaPrimeraRondaUnaSolaVez(): void
    {
        for ($n = 2; $n <= 17; $n++) {
            $primera = (new ModuloEliminacionDirecta())->generarCalendario(range(1, $n), [])[0]['partidos'];
            $ids = array_values(array_filter(array_merge(array_column($primera, 'local'), array_column($primera, 'visitante'))));
            sort($ids);
            $this->assertSame(range(1, $n), $ids, "Con $n participantes");
        }
    }

    public function testNuncaHayDosHuecosEnfrentadosEnLaPrimeraRonda(): void
    {
        for ($n = 2; $n <= 17; $n++) {
            foreach ((new ModuloEliminacionDirecta())->generarCalendario(range(1, $n), [])[0]['partidos'] as $p) {
                $this->assertNotNull($p['local'], "Con $n participantes hay un partido sin nadie");
                $this->assertSame($p['visitante'] === null, $p['pase_libre'], 'Sin rival = pase libre');
            }
        }
    }

    public function testLaCantidadDePasesLibresCompletaLaPotenciaDeDos(): void
    {
        $primera = (new ModuloEliminacionDirecta())->generarCalendario(range(1, 6), [])[0]['partidos'];
        $this->assertCount(2, array_filter($primera, fn($p) => $p['pase_libre']), '6 participantes en llave de 8: 2 pases libres');
    }

    public function testElGanadorAvanzaALaPosicionCorrecta(): void
    {
        $modulo = new ModuloEliminacionDirecta();
        $this->assertSame(['posicion' => 0, 'lado' => 'local'], $modulo->destinoDelGanador(0));
        $this->assertSame(['posicion' => 0, 'lado' => 'visitante'], $modulo->destinoDelGanador(1));
        $this->assertSame(['posicion' => 1, 'lado' => 'local'], $modulo->destinoDelGanador(2));
        $this->assertSame(['posicion' => 3, 'lado' => 'visitante'], $modulo->destinoDelGanador(7));
    }

    public function testNoAdmiteEmpates(): void
    {
        $this->assertFalse((new ModuloEliminacionDirecta())->admiteEmpates(['permitir_empates' => 1]));
    }
}
