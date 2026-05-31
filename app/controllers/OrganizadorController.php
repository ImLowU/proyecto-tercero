<?php
// ============================================================
// app/controllers/OrganizadorController.php
// Panel del organizador de torneos
// ============================================================

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/TorneoModel.php';
require_once __DIR__ . '/../models/ParticipanteModel.php';
require_once __DIR__ . '/../models/CompetenciaModel.php';
require_once __DIR__ . '/../models/AuditoriaModel.php';

class OrganizadorController
{
    private AuthController $auth;
    private TorneoModel $torneoModel;
    private ParticipanteModel $participanteModel;
    private CompetenciaModel $competenciaModel;
    private AuditoriaModel $auditoriaModel;

    public function __construct()
    {
        $this->auth = new AuthController();
        $this->auth->requerirRol(ROL_ORGANIZADOR);
        $this->torneoModel = new TorneoModel();
        $this->participanteModel = new ParticipanteModel();
        $this->competenciaModel = new CompetenciaModel();
        $this->auditoriaModel = new AuditoriaModel();
    }

    public function dashboard(): void
    {
        $torneos = $this->torneoModel->listarPorOrganizador((int) $_SESSION['usuario']['id']);
        $pageTitle = 'Panel organizador';
        require_once __DIR__ . '/../views/organizador/dashboard.php';
    }

    public function torneos(): void
    {
        $torneos = $this->torneoModel->listarPorOrganizador((int) $_SESSION['usuario']['id']);
        $flash = $this->consumirFlash();
        $pageTitle = 'Mis torneos';
        require_once __DIR__ . '/../views/organizador/torneos.php';
    }

    public function gestionar(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $torneo = $id ? $this->torneoModel->buscarPorId($id) : null;
        $this->validarPropiedad($torneo);

        $inscriptos = $this->competenciaModel->listarInscriptos($id);
        $disponibles = $this->competenciaModel->participantesDisponibles($id);
        $rondas = $this->competenciaModel->listarRondas($id);
        $enfrentamientos = $this->competenciaModel->listarEnfrentamientos($id);
        $tabla = $this->competenciaModel->obtenerTabla($id);
        $flash = $this->consumirFlash();
        $pageTitle = 'Gestionar torneo';
        $modoOrganizador = true;
        require_once __DIR__ . '/../views/admin/torneo_gestion.php';
    }

    public function inscribir(): void
    {
        $torneoId = filter_input(INPUT_POST, 'torneo_id', FILTER_VALIDATE_INT);
        $participanteId = filter_input(INPUT_POST, 'participante_id', FILTER_VALIDATE_INT);
        $torneo = $torneoId ? $this->torneoModel->buscarPorId($torneoId) : null;
        $this->validarPropiedad($torneo);

        if ($participanteId) {
            $this->competenciaModel->inscribir($torneoId, $participanteId);
            $this->registrar('organizador_inscribe', 'inscripciones', $torneoId, 'Participante ID: ' . $participanteId);
            $this->flash('success', 'Participante inscrito.');
        } else {
            $this->flash('error', 'Selecciona un participante valido.');
        }
        header('Location: ' . BASE_URL . '/organizador/torneos/gestionar?id=' . $torneoId);
        exit;
    }

    public function quitar(): void
    {
        $torneoId = filter_input(INPUT_POST, 'torneo_id', FILTER_VALIDATE_INT);
        $participanteId = filter_input(INPUT_POST, 'participante_id', FILTER_VALIDATE_INT);
        $torneo = $torneoId ? $this->torneoModel->buscarPorId($torneoId) : null;
        $this->validarPropiedad($torneo);
        if ($participanteId) {
            $this->competenciaModel->darDeBaja($torneoId, $participanteId);
            $this->registrar('organizador_quita_participante', 'inscripciones', $torneoId, 'Participante ID: ' . $participanteId);
            $this->flash('success', 'Participante quitado.');
        }
        header('Location: ' . BASE_URL . '/organizador/torneos/gestionar?id=' . $torneoId);
        exit;
    }

    public function generarRondas(): void
    {
        $torneoId = filter_input(INPUT_POST, 'torneo_id', FILTER_VALIDATE_INT);
        $modo = $_POST['modo'] ?? 'inicial';
        $torneo = $torneoId ? $this->torneoModel->buscarPorId($torneoId) : null;
        $this->validarPropiedad($torneo);
        try {
            $mensaje = $modo === 'suizo_siguiente'
                ? $this->competenciaModel->generarSiguienteRondaSuiza($torneo)
                : $this->competenciaModel->generarRondas($torneo);
            $this->torneoModel->actualizarEstado($torneoId, TORNEO_EN_CURSO);
            $this->competenciaModel->obtenerTabla($torneoId, true);
            $this->registrar('organizador_genera_rondas', 'rondas', $torneoId, $mensaje);
            $this->flash('success', $mensaje);
        } catch (Throwable $e) {
            $this->flash('error', $e->getMessage());
        }
        header('Location: ' . BASE_URL . '/organizador/torneos/gestionar?id=' . $torneoId);
        exit;
    }

    public function guardarResultado(): void
    {
        $enfrentamientoId = filter_input(INPUT_POST, 'enfrentamiento_id', FILTER_VALIDATE_INT);
        $torneoId = filter_input(INPUT_POST, 'torneo_id', FILTER_VALIDATE_INT);
        $torneo = $torneoId ? $this->torneoModel->buscarPorId($torneoId) : null;
        $this->validarPropiedad($torneo);

        $puntosA = filter_input(INPUT_POST, 'puntos_a', FILTER_VALIDATE_INT);
        $puntosB = filter_input(INPUT_POST, 'puntos_b', FILTER_VALIDATE_INT);
        if (!$enfrentamientoId || $puntosA === false || $puntosB === false || $puntosA < 0 || $puntosB < 0) {
            $this->flash('error', 'Resultado invalido.');
        } else {
            $this->competenciaModel->guardarResultado($enfrentamientoId, $puntosA, $puntosB, trim($_POST['observaciones'] ?? ''), (int) $_SESSION['usuario']['id']);
            $this->registrar('organizador_guarda_resultado', 'resultados', $enfrentamientoId, $puntosA . '-' . $puntosB);
            $this->flash('success', 'Resultado guardado.');
        }
        header('Location: ' . BASE_URL . '/organizador/torneos/gestionar?id=' . $torneoId);
        exit;
    }

    private function validarPropiedad(?array $torneo): void
    {
        if (!$torneo || (int) $torneo['organizador_id'] !== (int) $_SESSION['usuario']['id']) {
            http_response_code(403);
            require_once __DIR__ . '/../views/shared/403.php';
            exit;
        }
    }

    private function flash(string $tipo, string $mensaje): void
    {
        $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    }

    private function consumirFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    private function registrar(string $accion, string $tabla, int $registroId, string $detalle): void
    {
        $this->auditoriaModel->registrar($accion, (int) $_SESSION['usuario']['id'], $tabla, $registroId, $detalle);
    }
}
