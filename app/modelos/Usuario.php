<?php
// =====================================================================
// MODELO: USUARIO
// =====================================================================

class Usuario
{
    // Datos que se pueden mostrar del usuario (nunca el hash de la contraseña).
    private const CAMPOS = "u.id_usuario, u.nombre_usuario, u.correo, u.id_rol, r.codigo AS rol, r.nombre AS nombre_rol,
                            u.foto_perfil, u.biografia, u.activo, u.debe_cambiar_contrasena, u.fecha_registro, u.ultimo_acceso";

    // Columnas que se pueden modificar con actualizar().
    private const EDITABLES = ['nombre_usuario', 'correo', 'id_rol', 'foto_perfil', 'biografia', 'activo', 'debe_cambiar_contrasena'];

    public static function hashear(string $contrasena): string
    {
        return password_hash($contrasena, PASSWORD_DEFAULT, ['cost' => (int)Config::obtener('costo_hash', 10)]);
    }

    public static function buscarPorId(int $id): ?array
    {
        return BaseDatos::fila("SELECT " . self::CAMPOS . " FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol WHERE u.id_usuario = ?", [$id]);
    }

    public static function buscarPorNombre(string $nombre): ?array
    {
        return BaseDatos::fila("SELECT " . self::CAMPOS . " FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol WHERE u.nombre_usuario = ?", [$nombre]);
    }

    public static function buscarPorCorreo(string $correo): ?array
    {
        return BaseDatos::fila("SELECT " . self::CAMPOS . " FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol WHERE u.correo = ?", [$correo]);
    }

    public static function hashDe(int $id): ?string
    {
        return BaseDatos::valor("SELECT contrasena_hash FROM usuarios WHERE id_usuario = ?", [$id]);
    }

    public static function verificarContrasena(int $id, string $contrasena): bool
    {
        $hash = self::hashDe($id);
        return $hash !== null && password_verify($contrasena, $hash);
    }

    // Devuelve los campos que ya están usados por OTRO usuario.
    public static function conflictos(string $nombreUsuario, string $correo, ?int $excluirId = null): array
    {
        $filas = BaseDatos::filas(
            "SELECT nombre_usuario, correo FROM usuarios WHERE (nombre_usuario = ? OR correo = ?) AND id_usuario <> ?",
            [$nombreUsuario, $correo, $excluirId ?? 0]
        );
        $errores = [];
        foreach ($filas as $fila) {
            if (strcasecmp($fila['nombre_usuario'], $nombreUsuario) === 0) {
                $errores['nombre_usuario'] = 'Ese nombre de usuario ya está en uso.';
            }
            if (strcasecmp($fila['correo'], $correo) === 0) {
                $errores['correo'] = 'Ya hay una cuenta con ese correo.';
            }
        }
        return $errores;
    }

    public static function crear(string $nombreUsuario, string $correo, string $contrasena, string $rol): int
    {
        return BaseDatos::insertar(
            "INSERT INTO usuarios (nombre_usuario, correo, contrasena_hash, id_rol) VALUES (?, ?, ?, ?)",
            [$nombreUsuario, $correo, self::hashear($contrasena), Rol::idPorCodigo($rol)]
        );
    }

    public static function actualizar(int $id, array $campos): void
    {
        $campos = array_intersect_key($campos, array_flip(self::EDITABLES));
        if (!$campos) {
            return;
        }
        $asignaciones = implode(', ', array_map(fn($c) => "$c = ?", array_keys($campos)));
        BaseDatos::ejecutar("UPDATE usuarios SET $asignaciones WHERE id_usuario = ?", [...array_values($campos), $id]);
    }

    public static function cambiarContrasena(int $id, string $nueva): void
    {
        BaseDatos::ejecutar(
            "UPDATE usuarios SET contrasena_hash = ?, debe_cambiar_contrasena = 0 WHERE id_usuario = ?",
            [self::hashear($nueva), $id]
        );
    }

    public static function registrarAcceso(int $id): void
    {
        BaseDatos::ejecutar("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?", [$id]);
    }

    // Perfil que puede ver cualquiera: sin correo ni datos de la cuenta.
    public static function perfilPublico(int $id): ?array
    {
        $perfil = BaseDatos::fila(
            "SELECT u.id_usuario, u.nombre_usuario, r.codigo AS rol, r.nombre AS nombre_rol, u.foto_perfil, u.biografia, u.fecha_registro
             FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.id_usuario = ? AND u.activo = 1",
            [$id]
        );
        if (!$perfil) {
            return null;
        }
        $perfil['torneos_organizados'] = (int)BaseDatos::valor(
            "SELECT COUNT(*) FROM vista_torneos_publicos WHERE id_organizador = ?", [$id]);
        $perfil['torneos_jugados'] = (int)BaseDatos::valor(
            "SELECT COUNT(DISTINCT p.id_torneo) FROM participantes p
             LEFT JOIN equipo_miembros m ON m.id_equipo = p.id_equipo
             WHERE p.id_usuario = ? OR m.id_usuario = ?", [$id, $id]);
        // Partidos jugados (solo o con sus equipos) en torneos publicados.
        // Los pases libres no cuentan: no se jugaron.
        $partidos = BaseDatos::fila(
            "SELECT COUNT(*) AS pj,
                    COALESCE(SUM(jp.a_favor > jp.en_contra), 0) AS pg,
                    COALESCE(SUM(jp.a_favor = jp.en_contra), 0) AS pe,
                    COALESCE(SUM(jp.a_favor < jp.en_contra), 0) AS pp
             FROM vista_partidos_por_participante jp
             JOIN participantes p ON p.id_participante = jp.id_participante
             JOIN torneos t ON t.id_torneo = p.id_torneo
             WHERE jp.es_pase_libre = 0 AND t.publicado = 1
               AND (p.id_usuario = ? OR p.id_equipo IN (SELECT id_equipo FROM equipo_miembros WHERE id_usuario = ?))",
            [$id, $id]
        );
        $perfil['partidos'] = array_map('intval', $partidos);
        return $perfil;
    }

    public static function listar(array $filtros, int $pagina, int $porPagina): array
    {
        $donde = ['1 = 1'];
        $parametros = [];
        if (!empty($filtros['buscar'])) {
            $donde[] = '(u.nombre_usuario LIKE ? OR u.correo LIKE ?)';
            $parametros[] = BaseDatos::patronContiene($filtros['buscar']);
            $parametros[] = BaseDatos::patronContiene($filtros['buscar']);
        }
        if (!empty($filtros['rol'])) {
            $donde[] = 'r.codigo = ?';
            $parametros[] = $filtros['rol'];
        }
        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $donde[] = 'u.activo = ?';
            $parametros[] = (int)$filtros['activo'];
        }
        $condicion = implode(' AND ', $donde);
        $total = (int)BaseDatos::valor("SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol WHERE $condicion", $parametros);
        $filas = BaseDatos::filas(
            "SELECT " . self::CAMPOS . ",
                    (SELECT COUNT(*) FROM torneos t WHERE t.id_organizador = u.id_usuario) AS torneos_creados
             FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
             WHERE $condicion
             ORDER BY u.fecha_registro DESC, u.id_usuario DESC
             LIMIT ? OFFSET ?",
            [...$parametros, $porPagina, ($pagina - 1) * $porPagina]
        );
        return [$filas, $total];
    }

    // ¿Tiene datos asociados que se perderían al borrarlo?
    public static function tieneActividad(int $id): bool
    {
        return (bool)BaseDatos::valor(
            "SELECT EXISTS (SELECT 1 FROM torneos WHERE id_organizador = ?)
                 OR EXISTS (SELECT 1 FROM participantes WHERE id_usuario = ?)
                 OR EXISTS (SELECT 1 FROM equipo_miembros WHERE id_usuario = ?)
                 OR EXISTS (SELECT 1 FROM equipos WHERE id_capitan = ?)
                 OR EXISTS (SELECT 1 FROM resultados WHERE id_usuario_carga = ?)",
            [$id, $id, $id, $id, $id]
        );
    }

    // Borra un usuario SIN actividad. Primero borra a mano sus solicitudes
    // para que los triggers de auditoría registren cada borrado (MySQL no
    // dispara triggers en los borrados en cascada).
    public static function eliminar(int $id): void
    {
        BaseDatos::transaccion(function () use ($id) {
            BaseDatos::ejecutar("DELETE FROM equipo_solicitudes WHERE id_usuario = ?", [$id]);
            BaseDatos::ejecutar("DELETE FROM tokens_usuario WHERE id_usuario = ?", [$id]);
            BaseDatos::ejecutar("DELETE FROM usuarios WHERE id_usuario = ?", [$id]);
        });
    }
}
