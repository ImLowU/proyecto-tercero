<?php
// Tests del módulo liga (todos contra todos).

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModuloLigaTest extends TestCase
{
    private function partidos(array $rondas): array
    {
        return array_merge(...array_column($rondas, 'partidos'));
    }

    public static function cantidades(): array
    {
        return ['2' => [2], '3' => [3], '4' => [4], '5' => [5], '8' => [8], '11' => [11], '20' => [20]];
    }

    #[DataProvider('cantidades')]
    public function testCadaParSeEnfrentaExactamenteUnaVez(int $n): void
    {
        $rondas = (new ModuloLiga())->generarCalendario(range(1, $n), []);
        $partidos = $this->partidos($rondas);

        $this->assertCount(intdiv($n * ($n - 1), 2), $partidos, 'Cantidad de partidos = n(n-1)/2');
        $pares = array_map(fn($p) => min($p['local'], $p['visitante']) . '-' . max($p['local'], $p['visitante']), $partidos);
        $this->assertSame(count($pares), count(array_unique($pares)), 'Ningún cruce se repite');
    }

    #[DataProvider('cantidades')]
    public function testNadieJuegaDosVecesEnLaMismaFecha(int $n): void
    {
        foreach ((new ModuloLiga())->generarCalendario(range(1, $n), []) as $ronda) {
            $jugadores = array_merge(array_column($ronda['partidos'], 'local'), array_column($ronda['partidos'], 'visitante'));
            $this->assertSame(count($jugadores), count(array_unique($jugadores)), $ronda['nombre']);
        }
    }

    public function testConCantidadImparCadaUnoDescansaUnaFecha(): void
    {
        $rondas = (new ModuloLiga())->generarCalendario(range(1, 5), []);
        $this->assertCount(5, $rondas);
        foreach ($rondas as $ronda) {
            $this->assertCount(2, $ronda['partidos'], 'Juegan 4 de 5: uno descansa');
        }
    }

    public function testIdaYVueltaDuplicaLosPartidosInvirtiendoLaLocalia(): void
    {
        $modulo = new ModuloLiga();
        $rondas = $modulo->generarCalendario(range(1, 4), ['ida_y_vuelta' => 1]);
        $partidos = $this->partidos($rondas);

        $this->assertCount(6, $rondas);
        $this->assertSame(6, $modulo->totalRondas(4, ['ida_y_vuelta' => 1]));
        $this->assertCount(12, $partidos);
        $orientados = array_map(fn($p) => $p['local'] . '>' . $p['visitante'], $partidos);
        $this->assertSame(12, count(array_unique($orientados)), 'Cada par juega una vez de local y otra de visitante');
    }

    public function testLasFechasSeNumeranEnOrden(): void
    {
        $rondas = (new ModuloLiga())->generarCalendario([10, 20, 30, 40], []);
        $this->assertSame(['Fecha 1', 'Fecha 2', 'Fecha 3'], array_column($rondas, 'nombre'));
    }

    public function testRespetaLaConfiguracionDeEmpates(): void
    {
        $this->assertTrue((new ModuloLiga())->admiteEmpates(['permitir_empates' => 1]));
        $this->assertFalse((new ModuloLiga())->admiteEmpates(['permitir_empates' => 0]));
    }
}
