<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
?>
<main class="main-content">
    <div class="page-header"><h1 class="page-title">Panel del organizador</h1><p class="page-subtitle">Gestioná los torneos que tenés asignados.</p></div>
    <section class="card"><h2 class="card-title">Mis torneos</h2><p class="muted">Total asignados: <?= count($torneos) ?></p><a class="btn btn-primary mt-12" href="<?= BASE_URL ?>/organizador/torneos">Ver mis torneos</a></section>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
