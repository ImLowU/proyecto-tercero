<?php
// =====================================================================
// CLIENTE DE LA API PARA TESTS
// Hace peticiones a la aplicación sin servidor web: arma un objeto
// Peticion y se lo pasa a Aplicacion::manejar(), igual que el index.php
// real. Cada cliente es como un navegador distinto: guarda su propia
// sesión y sus cookies, y manda el token CSRF automáticamente.
// =====================================================================

final class ClienteApi
{
    public array $sesion = [];
    public array $cookies = [];
    public bool $enviarCsrf = true;
    public string $ip;

    public function __construct(string $ip = '10.0.0.1')
    {
        $this->ip = $ip;
    }

    // Cliente con la sesión ya iniciada para un usuario (más rápido que hacer login).
    public static function como(int $idUsuario): self
    {
        $cliente = new self();
        $cliente->sesion = ['id_usuario' => $idUsuario, 'ultima_actividad' => time()];
        return $cliente;
    }

    public function get(string $ruta): Respuesta
    {
        return $this->pedir('GET', $ruta);
    }

    public function post(string $ruta, array $datos = [], array $archivos = []): Respuesta
    {
        return $this->pedir('POST', $ruta, $datos, $archivos);
    }

    public function put(string $ruta, array $datos = []): Respuesta
    {
        return $this->pedir('PUT', $ruta, $datos);
    }

    public function delete(string $ruta, array $datos = []): Respuesta
    {
        return $this->pedir('DELETE', $ruta, $datos);
    }

    public function pedir(string $metodo, string $ruta, array $datos = [], array $archivos = [], array $cabeceras = []): Respuesta
    {
        $_SESSION = $this->sesion;
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        if ($this->enviarCsrf && $metodo !== 'GET') {
            $cabeceras['X-CSRF-Token'] = $_SESSION['csrf'];
        }

        [$camino, $consulta] = array_pad(explode('?', $ruta, 2), 2, '');
        parse_str($consulta, $query);

        ConfiguracionSistema::olvidarCache();
        $respuesta = Aplicacion::manejar(new Peticion($metodo, $camino, $query, $datos, $archivos, $cabeceras, $this->ip, $this->cookies));

        $this->sesion = $_SESSION;
        foreach ($respuesta->cookies as $cookie) {
            if ($cookie['expira'] < time() || $cookie['valor'] === '') {
                unset($this->cookies[$cookie['nombre']]);
            } else {
                $this->cookies[$cookie['nombre']] = $cookie['valor'];
            }
        }
        return $respuesta;
    }
}
