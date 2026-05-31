<?php
// ============================================================
//  config/database.php
//  Configuración de conexión a la base de datos
//  Modificar estos valores según el entorno local
// ============================================================

define('DB_HOST',     getenv('DB_HOST')     ?: '127.0.0.1');
define('DB_PORT',     getenv('DB_PORT')     ?: '3306');
define('DB_NAME',     getenv('DB_NAME')     ?: 'sgdm');
define('DB_USER',     getenv('DB_USER')     ?: 'springstudent');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'springstudent');
define('DB_CHARSET',  'utf8mb4');
