<?php
// ============================================================
//  app/models/Database.php
//  Conexión PDO como Singleton
//  Una sola instancia por request, reutilizable en todos los modelos
// ============================================================

require_once __DIR__ . '/../../config/database.php';

class Database
{
    private static ?PDO $instance = null;

    // Evitar instanciación directa
    private function __construct() {}

    /**
     * Devuelve la instancia única de PDO.
     * La crea la primera vez que se llama.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASSWORD, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // En producción no mostrar el mensaje detallado
                error_log('Error de conexión BD: ' . $e->getMessage());
                die(json_encode(['error' => 'No se pudo conectar a la base de datos.']));
            }
        }

        return self::$instance;
    }
}
