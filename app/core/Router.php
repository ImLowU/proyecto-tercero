<?php
// =====================================================================
// ROUTER
// Relaciona cada combinación "MÉTODO /ruta" con el método de un
// controlador. Las partes entre llaves son parámetros:
//   $router->get('/torneos/{id}', [TorneoControlador::class, 'ver']);
// {id} solo acepta números; {texto} acepta letras, números, _ y -.
// =====================================================================

class Router
{
    private array $rutas = [];

    public function get(string $patron, array $accion): void    { $this->agregar('GET', $patron, $accion); }
    public function post(string $patron, array $accion): void   { $this->agregar('POST', $patron, $accion); }
    public function put(string $patron, array $accion): void    { $this->agregar('PUT', $patron, $accion); }
    public function delete(string $patron, array $accion): void { $this->agregar('DELETE', $patron, $accion); }

    private function agregar(string $metodo, string $patron, array $accion): void
    {
        $regex = preg_replace_callback('/\{(\w+)\}/', function ($m) {
            $formato = str_starts_with($m[1], 'id') ? '\d+' : '[\w-]+';
            return "(?P<{$m[1]}>$formato)";
        }, rtrim($patron, '/') ?: '/');
        $this->rutas[] = ['metodo' => $metodo, 'regex' => "#^$regex$#", 'accion' => $accion];
    }

    public function despachar(Peticion $peticion): Respuesta
    {
        $metodosPermitidos = [];

        foreach ($this->rutas as $ruta) {
            if (!preg_match($ruta['regex'], $peticion->ruta, $coincidencias)) {
                continue;
            }
            if ($ruta['metodo'] !== $peticion->metodo) {
                $metodosPermitidos[] = $ruta['metodo'];
                continue;
            }
            $peticion->parametrosRuta = array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY);

            [$clase, $metodo] = $ruta['accion'];
            $controlador = new $clase($peticion);
            $resultado = $controlador->$metodo();
            return $resultado instanceof Respuesta ? $resultado : Respuesta::exito('', (array)$resultado);
        }

        if ($metodosPermitidos) {
            return new Respuesta(405,
                ['exito' => false, 'mensaje' => 'Método no permitido para esta ruta.'],
                ['Allow' => implode(', ', array_unique($metodosPermitidos))]);
        }
        throw ErrorHttp::noEncontrado('La ruta pedida no existe.');
    }

    public function rutas(): array
    {
        return array_map(fn($r) => $r['metodo'] . ' ' . $r['regex'], $this->rutas);
    }
}
