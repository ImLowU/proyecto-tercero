<?php
// =====================================================================
// SESIÓN DEL USUARIO
// Diferencias con la versión anterior:
// * En cada petición el usuario se vuelve a leer de la base. Si el
//   administrador lo desactivó o le cambió el rol, el cambio se aplica
//   en ese mismo momento (antes seguía operando con los datos viejos
//   guardados en la sesión hasta que cerraba sesión).
// * Al iniciar sesión se cambia el id de sesión (evita la "fijación de
//   sesión": alguien que conocía el id viejo no puede usar la sesión nueva).
// * La sesión vence después de un rato sin actividad (configurable). Los
//   archivos de sesión se guardan en storage/sesiones con ese mismo plazo:
//   antes PHP los podía borrar a los 24 minutos (su valor por defecto),
//   aunque la configuración dijera 2 horas.
// * "Recordarme" funciona de verdad: guarda una cookie con un token
//   que se cambia en cada uso; en la base solo queda su hash.
// =====================================================================

class Sesion
{
    public const COOKIE_RECORDARME = 'nl_recordarme';

    private static ?array $usuario = null;
    private static array $cookiesPendientes = [];

    public static function iniciar(): void
    {
        if (PHP_SAPI === 'cli') {
            // En los tests no hay navegador: la sesión es un arreglo en memoria.
            $_SESSION ??= [];
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        // PHP borra los archivos de sesión más viejos que gc_maxlifetime: se le
        // pasa el plazo de la configuración, y se usa una carpeta propia para
        // que ninguna limpieza del sistema (con su propio plazo) las borre antes.
        ini_set('session.gc_maxlifetime', (string)(self::minutosDeInactividad() * 60));
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        session_save_path(self::carpeta());
        session_name('NLSESION');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,             // JavaScript no puede leer la cookie
            'samesite' => 'Lax',
            'secure'   => (bool)Config::obtener('https', false),
        ]);
        session_start();
    }

    // Carga el usuario de la sesión (o lo recupera con la cookie "recordarme").
    public static function cargar(Peticion $peticion): void
    {
        self::$usuario = null;
        $idUsuario = $_SESSION['id_usuario'] ?? null;

        if ($idUsuario) {
            $limite = self::minutosDeInactividad() * 60;
            $usuario = Usuario::buscarPorId((int)$idUsuario);
            if (time() - ($_SESSION['ultima_actividad'] ?? 0) > $limite || !$usuario || !$usuario['activo']) {
                self::limpiar();
            } else {
                self::$usuario = $usuario;
                $_SESSION['ultima_actividad'] = time();
            }
        }

        if (!self::$usuario && ($cookie = $peticion->cookie(self::COOKIE_RECORDARME))) {
            self::restaurarConRecordarme($cookie);
        }
    }

    // Carpeta donde se guardan los archivos de sesión (se crea si no existe).
    public static function carpeta(): string
    {
        $carpeta = dirname(__DIR__, 2) . '/storage/sesiones';
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0700, true);
        }
        return $carpeta;
    }

    // Minutos sin actividad hasta que la sesión vence (configuración general).
    // Si la base no responde, se usan 120: la aplicación igual avisa el error.
    private static function minutosDeInactividad(): int
    {
        try {
            return max(1, ConfiguracionSistema::entero('minutos_inactividad_sesion', 120));
        } catch (Throwable) {
            return 120;
        }
    }

    public static function usuario(): ?array
    {
        return self::$usuario;
    }

    public static function idUsuario(): ?int
    {
        return self::$usuario ? (int)self::$usuario['id_usuario'] : null;
    }

    public static function rol(): ?string
    {
        return self::$usuario['rol'] ?? null;
    }

    public static function iniciarSesion(array $usuario, bool $recordar = false): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['id_usuario'] = (int)$usuario['id_usuario'];
        $_SESSION['ultima_actividad'] = time();
        self::$usuario = Usuario::buscarPorId((int)$usuario['id_usuario']);
        Usuario::registrarAcceso((int)$usuario['id_usuario']);
        BaseDatos::fijarUsuarioAuditoria((int)$usuario['id_usuario']);

        if ($recordar) {
            $dias = ConfiguracionSistema::entero('dias_recordarme', 30);
            [$selector, $validador] = TokenUsuario::crear((int)$usuario['id_usuario'], 'recordarme', $dias * 24 * 60);
            self::agregarCookie(self::COOKIE_RECORDARME, "$selector:$validador", time() + $dias * 86400);
        }
    }

    public static function cerrar(Peticion $peticion): void
    {
        $cookie = $peticion->cookie(self::COOKIE_RECORDARME);
        if ($cookie && str_contains($cookie, ':')) {
            TokenUsuario::eliminarPorSelector(explode(':', $cookie, 2)[0]);
        }
        self::agregarCookie(self::COOKIE_RECORDARME, '', time() - 3600);
        self::limpiar();
    }

    // Cookies que la Aplicación agrega a la respuesta.
    public static function tomarCookies(): array
    {
        $cookies = self::$cookiesPendientes;
        self::$cookiesPendientes = [];
        return $cookies;
    }

    private static function restaurarConRecordarme(string $cookie): void
    {
        [$selector, $validador] = array_pad(explode(':', $cookie, 2), 2, '');
        $token = TokenUsuario::validar($selector, $validador, 'recordarme');
        if (!$token) {
            self::agregarCookie(self::COOKIE_RECORDARME, '', time() - 3600);
            return;
        }
        $usuario = Usuario::buscarPorId((int)$token['id_usuario']);
        TokenUsuario::eliminar((int)$token['id_token']); // se usa una sola vez y se rota
        if (!$usuario || !$usuario['activo']) {
            return;
        }
        self::iniciarSesion($usuario, true);
        Auditoria::registrarEvento('iniciar_sesion', 'Ingreso automático con "Recordarme"', 'usuarios', (int)$usuario['id_usuario']);
    }

    private static function limpiar(): void
    {
        self::$usuario = null;
        $csrf = $_SESSION['csrf'] ?? null;
        $_SESSION = [];
        if ($csrf) {
            $_SESSION['csrf'] = $csrf; // la página abierta sigue pudiendo iniciar sesión
        }
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    private static function agregarCookie(string $nombre, string $valor, int $expira): void
    {
        self::$cookiesPendientes[] = ['nombre' => $nombre, 'valor' => $valor, 'expira' => $expira];
    }
}
