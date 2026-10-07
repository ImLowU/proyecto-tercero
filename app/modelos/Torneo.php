<?php
// =====================================================================
// MODELO: TORNEO (y su configuración)
// =====================================================================

class Torneo
{
    public const ESTADOS = ['pendiente', 'en_curso', 'finalizado', 'cancelado'];
    public const MODALIDADES = ['individual', 'equipos'];
    public const MONEDAS = ['UYU', 'USD'];

    private const EDITABLES = ['nombre', 'id_juego', 'id_modulo', 'modalidad', 'cupo', 'premio', 'moneda',
                               'fecha_inicio', 'fecha_fin', 'descripcion', 'logo', 'reglas_pdf', 'estado', 'publicado',
                               'id_organizador'];
    private const EDITABLES_CONFIGURACION = ['puntos_victoria', 'puntos_empate', 'puntos_derrota', 'ida_y_vuelta',
                                             'rondas_suizo', 'permitir_empates', 'permitir_correcciones', 'max_integrantes_equipo'];

    // El torneo con sus datos relacionados y su configuración.
    public static function buscar(int $id): ?array
    {
        $torneo = BaseDatos::fila("SELECT * FROM vista_torneos WHERE id_torneo = ?", [$id]);
        if ($torneo) {
            $torneo['configuracion'] = self::configuracion($id);
        }
        return $torneo;
    }

    public static function configuracion(int $id): array
    {
        return BaseDatos::fila(
            "SELECT puntos_victoria, puntos_empate, puntos_derrota, ida_y_vuelta, rondas_suizo,
                    permitir_empates, permitir_correcciones, max_integrantes_equipo
             FROM configuracion_torneo WHERE id_torneo = ?",
            [$id]
        ) ?? [];
    }

    // Listado con filtros y paginación, resuelto en la base (antes se traían
    // 50 torneos y se filtraba en el navegador, perdiendo resultados).
    // Los activos (inscripciones abiertas y en curso) aparecen primero.
    public static function listar(array $filtros, int $pagina, int $porPagina, bool $soloPublicos = true): array
    {
        $vista = $soloPublicos ? 'vista_torneos_publicos' : 'vista_torneos';
        $donde = ['1 = 1'];
        $parametros = [];

        if (!empty($filtros['buscar'])) {
            $donde[] = '(nombre LIKE ? OR juego LIKE ?)';
            $parametros[] = BaseDatos::patronContiene($filtros['buscar']);
            $parametros[] = BaseDatos::patronContiene($filtros['buscar']);
        }
        // "activos" agrupa los dos estados que le interesan a quien busca
        // dónde jugar o qué seguir: inscripción abierta y en curso. Para el
        // público, "inscripción abierta" deja afuera los torneos cuya fecha
        // de inicio ya pasó sin calendario (la inscripción se cerró sola);
        // el administrador los sigue viendo para poder ocuparse de ellos.
        $abiertos = $soloPublicos ? "(estado = 'pendiente' AND inscripcion_vencida = 0)" : "estado = 'pendiente'";
        if (($filtros['estado'] ?? '') === 'activos') {
            $donde[] = "($abiertos OR estado = 'en_curso')";
            unset($filtros['estado']);
        } elseif (($filtros['estado'] ?? '') === 'pendiente') {
            $donde[] = $abiertos;
            unset($filtros['estado']);
        }
        foreach (['id_juego' => 'id_juego', 'categoria' => 'categoria', 'estado' => 'estado',
                  'modulo' => 'modulo', 'modalidad' => 'modalidad', 'id_organizador' => 'id_organizador'] as $filtro => $columna) {
            if (isset($filtros[$filtro]) && $filtros[$filtro] !== '' && $filtros[$filtro] !== null) {
                $donde[] = "$columna = ?";
                $parametros[] = $filtros[$filtro];
            }
        }

        $condicion = implode(' AND ', $donde);
        $total = (int)BaseDatos::valor("SELECT COUNT(*) FROM $vista WHERE $condicion", $parametros);
        $filas = BaseDatos::filas(
            "SELECT id_torneo, nombre, id_juego, juego, categoria, modulo, nombre_modulo, modalidad, cupo, inscriptos,
                    premio, moneda, fecha_inicio, fecha_fin, logo, estado, publicado, id_organizador, organizador,
                    inscripcion_vencida, fin_vencido
             FROM $vista WHERE $condicion
             ORDER BY FIELD(estado, 'pendiente', 'en_curso', 'finalizado', 'cancelado'), fecha_inicio, id_torneo DESC
             LIMIT ? OFFSET ?",
            [...$parametros, $porPagina, ($pagina - 1) * $porPagina]
        );
        return [$filas, $total];
    }

    // El torneo activo más llamativo para la portada. Primero los que todavía
    // tienen lugares para anotarse (antes podía destacarse uno lleno, que
    // invitaba a inscribirse sin poder); después, el de mayor premio y, a
    // igual premio, el que tiene más inscriptos. No se destaca uno cuya
    // inscripción se cerró por fecha sin que empezara.
    public static function destacado(): ?array
    {
        return BaseDatos::fila(
            "SELECT id_torneo, nombre, juego, modulo, nombre_modulo, modalidad, cupo, inscriptos, premio, moneda, estado, logo,
                    inscripcion_vencida
             FROM vista_torneos_publicos
             WHERE estado = 'en_curso' OR (estado = 'pendiente' AND inscripcion_vencida = 0)
             ORDER BY (estado = 'pendiente' AND inscriptos < cupo) DESC, premio DESC, inscriptos DESC, fecha_inicio
             LIMIT 1"
        );
    }

    public static function delOrganizador(int $idUsuario): array
    {
        return BaseDatos::filas(
            "SELECT id_torneo, nombre, id_juego, juego, categoria, modulo, nombre_modulo, modalidad, cupo, inscriptos,
                    premio, moneda, fecha_inicio, fecha_fin, logo, estado, publicado, inscripcion_vencida, fin_vencido
             FROM vista_torneos WHERE id_organizador = ?
             ORDER BY FIELD(estado, 'pendiente', 'en_curso', 'finalizado', 'cancelado'), fecha_inicio DESC",
            [$idUsuario]
        );
    }

    // Torneos donde el usuario juega, solo o como miembro de un equipo.
    public static function dondeParticipa(int $idUsuario): array
    {
        return BaseDatos::filas(
            "SELECT DISTINCT t.id_torneo, t.nombre, t.id_juego, t.juego, t.categoria, t.modulo, t.nombre_modulo, t.modalidad, t.cupo,
                    t.inscriptos, t.premio, t.moneda, t.fecha_inicio, t.fecha_fin, t.logo, t.estado, t.publicado,
                    t.inscripcion_vencida, t.fin_vencido
             FROM vista_torneos t
             JOIN participantes p ON p.id_torneo = t.id_torneo
             LEFT JOIN equipo_miembros m ON m.id_equipo = p.id_equipo
             WHERE p.id_usuario = ? OR m.id_usuario = ?
             ORDER BY FIELD(t.estado, 'pendiente', 'en_curso', 'finalizado', 'cancelado'), t.fecha_inicio DESC",
            [$idUsuario, $idUsuario]
        );
    }

    public static function crear(array $datos, array $configuracion): int
    {
        return BaseDatos::transaccion(function () use ($datos, $configuracion) {
            $datos = array_intersect_key($datos, array_flip(self::EDITABLES));
            $columnas = array_keys($datos);
            $id = BaseDatos::insertar(
                "INSERT INTO torneos (" . implode(', ', $columnas) . ") VALUES (" . self::marcadores(count($columnas)) . ")",
                array_values($datos)
            );
            $configuracion = array_intersect_key($configuracion, array_flip(self::EDITABLES_CONFIGURACION));
            $columnas = array_merge(['id_torneo'], array_keys($configuracion));
            BaseDatos::insertar(
                "INSERT INTO configuracion_torneo (" . implode(', ', $columnas) . ") VALUES (" . self::marcadores(count($columnas)) . ")",
                array_merge([$id], array_values($configuracion))
            );
            return $id;
        });
    }

    public static function actualizar(int $id, array $campos): void
    {
        $campos = array_intersect_key($campos, array_flip(self::EDITABLES));
        if ($campos) {
            $asignaciones = implode(', ', array_map(fn($c) => "$c = ?", array_keys($campos)));
            BaseDatos::ejecutar("UPDATE torneos SET $asignaciones WHERE id_torneo = ?", [...array_values($campos), $id]);
        }
    }

    public static function actualizarConfiguracion(int $id, array $campos): void
    {
        $campos = array_intersect_key($campos, array_flip(self::EDITABLES_CONFIGURACION));
        if ($campos) {
            $asignaciones = implode(', ', array_map(fn($c) => "$c = ?", array_keys($campos)));
            BaseDatos::ejecutar("UPDATE configuracion_torneo SET $asignaciones WHERE id_torneo = ?", [...array_values($campos), $id]);
        }
    }

    public static function cambiarEstado(int $id, string $estado): void
    {
        BaseDatos::ejecutar("UPDATE torneos SET estado = ? WHERE id_torneo = ?", [$estado, $id]);
    }

    public static function tieneCalendario(int $id): bool
    {
        return (bool)BaseDatos::valor("SELECT EXISTS (SELECT 1 FROM rondas WHERE id_torneo = ?)", [$id]);
    }

    // Borra el torneo y todo lo que depende de él, tabla por tabla y en
    // orden. No se confía en el ON DELETE CASCADE porque MySQL no dispara
    // los triggers en los borrados en cascada: así cada fila borrada queda
    // registrada en la auditoría. (Los torneos en curso no se borran: se
    // cancelan; ver TorneoControlador::eliminar.)
    public static function eliminar(int $id): void
    {
        BaseDatos::transaccion(function () use ($id) {
            $porRonda = "IN (SELECT e.id_enfrentamiento FROM enfrentamientos e JOIN rondas r ON r.id_ronda = e.id_ronda WHERE r.id_torneo = ?)";
            BaseDatos::ejecutar("DELETE FROM resultados WHERE id_enfrentamiento $porRonda", [$id]);
            BaseDatos::ejecutar("DELETE e FROM enfrentamientos e JOIN rondas r ON r.id_ronda = e.id_ronda WHERE r.id_torneo = ?", [$id]);
            BaseDatos::ejecutar("DELETE FROM rondas WHERE id_torneo = ?", [$id]);
            BaseDatos::ejecutar("DELETE s FROM equipo_solicitudes s JOIN equipos e ON e.id_equipo = s.id_equipo WHERE e.id_torneo = ?", [$id]);
            BaseDatos::ejecutar("DELETE m FROM equipo_miembros m JOIN equipos e ON e.id_equipo = m.id_equipo WHERE e.id_torneo = ?", [$id]);
            BaseDatos::ejecutar("DELETE FROM participantes WHERE id_torneo = ?", [$id]);
            BaseDatos::ejecutar("DELETE FROM equipos WHERE id_torneo = ?", [$id]);
            BaseDatos::ejecutar("DELETE FROM configuracion_torneo WHERE id_torneo = ?", [$id]);
            BaseDatos::ejecutar("DELETE FROM torneos WHERE id_torneo = ?", [$id]);
        });
    }

    private static function marcadores(int $cantidad): string
    {
        return implode(', ', array_fill(0, $cantidad, '?'));
    }
}
