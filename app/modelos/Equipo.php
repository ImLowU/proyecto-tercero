<?php
// =====================================================================
// MODELO: EQUIPO
// =====================================================================

class Equipo
{
    public static function buscar(int $id): ?array
    {
        return BaseDatos::fila(
            "SELECT e.id_equipo, e.id_torneo, e.nombre, e.logo, e.id_capitan, u.nombre_usuario AS capitan, e.fecha_creacion,
                    t.nombre AS torneo, t.estado AS estado_torneo, t.publicado, t.id_organizador,
                    p.id_participante,
                    (SELECT COUNT(*) FROM equipo_miembros m WHERE m.id_equipo = e.id_equipo) AS cantidad_miembros
             FROM equipos e
             JOIN torneos t ON t.id_torneo = e.id_torneo
             LEFT JOIN usuarios u ON u.id_usuario = e.id_capitan
             LEFT JOIN participantes p ON p.id_equipo = e.id_equipo
             WHERE e.id_equipo = ?",
            [$id]
        );
    }

    public static function delTorneo(int $idTorneo): array
    {
        return BaseDatos::filas(
            "SELECT e.id_equipo, e.nombre, e.logo, e.id_capitan, u.nombre_usuario AS capitan,
                    (SELECT COUNT(*) FROM equipo_miembros m WHERE m.id_equipo = e.id_equipo) AS cantidad_miembros
             FROM equipos e LEFT JOIN usuarios u ON u.id_usuario = e.id_capitan
             WHERE e.id_torneo = ? ORDER BY e.nombre",
            [$idTorneo]
        );
    }

    public static function miembros(int $idEquipo): array
    {
        return BaseDatos::filas(
            "SELECT u.id_usuario, u.nombre_usuario, u.foto_perfil, m.fecha_union
             FROM equipo_miembros m JOIN usuarios u ON u.id_usuario = m.id_usuario
             WHERE m.id_equipo = ? ORDER BY m.fecha_union, u.id_usuario",
            [$idEquipo]
        );
    }

    public static function cantidadMiembros(int $idEquipo): int
    {
        return (int)BaseDatos::valor("SELECT COUNT(*) FROM equipo_miembros WHERE id_equipo = ?", [$idEquipo]);
    }

    public static function existeNombre(int $idTorneo, string $nombre): bool
    {
        return (bool)BaseDatos::valor("SELECT EXISTS (SELECT 1 FROM equipos WHERE id_torneo = ? AND nombre = ?)", [$idTorneo, $nombre]);
    }

    // Crea el equipo, suma al capitán como primer miembro (si lo hay) y lo
    // inscribe al torneo. Devuelve [id_equipo, id_participante].
    public static function crear(int $idTorneo, string $nombre, ?int $idCapitan): array
    {
        return BaseDatos::transaccion(function () use ($idTorneo, $nombre, $idCapitan) {
            $idEquipo = BaseDatos::insertar(
                "INSERT INTO equipos (id_torneo, nombre, id_capitan) VALUES (?, ?, ?)", [$idTorneo, $nombre, $idCapitan]);
            if ($idCapitan) {
                self::agregarMiembro($idEquipo, $idCapitan);
            }
            $idParticipante = Participante::inscribirEquipo($idTorneo, $idEquipo);
            return [$idEquipo, $idParticipante];
        });
    }

    public static function agregarMiembro(int $idEquipo, int $idUsuario): void
    {
        BaseDatos::insertar("INSERT INTO equipo_miembros (id_equipo, id_usuario) VALUES (?, ?)", [$idEquipo, $idUsuario]);
    }

    public static function quitarMiembro(int $idEquipo, int $idUsuario): void
    {
        BaseDatos::ejecutar("DELETE FROM equipo_miembros WHERE id_equipo = ? AND id_usuario = ?", [$idEquipo, $idUsuario]);
    }

    // El equipo del que forma parte el usuario en ese torneo (si hay uno).
    public static function delUsuarioEnTorneo(int $idTorneo, int $idUsuario): ?array
    {
        return BaseDatos::fila(
            "SELECT e.id_equipo, e.id_capitan, e.nombre FROM equipo_miembros m JOIN equipos e ON e.id_equipo = m.id_equipo
             WHERE e.id_torneo = ? AND m.id_usuario = ?",
            [$idTorneo, $idUsuario]
        );
    }

    // Borra el equipo y lo que depende de él, de a una tabla (auditoría).
    public static function eliminar(int $idEquipo): void
    {
        BaseDatos::transaccion(function () use ($idEquipo) {
            BaseDatos::ejecutar("DELETE FROM equipo_solicitudes WHERE id_equipo = ?", [$idEquipo]);
            BaseDatos::ejecutar("DELETE FROM equipo_miembros WHERE id_equipo = ?", [$idEquipo]);
            BaseDatos::ejecutar("DELETE FROM participantes WHERE id_equipo = ?", [$idEquipo]);
            BaseDatos::ejecutar("DELETE FROM equipos WHERE id_equipo = ?", [$idEquipo]);
        });
    }
}
