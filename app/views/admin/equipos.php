<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
?>
<main class="main-content">
    <div class="page-header row-between">
        <div>
            <h1 class="page-title">Equipos</h1>
            <p class="page-subtitle">Registro de equipos y planteles. Al crear un equipo también se crea su participante asociado.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/equipos/nuevo" class="btn btn-primary">+ Nuevo equipo</a>
    </div>
    <?php require __DIR__ . '/../shared/flash.php'; ?>
    <section class="card table-card">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Nombre</th><th>Participante asociado</th><th>Estado</th><th>Creado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($equipos as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['nombre']) ?></td>
                        <td><?= htmlspecialchars($e['participante_nombre'] ?? '-') ?></td>
                        <td><?= (int)$e['activo'] === 1 ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-muted">Inactivo</span>' ?></td>
                        <td><?= htmlspecialchars($e['created_at']) ?></td>
                        <td><a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/admin/equipos/editar?id=<?= (int)$e['id'] ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
