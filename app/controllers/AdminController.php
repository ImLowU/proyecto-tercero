<?php
// ============================================================
// app/controllers/AdminController.php
// Panel de administracion general
// ============================================================

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/../models/TorneoModel.php';
require_once __DIR__ . '/../models/ParticipanteModel.php';
require_once __DIR__ . '/../models/CompetenciaModel.php';
require_once __DIR__ . '/../models/AuditoriaModel.php';

class AdminController
{
    private AuthController $auth;
    private UsuarioModel $usuarioModel;
    private TorneoModel $torneoModel;
    private ParticipanteModel $participanteModel;
    private CompetenciaModel $competenciaModel;
    private AuditoriaModel $auditoriaModel;

    private array $estadosPermitidos = [TORNEO_BORRADOR, TORNEO_INSCRIPCION, TORNEO_EN_CURSO, TORNEO_FINALIZADO];

    public function __construct()
    {
        $this->auth = new AuthController();
        $this->auth->requerirRol(ROL_ADMIN);
        $this->usuarioModel = new UsuarioModel();
        $this->torneoModel = new TorneoModel();
        $this->participanteModel = new ParticipanteModel();
        $this->competenciaModel = new CompetenciaModel();
        $this->auditoriaModel = new AuditoriaModel();
    }

    public function dashboard(): void
    {
        $totalUsuarios = count($this->usuarioModel->listarTodos());
        $totalTorneos = count($this->torneoModel->listarTodos());
        $totalParticipantes = count($this->participanteModel->listarTodos());
        $pageTitle = 'Dashboard';
        require_once __DIR__ . '/../views/admin/dashboard.php';
    }

    public function usuarios(): void
    {
        $usuarios = $this->usuarioModel->listarTodos();
        $flash = $this->consumirFlash();
        $pageTitle = 'Gestion de usuarios';
        require_once __DIR__ . '/../views/admin/usuarios.php';
    }

    public function crearUsuario(): void
    {
        $usuarioForm = $this->consumirOld();
        $errores = $this->consumirErrores();
        $roles = $this->usuarioModel->listarRoles();
        $modo = 'crear';
        $pageTitle = 'Nuevo usuario';
        require_once __DIR__ . '/../views/admin/usuario_form.php';
    }

    public function guardarUsuario(): void
    {
        $datos = $this->datosUsuarioDesdePost(false);
        $errores = $this->validarDatosUsuario($datos, null, true);
        if ($errores) {
            $datos['password'] = '';
            $datos['password_confirm'] = '';
            $_SESSION['form_errors'] = $errores;
            $_SESSION['form_old'] = $datos;
            header('Location: ' . BASE_URL . '/admin/usuarios/nuevo');
            exit;
        }
        $usuarioId = $this->usuarioModel->crear($datos['nombre'], $datos['email'], $datos['password'], $datos['rol_id']);
        $this->registrar('crear_usuario', 'usuarios', $usuarioId, 'Usuario creado: ' . $datos['email']);
        $this->flash('success', 'Usuario creado correctamente.');
        header('Location: ' . BASE_URL . '/admin/usuarios');
        exit;
    }

    public function editarUsuario(): void
    {
        $id = $this->idDesdeGet('/admin/usuarios');
        $usuarioForm = $this->consumirOld() ?: $this->usuarioModel->buscarPorId($id);
        if (!$usuarioForm) {
            $this->flash('error', 'El usuario solicitado no existe o esta inactivo.');
            header('Location: ' . BASE_URL . '/admin/usuarios');
            exit;
        }
        $usuarioForm['id'] = $id;
        $errores = $this->consumirErrores();
        $roles = $this->usuarioModel->listarRoles();
        $modo = 'editar';
        $pageTitle = 'Editar usuario';
        require_once __DIR__ . '/../views/admin/usuario_form.php';
    }

    public function actualizarUsuario(): void
    {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            $this->flash('error', 'No se indico un usuario valido.');
            header('Location: ' . BASE_URL . '/admin/usuarios');
            exit;
        }
        $datos = $this->datosUsuarioDesdePost(true);
        $errores = $this->validarDatosUsuario($datos, $id, false);
        if ($errores) {
            $datos['id'] = $id;
            $datos['password'] = '';
            $datos['password_confirm'] = '';
            $_SESSION['form_errors'] = $errores;
            $_SESSION['form_old'] = $datos;
            header('Location: ' . BASE_URL . '/admin/usuarios/editar?id=' . $id);
            exit;
        }
        $this->usuarioModel->actualizar($id, $datos);
        $this->registrar('editar_usuario', 'usuarios', $id, 'Usuario actualizado: ' . $datos['email']);
        $this->flash('success', 'Usuario actualizado correctamente.');
        header('Location: ' . BASE_URL . '/admin/usuarios');
        exit;
    }

    public function cambiarEstadoUsuario(): void
    {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $activo = (int) ($_POST['activo'] ?? 0);
        if (!$id || $id === (int) $_SESSION['usuario']['id']) {
            $this->flash('error', 'No podes desactivar tu propio usuario desde este panel.');
            header('Location: ' . BASE_URL . '/admin/usuarios');
            exit;
        }
        $this->usuarioModel->cambiarActivo($id, $activo === 1 ? 1 : 0);
        $this->registrar('cambiar_estado_usuario', 'usuarios', $id, 'Activo: ' . $activo);
        $this->flash('success', 'Estado de usuario actualizado.');
        header('Location: ' . BASE_URL . '/admin/usuarios');
        exit;
    }

    public function participantes(): void
    {
        $participantes = $this->participanteModel->listarTodos();
        $flash = $this->consumirFlash();
        $pageTitle = 'Participantes';
        require_once __DIR__ . '/../views/admin/participantes.php';
    }

    public function crearParticipante(): void
    {
        $participante = $this->consumirOld();
        $errores = $this->consumirErrores();
        $usuariosParticipantes = $this->participanteModel->listarUsuariosParticipantes();
        $modo = 'crear';
        $pageTitle = 'Nuevo participante';
        require_once __DIR__ . '/../views/admin/participante_form.php';
    }

    public function guardarParticipante(): void
    {
        $datos = $this->datosParticipanteDesdePost();
        $errores = $this->validarParticipante($datos);
        if ($errores) {
            $_SESSION['form_errors'] = $errores;
            $_SESSION['form_old'] = $datos;
            header('Location: ' . BASE_URL . '/admin/participantes/nuevo');
            exit;
        }
        $id = $this->participanteModel->crear($datos);
        $this->registrar('crear_participante', 'participantes', $id, 'Participante creado: ' . $datos['nombre']);
        $this->flash('success', 'Participante creado correctamente.');
        header('Location: ' . BASE_URL . '/admin/participantes');
        exit;
    }

    public function editarParticipante(): void
    {
        $id = $this->idDesdeGet('/admin/participantes');
        $participante = $this->consumirOld() ?: $this->participanteModel->buscarPorId($id);
        if (!$participante) {
            $this->flash('error', 'Participante no encontrado.');
            header('Location: ' . BASE_URL . '/admin/participantes');
            exit;
        }
        $participante['id'] = $id;
        $errores = $this->consumirErrores();
        $usuariosParticipantes = $this->participanteModel->listarUsuariosParticipantes();
        $modo = 'editar';
        $pageTitle = 'Editar participante';
        require_once __DIR__ . '/../views/admin/participante_form.php';
    }

    public function actualizarParticipante(): void
    {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $datos = $this->datosParticipanteDesdePost();
        $errores = $this->validarParticipante($datos);
        if (!$id || $errores) {
            $datos['id'] = $id;
            $_SESSION['form_errors'] = $errores ?: ['general' => 'Participante invalido.'];
            $_SESSION['form_old'] = $datos;
            header('Location: ' . BASE_URL . '/admin/participantes/editar?id=' . (int) $id);
            exit;
        }
        $this->participanteModel->actualizar($id, $datos);
        $this->registrar('editar_participante', 'participantes', $id, 'Participante actualizado: ' . $datos['nombre']);
        $this->flash('success', 'Participante actualizado.');
        header('Location: ' . BASE_URL . '/admin/participantes');
        exit;
    }

    public function equipos(): void
    {
        $equipos = $this->participanteModel->listarEquipos();
        $flash = $this->consumirFlash();
        $pageTitle = 'Equipos';
        require_once __DIR__ . '/../views/admin/equipos.php';
    }

    public function crearEquipo(): void
    {
        $equipo = $this->consumirOld();
        $miembrosTexto = $equipo['miembros'] ?? '';
        $errores = $this->consumirErrores();
        $modo = 'crear';
        $pageTitle = 'Nuevo equipo';
        require_once __DIR__ . '/../views/admin/equipo_form.php';
    }

    public function guardarEquipo(): void
    {
        $datos = $this->datosEquipoDesdePost();
        $errores = $this->validarEquipo($datos);
        if ($errores) {
            $_SESSION['form_errors'] = $errores;
            $_SESSION['form_old'] = $datos;
            header('Location: ' . BASE_URL . '/admin/equipos/nuevo');
            exit;
        }
        $id = $this->participanteModel->crearEquipo($datos);
        $this->registrar('crear_equipo', 'equipos', $id, 'Equipo creado: ' . $datos['nombre']);
        $this->flash('success', 'Equipo creado correctamente.');
        header('Location: ' . BASE_URL . '/admin/equipos');
        exit;
    }

    public function editarEquipo(): void
    {
        $id = $this->idDesdeGet('/admin/equipos');
        $equipo = $this->consumirOld() ?: $this->participanteModel->buscarEquipoPorId($id);
        if (!$equipo) {
            $this->flash('error', 'Equipo no encontrado.');
            header('Location: ' . BASE_URL . '/admin/equipos');
            exit;
        }
        $miembros = $this->participanteModel->listarMiembrosEquipo($id);
        $miembrosTexto = $equipo['miembros'] ?? implode("\n", array_map(fn($m) => trim($m['nombre'] . (!empty($m['rol']) ? ' - ' . $m['rol'] : '')), $miembros));
        $equipo['id'] = $id;
        $errores = $this->consumirErrores();
        $modo = 'editar';
        $pageTitle = 'Editar equipo';
        require_once __DIR__ . '/../views/admin/equipo_form.php';
    }

    public function actualizarEquipo(): void
    {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $datos = $this->datosEquipoDesdePost();
        $errores = $this->validarEquipo($datos);
        if (!$id || $errores) {
            $datos['id'] = $id;
            $_SESSION['form_errors'] = $errores ?: ['general' => 'Equipo invalido.'];
            $_SESSION['form_old'] = $datos;
            header('Location: ' . BASE_URL . '/admin/equipos/editar?id=' . (int) $id);
            exit;
        }
        $this->participanteModel->actualizarEquipo($id, $datos);
        $this->registrar('editar_equipo', 'equipos', $id, 'Equipo actualizado: ' . $datos['nombre']);
        $this->flash('success', 'Equipo actualizado.');
        header('Location: ' . BASE_URL . '/admin/equipos');
        exit;
    }

    public function torneos(): void
    {
        $torneos = $this->torneoModel->listarTodos();
        $flash = $this->consumirFlash();
        $pageTitle = 'Gestion de torneos';
        require_once __DIR__ . '/../views/admin/torneos.php';
    }

    public function crearTorneo(): void
    {
        $torneo = $this->consumirOld();
        $errores = $this->consumirErrores();
        $tiposTorneo = $this->torneoModel->listarTipos();
        $organizadores = $this->usuarioModel->listarGestoresDeTorneos();
        $modo = 'crear';
        $pageTitle = 'Nuevo torneo';
        require_once __DIR__ . '/../views/admin/torneo_form.php';
    }

    public function guardarTorneo(): void
    {
        $datos = $this->datosTorneoDesdePost();
        $errores = $this->validarDatosTorneo($datos);
        if ($errores) {
            $_SESSION['form_errors'] = $errores;
            $_SESSION['form_old'] = $datos;
            header('Location: ' . BASE_URL . '/admin/torneos/nuevo');
            exit;
        }
        $torneoId = $this->torneoModel->crear($datos);
        $this->registrar('crear_torneo', 'torneos', $torneoId, 'Torneo creado: ' . $datos['nombre']);
        $this->flash('success', 'Torneo creado correctamente.');
        header('Location: ' . BASE_URL . '/admin/torneos');
        exit;
    }

    public function editarTorneo(): void
    {
        $id = $this->idDesdeGet('/admin/torneos');
        $torneo = $this->consumirOld() ?: $this->torneoModel->buscarPorId($id);
        if (!$torneo) {
            $this->flash('error', 'El torneo solicitado no existe.');
            header('Location: ' . BASE_URL . '/admin/torneos');
            exit;
        }
        $torneo['id'] = $id;
        $errores = $this->consumirErrores();
        $tiposTorneo = $this->torneoModel->listarTipos();
        $organizadores = $this->usuarioModel->listarGestoresDeTorneos();
        $modo = 'editar';
        $pageTitle = 'Editar torneo';
        require_once __DIR__ . '/../views/admin/torneo_form.php';
    }

    public function actualizarTorneo(): void
    {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $datos = $this->datosTorneoDesdePost();
        $errores = $this->validarDatosTorneo($datos);
        if (!$id || $errores) {
            $datos['id'] = $id;
            $_SESSION['form_errors'] = $errores ?: ['general' => 'Torneo invalido.'];
            $_SESSION['form_old'] = $datos;
            header('Location: ' . BASE_URL . '/admin/torneos/editar?id=' . (int) $id);
            exit;
        }
        $this->torneoModel->actualizar($id, $datos);
        $this->registrar('editar_torneo', 'torneos', $id, 'Torneo actualizado: ' . $datos['nombre']);
        $this->flash('success', 'Torneo actualizado correctamente.');
        header('Location: ' . BASE_URL . '/admin/torneos');
        exit;
    }

    public function cambiarEstado(): void
    {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $estado = $_POST['estado'] ?? '';
        if (!$id || !in_array($estado, $this->estadosPermitidos, true)) {
            $this->flash('error', 'Estado de torneo invalido.');
            header('Location: ' . BASE_URL . '/admin/torneos');
            exit;
        }
        $this->torneoModel->actualizarEstado($id, $estado);
        $this->registrar('cambiar_estado_torneo', 'torneos', $id, 'Nuevo estado: ' . $estado);
        $this->flash('success', 'Estado actualizado.');
        header('Location: ' . BASE_URL . '/admin/torneos');
        exit;
    }

    public function gestionarTorneo(): void
    {
        $id = $this->idDesdeGet('/admin/torneos');
        $torneo = $this->torneoModel->buscarPorId($id);
        if (!$torneo) {
            $this->flash('error', 'Torneo no encontrado.');
            header('Location: ' . BASE_URL . '/admin/torneos');
            exit;
        }
        $inscriptos = $this->competenciaModel->listarInscriptos($id);
        $disponibles = $this->competenciaModel->participantesDisponibles($id);
        $rondas = $this->competenciaModel->listarRondas($id);
        $enfrentamientos = $this->competenciaModel->listarEnfrentamientos($id);
        $tabla = $this->competenciaModel->obtenerTabla($id);
        $flash = $this->consumirFlash();
        $pageTitle = 'Gestionar torneo';
        require_once __DIR__ . '/../views/admin/torneo_gestion.php';
    }

    public function inscribirParticipante(): void
    {
        $torneoId = filter_input(INPUT_POST, 'torneo_id', FILTER_VALIDATE_INT);
        $participanteId = filter_input(INPUT_POST, 'participante_id', FILTER_VALIDATE_INT);
        if (!$torneoId || !$participanteId) {
            $this->flash('error', 'Selecciona un participante valido.');
        } else {
            $this->competenciaModel->inscribir($torneoId, $participanteId);
            $this->registrar('inscribir_participante', 'inscripciones', $torneoId, 'Participante ID: ' . $participanteId);
            $this->flash('success', 'Participante inscrito.');
        }
        header('Location: ' . BASE_URL . '/admin/torneos/gestionar?id=' . (int) $torneoId);
        exit;
    }

    public function quitarParticipante(): void
    {
        $torneoId = filter_input(INPUT_POST, 'torneo_id', FILTER_VALIDATE_INT);
        $participanteId = filter_input(INPUT_POST, 'participante_id', FILTER_VALIDATE_INT);
        if ($torneoId && $participanteId) {
            $this->competenciaModel->darDeBaja($torneoId, $participanteId);
            $this->registrar('baja_participante', 'inscripciones', $torneoId, 'Participante ID: ' . $participanteId);
            $this->flash('success', 'Participante quitado del torneo.');
        }
        header('Location: ' . BASE_URL . '/admin/torneos/gestionar?id=' . (int) $torneoId);
        exit;
    }

    public function generarRondas(): void
    {
        $torneoId = filter_input(INPUT_POST, 'torneo_id', FILTER_VALIDATE_INT);
        $modo = $_POST['modo'] ?? 'inicial';
        $torneo = $torneoId ? $this->torneoModel->buscarPorId($torneoId) : null;
        if (!$torneo) {
            $this->flash('error', 'Torneo no encontrado.');
            header('Location: ' . BASE_URL . '/admin/torneos');
            exit;
        }
        try {
            $mensaje = $modo === 'suizo_siguiente'
                ? $this->competenciaModel->generarSiguienteRondaSuiza($torneo)
                : $this->competenciaModel->generarRondas($torneo);
            $this->torneoModel->actualizarEstado($torneoId, TORNEO_EN_CURSO);
            $this->competenciaModel->obtenerTabla($torneoId, true);
            $this->registrar('generar_rondas', 'rondas', $torneoId, $mensaje);
            $this->flash('success', $mensaje);
        } catch (Throwable $e) {
            $this->flash('error', $e->getMessage());
        }
        header('Location: ' . BASE_URL . '/admin/torneos/gestionar?id=' . $torneoId);
        exit;
    }

    public function guardarResultado(): void
    {
        $enfrentamientoId = filter_input(INPUT_POST, 'enfrentamiento_id', FILTER_VALIDATE_INT);
        $puntosA = filter_input(INPUT_POST, 'puntos_a', FILTER_VALIDATE_INT);
        $puntosB = filter_input(INPUT_POST, 'puntos_b', FILTER_VALIDATE_INT);
        $observaciones = trim($_POST['observaciones'] ?? '');
        $volverTorneoId = filter_input(INPUT_POST, 'torneo_id', FILTER_VALIDATE_INT);

        if (!$enfrentamientoId || $puntosA === false || $puntosB === false || $puntosA < 0 || $puntosB < 0) {
            $this->flash('error', 'Resultado invalido. Usa puntajes enteros mayores o iguales a cero.');
            header('Location: ' . BASE_URL . '/admin/torneos/gestionar?id=' . (int) $volverTorneoId);
            exit;
        }

        try {
            $torneoId = $this->competenciaModel->guardarResultado($enfrentamientoId, $puntosA, $puntosB, $observaciones, (int) $_SESSION['usuario']['id']);
            $this->registrar('guardar_resultado', 'resultados', $enfrentamientoId, $puntosA . '-' . $puntosB);
            $this->flash('success', 'Resultado guardado y tabla recalculada.');
            header('Location: ' . BASE_URL . '/admin/torneos/gestionar?id=' . $torneoId);
        } catch (Throwable $e) {
            $this->flash('error', $e->getMessage());
            header('Location: ' . BASE_URL . '/admin/torneos/gestionar?id=' . (int) $volverTorneoId);
        }
        exit;
    }

    public function auditoria(): void
    {
        $auditoria = $this->auditoriaModel->listar(100);
        $pageTitle = 'Auditoria';
        require_once __DIR__ . '/../views/admin/auditoria.php';
    }

    public function modulos(): void
    {
        $pageTitle = 'Administracion del sistema';
        require_once __DIR__ . '/../views/admin/modulos.php';
    }

    private function datosUsuarioDesdePost(bool $editar): array
    {
        return [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'rol_id' => (int) ($_POST['rol_id'] ?? 0),
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
            'activo' => $editar ? (int) ($_POST['activo'] ?? 1) : 1,
        ];
    }

    private function validarDatosUsuario(array $datos, ?int $excluirId, bool $passwordObligatoria): array
    {
        $errores = [];
        if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) < 3) $errores['nombre'] = 'El nombre debe tener al menos 3 caracteres.';
        if ($datos['email'] === '' || !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) $errores['email'] = 'Ingresa un email valido.';
        elseif ($this->usuarioModel->emailExiste($datos['email'], $excluirId)) $errores['email'] = 'Ya existe un usuario con ese email.';
        if (!in_array($datos['rol_id'], [ROL_ADMIN, ROL_ORGANIZADOR, ROL_PARTICIPANTE], true)) $errores['rol_id'] = 'Selecciona un rol valido.';
        if ($passwordObligatoria || $datos['password'] !== '' || $datos['password_confirm'] !== '') {
            if (mb_strlen($datos['password']) < 8) $errores['password'] = 'La contrasena debe tener al menos 8 caracteres.';
            if ($datos['password'] !== $datos['password_confirm']) $errores['password_confirm'] = 'Las contrasenas no coinciden.';
        }
        return $errores;
    }

    private function datosParticipanteDesdePost(): array
    {
        return [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'tipo' => $_POST['tipo'] ?? 'individual',
            'usuario_id' => (int) ($_POST['usuario_id'] ?? 0),
            'contacto' => trim($_POST['contacto'] ?? ''),
            'activo' => (int) ($_POST['activo'] ?? 1),
        ];
    }

    private function validarParticipante(array $datos): array
    {
        $errores = [];
        if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) < 2) $errores['nombre'] = 'El nombre debe tener al menos 2 caracteres.';
        if (!in_array($datos['tipo'], ['individual', 'equipo'], true)) $errores['tipo'] = 'Selecciona un tipo valido.';
        if ($datos['contacto'] !== '' && mb_strlen($datos['contacto']) > 150) $errores['contacto'] = 'El contacto es demasiado largo.';
        return $errores;
    }

    private function datosEquipoDesdePost(): array
    {
        return [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'contacto' => trim($_POST['contacto'] ?? ''),
            'miembros' => trim($_POST['miembros'] ?? ''),
            'activo' => (int) ($_POST['activo'] ?? 1),
        ];
    }

    private function validarEquipo(array $datos): array
    {
        $errores = [];
        if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) < 2) $errores['nombre'] = 'El nombre del equipo debe tener al menos 2 caracteres.';
        if (mb_strlen($datos['descripcion']) > 1000) $errores['descripcion'] = 'La descripcion es demasiado larga.';
        return $errores;
    }

    private function datosTorneoDesdePost(): array
    {
        return [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'tipo_torneo_id' => (int) ($_POST['tipo_torneo_id'] ?? 0),
            'organizador_id' => (int) ($_POST['organizador_id'] ?? 0),
            'estado' => $_POST['estado'] ?? TORNEO_BORRADOR,
            'fecha_inicio' => trim($_POST['fecha_inicio'] ?? ''),
            'fecha_fin' => trim($_POST['fecha_fin'] ?? ''),
            'publico' => (int) ($_POST['publico'] ?? 0),
        ];
    }

    private function validarDatosTorneo(array $datos): array
    {
        $errores = [];
        if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) < 3) $errores['nombre'] = 'El nombre debe tener al menos 3 caracteres.';
        if ($datos['tipo_torneo_id'] <= 0) $errores['tipo_torneo_id'] = 'Selecciona un formato de torneo.';
        if ($datos['organizador_id'] <= 0) $errores['organizador_id'] = 'Selecciona un organizador responsable.';
        if (!in_array($datos['estado'], $this->estadosPermitidos, true)) $errores['estado'] = 'Selecciona un estado valido.';
        if ($datos['fecha_inicio'] !== '' && !$this->fechaValida($datos['fecha_inicio'])) $errores['fecha_inicio'] = 'La fecha de inicio no es valida.';
        if ($datos['fecha_fin'] !== '' && !$this->fechaValida($datos['fecha_fin'])) $errores['fecha_fin'] = 'La fecha de fin no es valida.';
        if ($datos['fecha_inicio'] !== '' && $datos['fecha_fin'] !== '' && $datos['fecha_fin'] < $datos['fecha_inicio']) $errores['fecha_fin'] = 'La fecha de fin no puede ser anterior al inicio.';
        return $errores;
    }

    private function fechaValida(string $fecha): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d', $fecha);
        return $dt && $dt->format('Y-m-d') === $fecha;
    }

    private function consumirFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    private function consumirErrores(): array
    {
        $errores = $_SESSION['form_errors'] ?? [];
        unset($_SESSION['form_errors']);
        return $errores;
    }

    private function consumirOld(): array
    {
        $old = $_SESSION['form_old'] ?? [];
        unset($_SESSION['form_old']);
        return $old;
    }

    private function flash(string $tipo, string $mensaje): void
    {
        $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    }

    private function registrar(string $accion, string $tabla, int $registroId, string $detalle): void
    {
        $this->auditoriaModel->registrar($accion, (int) $_SESSION['usuario']['id'], $tabla, $registroId, $detalle);
    }

    private function idDesdeGet(string $fallback): int
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            $this->flash('error', 'No se indico un registro valido.');
            header('Location: ' . BASE_URL . $fallback);
            exit;
        }
        return $id;
    }
}
