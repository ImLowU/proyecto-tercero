<?php
// ============================================================
//  config/app.php
//  Constantes generales de la aplicación
// ============================================================

define('APP_NAME',    'FlexArena');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    rtrim(getenv('BASE_URL') !== false ? getenv('BASE_URL') : '/sgdm/public', '/'));  // Ajustar por entorno

// Roles disponibles (deben coincidir con la tabla `roles`)
define('ROL_ADMIN',        1);
define('ROL_ORGANIZADOR',  2);
define('ROL_PARTICIPANTE', 3);

// Estados de torneo
define('TORNEO_BORRADOR',    'borrador');
define('TORNEO_INSCRIPCION', 'inscripcion');
define('TORNEO_EN_CURSO',    'en_curso');
define('TORNEO_FINALIZADO',  'finalizado');

// Tipos de torneo (deben coincidir con la tabla `tipos_torneo`)
define('TIPO_LIGA',               1);
define('TIPO_ELIMINACION_DIRECTA', 2);
define('TIPO_SUIZO',              3);

// Duración de sesión en segundos (2 horas)
define('SESSION_LIFETIME', 7200);

// Zona horaria
date_default_timezone_set('America/Montevideo');
