<?php
// =====================================================================
// SIMULAR EL PASO DEL TIEMPO EN LAS PRUEBAS DE PUNTA A PUNTA
// Varias reglas dependen de la fecha y la hora: el enlace para recuperar
// la contraseña vence a los 30 minutos, el bloqueo por intentos fallidos
// dura 15, la sesión vence después de 2 horas sin actividad, "Recordarme"
// dura 30 días, y los torneos y los partidos tienen fechas.
// Para probarlas sin esperar de verdad, en lugar de adelantar el reloj se
// atrasan los datos guardados: que un enlace se haya creado hace 31
// minutos es lo mismo que si hubieran pasado 31 minutos desde que se creó.
//
// Lo ejecuta Cypress con cy.simularTiempo() (tests/e2e/soporte/e2e.js),
// que le pasa el pedido en JSON por la variable de entorno NL_SIMULAR.
// Solo trabaja sobre la base de las pruebas (nextleague_e2e_test).
// =====================================================================

ini_set('display_errors', 'stderr'); // la salida estándar es solo el JSON

require __DIR__ . '/../../app/Aplicacion.php';

const BASE = 'nextleague_e2e_test';
Config::establecer(['bd' => ['nombre' => BASE]]);

$pedido = json_decode((string)getenv('NL_SIMULAR'), true);
if (!is_array($pedido) || empty($pedido['accion'])) {
    fwrite(STDERR, "Falta el pedido en NL_SIMULAR (lo manda cypress.config.js).\n");
    exit(1);
}
$entero = fn(string $clave) => (int)($pedido[$clave] ?? 0);

$afectados = match ($pedido['accion']) {
    // Pasan N días para un torneo: se atrasan juntas todas sus fechas (inicio,
    // fin, rondas y partidos), como si el tiempo hubiera corrido.
    'pasan_dias' => atrasarTorneo($entero('id'), $entero('dias')),
    // Fechas de un torneo, en días desde hoy (inicio -1 = empezó ayer)
    'torneo' => BaseDatos::ejecutar(
        'UPDATE torneos SET fecha_inicio = CURDATE() + INTERVAL ? DAY, fecha_fin = CURDATE() + INTERVAL ? DAY WHERE id_torneo = ?',
        [$entero('inicio'), $entero('fin'), $entero('id')]
    ),
    // Fecha y hora de un partido, en días desde hoy y hora "HH:MM"
    'partido' => BaseDatos::ejecutar(
        'UPDATE enfrentamientos SET fecha_hora = TIMESTAMP(CURDATE() + INTERVAL ? DAY, ?) WHERE id_enfrentamiento = ?',
        [$entero('dias'), (string)($pedido['hora'] ?? '20:00'), $entero('id')]
    ),
    // Pasaron N minutos desde que se pidió el enlace para recuperar la contraseña
    'token_recuperacion' => atrasarTokens((string)$pedido['correo'], 'recuperacion', $entero('minutos')),
    // Pasaron N días desde que se marcó "Recordarme"
    'recordarme' => atrasarTokens((string)$pedido['correo'], 'recordarme', $entero('dias') * 24 * 60),
    // Pasaron N minutos desde los intentos de ingreso de ese correo
    'intentos_login' => BaseDatos::ejecutar(
        'UPDATE intentos_login SET fecha = fecha - INTERVAL ? MINUTE WHERE correo = ?',
        [$entero('minutos'), (string)$pedido['correo']]
    ),
    // Pasaron N minutos sin actividad en una sesión abierta
    'sesion' => atrasarSesion((string)$pedido['id'], $entero('minutos')),
    default => null,
};

if ($afectados === null) {
    fwrite(STDERR, "Acción desconocida: {$pedido['accion']}\n");
    exit(1);
}
echo json_encode(['afectados' => $afectados]);

function atrasarTorneo(int $idTorneo, int $dias): int
{
    $afectados = BaseDatos::ejecutar(
        'UPDATE torneos SET fecha_inicio = fecha_inicio - INTERVAL ? DAY, fecha_fin = fecha_fin - INTERVAL ? DAY WHERE id_torneo = ?',
        [$dias, $dias, $idTorneo]
    );
    BaseDatos::ejecutar('UPDATE rondas SET fecha = fecha - INTERVAL ? DAY WHERE id_torneo = ? AND fecha IS NOT NULL', [$dias, $idTorneo]);
    BaseDatos::ejecutar(
        'UPDATE enfrentamientos e JOIN rondas r ON r.id_ronda = e.id_ronda
         SET e.fecha_hora = e.fecha_hora - INTERVAL ? DAY WHERE r.id_torneo = ? AND e.fecha_hora IS NOT NULL',
        [$dias, $idTorneo]
    );
    return $afectados;
}

function atrasarTokens(string $correo, string $tipo, int $minutos): int
{
    return BaseDatos::ejecutar(
        'UPDATE tokens_usuario t JOIN usuarios u ON u.id_usuario = t.id_usuario
         SET t.vence = t.vence - INTERVAL ? MINUTE, t.fecha_creacion = t.fecha_creacion - INTERVAL ? MINUTE
         WHERE u.correo = ? AND t.tipo = ?',
        [$minutos, $minutos, $correo, $tipo]
    );
}

// La sesión la guarda PHP en un archivo (en la carpeta de Sesion::carpeta())
// con la hora de la última actividad; se le restan los minutos.
function atrasarSesion(string $idSesion, int $minutos): int
{
    if (!preg_match('/^[A-Za-z0-9,-]{20,128}$/', $idSesion)) {
        fwrite(STDERR, "Id de sesión inválido.\n");
        exit(1);
    }
    $archivo = Sesion::carpeta() . DIRECTORY_SEPARATOR . "sess_$idSesion";
    if (!is_file($archivo)) {
        return 0;
    }
    $datos = preg_replace_callback('/ultima_actividad\|i:(\d+);/',
        fn($m) => 'ultima_actividad|i:' . ((int)$m[1] - $minutos * 60) . ';',
        (string)file_get_contents($archivo), 1, $cambios);
    file_put_contents($archivo, $datos);
    return $cambios;
}
