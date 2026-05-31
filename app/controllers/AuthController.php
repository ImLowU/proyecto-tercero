<?php
// ============================================================
//  app/controllers/AuthController.php
//  Maneja login, logout y verificación de sesión
// ============================================================

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/../models/AuditoriaModel.php';

class AuthController
{
    private UsuarioModel  $usuarioModel;
    private AuditoriaModel $auditoriaModel;

    public function __construct()
    {
        $this->usuarioModel   = new UsuarioModel();
        $this->auditoriaModel = new AuditoriaModel();
    }

    // ----------------------------------------------------------
    //  GET /login → muestra el formulario
    // ----------------------------------------------------------
    public function mostrarLogin(): void
    {
        // Si ya tiene sesión activa, redirigir según su rol
        if ($this->estaAutenticado()) {
            $this->redirigirSegunRol();
            return;
        }

        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        require_once __DIR__ . '/../views/auth/login.php';
    }

    // ----------------------------------------------------------
    //  POST /login → procesa el formulario
    // ----------------------------------------------------------
    public function procesarLogin(): void
    {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        // Validaciones básicas de formato
        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = 'Completá todos los campos.';
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['login_error'] = 'El formato del email no es válido.';
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $usuario = $this->usuarioModel->buscarPorEmail($email);

        // Verificar existencia y contraseña
        if (!$usuario || !password_verify($password, $usuario['password'])) {
            // Registrar intento fallido en auditoría
            $this->auditoriaModel->registrar(
                'login_fallido',
                null,
                'usuarios',
                null,
                "Email: $email"
            );

            $_SESSION['login_error'] = 'Email o contraseña incorrectos.';
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        // Regenerar ID de sesión para prevenir session fixation
        session_regenerate_id(true);

        // Guardar datos del usuario en sesión (sin el hash de password)
        $_SESSION['usuario'] = [
            'id'         => $usuario['id'],
            'nombre'     => $usuario['nombre'],
            'email'      => $usuario['email'],
            'rol_id'     => $usuario['rol_id'],
            'rol_nombre' => $usuario['rol_nombre'],
        ];
        $_SESSION['login_time'] = time();

        // Auditoría de login exitoso
        $this->auditoriaModel->registrar(
            'login',
            $usuario['id'],
            'usuarios',
            $usuario['id'],
            'Login exitoso'
        );

        $this->redirigirSegunRol();
    }

    // ----------------------------------------------------------
    //  GET /logout → cierra sesión
    // ----------------------------------------------------------
    public function logout(): void
    {
        $usuarioId = $_SESSION['usuario']['id'] ?? null;

        $this->auditoriaModel->registrar('logout', $usuarioId, 'usuarios', $usuarioId);

        // Destruir sesión completamente
        $_SESSION = [];
        session_destroy();

        header('Location: ' . BASE_URL . '/login');
        exit;
    }

    // ----------------------------------------------------------
    //  Helpers
    // ----------------------------------------------------------

    /**
     * Verifica si el usuario tiene sesión activa y no expiró.
     */
    public function estaAutenticado(): bool
    {
        if (empty($_SESSION['usuario'])) {
            return false;
        }

        // Verificar expiración de sesión
        if (time() - ($_SESSION['login_time'] ?? 0) > SESSION_LIFETIME) {
            $this->logout();
            return false;
        }

        return true;
    }

    /**
     * Middleware: si no está autenticado, redirige al login.
     * Llamar al inicio de cualquier controlador que requiera login.
     */
    public function requerirLogin(): void
    {
        if (!$this->estaAutenticado()) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
    }

    /**
     * Middleware: verifica que el usuario tenga el rol requerido.
     */
    public function requerirRol(int $rolId): void
    {
        $this->requerirRoles([$rolId]);
    }

    /**
     * Middleware: permite varios roles.
     */
    public function requerirRoles(array $rolesPermitidos): void
    {
        $this->requerirLogin();

        $rolActual = (int) ($_SESSION['usuario']['rol_id'] ?? 0);
        if (!in_array($rolActual, array_map('intval', $rolesPermitidos), true)) {
            http_response_code(403);
            require_once __DIR__ . '/../views/shared/403.php';
            exit;
        }
    }

    /**
     * Redirige al panel correspondiente según el rol del usuario.
     */
    private function redirigirSegunRol(): void
    {
        $rolId = $_SESSION['usuario']['rol_id'] ?? null;

        match ((int) $rolId) {
            ROL_ADMIN       => header('Location: ' . BASE_URL . '/admin/dashboard'),
            ROL_ORGANIZADOR => header('Location: ' . BASE_URL . '/organizador/dashboard'),
            default         => header('Location: ' . BASE_URL . '/participante/dashboard'),
        };
        exit;
    }
}
