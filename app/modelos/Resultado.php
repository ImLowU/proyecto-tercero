<?php
// =====================================================================
// MODELO: RESULTADO (con historial de correcciones)
// =====================================================================

class Resultado
{
    // Guarda un resultado nuevo como vigente; el anterior (si había) queda
    // en el historial con vigente = 0. Se hace dentro de una transacción y
    // bloqueando el partido, así nunca quedan dos vigentes a la vez.
    public static function registrar(int $idEnfrentamiento, int $local, int $visitante, ?int $idUsuario, ?string $observacion): int
    {
        return BaseDatos::transaccion(function () use ($idEnfrentamiento, $local, $visitante, $idUsuario, $observacion) {
            BaseDatos::valor("SELECT id_enfrentamiento FROM enfrentamientos WHERE id_enfrentamiento = ? FOR UPDATE", [$idEnfrentamiento]);
            BaseDatos::ejecutar("UPDATE resultados SET vigente = 0 WHERE id_enfrentamiento = ? AND vigente = 1", [$idEnfrentamiento]);
            return BaseDatos::insertar(
                "INSERT INTO resultados (id_enfrentamiento, puntaje_local, puntaje_visitante, id_usuario_carga, observacion)
                 VALUES (?, ?, ?, ?, ?)",
                [$idEnfrentamiento, $local, $visitante, $idUsuario, $observacion]
            );
        });
    }

    public static function vigente(int $idEnfrentamiento): ?array
    {
        return BaseDatos::fila(
            "SELECT id_resultado, puntaje_local, puntaje_visitante, fecha_carga FROM resultados
             WHERE id_enfrentamiento = ? AND vigente = 1",
            [$idEnfrentamiento]
        );
    }

    public static function historial(int $idEnfrentamiento): array
    {
        return BaseDatos::filas(
            "SELECT r.id_resultado, r.puntaje_local, r.puntaje_visitante, r.vigente, r.fecha_carga, r.observacion,
                    u.nombre_usuario AS cargado_por
             FROM resultados r LEFT JOIN usuarios u ON u.id_usuario = r.id_usuario_carga
             WHERE r.id_enfrentamiento = ? ORDER BY r.id_resultado DESC",
            [$idEnfrentamiento]
        );
    }
}
