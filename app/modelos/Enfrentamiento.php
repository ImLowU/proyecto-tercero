<?php
// =====================================================================
// MODELO: ENFRENTAMIENTO (partido)
// =====================================================================

class Enfrentamiento
{
    private const CAMPOS_CALENDARIO = "id_enfrentamiento, id_torneo, torneo, id_juego, juego, id_ronda, ronda, nombre_ronda,
                                       estado_ronda, posicion, id_local, nombre_local, id_visitante, nombre_visitante, id_ganador,
                                       es_pase_libre, fecha_hora, lugar, estado, puntaje_local, puntaje_visitante";

    // Todos los partidos del torneo. Con $soloPublicos se omiten las rondas
    // en borrador (lo que ve un visitante).
    public static function delTorneo(int $idTorneo, bool $soloPublicos): array
    {
        $filtro = $soloPublicos ? 'AND es_publico = 1' : '';
        return BaseDatos::filas(
            "SELECT " . self::CAMPOS_CALENDARIO . " FROM vista_calendario
             WHERE id_torneo = ? $filtro ORDER BY ronda, posicion",
            [$idTorneo]
        );
    }

    // El partido con los datos que hacen falta para validar una acción sobre él.
    public static function buscar(int $id): ?array
    {
        return BaseDatos::fila(
            "SELECT e.id_enfrentamiento, e.id_ronda, e.posicion, e.id_local, e.id_visitante, e.id_ganador,
                    e.es_pase_libre, e.fecha_hora, e.lugar, e.estado,
                    r.id_torneo, r.numero AS ronda, r.estado AS estado_ronda,
                    t.id_organizador, t.estado AS estado_torneo, m.codigo AS modulo
             FROM enfrentamientos e
             JOIN rondas r ON r.id_ronda = e.id_ronda
             JOIN torneos t ON t.id_torneo = r.id_torneo
             JOIN modulos_competencia m ON m.id_modulo = t.id_modulo
             WHERE e.id_enfrentamiento = ?",
            [$id]
        );
    }

    public static function deRonda(int $idRonda): array
    {
        return BaseDatos::filas(
            "SELECT id_enfrentamiento, posicion, id_local, id_visitante, id_ganador, es_pase_libre, estado
             FROM enfrentamientos WHERE id_ronda = ? ORDER BY posicion",
            [$idRonda]
        );
    }

    public static function enPosicion(int $idTorneo, int $numeroRonda, int $posicion): ?array
    {
        return BaseDatos::fila(
            "SELECT e.id_enfrentamiento, e.id_local, e.id_visitante, e.estado
             FROM enfrentamientos e JOIN rondas r ON r.id_ronda = e.id_ronda
             WHERE r.id_torneo = ? AND r.numero = ? AND e.posicion = ?",
            [$idTorneo, $numeroRonda, $posicion]
        );
    }

    public static function crear(int $idRonda, int $posicion, ?int $local, ?int $visitante, bool $paseLibre = false): int
    {
        return BaseDatos::insertar(
            "INSERT INTO enfrentamientos (id_ronda, posicion, id_local, id_visitante, id_ganador, es_pase_libre, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$idRonda, $posicion, $local, $visitante, $paseLibre ? $local : null, $paseLibre ? 1 : 0, $paseLibre ? 'finalizado' : 'pendiente']
        );
    }

    // Coloca a un participante como local o visitante (avance en la llave).
    public static function ubicar(int $id, string $lado, ?int $idParticipante): void
    {
        $columna = $lado === 'local' ? 'id_local' : 'id_visitante';
        BaseDatos::ejecutar("UPDATE enfrentamientos SET $columna = ? WHERE id_enfrentamiento = ?", [$idParticipante, $id]);
    }

    public static function programar(int $id, ?string $fechaHora, ?string $lugar): void
    {
        BaseDatos::ejecutar("UPDATE enfrentamientos SET fecha_hora = ?, lugar = ? WHERE id_enfrentamiento = ?", [$fechaHora, $lugar, $id]);
    }

    public static function finalizar(int $id, ?int $idGanador): void
    {
        BaseDatos::ejecutar("UPDATE enfrentamientos SET estado = 'finalizado', id_ganador = ? WHERE id_enfrentamiento = ?", [$idGanador, $id]);
    }

    public static function pendientesDelTorneo(int $idTorneo): int
    {
        return (int)BaseDatos::valor(
            "SELECT COUNT(*) FROM enfrentamientos e JOIN rondas r ON r.id_ronda = e.id_ronda
             WHERE r.id_torneo = ? AND e.estado <> 'finalizado'",
            [$idTorneo]
        );
    }

    // Primer y último día con algo programado en el torneo (partidos o
    // rondas con fecha): [AAAA-MM-DD, AAAA-MM-DD], o [null, null].
    public static function rangoProgramado(int $idTorneo): array
    {
        $fila = BaseDatos::fila(
            "SELECT MIN(dia) AS primero, MAX(dia) AS ultimo FROM (
                 SELECT DATE(e.fecha_hora) AS dia FROM enfrentamientos e JOIN rondas r ON r.id_ronda = e.id_ronda
                 WHERE r.id_torneo = ? AND e.fecha_hora IS NOT NULL
                 UNION ALL
                 SELECT fecha FROM rondas WHERE id_torneo = ? AND fecha IS NOT NULL
             ) AS programado",
            [$idTorneo, $idTorneo]
        );
        return [$fila['primero'] ?? null, $fila['ultimo'] ?? null];
    }

    // Pares de rivales que ya se enfrentaron (para que el suizo no repita).
    public static function crucesDelTorneo(int $idTorneo): array
    {
        return BaseDatos::filas(
            "SELECT e.id_local, e.id_visitante, e.es_pase_libre
             FROM enfrentamientos e JOIN rondas r ON r.id_ronda = e.id_ronda
             WHERE r.id_torneo = ?",
            [$idTorneo]
        );
    }

    // Calendario público (portada y página de partidos): próximos partidos,
    // opcionalmente de un juego. Devuelve [filas de la página, total].
    // "Próximos" son los de hoy en adelante y los que todavía no tienen fecha:
    // un partido de días anteriores sin resultado está atrasado (antes
    // aparecía primero, como si fuera el próximo). Los de un torneo
    // cancelado no se van a jugar.
    public static function proximos(?int $idJuego, int $pagina, int $porPagina): array
    {
        return self::paginaPublica(
            "estado = 'pendiente' AND id_local IS NOT NULL AND id_visitante IS NOT NULL AND estado_torneo <> 'cancelado'
             AND (fecha_hora IS NULL OR fecha_hora >= CURDATE())",
            'fecha_hora IS NULL, fecha_hora, id_torneo, ronda, posicion',
            $idJuego, $pagina, $porPagina
        );
    }

    // Últimos resultados públicos (sin los pases libres, que no se jugaron).
    public static function recientes(?int $idJuego, int $pagina, int $porPagina): array
    {
        return self::paginaPublica(
            "estado = 'finalizado' AND es_pase_libre = 0 AND estado_torneo <> 'cancelado'",
            'fecha_resultado DESC, id_enfrentamiento DESC',
            $idJuego, $pagina, $porPagina
        );
    }

    // $condicion y $orden son texto fijo de este archivo, nunca datos del usuario.
    private static function paginaPublica(string $condicion, string $orden, ?int $idJuego, int $pagina, int $porPagina): array
    {
        $donde = "es_publico = 1 AND $condicion";
        $parametros = [];
        if ($idJuego) {
            $donde .= ' AND id_juego = ?';
            $parametros[] = $idJuego;
        }
        $total = (int)BaseDatos::valor("SELECT COUNT(*) FROM vista_calendario WHERE $donde", $parametros);
        $filas = BaseDatos::filas(
            "SELECT " . self::CAMPOS_CALENDARIO . ", fecha_resultado FROM vista_calendario
             WHERE $donde ORDER BY $orden LIMIT ? OFFSET ?",
            [...$parametros, $porPagina, ($pagina - 1) * $porPagina]
        );
        return [$filas, $total];
    }

    // Próximos partidos del usuario (solo o con su equipo), de rondas
    // publicadas. "id_propio" es su lado del partido.
    public static function proximosDelUsuario(int $idUsuario, int $limite = 50): array
    {
        return BaseDatos::filas(
            "SELECT DISTINCT c.id_enfrentamiento, c.id_torneo, c.torneo, c.juego, c.ronda, c.nombre_ronda, c.id_local, c.nombre_local,
                    c.id_visitante, c.nombre_visitante, c.fecha_hora, c.lugar, c.estado, p.id_participante AS id_propio
             FROM vista_calendario c
             JOIN participantes p ON p.id_participante IN (c.id_local, c.id_visitante)
             LEFT JOIN equipo_miembros m ON m.id_equipo = p.id_equipo
             WHERE c.es_publico = 1 AND c.estado = 'pendiente' AND c.id_local IS NOT NULL AND c.id_visitante IS NOT NULL
               AND c.estado_torneo <> 'cancelado' AND (c.fecha_hora IS NULL OR c.fecha_hora >= CURDATE())
               AND (p.id_usuario = ? OR m.id_usuario = ?)
             ORDER BY c.fecha_hora IS NULL, c.fecha_hora, c.ronda
             LIMIT ?",
            [$idUsuario, $idUsuario, $limite]
        );
    }

    // Últimos resultados del usuario (solo o con su equipo), para mostrar
    // si ganó, empató o perdió (con "id_propio").
    public static function recientesDelUsuario(int $idUsuario, int $limite = 10): array
    {
        return BaseDatos::filas(
            "SELECT DISTINCT c.id_enfrentamiento, c.id_torneo, c.torneo, c.juego, c.ronda, c.nombre_ronda, c.id_local, c.nombre_local,
                    c.id_visitante, c.nombre_visitante, c.id_ganador, c.es_pase_libre, c.fecha_hora, c.lugar, c.estado,
                    c.puntaje_local, c.puntaje_visitante, c.fecha_resultado, p.id_participante AS id_propio
             FROM vista_calendario c
             JOIN participantes p ON p.id_participante IN (c.id_local, c.id_visitante)
             LEFT JOIN equipo_miembros m ON m.id_equipo = p.id_equipo
             WHERE c.es_publico = 1 AND c.estado = 'finalizado' AND c.es_pase_libre = 0
               AND (p.id_usuario = ? OR m.id_usuario = ?)
             ORDER BY c.fecha_resultado DESC, c.id_enfrentamiento DESC
             LIMIT ?",
            [$idUsuario, $idUsuario, $limite]
        );
    }
}
