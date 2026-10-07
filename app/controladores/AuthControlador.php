<?php
// =====================================================================
// CONTROLADOR: AUTENTICACIÓN
// Registro, inicio y cierre de sesión, y recuperación de contraseña.
// =====================================================================

class AuthControlador extends Controlador
{
    // GET /auth/sesion — quién está logueado, el token CSRF para la página
    // y si se aceptan cuentas nuevas (para no mostrar un registro cerrado).
    public function sesion(): Respuesta
    {
        $usuario = $this->usuario();
        return Respuesta::exito('', [
            'logueado'         => $usuario !== null,
            'usuario'          => $usuario ? self::datosPublicosDeSesion($usuario) : null,
            'csrf'             => Csrf::token(),
            'registro_abierto' => ConfiguracionSistema::booleano('registro_abierto', true),
        ]);
    }

    // POST /auth/registro
    public function registro(): Respuesta
    {
        if (!ConfiguracionSistema::booleano('registro_abierto', true)) {
            throw ErrorHttp::prohibido('El registro de cuentas nuevas está cerrado por el momento.');
        }
        $datos = $this->peticion->cuerpo;
        (new Validador($datos))
            ->requerido('nombre_usuario', 'El nombre de usuario')->nombreUsuario('nombre_usuario')
            ->requerido('correo', 'El correo')->correo('correo')
            ->requerido('contrasena', 'La contraseña')->contrasena('contrasena')
            ->requerido('rol', 'El tipo de cuenta')
            ->enLista('rol', 'El tipo de cuenta', [Rol::ORGANIZADOR, Rol::PARTICIPANTE])
            ->validar();

        $nombre = trim($datos['nombre_usuario']);
        $correo = mb_strtolower(trim($datos['correo']));
        $conflictos = Usuario::conflictos($nombre, $correo);
        if ($conflictos) {
            throw new ErrorValidacion($conflictos);
        }

        $id = Usuario::crear($nombre, $correo, $datos['contrasena'], $datos['rol']);
        return Respuesta::exito('Cuenta creada. Ya podés iniciar sesión.', ['id_usuario' => $id], 201);
    }

    // POST /auth/login
    public function login(): Respuesta
    {
        $correo = mb_strtolower((string)$this->peticion->dato('correo', ''));
        $contrasena = (string)($this->peticion->cuerpo['contrasena'] ?? '');
        (new Validador(['correo' => $correo, 'contrasena' => $contrasena]))
            ->requerido('correo', 'El correo')->requerido('contrasena', 'La contraseña')->validar();

        $bloqueo = IntentoLogin::minutosDeBloqueo($correo, $this->peticion->ip);
        if ($bloqueo > 0) {
            throw new ErrorHttp("Demasiados intentos fallidos. Esperá $bloqueo minuto(s) y volvé a intentar.", 429);
        }

        $usuario = Usuario::buscarPorCorreo($correo);
        if (!$usuario || !Usuario::verificarContrasena((int)$usuario['id_usuario'], $contrasena)) {
            IntentoLogin::registrar($correo, $this->peticion->ip, false);
            // Si este intento fue el que bloqueó, se avisa ahora (antes se decía
            // "incorrectos" y el bloqueo recién se veía en el intento siguiente).
            $bloqueo = IntentoLogin::minutosDeBloqueo($correo, $this->peticion->ip);
            if ($bloqueo > 0) {
                Auditoria::registrarEvento('bloqueo_login', "Bloqueo temporal por intentos fallidos ($correo)", 'usuarios', $usuario['id_usuario'] ?? null);
                throw new ErrorHttp("Demasiados intentos fallidos. Esperá $bloqueo minuto(s) y volvé a intentar.", 429);
            }
            // Mismo mensaje exista o no el correo: no se revela qué cuentas existen
            // (los intentos que quedan se cuentan por correo, exista o no).
            $aviso = IntentoLogin::intentosRestantes($correo) === 1
                ? ' Te queda 1 intento: si fallás de nuevo, se bloquea por unos minutos.' : '';
            throw ErrorHttp::noAutenticado('Correo o contraseña incorrectos.' . $aviso);
        }
        if (!$usuario['activo']) {
            throw ErrorHttp::prohibido('Tu cuenta está desactivada. Comunicate con el administrador.');
        }

        IntentoLogin::registrar($correo, $this->peticion->ip, true);
        Sesion::iniciarSesion($usuario, (bool)$this->peticion->dato('recordar', false));
        Auditoria::registrarEvento('iniciar_sesion', 'Inicio de sesión', 'usuarios', (int)$usuario['id_usuario']);

        return Respuesta::exito('Sesión iniciada.', [
            'usuario' => self::datosPublicosDeSesion(Sesion::usuario()),
            'csrf'    => Csrf::token(),
        ]);
    }

    // POST /auth/logout (solo POST: un link o una imagen no pueden cerrar la sesión)
    public function logout(): Respuesta
    {
        if ($this->usuario()) {
            Auditoria::registrarEvento('cerrar_sesion', 'Cierre de sesión', 'usuarios', $this->idUsuario());
        }
        Sesion::cerrar($this->peticion);
        return Respuesta::exito('Sesión cerrada.', ['csrf' => Csrf::token()]);
    }

    // POST /auth/recuperar — manda por correo un link para elegir otra contraseña.
    public function recuperar(): Respuesta
    {
        $correo = mb_strtolower((string)$this->peticion->dato('correo', ''));
        (new Validador(['correo' => $correo]))->requerido('correo', 'El correo')->correo('correo')->validar();

        $mensaje = 'Si el correo está registrado, te mandamos un enlace para restablecer la contraseña.';
        $usuario = Usuario::buscarPorCorreo($correo);
        if (!$usuario || !$usuario['activo']) {
            return Respuesta::exito($mensaje); // misma respuesta: no se revela si la cuenta existe
        }

        $minutos = ConfiguracionSistema::entero('minutos_token_recuperacion', 30);
        TokenUsuario::eliminarDeUsuario((int)$usuario['id_usuario'], 'recuperacion');
        [$selector, $validador] = TokenUsuario::crear((int)$usuario['id_usuario'], 'recuperacion', $minutos);
        $link = rtrim(Config::obtener('url_publica'), '/') . "/home/restablecer?token=$selector.$validador";

        Correo::enviar($correo, 'Restablecer tu contraseña de NextLeague',
            "Hola {$usuario['nombre_usuario']}:\n\nPara elegir una contraseña nueva entrá a este enlace (vale por $minutos minutos):\n$link\n\n"
            . "Si no lo pediste, ignorá este correo: tu contraseña sigue siendo la misma.");
        Auditoria::registrarEvento('solicitar_recuperacion', 'Pedido de enlace para restablecer la contraseña', 'usuarios', (int)$usuario['id_usuario']);

        // Solo en desarrollo (XAMPP no manda correos) se muestra el link en pantalla.
        // En producción NUNCA: si no, cualquiera que sepa un correo podría tomar la cuenta.
        return Respuesta::exito($mensaje, Config::esDesarrollo() ? ['link_demo' => $link] : []);
    }

    // POST /auth/restablecer — elige la contraseña nueva con el token del link.
    public function restablecer(): Respuesta
    {
        $token = (string)$this->peticion->dato('token', '');
        $nueva = (string)($this->peticion->cuerpo['contrasena'] ?? '');
        (new Validador(['contrasena' => $nueva]))
            ->requerido('contrasena', 'La contraseña nueva')->contrasena('contrasena')->validar();

        [$selector, $validador] = array_pad(explode('.', $token, 2), 2, '');
        $registro = TokenUsuario::validar($selector, $validador, 'recuperacion');
        if (!$registro) {
            throw new ErrorHttp('El enlace venció o ya se usó. Pedí uno nuevo.', 400);
        }

        $idUsuario = (int)$registro['id_usuario'];
        BaseDatos::transaccion(function () use ($idUsuario, $nueva) {
            Usuario::cambiarContrasena($idUsuario, $nueva);
            // El link es de un solo uso, y se cierran las sesiones "recordarme" abiertas.
            TokenUsuario::eliminarDeUsuario($idUsuario, 'recuperacion');
            TokenUsuario::eliminarDeUsuario($idUsuario, 'recordarme');
            Auditoria::registrarEvento('restablecer_contrasena', 'Contraseña restablecida con el enlace de recuperación', 'usuarios', $idUsuario);
        });
        return Respuesta::exito('Contraseña actualizada. Ya podés iniciar sesión.');
    }

    // Lo que la página necesita saber del usuario logueado.
    public static function datosPublicosDeSesion(array $usuario): array
    {
        return [
            'id_usuario'              => (int)$usuario['id_usuario'],
            'nombre_usuario'          => $usuario['nombre_usuario'],
            'correo'                  => $usuario['correo'],
            'rol'                     => $usuario['rol'],
            'foto_perfil'             => $usuario['foto_perfil'],
            'debe_cambiar_contrasena' => (bool)$usuario['debe_cambiar_contrasena'],
        ];
    }
}
