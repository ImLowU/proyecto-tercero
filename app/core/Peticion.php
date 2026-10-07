<?php
// =====================================================================
// PETICIÓN HTTP
// Junta en un objeto todo lo que llega del navegador: método, ruta,
// parámetros de la URL, cuerpo (JSON o formulario), archivos y cabeceras.
// Los controladores leen de acá en vez de usar $_GET/$_POST directamente,
// lo que además permite probarlos sin un servidor web.
// =====================================================================

class Peticion
{
    // Parámetros que vienen dentro de la ruta, ej: {id} en /torneos/{id}
    public array $parametrosRuta = [];

    public function __construct(
        public string $metodo,
        public string $ruta,
        public array $query = [],
        public array $cuerpo = [],
        public array $archivos = [],
        public array $cabeceras = [],
        public string $ip = '127.0.0.1',
        public array $cookies = []
    ) {
        $this->metodo = strtoupper($metodo);
        $this->ruta = '/' . trim($ruta, '/');
        $this->cabeceras = array_change_key_case($cabeceras, CASE_LOWER);
    }

    // Arma la petición a partir de las variables de PHP. La ruta es lo que
    // viene después de "/api" en la URL, así funciona en cualquier carpeta.
    public static function desdeGlobales(): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $posicion = strpos($uri, '/api/');
        $ruta = $posicion === false ? '/' : substr($uri, $posicion + 4);

        $cuerpo = $_POST;
        $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($tipo, 'application/json')) {
            $json = json_decode(file_get_contents('php://input') ?: '', true);
            $cuerpo = is_array($json) ? $json : [];
        }

        $cabeceras = [];
        foreach ($_SERVER as $clave => $valor) {
            if (str_starts_with($clave, 'HTTP_')) {
                $cabeceras[str_replace('_', '-', strtolower(substr($clave, 5)))] = $valor;
            }
        }

        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            rawurldecode($ruta),
            $_GET,
            $cuerpo,
            $_FILES,
            $cabeceras,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_COOKIE
        );
    }

    public function cookie(string $nombre): ?string
    {
        $valor = $this->cookies[$nombre] ?? null;
        return is_string($valor) ? $valor : null;
    }

    public function dato(string $clave, mixed $defecto = null): mixed
    {
        $valor = $this->cuerpo[$clave] ?? $defecto;
        return is_string($valor) ? trim($valor) : $valor;
    }

    public function param(string $clave, mixed $defecto = null): mixed
    {
        $valor = $this->query[$clave] ?? $defecto;
        return is_string($valor) ? trim($valor) : $valor;
    }

    public function idRuta(string $nombre = 'id'): int
    {
        return (int)($this->parametrosRuta[$nombre] ?? 0);
    }

    public function archivo(string $nombre): ?array
    {
        $archivo = $this->archivos[$nombre] ?? null;
        if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $archivo;
    }

    public function cabecera(string $nombre): ?string
    {
        return $this->cabeceras[strtolower($nombre)] ?? null;
    }
}
