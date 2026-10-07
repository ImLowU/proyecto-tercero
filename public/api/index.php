<?php
// =====================================================================
// PUNTO DE ENTRADA DE LA API (front controller)
// Todas las peticiones a /api/... llegan a este archivo (lo hace el
// .htaccess de esta carpeta). Antes había un archivo PHP por acción
// (37 en total) y cada uno repetía la conexión, la sesión y los errores;
// ahora todo pasa por un único lugar.
// =====================================================================

require __DIR__ . '/../../app/Aplicacion.php';

Aplicacion::manejar(Peticion::desdeGlobales())->enviar();
