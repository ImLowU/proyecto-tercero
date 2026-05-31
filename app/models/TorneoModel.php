<?php
// ============================================================
//  app/models/TorneoModel.php
//  Acceso a datos de torneos y tipos de torneo
// ============================================================

require_once __DIR__ . '/Database.php';

class TorneoModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Lista los tipos de torneo configurados en la base.
     */
    public function listarTipos(): array
    {
        return $this->db
            ->query('SELECT id, nombre, descripcion FROM tipos_torneo ORDER BY id ASC')
            ->fetchAll();
    }

    /**
     * Lista torneos públicos visibles para usuarios no autenticados.
     */
    public function listarPublicos(?int $limite = null): array
    {
        $sql = 'SELECT t.*, tt.nombre AS tipo_nombre, u.nombre AS organizador_nombre,
                       (SELECT COUNT(*) FROM inscripciones i WHERE i.torneo_id = t.id AND i.estado = "confirmada") AS total_inscriptos
                FROM torneos t
                JOIN tipos_torneo tt ON tt.id = t.tipo_torneo_id
                JOIN usuarios u      ON u.id  = t.organizador_id
                WHERE t.publico = 1
                ORDER BY t.created_at DESC';

        if ($limite !== null) {
            $sql .= ' LIMIT ' . (int) $limite;
        }

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Busca un torneo público por ID.
     */
    public function buscarPublicoPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, tt.nombre AS tipo_nombre, u.nombre AS organizador_nombre,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.torneo_id = t.id AND i.estado = "confirmada") AS total_inscriptos
             FROM torneos t
             JOIN tipos_torneo tt ON tt.id = t.tipo_torneo_id
             JOIN usuarios u      ON u.id  = t.organizador_id
             WHERE t.id = :id AND t.publico = 1
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $torneo = $stmt->fetch();
        return $torneo ?: null;
    }

    /**
     * Lista todos los torneos para el panel administrativo.
     */
    public function listarTodos(): array
    {
        return $this->db->query(
            'SELECT t.*, tt.nombre AS tipo_nombre, u.nombre AS organizador_nombre,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.torneo_id = t.id AND i.estado = "confirmada") AS total_inscriptos
             FROM torneos t
             JOIN tipos_torneo tt ON tt.id = t.tipo_torneo_id
             JOIN usuarios u      ON u.id  = t.organizador_id
             ORDER BY t.created_at DESC'
        )->fetchAll();
    }

    /**
     * Busca un torneo por ID para administración.
     */
    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, tt.nombre AS tipo_nombre, u.nombre AS organizador_nombre
             FROM torneos t
             JOIN tipos_torneo tt ON tt.id = t.tipo_torneo_id
             JOIN usuarios u      ON u.id  = t.organizador_id
             WHERE t.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $torneo = $stmt->fetch();
        return $torneo ?: null;
    }

    /**
     * Lista torneos de un organizador específico.
     */
    public function listarPorOrganizador(int $organizadorId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, tt.nombre AS tipo_nombre,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.torneo_id = t.id AND i.estado = "confirmada") AS total_inscriptos
             FROM torneos t
             JOIN tipos_torneo tt ON tt.id = t.tipo_torneo_id
             WHERE t.organizador_id = :org_id
             ORDER BY t.created_at DESC'
        );
        $stmt->execute([':org_id' => $organizadorId]);
        return $stmt->fetchAll();
    }

    /**
     * Crea un torneo nuevo y devuelve el ID generado.
     */
    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO torneos
                (nombre, descripcion, tipo_torneo_id, organizador_id, estado, fecha_inicio, fecha_fin, publico)
             VALUES
                (:nombre, :descripcion, :tipo_torneo_id, :organizador_id, :estado, :fecha_inicio, :fecha_fin, :publico)'
        );
        $stmt->execute([
            ':nombre'         => $datos['nombre'],
            ':descripcion'    => $datos['descripcion']    ?: null,
            ':tipo_torneo_id' => $datos['tipo_torneo_id'],
            ':organizador_id' => $datos['organizador_id'],
            ':estado'         => $datos['estado']         ?? TORNEO_BORRADOR,
            ':fecha_inicio'   => $datos['fecha_inicio']   ?: null,
            ':fecha_fin'      => $datos['fecha_fin']      ?: null,
            ':publico'        => $datos['publico']        ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza los datos editables de un torneo.
     */
    public function actualizar(int $id, array $datos): void
    {
        $stmt = $this->db->prepare(
            'UPDATE torneos
             SET nombre = :nombre,
                 descripcion = :descripcion,
                 tipo_torneo_id = :tipo_torneo_id,
                 organizador_id = :organizador_id,
                 estado = :estado,
                 fecha_inicio = :fecha_inicio,
                 fecha_fin = :fecha_fin,
                 publico = :publico
             WHERE id = :id'
        );
        $stmt->execute([
            ':id'             => $id,
            ':nombre'         => $datos['nombre'],
            ':descripcion'    => $datos['descripcion'] ?: null,
            ':tipo_torneo_id' => $datos['tipo_torneo_id'],
            ':organizador_id' => $datos['organizador_id'],
            ':estado'         => $datos['estado'],
            ':fecha_inicio'   => $datos['fecha_inicio'] ?: null,
            ':fecha_fin'      => $datos['fecha_fin'] ?: null,
            ':publico'        => $datos['publico'],
        ]);
    }

    /**
     * Actualiza solo el estado de un torneo.
     */
    public function actualizarEstado(int $id, string $estado): void
    {
        $stmt = $this->db->prepare('UPDATE torneos SET estado = :estado WHERE id = :id');
        $stmt->execute([':estado' => $estado, ':id' => $id]);
    }
}
