<?php
// =====================================================================
// ARRANQUE DE LOS TESTS
// PHPUnit ejecuta este archivo una vez antes de todos los tests.
// =====================================================================

date_default_timezone_set('America/Montevideo');

require_once __DIR__ . '/../app/Aplicacion.php';
require_once __DIR__ . '/../database/herramientas/Instalador.php';
require_once __DIR__ . '/soporte/BaseDePruebas.php';
require_once __DIR__ . '/soporte/CasoBaseDeDatos.php';
require_once __DIR__ . '/soporte/ClienteApi.php';
require_once __DIR__ . '/soporte/CasoApi.php';
