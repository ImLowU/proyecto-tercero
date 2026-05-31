<?php
// ============================================================
// app/models/ParticipanteModel.php
// Gestion de participantes individuales y equipos
// ============================================================

require_once __DIR__ . '/Database.php';

class ParticipanteModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listarTodos(): array
    {
        $stmt = $this->db->query(
            'SELECT p.*, u.email AS usuario_email
             FROM participantes p
             LEFT JOIN usuarios u ON u.id = p.usuario_id
             ORDER BY p.activo DESC, p.nombre ASC'
        );
        return $stmt->fetchAll();
    }

    public function listarActivos(): array
    {
        $stmt = $this->db->query(
            'SELECT id, nombre, tipo, contacto
             FROM participantes
             WHERE activo = 1
             ORDER BY nombre ASC'
        );
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM participantes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $participante = $stmt->fetch();
        return $participante ?: null;
    }

    public function crear(array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO participantes (nombre, tipo, usuario_id, contacto, activo)
             VALUES (:nombre, :tipo, :usuario_id, :contacto, 1)'
        );
        $stmt->execute([
            ':nombre'     => $datos['nombre'],
            ':tipo'       => $datos['tipo'],
            ':usuario_id' => $datos['usuario_id'] ?: null,
            ':contacto'   => $datos['contacto'] ?: null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        $stmt = $this->db->prepare(
            'UPDATE participantes
             SET nombre = :nombre,
                 tipo = :tipo,
                 usuario_id = :usuario_id,
                 contacto = :contacto,
                 activo = :activo
             WHERE id = :id'
        );
        $stmt->execute([
            ':id'         => $id,
            ':nombre'     => $datos['nombre'],
            ':tipo'       => $datos['tipo'],
            ':usuario_id' => $datos['usuario_id'] ?: null,
            ':contacto'   => $datos['contacto'] ?: null,
            ':activo'     => (int) $datos['activo'],
        ]);
    }

    public function cambiarActivo(int $id, int $activo): void
    {
        $stmt = $this->db->prepare('UPDATE participantes SET activo = :activo WHERE id = :id');
        $stmt->execute([':activo' => $activo, ':id' => $id]);
    }

    public function listarUsuariosParticipantes(): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.nombre, u.email
             FROM usuarios u
             WHERE u.activo = 1 AND u.rol_id = :rol
             ORDER BY u.nombre ASC'
        );
        $stmt->execute([':rol' => ROL_PARTICIPANTE]);
        return $stmt->fetchAll();
    }

    public function listarPorUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM participantes
             WHERE usuario_id = :usuario_id AND activo = 1
             ORDER BY nombre ASC'
        );
        $stmt->execute([':usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }

    public function listarEquipos(): array
    {
        $stmt = $this->db->query(
            'SELECT e.*, p.nombre AS participante_nombre
             FROM equipos e
             LEFT JOIN participantes p ON p.id = e.participante_id
             ORDER BY e.activo DESC, e.nombre ASC'
        );
        return $stmt->fetchAll();
    }

    public function buscarEquipoPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM equipos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $equipo = $stmt->fetch();
        return $equipo ?: null;
    }

    public function crearEquipo(array $datos): int
    {
        $this->db->beginTransaction();
        try {
            $participanteId = $this->crear([
                'nombre'     => $datos['nombre'],
                'tipo'       => 'equipo',
                'usuario_id' => null,
                'contacto'   => $datos['contacto'] ?? null,
            ]);

            $stmt = $this->db->prepare(
                'INSERT INTO equipos (nombre, descripcion, participante_id, activo)
                 VALUES (:nombre, :descripcion, :participante_id, 1)'
            );
            $stmt->execute([
                ':nombre'          => $datos['nombre'],
                ':descripcion'     => $datos['descripcion'] ?: null,
                ':participante_id' => $participanteId,
            ]);
            $equipoId = (int) $this->db->lastInsertId();

            $this->guardarMiembros($equipoId, $datos['miembros'] ?? '');
            $this->db->commit();
            return $equipoId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function actualizarEquipo(int $id, array $datos): void
    {
        $equipo = $this->buscarEquipoPorId($id);
        if (!$equipo) {
            return;
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE equipos
                 SET nombre = :nombre, descripcion = :descripcion, activo = :activo
                 WHERE id = :id'
            );
            $stmt->execute([
                ':id'          => $id,
                ':nombre'      => $datos['nombre'],
                ':descripcion' => $datos['descripcion'] ?: null,
                ':activo'      => (int) $datos['activo'],
            ]);

            if (!empty($equipo['participante_id'])) {
                $this->actualizar((int) $equipo['participante_id'], [
                    'nombre'     => $datos['nombre'],
                    'tipo'       => 'equipo',
                    'usuario_id' => null,
                    'contacto'   => $datos['contacto'] ?? null,
                    'activo'     => (int) $datos['activo'],
                ]);
            }

            $this->db->prepare('DELETE FROM equipo_miembros WHERE equipo_id = :id')->execute([':id' => $id]);
            $this->guardarMiembros($id, $datos['miembros'] ?? '');
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function listarMiembrosEquipo(int $equipoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM equipo_miembros
             WHERE equipo_id = :equipo_id
             ORDER BY id ASC'
        );
        $stmt->execute([':equipo_id' => $equipoId]);
        return $stmt->fetchAll();
    }

    private function guardarMiembros(int $equipoId, string $texto): void
    {
        $lineas = preg_split('/\r\n|\r|\n/', trim($texto));
        if (!$lineas) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO equipo_miembros (equipo_id, nombre, rol)
             VALUES (:equipo_id, :nombre, :rol)'
        );

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }

            $partes = array_map('trim', explode('-', $linea, 2));
            $stmt->execute([
                ':equipo_id' => $equipoId,
                ':nombre'    => $partes[0],
                ':rol'       => $partes[1] ?? null,
            ]);
        }
    }
}
