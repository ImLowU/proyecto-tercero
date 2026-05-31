<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
$usuario = $_SESSION['usuario'];
?>
<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">Panel de administración</h1>
        <p class="page-subtitle">Bienvenido, <?= htmlspecialchars($usuario['nombre']) ?>. Desde acá se gestionan usuarios, participantes, equipos, torneos, resultados y auditoría.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon stat-icon-users" aria-hidden="true"></div><div><span class="stat-value"><?= (int) $totalUsuarios ?></span><span class="stat-label">Usuarios</span></div></div>
        <div class="stat-card"><div class="stat-icon stat-icon-participants" aria-hidden="true"></div><div><span class="stat-value"><?= (int) $totalParticipantes ?></span><span class="stat-label">Participantes</span></div></div>
        <div class="stat-card"><div class="stat-icon stat-icon-tournaments" aria-hidden="true"></div><div><span class="stat-value"><?= (int) $totalTorneos ?></span><span class="stat-label">Torneos</span></div></div>
    </div>

    <section class="card mt-24">
        <h2 class="card-title">Accesos rápidos</h2>
        <div class="quick-actions">
            <a class="quick-btn" href="<?= BASE_URL ?>/admin/usuarios">Usuarios</a>
            <a class="quick-btn" href="<?= BASE_URL ?>/admin/participantes">Participantes</a>
            <a class="quick-btn" href="<?= BASE_URL ?>/admin/equipos">Equipos</a>
            <a class="quick-btn" href="<?= BASE_URL ?>/admin/torneos">Torneos</a>
            <a class="quick-btn" href="<?= BASE_URL ?>/admin/auditoria">Auditoría</a>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
