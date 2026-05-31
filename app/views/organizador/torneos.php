<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
?>
<main class="main-content">
    <div class="page-header"><h1 class="page-title">Mis torneos</h1><p class="page-subtitle">Solo aparecen los torneos donde sos organizador responsable.</p></div>
    <?php require __DIR__ . '/../shared/flash.php'; ?>
    <div class="cards-grid">
        <?php foreach ($torneos as $t): ?>
            <article class="card torneo-card">
                <div class="card-head"><span class="badge"><?= htmlspecialchars($t['tipo_nombre']) ?></span><span class="badge"><?= htmlspecialchars($t['estado']) ?></span></div>
                <h2 class="card-title"><?= htmlspecialchars($t['nombre']) ?></h2>
                <p class="muted">Inscriptos: <?= (int)$t['total_inscriptos'] ?></p>
                <div class="card-actions"><a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/organizador/torneos/gestionar?id=<?= (int)$t['id'] ?>">Gestionar</a><a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/torneo?id=<?= (int)$t['id'] ?>">Ver público</a></div>
            </article>
        <?php endforeach; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
