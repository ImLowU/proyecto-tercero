<?php
// =====================================================================
// MÓDULO SISTEMA SUIZO
// Se juegan varias rondas; en cada una se enfrentan participantes con
// puntajes parecidos. Diferencias con la versión anterior:
// * NO se repiten rivales: el emparejamiento prueba combinaciones
//   (backtracking) hasta encontrar una sin revanchas, como pide la letra
//   ("evitando repeticiones"). Solo si es imposible acepta repetir.
// * Con cantidad impar, el pase libre lo recibe el peor ubicado que
//   todavía no tuvo uno, y suma como victoria (antes no sumaba nada y le
//   tocaba siempre al mismo).
// * Tiene una cantidad de rondas definida (configurable; por defecto
//   log2 de los participantes, redondeado hacia arriba) y el torneo
//   termina solo al completarla.
// =====================================================================

class ModuloSuizo implements ModuloCompetencia
{
    // Límite de pruebas del backtracking, para que nunca se cuelgue.
    private const MAXIMO_INTENTOS = 20000;
    private int $intentos = 0;

    // Primera ronda: se empareja en el orden recibido (ya viene mezclado).
    public function generarCalendario(array $participantes, array $configuracion): array
    {
        return [['nombre' => 'Ronda 1', 'partidos' => $this->generarRonda(array_values($participantes), [], [])]];
    }

    // $clasificacion: id ordenados del primero al último.
    // $cruces: lista de [idA, idB] de partidos ya jugados.
    // $conPaseLibre: id de quienes ya recibieron un pase libre.
    public function generarRonda(array $clasificacion, array $cruces, array $conPaseLibre): array
    {
        $jugaron = [];
        foreach ($cruces as [$a, $b]) {
            if ($a !== null && $b !== null) {
                $jugaron[$a][$b] = true;
                $jugaron[$b][$a] = true;
            }
        }

        $lista = array_values($clasificacion);
        $partidos = [];
        $paseLibre = null;

        if (count($lista) % 2 !== 0) {
            $paseLibre = $this->elegirPaseLibre($lista, $conPaseLibre);
            $lista = array_values(array_filter($lista, fn($id) => $id !== $paseLibre));
        }

        $this->intentos = 0;
        $pares = $this->emparejar($lista, $jugaron) ?? $this->emparejarEnOrden($lista);
        foreach ($pares as [$local, $visitante]) {
            $partidos[] = ['local' => $local, 'visitante' => $visitante, 'pase_libre' => false];
        }
        if ($paseLibre !== null) {
            $partidos[] = ['local' => $paseLibre, 'visitante' => null, 'pase_libre' => true];
        }
        return $partidos;
    }

    public function totalRondas(int $cantidadParticipantes, array $configuracion): int
    {
        $maximoSinRepetir = $cantidadParticipantes % 2 === 0 ? $cantidadParticipantes - 1 : $cantidadParticipantes;
        $pedidas = (int)($configuracion['rondas_suizo'] ?? 0);
        $recomendadas = max(1, (int)ceil(log(max(2, $cantidadParticipantes), 2)));
        return max(1, min($pedidas ?: $recomendadas, $maximoSinRepetir));
    }

    public function admiteEmpates(array $configuracion): bool
    {
        return (bool)($configuracion['permitir_empates'] ?? true);
    }

    // El peor ubicado que todavía no tuvo pase libre (si todos tuvieron, el último).
    private function elegirPaseLibre(array $lista, array $conPaseLibre): int
    {
        foreach (array_reverse($lista) as $id) {
            if (!in_array($id, $conPaseLibre, true)) {
                return $id;
            }
        }
        return end($lista);
    }

    // Toma al mejor ubicado sin rival y prueba enfrentarlo con el siguiente
    // más cercano en la tabla que no haya enfrentado; si más adelante no
    // se puede completar la ronda, vuelve atrás y prueba con otro.
    private function emparejar(array $lista, array $jugaron): ?array
    {
        if (!$lista) {
            return [];
        }
        if (++$this->intentos > self::MAXIMO_INTENTOS) {
            return null;
        }
        $primero = array_shift($lista);
        foreach ($lista as $i => $rival) {
            if (isset($jugaron[$primero][$rival])) {
                continue;
            }
            $resto = $lista;
            unset($resto[$i]);
            $pares = $this->emparejar(array_values($resto), $jugaron);
            if ($pares !== null) {
                return array_merge([[$primero, $rival]], $pares);
            }
        }
        return null;
    }

    // Último recurso (casi nunca pasa): parejas consecutivas aunque repitan.
    private function emparejarEnOrden(array $lista): array
    {
        $pares = [];
        for ($i = 0; $i + 1 < count($lista); $i += 2) {
            $pares[] = [$lista[$i], $lista[$i + 1]];
        }
        return $pares;
    }
}
