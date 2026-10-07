<?php
// =====================================================================
// PREPARA LA BASE DE LAS PRUEBAS DE PUNTA A PUNTA
// Cypress lo ejecuta antes de cada archivo de pruebas (cy.task('prepararBase')):
//   1. borra los archivos que subieron las pruebas anteriores (logos,
//      reglas, fotos), para no llenar public/uploads;
//   2. vuelve a crear nextleague_e2e_test con los scripts de instalación;
//   3. crea usuarios y torneos conocidos usando la propia API (sin
//      servidor web, igual que los tests de PHPUnit);
//   4. imprime en JSON los ids que las pruebas necesitan.
// Nunca toca nextleague_v2: el nombre de la base tiene que terminar en _test.
// La contraseña de las cuentas la manda cypress.config.js (NL_E2E_CLAVE).
// =====================================================================

ini_set('display_errors', 'stderr'); // la salida estándar es solo el JSON

require __DIR__ . '/../../app/Aplicacion.php';
require __DIR__ . '/../../database/herramientas/Instalador.php';
require __DIR__ . '/../soporte/ClienteApi.php';

const BASE = 'nextleague_e2e_test';

$clave = getenv('NL_E2E_CLAVE');
if (!$clave) {
    fwrite(STDERR, "Falta NL_E2E_CLAVE: este script lo ejecuta Cypress (ver cypress.config.js).\n");
    exit(1);
}

Config::establecer(['bd' => ['nombre' => BASE], 'entorno' => 'desarrollo']);
GestorCompetencia::$mezclar = function (array &$lista): void {}; // sin sorteo: siempre los mismos cruces
Correo::$modoPrueba = true;

$root = ConexionAdmin::crear(
    getenv('NL_TEST_HOST') ?: 'localhost',
    (int)(getenv('NL_TEST_PUERTO') ?: 3306),
    getenv('NL_TEST_ADMIN_USUARIO') ?: 'root',
    getenv('NL_TEST_ADMIN_CLAVE') ?: ''
);
$instalador = new Instalador($root);

// ── 1. Archivos subidos en la corrida anterior ──
if ($instalador->existeBase(BASE) && $instalador->cantidadTablas(BASE) > 0) {
    $consultas = [
        'torneos/logos'  => 'SELECT logo FROM ' . BASE . '.torneos WHERE logo IS NOT NULL',
        'torneos/reglas' => 'SELECT reglas_pdf FROM ' . BASE . '.torneos WHERE reglas_pdf IS NOT NULL',
        'perfiles'       => 'SELECT foto_perfil FROM ' . BASE . '.usuarios WHERE foto_perfil IS NOT NULL',
    ];
    foreach ($consultas as $carpeta => $sql) {
        foreach ($root->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $archivo) {
            Archivo::borrar($carpeta, $archivo);
        }
    }
}

// ── 2. Base nueva ──
$instalador->instalarNueva(BASE, true);

// ── 3. Datos conocidos ──
function exigir(Respuesta $respuesta, string $que): array
{
    if ($respuesta->estado >= 300 || !($respuesta->cuerpo['exito'] ?? true)) {
        fwrite(STDERR, "No se pudo $que: " . json_encode($respuesta->cuerpo, JSON_UNESCAPED_UNICODE) . "\n");
        exit(1);
    }
    return $respuesta->cuerpo;
}

$usuarios = [];
foreach ([
    'admin_e2e' => Rol::ADMINISTRADOR,
    'orga_e2e' => Rol::ORGANIZADOR,
    'orga_otro_e2e' => Rol::ORGANIZADOR,
    'capitan_e2e' => Rol::PARTICIPANTE,
    'jugador_e2e1' => Rol::PARTICIPANTE, 'jugador_e2e2' => Rol::PARTICIPANTE,
    'jugador_e2e3' => Rol::PARTICIPANTE, 'jugador_e2e4' => Rol::PARTICIPANTE,
    'jugador_e2e5' => Rol::PARTICIPANTE, 'jugador_e2e6' => Rol::PARTICIPANTE,
    'jugador_e2e7' => Rol::PARTICIPANTE, 'jugador_e2e8' => Rol::PARTICIPANTE,
] as $nombre => $rol) {
    $usuarios[$nombre] = Usuario::crear($nombre, "$nombre@e2e.uy", $clave, $rol);
}
$como = fn(string $nombre) => ClienteApi::como($usuarios[$nombre]);
$orga = $como('orga_e2e');

function fecha(string $cambio): string
{
    return date('Y-m-d', strtotime($cambio));
}

function crearTorneo(ClienteApi $organizador, array $datos): int
{
    return exigir($organizador->post('/torneos', $datos + [
        'modalidad' => 'individual', 'cupo' => 4,
        'fecha_inicio' => fecha('+7 days'), 'fecha_fin' => fecha('+30 days'),
        'descripcion' => 'Torneo creado para las pruebas de punta a punta.',
    ]), "crear el torneo {$datos['nombre']}")['id_torneo'];
}

function inscribir(int $idTorneo, array $clientes): void
{
    foreach ($clientes as $cliente) {
        exigir($cliente->post("/torneos/$idTorneo/inscripcion"), "inscribir en el torneo $idTorneo");
    }
}

function partidos(ClienteApi $cliente, int $idTorneo, int $numeroRonda): array
{
    foreach (exigir($cliente->get("/torneos/$idTorneo/calendario"), 'leer el calendario')['rondas'] as $ronda) {
        if ((int)$ronda['numero'] === $numeroRonda) {
            return array_values(array_filter($ronda['partidos'], fn($p) => $p['id_local'] && $p['id_visitante'] && !$p['es_pase_libre']));
        }
    }
    return [];
}

$cuatro = array_map($como, ['jugador_e2e1', 'jugador_e2e2', 'jugador_e2e3', 'jugador_e2e4']);
$torneos = [];

// Liga en curso: ronda 1 jugada y un partido de la ronda 2 programado
// (dentro de las fechas del torneo: empieza en 7 días)
$torneos['liga_en_curso'] = $id = crearTorneo($orga, ['nombre' => 'Liga E2E de Ajedrez', 'id_juego' => 28, 'modulo' => 'liga']);
inscribir($id, $cuatro);
exigir($orga->post("/torneos/$id/calendario"), 'generar el calendario de la liga');
exigir($orga->post("/torneos/$id/rondas/publicar"), 'publicar la liga');
foreach (partidos($orga, $id, 1) as $i => $p) {
    exigir($orga->post("/enfrentamientos/{$p['id_enfrentamiento']}/resultado", ['puntaje_local' => 2 + $i, 'puntaje_visitante' => 0]), 'cargar un resultado');
}
$programado = partidos($orga, $id, 2)[0];
exigir($orga->put("/enfrentamientos/{$programado['id_enfrentamiento']}", [
    'fecha_hora' => fecha('+8 days') . 'T19:30', 'lugar' => 'Sala E2E',
]), 'programar un partido');

// Eliminación directa terminada (con campeón)
$torneos['copa_final'] = $id = crearTorneo($orga, ['nombre' => 'Copa E2E de Damas', 'id_juego' => 29, 'modulo' => 'eliminacion_directa']);
inscribir($id, $cuatro);
exigir($orga->post("/torneos/$id/calendario"), 'generar las llaves');
exigir($orga->post("/torneos/$id/rondas/publicar"), 'publicar las llaves');
foreach ([1, 2] as $ronda) {
    foreach (partidos($orga, $id, $ronda) as $p) {
        exigir($orga->post("/enfrentamientos/{$p['id_enfrentamiento']}/resultado", ['puntaje_local' => 3, 'puntaje_visitante' => 1]), 'cargar un resultado de la copa');
    }
}

// Liga de 8 jugadores (7 rondas): en el celular el selector de rondas no entra y se desliza
$torneos['liga_grande'] = $id = crearTorneo($orga, ['nombre' => 'Liga E2E Grande', 'id_juego' => 30, 'modulo' => 'liga', 'cupo' => 8]);
inscribir($id, array_map($como, ['jugador_e2e1', 'jugador_e2e2', 'jugador_e2e3', 'jugador_e2e4',
    'jugador_e2e5', 'jugador_e2e6', 'jugador_e2e7', 'jugador_e2e8']));
exigir($orga->post("/torneos/$id/calendario"), 'generar el calendario de la liga grande');
exigir($orga->post("/torneos/$id/rondas/publicar"), 'publicar la liga grande');

// Torneos con 4 inscriptos, listos para que el organizador genere el calendario desde la página
foreach ([
    'liga_lista' => ['nombre' => 'Liga E2E Lista', 'id_juego' => 31, 'modulo' => 'liga'],
    'copa_lista' => ['nombre' => 'Copa E2E Lista', 'id_juego' => 32, 'modulo' => 'eliminacion_directa'],
    'suizo_listo' => ['nombre' => 'Suizo E2E Listo', 'id_juego' => 28, 'modulo' => 'sistema_suizo'],
] as $codigo => $datos) {
    $torneos[$codigo] = $id = crearTorneo($orga, $datos);
    inscribir($id, $cuatro);
}

// Abierto individual sin inscriptos (inscribirse, darse de baja, cupo)
$torneos['abierto'] = crearTorneo($orga, ['nombre' => 'Abierto E2E de Póker', 'id_juego' => 31, 'modulo' => 'liga']);

// Por equipos, con un equipo que tiene capitán (pedir unirse y aceptar)
$torneos['equipos'] = $id = crearTorneo($orga, ['nombre' => 'Torneo E2E de Fútbol 5', 'id_juego' => 2, 'modulo' => 'liga',
    'modalidad' => 'equipos', 'max_integrantes_equipo' => 5]);
$equipo = exigir($como('capitan_e2e')->post("/torneos/$id/equipos", ['nombre' => 'Los Toros E2E']), 'crear el equipo');

// Oculto (solo lo ven su organizador y los administradores)
$torneos['oculto'] = crearTorneo($orga, ['nombre' => 'Torneo E2E Oculto', 'id_juego' => 28, 'modulo' => 'liga', 'publicado' => 0]);

// De otro organizador (orga_e2e no lo puede gestionar)
$torneos['ajeno'] = crearTorneo($como('orga_otro_e2e'), ['nombre' => 'Torneo E2E Ajeno', 'id_juego' => 28, 'modulo' => 'liga']);

// Nombre con código: tiene que mostrarse como texto, nunca ejecutarse
$torneos['con_codigo'] = crearTorneo($orga, ['nombre' => '<img src=x onerror=alert(1)> Copa XSS', 'id_juego' => 28, 'modulo' => 'liga']);

// ── 4. Datos para las pruebas ──
echo json_encode([
    'usuarios' => $usuarios,
    'torneos' => $torneos,
    'equipo' => (int)($equipo['id_equipo'] ?? BaseDatos::valor('SELECT id_equipo FROM equipos WHERE nombre = ?', ['Los Toros E2E'])),
    'partido_programado' => (int)$programado['id_enfrentamiento'],
], JSON_UNESCAPED_UNICODE);
