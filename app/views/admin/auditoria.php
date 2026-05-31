<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
?>
<main class="main-content">
    <div class="page-header"><h1 class="page-title">Auditoría</h1><p class="page-subtitle">Últimos cambios registrados sobre la base de datos y acciones importantes.</p></div>
    <section class="card table-card">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Tabla</th><th>Registro</th><th>Detalle</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($auditoria as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['created_at']) ?></td>
                        <td><?= htmlspecialchars($a['usuario_nombre'] ?? 'Sistema') ?></td>
                        <td><?= htmlspecialchars($a['accion']) ?></td>
                        <td><?= htmlspecialchars($a['tabla'] ?? $a['tabla_afectada'] ?? '-') ?></td>
                        <td><?= htmlspecialchars((string)($a['registro_id'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars($a['detalle'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($a['ip'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
