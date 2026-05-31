<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
?>
<main class="main-content">
    <div class="page-header row-between">
        <div>
            <h1 class="page-title">Usuarios</h1>
            <p class="page-subtitle">Alta, edición y baja lógica de cuentas del sistema.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/usuarios/nuevo" class="btn btn-primary">+ Nuevo usuario</a>
    </div>
    <?php require __DIR__ . '/../shared/flash.php'; ?>

    <section class="card table-card">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>Creado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nombre']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($u['rol_nombre']) ?></span></td>
                        <td><?= (int) $u['activo'] === 1 ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-muted">Inactivo</span>' ?></td>
                        <td><?= htmlspecialchars($u['created_at']) ?></td>
                        <td class="actions-cell">
                            <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/admin/usuarios/editar?id=<?= (int) $u['id'] ?>">Editar</a>
                            <?php if ((int) $u['id'] !== (int) $_SESSION['usuario']['id']): ?>
                                <form method="POST" action="<?= BASE_URL ?>/admin/usuarios/estado" class="inline-form">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <input type="hidden" name="activo" value="<?= (int) $u['activo'] === 1 ? 0 : 1 ?>">
                                    <button class="btn btn-sm <?= (int) $u['activo'] === 1 ? 'btn-danger' : 'btn-success' ?>" onclick="return confirmar('¿Cambiar estado del usuario?')">
                                        <?= (int) $u['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
