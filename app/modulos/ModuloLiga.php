<?php
// =====================================================================
// MÓDULO LIGA: todos contra todos
// Usa el "método del círculo" (igual que la versión anterior): un
// participante queda fijo y los demás rotan una posición por fecha. Así
// cada par se enfrenta exactamente una vez y nadie juega dos veces en la
// misma fecha. Con cantidad impar, en cada fecha uno descansa.
// Novedad: opción de ida y vuelta (la segunda mitad invierte la localía).
// =====================================================================

class ModuloLiga implements ModuloCompetencia
{
    public function generarCalendario(array $participantes, array $configuracion): array
    {
        $lista = array_values($participantes);
        if (count($lista) % 2 !== 0) {
            $lista[] = null; // "descansa": el que le toque contra null no juega esa fecha
        }
        $n = count($lista);
        $rondas = [];

        for ($fecha = 0; $fecha < $n - 1; $fecha++) {
            $partidos = [];
            for ($i = 0; $i < $n / 2; $i++) {
                $local = $lista[$i];
                $visitante = $lista[$n - 1 - $i];
                if ($local !== null && $visitante !== null) {
                    // Se alterna la localía del fijo para que no sea siempre local.
                    if ($i === 0 && $fecha % 2 === 1) {
                        [$local, $visitante] = [$visitante, $local];
                    }
                    $partidos[] = ['local' => $local, 'visitante' => $visitante, 'pase_libre' => false];
                }
            }
            $rondas[] = ['nombre' => 'Fecha ' . ($fecha + 1), 'partidos' => $partidos];

            // Rotar todos menos el primero
            $fijo = $lista[0];
            $resto = array_slice($lista, 1);
            array_unshift($resto, array_pop($resto));
            $lista = array_merge([$fijo], $resto);
        }

        if (!empty($configuracion['ida_y_vuelta'])) {
            $cantidadIda = count($rondas);
            foreach (array_slice($rondas, 0, $cantidadIda) as $i => $ronda) {
                $rondas[] = [
                    'nombre' => 'Fecha ' . ($cantidadIda + $i + 1),
                    'partidos' => array_map(fn($p) => ['local' => $p['visitante'], 'visitante' => $p['local'], 'pase_libre' => false], $ronda['partidos']),
                ];
            }
        }
        return $rondas;
    }

    public function totalRondas(int $cantidadParticipantes, array $configuracion): int
    {
        $fechas = $cantidadParticipantes % 2 === 0 ? $cantidadParticipantes - 1 : $cantidadParticipantes;
        return !empty($configuracion['ida_y_vuelta']) ? $fechas * 2 : $fechas;
    }

    public function admiteEmpates(array $configuracion): bool
    {
        return (bool)($configuracion['permitir_empates'] ?? true);
    }
}
