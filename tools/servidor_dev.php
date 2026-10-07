<?php
// =====================================================================
// ROUTER DEL SERVIDOR DE DESARROLLO
// Para probar sin Apache, desde la carpeta del proyecto:
//   C:\xampp\php\php.exe -S 127.0.0.1:8080 -t . tools\servidor_dev.php
// y abrir http://127.0.0.1:8080
// Hace lo mismo que los .htaccess: manda /public/api/... a la API, no
// deja descargar las carpetas privadas, manda las cabeceras de seguridad
// y entrega las páginas por su dirección sin ".html".
// =====================================================================

$ruta = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// "/public/../app/..." saltearía los controles de abajo (que miran el
// comienzo de la ruta): los navegadores nunca piden rutas con "..".
if (preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $ruta)) {
    http_response_code(400);
    echo 'Ruta inválida';
    return true;
}

// Las cabeceras de seguridad (CSP, nosniff, etc.) se leen de
// public/.htaccess para no tener dos listas: así en desarrollo se prueba
// con la misma CSP que en el servidor y aparece cualquier violación.
if (str_starts_with($ruta, '/public/')) {
    $htaccess = (string)file_get_contents(__DIR__ . '/../public/.htaccess');
    preg_match_all('/^\s*Header always set (\S+) "(.*)"\s*$/m', $htaccess, $cabeceras, PREG_SET_ORDER);
    foreach ($cabeceras as [, $nombre, $valor]) {
        header("$nombre: $valor");
    }
}

if (preg_match('#^/public/api(/|$)#', $ruta)) {
    require __DIR__ . '/../public/api/index.php';
    return true;
}

// Como en los .htaccess: también los archivos y carpetas ocultos (.git,
// .htaccess, ...) y los .md, en cualquier carpeta.
if (preg_match('#^/(app|database|docker|scripts|tests|tools|storage|node_modules)(/|$)|^/(config(\.ejemplo)?\.php|phpunit\.xml|package(-lock)?\.json|cypress\.config\.js|Dockerfile|docker-compose\.yml)|(^|/)\.|\.md$#i', $ruta)) {
    http_response_code(403);
    echo 'Acceso denegado';
    return true;
}

if (preg_match('#^/public/uploads/.*\.(php\d?|phtml|phar)$#i', $ruta)) {
    http_response_code(403);
    echo 'Acceso denegado';
    return true;
}

// Como en public/.htaccess: una dirección vieja con ".html" (un enlace
// guardado, un correo) lleva a la nueva, con sus parámetros, y
// home/index.html lleva a home/.
if (str_starts_with($ruta, '/public/') && str_ends_with($ruta, '.html') && is_file(__DIR__ . '/..' . $ruta)) {
    $nueva = str_ends_with($ruta, '/index.html') ? substr($ruta, 0, -strlen('index.html')) : substr($ruta, 0, -strlen('.html'));
    $consulta = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $nueva . ($consulta !== '' ? "?$consulta" : ''), true, 302);
    return true;
}

// Páginas, estilos, scripts e imágenes de public/: se entregan desde acá
// porque, si el router devuelve false, PHP los sirve sin las cabeceras de
// arriba y la CSP no se aplicaría.
const TIPOS = [
    'html' => 'text/html; charset=UTF-8', 'css' => 'text/css; charset=UTF-8',
    'js' => 'text/javascript; charset=UTF-8', 'json' => 'application/json',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
    'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'pdf' => 'application/pdf',
];
$publica = realpath(__DIR__ . '/../public');
// La dirección sin ".html" (/public/torneos/torneo) entrega torneo.html.
$archivo = realpath(__DIR__ . '/..' . $ruta) ?: realpath(__DIR__ . '/..' . $ruta . '.html');
if ($archivo && str_starts_with($archivo, $publica . DIRECTORY_SEPARATOR)) {
    if (is_dir($archivo)) {
        if (!str_ends_with($ruta, '/')) { // /public/home → /public/home/ (si no, fallan las rutas relativas)
            $consulta = $_SERVER['QUERY_STRING'] ?? '';
            header('Location: ' . $ruta . '/' . ($consulta !== '' ? "?$consulta" : ''), true, 301);
            return true;
        }
        $archivo .= DIRECTORY_SEPARATOR . 'index.html';
    }
    $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    if (is_file($archivo) && $extension !== 'php') {
        header('Content-Type: ' . (TIPOS[$extension] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($archivo));
        header('Cache-Control: no-cache'); // en desarrollo, cada cambio se ve al recargar
        readfile($archivo);
        return true;
    }
}

return false; // el resto (por ejemplo, un archivo que no existe) lo resuelve PHP
