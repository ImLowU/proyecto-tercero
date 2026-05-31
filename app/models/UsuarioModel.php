<?php
// ============================================================
//  app/models/UsuarioModel.php
//  Lógica de acceso a datos para la tabla `usuarios`
// ============================================================

require_once __DIR__ . '/Database.php';

class UsuarioModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Busca un usuario por email.
     * Devuelve el array con sus datos (incluyendo rol) o null si no existe.
     */
    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, r.nombre AS rol_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             WHERE u.email = :email AND u.activo = 1
             LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    /**
     * Busca un usuario por ID.
     */
    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, r.nombre AS rol_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             WHERE u.id = :id AND u.activo = 1
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    /**
     * Crea un nuevo usuario. Recibe el password en texto plano y lo hashea aquí.
     * Devuelve el ID del usuario creado.
     */
    public function crear(string $nombre, string $email, string $password, int $rolId): int
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (nombre, email, password, rol_id)
             VALUES (:nombre, :email, :password, :rol_id)'
        );
        $stmt->execute([
            ':nombre'   => $nombre,
            ':email'    => $email,
            ':password' => $hash,
            ':rol_id'   => $rolId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Lista los roles disponibles para asignarlos a usuarios desde el panel admin.
     */
    public function listarRoles(): array
    {
        $stmt = $this->db->query(
            'SELECT id, nombre, descripcion
             FROM roles
             ORDER BY id ASC'
        );
        return $stmt->fetchAll();
    }

    /**
     * Lista todos los usuarios (para panel de admin).
     */
    public function listarTodos(): array
    {
        $stmt = $this->db->query(
            'SELECT u.id, u.nombre, u.email, u.activo, u.created_at, r.nombre AS rol_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             ORDER BY u.created_at DESC'
        );
        return $stmt->fetchAll();
    }



    /**
     * Lista usuarios que pueden organizar torneos: administradores y organizadores.
     */
    public function listarGestoresDeTorneos(): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.nombre, u.email, r.nombre AS rol_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             WHERE u.activo = 1 AND u.rol_id IN (:admin, :organizador)
             ORDER BY r.id ASC, u.nombre ASC'
        );
        $stmt->execute([
            ':admin'       => ROL_ADMIN,
            ':organizador' => ROL_ORGANIZADOR,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Verifica si ya existe un email en la tabla.
     */
    public function emailExiste(string $email, ?int $excluirId = null): bool
    {
        $sql = 'SELECT id FROM usuarios WHERE email = :email';
        $params = [':email' => $email];

        if ($excluirId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excluirId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }


    /**
     * Actualiza datos basicos de usuario. Si password viene vacia, no se modifica.
     */
    public function actualizar(int $id, array $datos): void
    {
        if (!empty($datos['password'])) {
            $stmt = $this->db->prepare(
                'UPDATE usuarios
                 SET nombre = :nombre, email = :email, rol_id = :rol_id, activo = :activo, password = :password
                 WHERE id = :id'
            );
            $stmt->execute([
                ':id'       => $id,
                ':nombre'   => $datos['nombre'],
                ':email'    => $datos['email'],
                ':rol_id'   => $datos['rol_id'],
                ':activo'   => (int) $datos['activo'],
                ':password' => password_hash($datos['password'], PASSWORD_BCRYPT),
            ]);
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET nombre = :nombre, email = :email, rol_id = :rol_id, activo = :activo
             WHERE id = :id'
        );
        $stmt->execute([
            ':id'     => $id,
            ':nombre' => $datos['nombre'],
            ':email'  => $datos['email'],
            ':rol_id' => $datos['rol_id'],
            ':activo' => (int) $datos['activo'],
        ]);
    }

    /**
     * Activa o desactiva un usuario.
     */
    public function cambiarActivo(int $id, int $activo): void
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET activo = :activo WHERE id = :id');
        $stmt->execute([':id' => $id, ':activo' => $activo]);
    }

    /**
     * Desactiva un usuario (baja lógica).
     */
    public function desactivar(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET activo = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
