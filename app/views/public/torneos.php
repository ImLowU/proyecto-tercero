<?php
$bodyClass = 'public-page';
require_once __DIR__ . '/../shared/header.php';
?>
<header class="public-mini-header">
    <?php require __DIR__ . "/../shared/public_nav.php"; ?>
</header>
<main class="main-content">
    <div class="page-header"><h1 class="page-title">Torneos públicos</h1><p class="page-subtitle">Calendarios, resultados y posiciones disponibles para consulta.</p></div>
    <div class="cards-grid">
        <?php foreach ($torneos as $t): ?>
            <article class="card torneo-card">
                <div class="card-head"><span class="badge"><?= htmlspecialchars($t['tipo_nombre']) ?></span><span class="badge"><?= htmlspecialchars($t['estado']) ?></span></div>
                <h2 class="card-title"><?= htmlspecialchars($t['nombre']) ?></h2>
                <p class="muted"><?= htmlspecialchars(mb_strimwidth($t['descripcion'] ?? 'Sin descripción', 0, 130, '...')) ?></p>
                <div class="meta-list"><span>Organizador: <?= htmlspecialchars($t['organizador_nombre']) ?></span><span>Inscriptos: <?= (int)$t['total_inscriptos'] ?></span></div>
                <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/torneo?id=<?= (int)$t['id'] ?>">Ver detalle</a>
            </article>
        <?php endforeach; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
