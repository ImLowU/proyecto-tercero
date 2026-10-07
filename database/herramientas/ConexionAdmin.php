<?php
// =====================================================================
// CONEXIÓN DE ADMINISTRACIÓN PARA LAS HERRAMIENTAS DE CONSOLA
// El instalador y el generador de triggers necesitan crear bases y
// usuarios, así que se conectan con una cuenta con todos los permisos
// (en XAMPP: root sin contraseña). La aplicación NO usa esta conexión:
// usa la cuenta limitada nl_app (ver 05_usuarios_dcl.sql).
// =====================================================================

class ConexionAdmin
{
    // Lee --host, --puerto, --usuario y --clave de la línea de comandos.
    public static function desdeArgumentos(array $opciones): PDO
    {
        $host    = $opciones['host'] ?? 'localhost';
        $puerto  = (int)($opciones['puerto'] ?? 3306);
        $usuario = $opciones['usuario'] ?? 'root';
        $clave   = $opciones['clave'] ?? '';
        return self::crear($host, $puerto, $usuario, $clave);
    }

    public static function crear(string $host, int $puerto, string $usuario, string $clave): PDO
    {
        $pdo = new PDO("mysql:host=$host;port=$puerto;charset=utf8mb4", $usuario, $clave, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Modo estricto: un dato inválido da error en vez de recortarse en silencio.
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $pdo->exec("SET time_zone = '-03:00'");
        return $pdo;
    }

    // Valida un nombre de base para poder usarlo entre `...` sin riesgo.
    public static function nombreBaseValido(string $nombre): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9_]{1,64}$/', $nombre);
    }
}
