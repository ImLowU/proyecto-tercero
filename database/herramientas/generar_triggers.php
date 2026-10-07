<?php
// =====================================================================
// GENERADOR DE TRIGGERS DE AUDITORÍA
// Escribe database/04_triggers.sql a partir de las columnas reales de
// cada tabla. Hay 3 triggers por tabla (después de INSERT, UPDATE y
// DELETE) y escribirlos a mano sería repetitivo y fácil de equivocar:
// si mañana se agrega una columna, se vuelve a correr este script.
//
// Uso (con MySQL de XAMPP encendido):
//   php database/herramientas/generar_triggers.php [--usuario=root] [--clave=]
//
// Crea una base temporal, ejecuta 01_esquema.sql, lee las columnas desde
// information_schema, genera el archivo y borra la base temporal.
// =====================================================================

require __DIR__ . '/EjecutorSql.php';
require __DIR__ . '/ConexionAdmin.php';

// Tablas auditadas. "ocultar": columnas sensibles que no se copian al
// historial; solo se registra SI cambiaron, con el nombre indicado.
// "ignorar": columnas que cambian solas y no vale la pena registrar (el
// último acceso cambia en cada login, que ya se registra aparte).
const TABLAS_AUDITADAS = [
    'roles'                 => [],
    'usuarios'              => ['ocultar' => ['contrasena_hash' => 'contrasena_cambiada'], 'ignorar' => ['ultimo_acceso']],
    'juegos'                => [],
    'modulos_competencia'   => [],
    'torneos'               => [],
    'configuracion_torneo'  => [],
    'equipos'               => [],
    'equipo_miembros'       => [],
    'equipo_solicitudes'    => [],
    'participantes'         => [],
    'rondas'                => [],
    'enfrentamientos'       => [],
    'resultados'            => [],
    'configuracion_sistema' => [],
];

$opciones = getopt('', ['host::', 'puerto::', 'usuario::', 'clave::']);
$pdo = ConexionAdmin::desdeArgumentos($opciones);

$baseTemporal = 'nextleague_tmp_triggers';
$pdo->exec("DROP DATABASE IF EXISTS `$baseTemporal`");
$pdo->exec("CREATE DATABASE `$baseTemporal` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$baseTemporal`");

try {
    EjecutorSql::ejecutarArchivo($pdo, __DIR__ . '/../01_esquema.sql');

    $salida  = "-- =====================================================================\n";
    $salida .= "-- NEXTLEAGUE v2 - TRIGGERS DE AUDITORÍA\n";
    $salida .= "-- ARCHIVO GENERADO por database/herramientas/generar_triggers.php.\n";
    $salida .= "-- No editar a mano: si cambia una tabla, volver a correr el generador.\n";
    $salida .= "--\n";
    $salida .= "-- Cada INSERT, UPDATE o DELETE en las tablas de negocio agrega una fila\n";
    $salida .= "-- en \"auditoria\" con los valores anteriores y nuevos en formato JSON.\n";
    $salida .= "-- @id_usuario_app y @ip_app son variables que la aplicación fija al\n";
    $salida .= "-- conectarse; si el cambio se hace desde phpMyAdmin quedan en NULL y\n";
    $salida .= "-- usuario_bd muestra qué cuenta de MySQL lo hizo.\n";
    $salida .= "--\n";
    $salida .= "-- Cada trigger es una sola sentencia (sin BEGIN...END), así el archivo\n";
    $salida .= "-- se importa en phpMyAdmin sin tener que cambiar el DELIMITER.\n";
    $salida .= "-- Los UPDATE solo se registran si de verdad cambió algún valor.\n";
    $salida .= "-- Nota: MySQL no dispara triggers en los borrados en cascada de las\n";
    $salida .= "-- claves foráneas; queda registrado el borrado de la fila principal.\n";
    $salida .= "-- =====================================================================\n\n";
    $salida .= "SET NAMES utf8mb4;\n";

    foreach (TABLAS_AUDITADAS as $tabla => $reglas) {
        $columnas = columnasDe($pdo, $baseTemporal, $tabla);
        $clave = clavePrimariaDe($pdo, $baseTemporal, $tabla);
        $ocultar = $reglas['ocultar'] ?? [];
        $ignorar = $reglas['ignorar'] ?? [];
        $visibles = array_values(array_diff($columnas, array_keys($ocultar), $ignorar));
        $comparables = array_values(array_diff($columnas, $ignorar));

        $salida .= "\n-- ---------------------------------------------------------------------\n";
        $salida .= "-- $tabla\n";
        $salida .= "-- ---------------------------------------------------------------------\n";

        // Después de INSERT
        $salida .= "CREATE TRIGGER trg_{$tabla}_ai AFTER INSERT ON $tabla FOR EACH ROW\n";
        $salida .= "INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)\n";
        $salida .= "VALUES ('bd', 'crear', '$tabla', " . idRegistro($clave, 'NEW') . ", @id_usuario_app, USER(), @ip_app,\n";
        $salida .= "        " . jsonObjeto($visibles, 'NEW') . ");\n\n";

        // Después de UPDATE (solo si cambió alguna columna comparable)
        $jsonNuevos = jsonObjeto($visibles, 'NEW', array_map(
            fn($c, $marca) => "'$marca', NOT (OLD.$c <=> NEW.$c)",
            array_keys($ocultar),
            $ocultar
        ));
        $condicion = implode("\n           AND ", array_map(fn($c) => "OLD.$c <=> NEW.$c", $comparables));
        $salida .= "CREATE TRIGGER trg_{$tabla}_au AFTER UPDATE ON $tabla FOR EACH ROW\n";
        $salida .= "INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)\n";
        $salida .= "SELECT 'bd', 'editar', '$tabla', " . idRegistro($clave, 'NEW') . ", @id_usuario_app, USER(), @ip_app,\n";
        $salida .= "       " . jsonObjeto($visibles, 'OLD') . ",\n";
        $salida .= "       $jsonNuevos\n";
        $salida .= "FROM DUAL\n";
        $salida .= "WHERE NOT (    $condicion);\n\n";

        // Después de DELETE
        $salida .= "CREATE TRIGGER trg_{$tabla}_ad AFTER DELETE ON $tabla FOR EACH ROW\n";
        $salida .= "INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)\n";
        $salida .= "VALUES ('bd', 'eliminar', '$tabla', " . idRegistro($clave, 'OLD') . ", @id_usuario_app, USER(), @ip_app,\n";
        $salida .= "        " . jsonObjeto($visibles, 'OLD') . ");\n";
    }

    file_put_contents(__DIR__ . '/../04_triggers.sql', $salida);
    echo "Listo: database/04_triggers.sql (" . count(TABLAS_AUDITADAS) * 3 . " triggers)\n";
} finally {
    $pdo->exec("DROP DATABASE IF EXISTS `$baseTemporal`");
}

// ── Funciones auxiliares ──

function columnasDe(PDO $pdo, string $base, string $tabla): array
{
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
    $stmt->execute([$base, $tabla]);
    $columnas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$columnas) {
        throw new RuntimeException("La tabla $tabla no existe en 01_esquema.sql");
    }
    return $columnas;
}

function clavePrimariaDe(PDO $pdo, string $base, string $tabla): array
{
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY'
                           ORDER BY ORDINAL_POSITION");
    $stmt->execute([$base, $tabla]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// La clave del registro afectado; si es compuesta se une con "-" (ej: "4-12").
function idRegistro(array $clave, string $fila): string
{
    if (count($clave) === 1) {
        return "$fila.{$clave[0]}";
    }
    return "CONCAT_WS('-', " . implode(', ', array_map(fn($c) => "$fila.$c", $clave)) . ")";
}

function jsonObjeto(array $columnas, string $fila, array $extras = []): string
{
    $pares = array_map(fn($c) => "'$c', $fila.$c", $columnas);
    return 'JSON_OBJECT(' . implode(', ', array_merge($pares, $extras)) . ')';
}
