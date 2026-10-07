<?php
// =====================================================================
// MODELO: PARTICIPANTE (persona o equipo inscripto en un torneo)
// =====================================================================

class Participante
{
    public static function delTorneo(int $idTorneo): array
    {
        return BaseDatos::filas(
            "SELECT id_participante, id_usuario, id_equipo, tipo, nombre, imagen, id_capitan, cantidad_miembros, fecha_inscripcion
             FROM vista_participantes WHERE id_torneo = ?
             ORDER BY fecha_inscripcion, id_participante",
            [$idTorneo]
        );
    }

    public static function idsDelTorneo(int $idTorneo): array
    {
        return array_map('intval', array_column(
            BaseDatos::filas("SELECT id_participante FROM participantes WHERE id_torneo = ? ORDER BY id_participante", [$idTorneo]),
            'id_participante'
        ));
    }

    public static function buscar(int $id): ?array
    {
        return BaseDatos::fila(
            "SELECT id_participante, id_torneo, id_usuario, id_equipo, tipo, nombre, imagen, id_capitan
             FROM vista_participantes WHERE id_participante = ?",
            [$id]
        );
    }

    public static function cantidad(int $idTorneo): int
    {
        return (int)BaseDatos::valor("SELECT COUNT(*) FROM participantes WHERE id_torneo = ?", [$idTorneo]);
    }

    // ¿El usuario ya juega en este torneo, solo o dentro de algún equipo?
    public static function usuarioEstaEnTorneo(int $idTorneo, int $idUsuario): bool
    {
        return (bool)BaseDatos::valor(
            "SELECT EXISTS (SELECT 1 FROM participantes WHERE id_torneo = ? AND id_usuario = ?)
                 OR EXISTS (SELECT 1 FROM equipo_miembros m JOIN equipos e ON e.id_equipo = m.id_equipo
                            WHERE e.id_torneo = ? AND m.id_usuario = ?)",
            [$idTorneo, $idUsuario, $idTorneo, $idUsuario]
        );
    }

    public static function inscribirUsuario(int $idTorneo, int $idUsuario): int
    {
        return BaseDatos::insertar("INSERT INTO participantes (id_torneo, id_usuario) VALUES (?, ?)", [$idTorneo, $idUsuario]);
    }

    public static function inscribirEquipo(int $idTorneo, int $idEquipo): int
    {
        return BaseDatos::insertar("INSERT INTO participantes (id_torneo, id_equipo) VALUES (?, ?)", [$idTorneo, $idEquipo]);
    }

    // Saca al participante del torneo. Si es un equipo, borra también el
    // equipo con sus miembros y solicitudes, uno por uno (por la auditoría).
    public static function eliminar(int $idParticipante): void
    {
        BaseDatos::transaccion(function () use ($idParticipante) {
            $participante = BaseDatos::fila("SELECT id_equipo FROM participantes WHERE id_participante = ?", [$idParticipante]);
            if (!$participante) {
                return;
            }
            BaseDatos::ejecutar("DELETE FROM participantes WHERE id_participante = ?", [$idParticipante]);
            if ($participante['id_equipo']) {
                Equipo::eliminar((int)$participante['id_equipo']);
            }
        });
    }

    public static function delUsuarioEnTorneo(int $idTorneo, int $idUsuario): ?array
    {
        return BaseDatos::fila(
            "SELECT id_participante, tipo, id_equipo FROM vista_participantes WHERE id_torneo = ? AND id_usuario = ?",
            [$idTorneo, $idUsuario]
        );
    }
}
