<?php
// =====================================================================
// CONFIGURACIÓN DE NEXTLEAGUE DENTRO DE DOCKER
// La imagen copia este archivo como config.php. Los datos salen de las
// variables de entorno que docker-compose.yml toma del archivo .env, así
// la misma imagen sirve en cualquier máquina y ninguna clave queda
// escrita en el código (ver config.ejemplo.php para qué es cada valor).
// =====================================================================

$variable = fn(string $nombre, string $defecto = ''): string =>
    getenv($nombre) !== false ? (string)getenv($nombre) : $defecto;

return [
    // 'produccion' oculta los detalles de los errores y no muestra el link
    // de recuperación en pantalla. Solo para probar: NL_ENTORNO=desarrollo.
    'entorno' => $variable('NL_ENTORNO', 'produccion'),

    'zona_horaria' => 'America/Montevideo',

    // Cuenta limitada de la aplicación (database/05_usuarios_dcl.sql).
    // "bd" es el nombre del contenedor de MariaDB dentro de la red.
    'bd' => [
        'host'    => $variable('NL_BD_HOST', 'bd'),
        'puerto'  => (int)$variable('NL_BD_PUERTO', '3306'),
        'nombre'  => $variable('NL_BD_NOMBRE', 'nextleague'),
        'usuario' => 'nl_app',
        'clave'   => $variable('NL_BD_CLAVE'),
    ],

    // Dirección del sitio, para los links que se mandan por correo.
    'url_publica' => rtrim($variable('NL_URL_PUBLICA', 'https://localhost:8443'), '/'),

    'correo' => [
        'remitente' => $variable('NL_CORREO_REMITENTE', 'NextLeague <no-responder@nextleague.uy>'),
    ],

    // El contenedor siempre sirve por HTTPS: las cookies se marcan Secure.
    'https' => true,
];
