<?php
/**
 * Router para el servidor embebido de PHP (`php -S`).
 * Replica el .htaccess de Apache y, además, aplica cabeceras de seguridad y
 * protección de rutas, porque `php -S` NO procesa el .htaccess.
 *
 * Uso:  php -S 0.0.0.0:8082 -t public scripts\php_router.php
 */
$docroot = realpath(__DIR__ . '/../public');
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

// ── Seguridad: bloquear path traversal y bytes nulos ─────────
if (strpos($uri, '..') !== false || strpos($uri, "\0") !== false) {
    http_response_code(400);
    exit('Bad request');
}

// ── Cabeceras de seguridad (no hay Apache/.htaccess con php -S) ──
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 1; mode=block');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
     . "style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; "
     . "font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
header_remove('X-Powered-By');

// ── Servir archivos estáticos reales dentro de public/ ───────
// - Nunca servir .php directamente (todo PHP pasa por el front controller).
// - Nunca servir archivos ocultos (.env, .htaccess, etc.).
// - El realpath debe quedar contenido en public/ (anti-traversal).
$real = realpath($docroot . $uri);
if ($uri !== '/' && $real !== false && is_file($real)
    && strpos($real, $docroot . DIRECTORY_SEPARATOR) === 0
    && strtolower(pathinfo($real, PATHINFO_EXTENSION)) !== 'php'
    && substr(basename($real), 0, 1) !== '.') {
    return false; // lo sirve el servidor embebido
}

// ── Resto → front controller (igual que el RewriteRule) ──────
$_GET['url'] = ltrim($uri, '/');
require $docroot . '/index.php';
