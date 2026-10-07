<?php
// =====================================================================
// RESPUESTA HTTP
// Todas las respuestas de la API tienen el mismo formato JSON:
//   { "exito": true|false, "mensaje": "...", ...datos }
// y usan el código HTTP que corresponde (200, 201, 400, 401, 403, 404,
// 409, 422, 429, 500), en vez de responder siempre 200.
// =====================================================================

class Respuesta
{
    // Cookies que se mandan con la respuesta (los tests las leen de acá).
    public array $cookies = [];

    public function __construct(
        public int $estado = 200,
        public array $cuerpo = [],
        public array $cabeceras = [],
        public ?string $contenidoCrudo = null
    ) {
    }

    public static function exito(string $mensaje = '', array $datos = [], int $estado = 200): self
    {
        return new self($estado, array_merge(['exito' => true, 'mensaje' => $mensaje], $datos));
    }

    public static function error(string $mensaje, int $estado = 400, array $extra = []): self
    {
        return new self($estado, array_merge(['exito' => false, 'mensaje' => $mensaje], $extra));
    }

    // Archivo CSV para descargar (se abre bien en Excel: separador ";" y BOM UTF-8).
    public static function csv(string $nombreArchivo, array $encabezados, array $filas): self
    {
        $salida = fopen('php://temp', 'r+');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, $encabezados, ';');
        foreach ($filas as $fila) {
            fputcsv($salida, array_map(fn($v) => self::celdaSegura($v), array_values($fila)), ';');
        }
        rewind($salida);
        $contenido = stream_get_contents($salida);
        fclose($salida);

        $nombre = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $nombreArchivo);
        return new self(200, [], [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$nombre\"",
        ], $contenido);
    }

    // Evita "inyección de fórmulas": si una celda empieza con = + - @, Excel
    // la ejecutaría como fórmula. Se le antepone una comilla simple.
    private static function celdaSegura(mixed $valor): string
    {
        $texto = (string)$valor;
        return preg_match('/^[=+\-@\t\r]/', $texto) ? "'" . $texto : $texto;
    }

    public function enviar(): void
    {
        http_response_code($this->estado);
        foreach ($this->cabeceras as $nombre => $valor) {
            header("$nombre: $valor");
        }
        foreach ($this->cookies as $cookie) {
            setcookie($cookie['nombre'], $cookie['valor'], [
                'expires'  => $cookie['expira'],
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => (bool)Config::obtener('https', false),
            ]);
        }
        if ($this->contenidoCrudo !== null) {
            echo $this->contenidoCrudo;
            return;
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($this->cuerpo, JSON_UNESCAPED_UNICODE);
    }
}
