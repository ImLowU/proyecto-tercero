<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
?>
<main class="main-content">
    <div class="page-header row-between">
        <div>
            <h1 class="page-title">Participantes</h1>
            <p class="page-subtitle">Personas o equipos que pueden inscribirse en torneos.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/participantes/nuevo" class="btn btn-primary">+ Nuevo participante</a>
    </div>
    <?php require __DIR__ . '/../shared/flash.php'; ?>
    <section class="card table-card">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Nombre</th><th>Tipo</th><th>Contacto</th><th>Usuario</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($participantes as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nombre']) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($p['tipo']) ?></span></td>
                        <td><?= htmlspecialchars($p['contacto'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['usuario_email'] ?? '-') ?></td>
                        <td><?= (int)$p['activo'] === 1 ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-muted">Inactivo</span>' ?></td>
                        <td><a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/admin/participantes/editar?id=<?= (int)$p['id'] ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
