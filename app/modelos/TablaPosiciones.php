<?php
// =====================================================================
// MODELO: TABLA DE POSICIONES
// Lee la vista vista_tabla_posiciones (que se calcula desde los
// resultados vigentes) y la ordena con los criterios de desempate.
// Para el sistema suizo agrega el Buchholz: la suma de los puntos de los
// rivales que enfrentó cada uno (premia a quien tuvo rivales más fuertes).
// =====================================================================

class TablaPosiciones
{
    public static function delTorneo(int $idTorneo, bool $conBuchholz = false): array
    {
        $tabla = BaseDatos::filas(
            "SELECT id_participante, nombre, tipo, imagen, pj, pg, pe, pp, gf, gc, diferencia, puntos
             FROM vista_tabla_posiciones WHERE id_torneo = ?",
            [$idTorneo]
        );
        // Las sumas de la vista llegan como texto (DECIMAL): se pasan a enteros.
        foreach ($tabla as &$fila) {
            foreach (['id_participante', 'pj', 'pg', 'pe', 'pp', 'gf', 'gc', 'diferencia', 'puntos'] as $columna) {
                $fila[$columna] = (int)$fila[$columna];
            }
        }
        unset($fila);

        if ($conBuchholz) {
            $puntos = array_column($tabla, 'puntos', 'id_participante');
            $buchholz = array_fill_keys(array_keys($puntos), 0);
            $partidos = BaseDatos::filas(
                "SELECT id_participante, id_rival FROM vista_partidos_por_participante
                 WHERE id_torneo = ? AND es_pase_libre = 0 AND id_rival IS NOT NULL",
                [$idTorneo]
            );
            foreach ($partidos as $p) {
                $buchholz[$p['id_participante']] += $puntos[$p['id_rival']] ?? 0;
            }
            foreach ($tabla as &$fila) {
                $fila['buchholz'] = $buchholz[$fila['id_participante']];
            }
            unset($fila);
        }

        usort($tabla, function ($a, $b) use ($conBuchholz) {
            return [$b['puntos'], $conBuchholz ? $b['buchholz'] : 0, $b['diferencia'], $b['gf'], $a['nombre']]
               <=> [$a['puntos'], $conBuchholz ? $a['buchholz'] : 0, $a['diferencia'], $a['gf'], $b['nombre']];
        });

        foreach ($tabla as $i => &$fila) {
            $fila['posicion'] = $i + 1;
        }
        return $tabla;
    }

    // Solo los id ordenados del primero al último (lo usa el suizo para emparejar).
    public static function ordenDeParticipantes(int $idTorneo): array
    {
        return array_map(fn($f) => (int)$f['id_participante'], self::delTorneo($idTorneo, true));
    }
}
