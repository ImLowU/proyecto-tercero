<?php
// =====================================================================
// MODELO: RONDA
// =====================================================================

class Ronda
{
    public const ESTADOS = ['borrador', 'publicada', 'cerrada'];

    public static function delTorneo(int $idTorneo): array
    {
        return BaseDatos::filas(
            "SELECT id_ronda, numero, nombre, estado, fecha FROM rondas WHERE id_torneo = ? ORDER BY numero",
            [$idTorneo]
        );
    }

    public static function buscar(int $id): ?array
    {
        return BaseDatos::fila("SELECT id_ronda, id_torneo, numero, nombre, estado, fecha FROM rondas WHERE id_ronda = ?", [$id]);
    }

    public static function crear(int $idTorneo, int $numero, ?string $nombre, string $estado = 'borrador'): int
    {
        return BaseDatos::insertar(
            "INSERT INTO rondas (id_torneo, numero, nombre, estado) VALUES (?, ?, ?, ?)",
            [$idTorneo, $numero, $nombre, $estado]
        );
    }

    public static function actualizar(int $id, array $campos): void
    {
        $campos = array_intersect_key($campos, array_flip(['estado', 'fecha', 'nombre']));
        if ($campos) {
            $asignaciones = implode(', ', array_map(fn($c) => "$c = ?", array_keys($campos)));
            BaseDatos::ejecutar("UPDATE rondas SET $asignaciones WHERE id_ronda = ?", [...array_values($campos), $id]);
        }
    }

    // Al terminar el torneo: las rondas publicadas pasan a cerradas.
    public static function cerrarTodas(int $idTorneo): void
    {
        BaseDatos::ejecutar("UPDATE rondas SET estado = 'cerrada' WHERE id_torneo = ? AND estado = 'publicada'", [$idTorneo]);
    }

    public static function ultima(int $idTorneo): ?array
    {
        return BaseDatos::fila(
            "SELECT id_ronda, numero, nombre, estado FROM rondas WHERE id_torneo = ? ORDER BY numero DESC LIMIT 1",
            [$idTorneo]
        );
    }

    public static function cantidad(int $idTorneo): int
    {
        return (int)BaseDatos::valor("SELECT COUNT(*) FROM rondas WHERE id_torneo = ?", [$idTorneo]);
    }

    public static function partidosSinTerminar(int $idRonda): int
    {
        return (int)BaseDatos::valor("SELECT COUNT(*) FROM enfrentamientos WHERE id_ronda = ? AND estado <> 'finalizado'", [$idRonda]);
    }
}
