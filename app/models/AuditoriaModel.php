<?php
// ============================================================
// app/models/AuditoriaModel.php
// Registro de acciones importantes del sistema
// ============================================================

require_once __DIR__ . '/Database.php';

class AuditoriaModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function registrar(string $accion, ?int $usuarioId = null, ?string $tabla = null, ?int $registroId = null, ?string $detalle = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $this->db->prepare(
            'INSERT INTO auditoria (usuario_id, accion, tabla, tabla_afectada, registro_id, detalle, ip)
             VALUES (:usuario_id, :accion, :tabla, :tabla_afectada, :registro_id, :detalle, :ip)'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':accion' => $accion,
            ':tabla' => $tabla,
            ':tabla_afectada' => $tabla,
            ':registro_id' => $registroId,
            ':detalle' => $detalle,
            ':ip' => $ip,
        ]);
    }

    public function listar(int $limite = 100): array
    {
        $limite = max(1, min(500, $limite));
        $stmt = $this->db->query(
            'SELECT a.*, u.nombre AS usuario_nombre, u.email AS usuario_email
             FROM auditoria a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             ORDER BY a.created_at DESC
             LIMIT ' . $limite
        );
        return $stmt->fetchAll();
    }
}
