<?php
// ============================================================
// app/controllers/PublicoController.php
// Vistas publicas sin autenticacion
// ============================================================

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../models/TorneoModel.php';
require_once __DIR__ . '/../models/CompetenciaModel.php';

class PublicoController
{
    private TorneoModel $torneoModel;
    private CompetenciaModel $competenciaModel;

    public function __construct()
    {
        $this->torneoModel = new TorneoModel();
        $this->competenciaModel = new CompetenciaModel();
    }

    public function inicio(): void
    {
        $torneos = $this->torneoModel->listarPublicos(6);
        $pageTitle = 'Inicio';
        require_once __DIR__ . '/../views/public/inicio.php';
    }

    public function torneos(): void
    {
        $torneos = $this->torneoModel->listarPublicos();
        $pageTitle = 'Torneos';
        require_once __DIR__ . '/../views/public/torneos.php';
    }

    public function detalle(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            header('Location: ' . BASE_URL . '/torneos');
            exit;
        }

        $torneo = $this->torneoModel->buscarPublicoPorId($id);
        if (!$torneo) {
            http_response_code(404);
            echo '<p>Torneo no encontrado. <a href="' . BASE_URL . '/torneos">Ver torneos</a></p>';
            return;
        }

        $inscriptos = $this->competenciaModel->listarInscriptos($id);
        $enfrentamientos = $this->competenciaModel->listarEnfrentamientos($id);
        $tabla = $this->competenciaModel->obtenerTabla($id, false);
        $pageTitle = $torneo['nombre'];
        require_once __DIR__ . '/../views/public/torneo_detalle.php';
    }
}
