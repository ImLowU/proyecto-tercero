<?php
// =====================================================================
// MODELO: SOLICITUD PARA UNIRSE A UN EQUIPO
// =====================================================================

class SolicitudEquipo
{
    public static function buscar(int $id): ?array
    {
        return BaseDatos::fila(
            "SELECT s.id_solicitud, s.id_equipo, s.id_usuario, s.estado, e.id_capitan, e.id_torneo, e.nombre AS equipo
             FROM equipo_solicitudes s JOIN equipos e ON e.id_equipo = s.id_equipo
             WHERE s.id_solicitud = ?",
            [$id]
        );
    }

    public static function pendientesDelEquipo(int $idEquipo): array
    {
        return BaseDatos::filas(
            "SELECT s.id_solicitud, s.fecha_solicitud, u.id_usuario, u.nombre_usuario
             FROM equipo_solicitudes s JOIN usuarios u ON u.id_usuario = s.id_usuario
             WHERE s.id_equipo = ? AND s.estado = 'pendiente' ORDER BY s.fecha_solicitud",
            [$idEquipo]
        );
    }

    public static function tienePendiente(int $idEquipo, int $idUsuario): bool
    {
        return (bool)BaseDatos::valor(
            "SELECT EXISTS (SELECT 1 FROM equipo_solicitudes WHERE id_equipo = ? AND id_usuario = ? AND estado = 'pendiente')",
            [$idEquipo, $idUsuario]
        );
    }

    public static function crear(int $idEquipo, int $idUsuario): int
    {
        return BaseDatos::insertar("INSERT INTO equipo_solicitudes (id_equipo, id_usuario) VALUES (?, ?)", [$idEquipo, $idUsuario]);
    }

    public static function responder(int $id, string $estado): void
    {
        BaseDatos::ejecutar("UPDATE equipo_solicitudes SET estado = ?, fecha_respuesta = NOW() WHERE id_solicitud = ?", [$estado, $id]);
    }
}
