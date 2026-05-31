<?php
// ============================================================
// app/controllers/ParticipanteController.php
// Panel del participante autenticado
// ============================================================

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/ParticipanteModel.php';
require_once __DIR__ . '/../models/CompetenciaModel.php';
require_once __DIR__ . '/../models/Database.php';

class ParticipanteController
{
    private AuthController $auth;
    private ParticipanteModel $participanteModel;
    private CompetenciaModel $competenciaModel;
    private PDO $db;

    public function __construct()
    {
        $this->auth = new AuthController();
        $this->auth->requerirLogin();
        $this->participanteModel = new ParticipanteModel();
        $this->competenciaModel = new CompetenciaModel();
        $this->db = Database::getConnection();
    }

    public function dashboard(): void
    {
        $usuario = $_SESSION['usuario'];
        $participantes = $this->participanteModel->listarPorUsuario((int) $usuario['id']);
        $torneos = $this->listarTorneosDelUsuario((int) $usuario['id']);
        $pageTitle = 'Mis torneos';
        require_once __DIR__ . '/../views/participante/dashboard.php';
    }

    private function listarTorneosDelUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, tt.nombre AS tipo_nombre, p.nombre AS participante_nombre
             FROM participantes p
             JOIN inscripciones i ON i.participante_id = p.id AND i.estado = "confirmada"
             JOIN torneos t ON t.id = i.torneo_id
             JOIN tipos_torneo tt ON tt.id = t.tipo_torneo_id
             WHERE p.usuario_id = :usuario_id
             ORDER BY t.fecha_inicio DESC, t.created_at DESC'
        );
        $stmt->execute([':usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }
}
