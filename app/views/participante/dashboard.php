<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
?>
<main class="main-content">
    <div class="page-header"><h1 class="page-title">Mis torneos</h1><p class="page-subtitle">Consulta de torneos donde tu participante está inscrito.</p></div>
    <?php if (empty($participantes)): ?>
        <div class="alert alert-info"><span class="alert-icon">i</span><span>Tu usuario todavía no está vinculado a un participante. Un administrador puede vincularlo desde Participantes.</span></div>
    <?php endif; ?>
    <div class="cards-grid">
        <?php foreach ($torneos as $t): ?>
            <article class="card torneo-card">
                <span class="badge"><?= htmlspecialchars($t['tipo_nombre']) ?></span>
                <h2 class="card-title mt-12"><?= htmlspecialchars($t['nombre']) ?></h2>
                <p class="muted">Inscrito como: <?= htmlspecialchars($t['participante_nombre']) ?></p>
                <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/torneo?id=<?= (int)$t['id'] ?>">Ver calendario y posiciones</a>
            </article>
        <?php endforeach; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
