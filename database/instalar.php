<?php
// =====================================================================
// INSTALADOR DE LA BASE DE DATOS (línea de comandos)
//
// Instalación nueva (crea la base y ejecuta 01 → 05):
//   php database/instalar.php --bd=nextleague_v2
//   php database/instalar.php --bd=nextleague_v2 --datos-prueba
//
// Volver a crear desde cero (BORRA todos los datos de esa base):
//   php database/instalar.php --bd=nextleague_v2 --recrear
//
// Migrar una base de la versión anterior a la v2 (sin perder datos):
//   php database/instalar.php --bd=nextleague --migrar
//
// Conexión (por defecto la de XAMPP): --host=localhost --puerto=3306
//                                     --usuario=root --clave=
// En XAMPP, "php" es C:\xampp\php\php.exe
// =====================================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script se ejecuta desde la consola.\n");
}

require __DIR__ . '/herramientas/Instalador.php';

$opciones = getopt('', ['bd:', 'host::', 'puerto::', 'usuario::', 'clave::',
                        'recrear', 'datos-prueba', 'migrar', 'forzar', 'ayuda']);

if (isset($opciones['ayuda']) || empty($opciones['bd'])) {
    $ayuda = file_get_contents(__FILE__);
    preg_match('/\/\/ INSTALADOR.*?\/\/ =+\n/s', $ayuda, $bloque);
    echo preg_replace('/^\/\/ ?/m', '', $bloque[0] ?? "Uso: php database/instalar.php --bd=NOMBRE\n");
    exit(empty($opciones['bd']) && !isset($opciones['ayuda']) ? 1 : 0);
}

$bd = $opciones['bd'];

// Pide confirmación antes de cualquier operación que pueda perder datos.
function confirmar(string $pregunta, string $esperado): void
{
    echo "$pregunta\nEscribí \"$esperado\" para continuar: ";
    $respuesta = trim((string)fgets(STDIN));
    if ($respuesta !== $esperado) {
        echo "Cancelado.\n";
        exit(1);
    }
}

try {
    $pdo = ConexionAdmin::desdeArgumentos($opciones);
    $instalador = new Instalador($pdo, fn($m) => print("  $m\n"));

    if (isset($opciones['migrar'])) {
        if (!isset($opciones['forzar'])) {
            confirmar("Se va a convertir la base `$bd` a la versión 2. Hacé un respaldo antes (phpMyAdmin → Exportar).", $bd);
        }
        echo "Migrando `$bd`...\n";
        $instalador->migrar($bd);
    } else {
        $recrear = isset($opciones['recrear']);
        if ($recrear && $instalador->existeBase($bd) && !isset($opciones['forzar'])) {
            confirmar("Se va a BORRAR la base `$bd` con todos sus datos.", $bd);
        }
        echo "Instalando `$bd`...\n";
        $instalador->instalarNueva($bd, $recrear, isset($opciones['datos-prueba']));
    }
    echo "Listo.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
