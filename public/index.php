<?php
// ============================================================
// public/index.php - Front Controller FlexArena
// ============================================================

session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

spl_autoload_register(function (string $clase) {
    $rutas = [
        __DIR__ . '/../app/controllers/' . $clase . '.php',
        __DIR__ . '/../app/models/' . $clase . '.php',
    ];
    foreach ($rutas as $ruta) {
        if (file_exists($ruta)) {
            require_once $ruta;
            return;
        }
    }
});

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
$path = '/' . ltrim(str_replace($basePath, '', $path), '/');

$rutas = [
    'GET:/login' => ['AuthController', 'mostrarLogin'],
    'POST:/login' => ['AuthController', 'procesarLogin'],
    'GET:/logout' => ['AuthController', 'logout'],

    'GET:/admin/dashboard' => ['AdminController', 'dashboard'],
    'GET:/admin/usuarios' => ['AdminController', 'usuarios'],
    'GET:/admin/usuarios/nuevo' => ['AdminController', 'crearUsuario'],
    'POST:/admin/usuarios/nuevo' => ['AdminController', 'guardarUsuario'],
    'GET:/admin/usuarios/editar' => ['AdminController', 'editarUsuario'],
    'POST:/admin/usuarios/editar' => ['AdminController', 'actualizarUsuario'],
    'POST:/admin/usuarios/estado' => ['AdminController', 'cambiarEstadoUsuario'],

    'GET:/admin/participantes' => ['AdminController', 'participantes'],
    'GET:/admin/participantes/nuevo' => ['AdminController', 'crearParticipante'],
    'POST:/admin/participantes/nuevo' => ['AdminController', 'guardarParticipante'],
    'GET:/admin/participantes/editar' => ['AdminController', 'editarParticipante'],
    'POST:/admin/participantes/editar' => ['AdminController', 'actualizarParticipante'],

    'GET:/admin/equipos' => ['AdminController', 'equipos'],
    'GET:/admin/equipos/nuevo' => ['AdminController', 'crearEquipo'],
    'POST:/admin/equipos/nuevo' => ['AdminController', 'guardarEquipo'],
    'GET:/admin/equipos/editar' => ['AdminController', 'editarEquipo'],
    'POST:/admin/equipos/editar' => ['AdminController', 'actualizarEquipo'],

    'GET:/admin/torneos' => ['AdminController', 'torneos'],
    'GET:/admin/torneos/nuevo' => ['AdminController', 'crearTorneo'],
    'POST:/admin/torneos/nuevo' => ['AdminController', 'guardarTorneo'],
    'GET:/admin/torneos/editar' => ['AdminController', 'editarTorneo'],
    'POST:/admin/torneos/editar' => ['AdminController', 'actualizarTorneo'],
    'POST:/admin/torneos/estado' => ['AdminController', 'cambiarEstado'],
    'GET:/admin/torneos/gestionar' => ['AdminController', 'gestionarTorneo'],
    'POST:/admin/torneos/inscribir' => ['AdminController', 'inscribirParticipante'],
    'POST:/admin/torneos/quitar' => ['AdminController', 'quitarParticipante'],
    'POST:/admin/torneos/generar-rondas' => ['AdminController', 'generarRondas'],
    'POST:/admin/resultados/guardar' => ['AdminController', 'guardarResultado'],
    'GET:/admin/auditoria' => ['AdminController', 'auditoria'],
    'GET:/admin/modulos' => ['AdminController', 'modulos'],

    'GET:/organizador/dashboard' => ['OrganizadorController', 'dashboard'],
    'GET:/organizador/torneos' => ['OrganizadorController', 'torneos'],
    'GET:/organizador/torneos/gestionar' => ['OrganizadorController', 'gestionar'],
    'POST:/organizador/torneos/inscribir' => ['OrganizadorController', 'inscribir'],
    'POST:/organizador/torneos/quitar' => ['OrganizadorController', 'quitar'],
    'POST:/organizador/torneos/generar-rondas' => ['OrganizadorController', 'generarRondas'],
    'POST:/organizador/resultados/guardar' => ['OrganizadorController', 'guardarResultado'],

    'GET:/participante/dashboard' => ['ParticipanteController', 'dashboard'],

    'GET:/' => ['PublicoController', 'inicio'],
    'GET:/torneos' => ['PublicoController', 'torneos'],
    'GET:/torneo' => ['PublicoController', 'detalle'],
];

$clave = $method . ':' . $path;
if (isset($rutas[$clave])) {
    [$clase, $metodo] = $rutas[$clave];
    $controlador = new $clase();
    $controlador->$metodo();
    exit;
}

http_response_code(404);
echo '<h1>404 - Página no encontrada</h1>';
echo '<p><a href="' . htmlspecialchars(BASE_URL) . '">Volver al inicio</a></p>';
