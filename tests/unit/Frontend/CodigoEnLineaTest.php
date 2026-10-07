<?php
// =====================================================================
// La CSP de public/.htaccess no permite scripts ni estilos escritos
// dentro del HTML ('unsafe-inline'). Si alguien agrega un onclick="..."
// o un style="..." en una página o en una plantilla de JS, el navegador
// lo bloquea en silencio y la pantalla queda rota sin que falle nada más.
// Estos tests revisan los archivos y avisan antes (no usan la base).
// =====================================================================

use PHPUnit\Framework\TestCase;

final class CodigoEnLineaTest extends TestCase
{
    private const PUBLICA = __DIR__ . '/../../../public';

    // Directivas de la CSP como ['script-src' => "'self'", ...]
    private static function csp(): array
    {
        $htaccess = (string)file_get_contents(self::PUBLICA . '/.htaccess');
        preg_match('/Header always set Content-Security-Policy "(.*)"/', $htaccess, $coincidencia);
        $directivas = [];
        foreach (array_filter(array_map('trim', explode(';', $coincidencia[1] ?? ''))) as $directiva) {
            [$nombre, $valor] = explode(' ', $directiva, 2) + [1 => ''];
            $directivas[$nombre] = $valor;
        }
        return $directivas;
    }

    // Archivos de public/ con esa extensión (sin los subidos por usuarios)
    private static function archivos(string $extension): array
    {
        $archivos = [];
        $recorrido = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::PUBLICA, FilesystemIterator::SKIP_DOTS));
        foreach ($recorrido as $archivo) {
            $ruta = str_replace('\\', '/', $archivo->getPathname());
            if ($archivo->getExtension() === $extension && !str_contains($ruta, '/uploads/')) {
                $archivos[] = $ruta;
            }
        }
        return $archivos;
    }

    // "archivo:línea  código" de cada línea que cumple alguno de los patrones
    private static function buscar(array $archivos, array $patrones): array
    {
        $encontrados = [];
        foreach ($archivos as $ruta) {
            foreach (file($ruta) as $numero => $linea) {
                foreach ($patrones as $patron) {
                    if (preg_match($patron, $linea)) {
                        $encontrados[] = basename(dirname($ruta)) . '/' . basename($ruta) . ':' . ($numero + 1) . '  ' . trim($linea);
                    }
                }
            }
        }
        return $encontrados;
    }

    public function testLaCspSoloPermiteArchivosDelPropioSitio(): void
    {
        $csp = self::csp();
        $this->assertSame("'self'", $csp['script-src'] ?? null, 'Sin unsafe-inline ni unsafe-eval');
        $this->assertSame("'self'", $csp['style-src'] ?? null, 'Sin unsafe-inline');
        $this->assertSame("'self'", $csp['default-src'] ?? null);
        $this->assertSame("'none'", $csp['object-src'] ?? null);
        $this->assertSame("'none'", $csp['frame-ancestors'] ?? null, 'Nadie puede meter el sitio en un iframe');
    }

    public function testLasPaginasNoTienenScriptsNiEstilosAdentro(): void
    {
        $paginas = self::archivos('html');
        $this->assertNotEmpty($paginas);
        $encontrados = self::buscar($paginas, [
            '/<script(?![^>]*\ssrc=)[^>]*>/i',  // <script> sin src
            '/<style[\s>]/i',
            '/\sstyle\s*=/i',
            '/\son[a-z]+\s*=/i',                // onclick=, onload=, ...
            '/javascript:/i',
        ]);
        $this->assertSame([], $encontrados, 'La CSP bloquearía estas líneas');
    }

    public function testLosScriptsNoArmanCodigoEnLinea(): void
    {
        $scripts = self::archivos('js');
        $this->assertNotEmpty($scripts);
        $encontrados = self::buscar($scripts, [
            '/\sstyle\s*=\s*["\'`$\\\\]/i',             // style="..." dentro de una plantilla
            '/\son[a-z]+\s*=\s*["\'`$\\\\]/i',          // onclick="..." dentro de una plantilla
            '/setAttribute\(\s*["\'](style|on[a-z]+)["\']/i',
            '/<style[\s>]/i',
            '/\beval\s*\(|new\s+Function\s*\(/',
            '/javascript:/i',
        ]);
        $this->assertSame([], $encontrados, 'La CSP bloquearía estas líneas');
    }
}
