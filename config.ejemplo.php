<?php
// =====================================================================
// CONFIGURACIÓN DE NEXTLEAGUE (plantilla)
//
// Copiá este archivo como "config.php" en la misma carpeta y completá
// los datos de tu máquina o del servidor. config.php NO se sube a git
// (está en .gitignore), así cada PC y el servidor tienen el suyo y las
// actualizaciones desde GitHub no lo pisan.
// Si config.php no existe, la aplicación usa estos valores, que sirven
// para desarrollo local con XAMPP.
// =====================================================================

return [
    // 'desarrollo' muestra detalles de los errores y el link de recuperación
    // de contraseña en pantalla (XAMPP no manda correos). En el servidor
    // tiene que ser 'produccion'.
    'entorno' => 'desarrollo',

    'zona_horaria' => 'America/Montevideo',

    // Cuenta limitada de la aplicación (ver database/05_usuarios_dcl.sql).
    // En el servidor cambiá la clave con ALTER USER y ponela acá.
    'bd' => [
        'host'    => 'localhost',
        'puerto'  => 3306,
        'nombre'  => 'nextleague_v2',
        'usuario' => 'nl_app',
        'clave'   => 'NlApp_Desarrollo_2026',
    ],

    // Dirección pública de la carpeta public/, para armar los links que se
    // mandan por correo (ej: restablecer contraseña). Este valor sirve con
    // el proyecto en C:\xampp\htdocs\nextleague; con otra carpeta, o con el
    // servidor de desarrollo de PHP (http://127.0.0.1:8080/public), cambialo
    // en tu config.php.
    'url_publica' => 'http://localhost/nextleague/public',

    'correo' => [
        'remitente' => 'NextLeague <no-responder@nextleague.uy>',
    ],

    // true solo si el sitio se sirve con HTTPS (las cookies se marcan Secure).
    'https' => false,
];
