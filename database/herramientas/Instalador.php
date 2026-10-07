<?php
// =====================================================================
// INSTALADOR DE LA BASE DE DATOS
// Ejecuta los scripts en el orden correcto, tanto para una instalación
// nueva como para migrar una base de la versión anterior. Lo usan el
// script de consola database/instalar.php y los tests.
// =====================================================================

require_once __DIR__ . '/EjecutorSql.php';
require_once __DIR__ . '/ConexionAdmin.php';

class Instalador
{
    private const CARPETA = __DIR__ . '/..';

    // Scripts de una instalación nueva, en orden.
    public const SCRIPTS_NUEVA = [
        '01_esquema.sql',
        '02_datos_base.sql',
        '03_vistas.sql',
        '04_triggers.sql',
        '05_usuarios_dcl.sql',
    ];

    // Scripts de la migración desde la versión anterior (v6), en orden.
    public const SCRIPTS_MIGRACION = [
        'migracion_v7_preparar.sql',
        '01_esquema.sql',
        'migracion_v7_datos.sql',
        '03_vistas.sql',
        '04_triggers.sql',
        '05_usuarios_dcl.sql',
    ];

    public const SCRIPT_DATOS_PRUEBA = '06_datos_prueba.sql';

    /** @var callable|null */
    private $avisar;

    public function __construct(private PDO $pdo, ?callable $avisar = null)
    {
        $this->avisar = $avisar;
    }

    public function existeBase(string $bd): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$bd]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function cantidadTablas(string $bd): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?");
        $stmt->execute([$bd]);
        return (int)$stmt->fetchColumn();
    }

    // Crea la base (si $recrear es true, primero borra la existente) y
    // ejecuta los scripts de instalación nueva.
    public function instalarNueva(string $bd, bool $recrear = false, bool $datosPrueba = false): void
    {
        $this->validarNombre($bd);

        if ($this->existeBase($bd)) {
            if (!$recrear && $this->cantidadTablas($bd) > 0) {
                throw new RuntimeException("La base `$bd` ya existe y tiene tablas. Usá --recrear para borrarla y crearla de nuevo.");
            }
            $this->pdo->exec("DROP DATABASE `$bd`");
            $this->aviso("Base `$bd` borrada.");
        }

        $this->pdo->exec("CREATE DATABASE `$bd` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->pdo->exec("USE `$bd`");
        $this->aviso("Base `$bd` creada.");

        $scripts = self::SCRIPTS_NUEVA;
        if ($datosPrueba) {
            $scripts[] = self::SCRIPT_DATOS_PRUEBA;
        }
        $this->ejecutarScripts($scripts);
    }

    // Convierte en el lugar una base de la versión anterior a la v2.
    public function migrar(string $bd): void
    {
        $this->validarNombre($bd);
        if (!$this->existeBase($bd)) {
            throw new RuntimeException("La base `$bd` no existe.");
        }
        $this->pdo->exec("USE `$bd`");

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'rol'");
        $stmt->execute([$bd]);
        if ((int)$stmt->fetchColumn() === 0) {
            throw new RuntimeException("La base `$bd` no tiene el formato de la versión anterior (no encontré usuarios.rol). ¿Ya estaba migrada?");
        }

        $this->ejecutarScripts(self::SCRIPTS_MIGRACION);
    }

    private function ejecutarScripts(array $scripts): void
    {
        foreach ($scripts as $script) {
            $cantidad = EjecutorSql::ejecutarArchivo($this->pdo, self::CARPETA . '/' . $script);
            $this->aviso(str_pad($script, 30) . " $cantidad sentencias");
        }
    }

    private function validarNombre(string $bd): void
    {
        if (!ConexionAdmin::nombreBaseValido($bd)) {
            throw new InvalidArgumentException("Nombre de base inválido: usá solo letras, números y guion bajo.");
        }
    }

    private function aviso(string $mensaje): void
    {
        if ($this->avisar) {
            ($this->avisar)($mensaje);
        }
    }
}
