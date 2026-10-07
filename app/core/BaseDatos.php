<?php
// =====================================================================
// ACCESO A LA BASE DE DATOS (PDO)
// Una sola conexión por petición. Todas las consultas pasan por acá y
// usan sentencias preparadas: los valores viajan separados del SQL, así
// que no hay forma de inyectar SQL con un dato del usuario.
// =====================================================================

class BaseDatos
{
    private static ?PDO $pdo = null;
    private static int $nivelTransaccion = 0;
    private static ?string $ip = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            $bd = Config::obtener('bd');
            $dsn = "mysql:host={$bd['host']};port={$bd['puerto']};dbname={$bd['nombre']};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $bd['usuario'], $bd['clave'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Modo estricto: un dato inválido da error en vez de guardarse recortado.
            self::$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            // Misma hora que PHP (Uruguay, sin horario de verano).
            self::$pdo->exec("SET time_zone = '-03:00'");
        }
        return self::$pdo;
    }

    // Los tests le pasan su propia conexión (a la base de pruebas).
    public static function usar(?PDO $pdo): void
    {
        self::$pdo = $pdo;
        self::$nivelTransaccion = 0;
    }

    // Variables que leen los triggers de auditoría: quién hizo el cambio y
    // desde qué IP. La IP se recuerda para no perderla al cambiar de usuario
    // a mitad de la petición (por ejemplo, al iniciar sesión).
    public static function fijarUsuarioAuditoria(?int $idUsuario, ?string $ip = null): void
    {
        if ($ip !== null) {
            self::$ip = $ip;
        }
        $stmt = self::conexion()->prepare("SET @id_usuario_app = ?, @ip_app = ?");
        $stmt->execute([$idUsuario, self::$ip]);
    }

    public static function filas(string $sql, array $parametros = []): array
    {
        return self::preparar($sql, $parametros)->fetchAll();
    }

    public static function fila(string $sql, array $parametros = []): ?array
    {
        $fila = self::preparar($sql, $parametros)->fetch();
        return $fila === false ? null : $fila;
    }

    public static function valor(string $sql, array $parametros = []): mixed
    {
        $valor = self::preparar($sql, $parametros)->fetchColumn();
        return $valor === false ? null : $valor;
    }

    // INSERT: devuelve el id generado.
    public static function insertar(string $sql, array $parametros = []): int
    {
        self::preparar($sql, $parametros);
        return (int)self::conexion()->lastInsertId();
    }

    // UPDATE / DELETE: devuelve cuántas filas cambiaron.
    public static function ejecutar(string $sql, array $parametros = []): int
    {
        return self::preparar($sql, $parametros)->rowCount();
    }

    // Patrón para buscar con LIKE un texto que escribió la persona ("que
    // contenga..."). En LIKE, % y _ son comodines: se escapan para que se
    // busquen como caracteres comunes. Sin esto, buscar "_" devolvía todo y
    // "100%" también encontraba "1000".
    public static function patronContiene(string $texto): string
    {
        return '%' . addcslashes($texto, '\\%_') . '%';
    }

    // Ejecuta $tarea dentro de una transacción: si algo falla, se deshace
    // todo. Se puede anidar (usa SAVEPOINT en los niveles internos).
    public static function transaccion(callable $tarea): mixed
    {
        $pdo = self::conexion();
        $externa = !$pdo->inTransaction();
        $punto = 'nivel_' . self::$nivelTransaccion;

        if ($externa) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec("SAVEPOINT $punto");
        }
        self::$nivelTransaccion++;

        try {
            $resultado = $tarea();
            self::$nivelTransaccion--;
            if ($externa) {
                $pdo->commit();
            } else {
                $pdo->exec("RELEASE SAVEPOINT $punto");
            }
            return $resultado;
        } catch (Throwable $e) {
            self::$nivelTransaccion--;
            if ($externa) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } else {
                $pdo->exec("ROLLBACK TO SAVEPOINT $punto");
            }
            throw $e;
        }
    }

    private static function preparar(string $sql, array $parametros): PDOStatement
    {
        $stmt = self::conexion()->prepare($sql);
        foreach (array_values($parametros) as $i => $valor) {
            $tipo = match (true) {
                is_int($valor)  => PDO::PARAM_INT,
                is_bool($valor) => PDO::PARAM_BOOL,
                $valor === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($i + 1, $valor, $tipo);
        }
        $stmt->execute();
        return $stmt;
    }
}
