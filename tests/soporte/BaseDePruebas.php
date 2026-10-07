<?php
// =====================================================================
// BASE DE DATOS DE PRUEBAS
// Crea "nextleague_test" desde cero con los mismos scripts que la
// instalación real (una sola vez por corrida) y entrega conexiones con
// cada cuenta: root (administración), nl_app (la de la aplicación) y
// nl_lectura (solo lectura). Nunca toca la base de desarrollo.
// =====================================================================

final class BaseDePruebas
{
    private static bool $instalada = false;
    private static array $conexiones = [];

    public static function nombre(): string
    {
        return getenv('NL_TEST_BD') ?: 'nextleague_test';
    }

    public static function asegurarInstalada(): void
    {
        if (self::$instalada) {
            return;
        }
        $nombre = self::nombre();
        if (!str_ends_with($nombre, '_test')) {
            throw new RuntimeException("Por seguridad la base de pruebas tiene que terminar en _test (se recibió $nombre).");
        }
        (new Instalador(self::admin()))->instalarNueva($nombre, true);
        self::$instalada = true;
    }

    // Conexión root, sin base seleccionada (para crear bases temporales).
    public static function admin(): PDO
    {
        return self::$conexiones['admin'] ??= ConexionAdmin::crear(
            getenv('NL_TEST_HOST') ?: 'localhost',
            (int)(getenv('NL_TEST_PUERTO') ?: 3306),
            getenv('NL_TEST_ADMIN_USUARIO') ?: 'root',
            getenv('NL_TEST_ADMIN_CLAVE') ?: ''
        );
    }

    public static function root(): PDO
    {
        self::asegurarInstalada();
        return self::$conexiones['root'] ??= self::conectar(
            getenv('NL_TEST_ADMIN_USUARIO') ?: 'root',
            getenv('NL_TEST_ADMIN_CLAVE') ?: ''
        );
    }

    public static function app(): PDO
    {
        self::asegurarInstalada();
        return self::$conexiones['app'] ??= self::conectar('nl_app', getenv('NL_TEST_APP_CLAVE') ?: '');
    }

    public static function lectura(): PDO
    {
        self::asegurarInstalada();
        return self::$conexiones['lectura'] ??= self::conectar('nl_lectura', getenv('NL_TEST_LECTURA_CLAVE') ?: '');
    }

    private static function conectar(string $usuario, string $clave): PDO
    {
        $host = getenv('NL_TEST_HOST') ?: 'localhost';
        $puerto = (int)(getenv('NL_TEST_PUERTO') ?: 3306);
        $pdo = new PDO("mysql:host=$host;port=$puerto;dbname=" . self::nombre() . ";charset=utf8mb4", $usuario, $clave, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $pdo->exec("SET time_zone = '-03:00'");
        return $pdo;
    }
}
