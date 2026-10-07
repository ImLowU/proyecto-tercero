<?php
// =====================================================================
// ROUTER DEL SERVIDOR PARA LAS PRUEBAS DE PUNTA A PUNTA (Cypress)
// Es el mismo servidor de desarrollo (servidor_dev.php) pero contra la
// base nextleague_e2e_test, que las pruebas borran y vuelven a crear
// antes de cada archivo de pruebas: así nunca tocan los datos de
// nextleague_v2. Cypress lo levanta solo (ver cypress.config.js):
//   C:\xampp\php\php.exe -S 127.0.0.1:8081 -t . tools\servidor_e2e.php
// =====================================================================

require_once __DIR__ . '/../app/autoload.php';

Config::establecer([
    'bd'          => ['nombre' => 'nextleague_e2e_test'],
    'url_publica' => 'http://127.0.0.1:8081/public',
    'entorno'     => 'desarrollo', // el enlace de recuperación se muestra en pantalla
]);

return require __DIR__ . '/servidor_dev.php';
