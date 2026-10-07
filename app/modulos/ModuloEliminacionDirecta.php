<?php
// =====================================================================
// MÓDULO ELIMINACIÓN DIRECTA: llaves
// Igual que la versión anterior: la llave se completa hasta la próxima
// potencia de 2 con "pases libres" (quien no tiene rival pasa directo),
// repartidos para que nunca queden dos huecos enfrentados en la primera
// ronda. Se arman todas las rondas desde el principio; las siguientes
// quedan vacías y se van llenando con los ganadores.
// El ganador de la posición p pasa a la posición floor(p/2) de la ronda
// siguiente: como local si p es par, como visitante si es impar.
// =====================================================================

class ModuloEliminacionDirecta implements ModuloCompetencia
{
    public function generarCalendario(array $participantes, array $configuracion): array
    {
        $lista = array_values($participantes);
        $n = count($lista);
        $tamano = $this->tamanoLlave($n);
        $cantidadPasesLibres = $tamano - $n;

        // Primero los que pasan directo, después los que juegan entre sí.
        $partidos = [];
        foreach (array_slice($lista, 0, $cantidadPasesLibres) as $id) {
            $partidos[] = ['local' => $id, 'visitante' => null, 'pase_libre' => true];
        }
        $juegan = array_slice($lista, $cantidadPasesLibres);
        for ($i = 0; $i < count($juegan); $i += 2) {
            $partidos[] = ['local' => $juegan[$i], 'visitante' => $juegan[$i + 1], 'pase_libre' => false];
        }

        $rondas = [['nombre' => $this->nombreRonda($tamano), 'partidos' => $partidos]];
        for ($quedan = $tamano / 2; $quedan >= 2; $quedan /= 2) {
            $vacios = array_fill(0, (int)($quedan / 2), ['local' => null, 'visitante' => null, 'pase_libre' => false]);
            $rondas[] = ['nombre' => $this->nombreRonda((int)$quedan), 'partidos' => $vacios];
        }
        return $rondas;
    }

    // A qué partido de la ronda siguiente va el ganador de la posición dada.
    public function destinoDelGanador(int $posicion): array
    {
        return ['posicion' => intdiv($posicion, 2), 'lado' => $posicion % 2 === 0 ? 'local' : 'visitante'];
    }

    public function totalRondas(int $cantidadParticipantes, array $configuracion): int
    {
        return (int)log($this->tamanoLlave($cantidadParticipantes), 2);
    }

    public function admiteEmpates(array $configuracion): bool
    {
        return false; // siempre tiene que haber un ganador que avance
    }

    private function tamanoLlave(int $n): int
    {
        $tamano = 2;
        while ($tamano < $n) {
            $tamano *= 2;
        }
        return $tamano;
    }

    // Nombre según cuántos participantes quedan en esa ronda.
    private function nombreRonda(int $participantesEnRonda): string
    {
        return match ($participantesEnRonda) {
            2       => 'Final',
            4       => 'Semifinal',
            8       => 'Cuartos de final',
            16      => 'Octavos de final',
            32      => 'Dieciseisavos de final',
            default => "Ronda de $participantesEnRonda",
        };
    }
}
