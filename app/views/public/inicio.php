<?php
$bodyClass = 'public-page';
require_once __DIR__ . '/../shared/header.php';
?>
<header class="public-header">
    <?php require __DIR__ . "/../shared/public_nav.php"; ?>
    <section class="hero">
        <div class="hero-brand"><img src="<?= BASE_URL ?>/assets/img/flexarena-logo.svg" alt="FlexArena" class="brand-logo-hero"><span class="hero-brand-name">FlexArena</span></div>
        <span class="eyebrow">Sistema de Gestión Deportiva Modular</span>
        <h1>Organizá torneos deportivos, mentales y electrónicos.</h1>
        <p>Consulta pública de calendarios, resultados, posiciones y llaves competitivas.</p>
        <a href="<?= BASE_URL ?>/torneos" class="btn btn-primary">Ver torneos públicos</a>
    </section>
</header>
<main class="main-content">
    <div class="page-header"><h2 class="page-title">Torneos destacados</h2><p class="page-subtitle">Últimos torneos publicados.</p></div>
    <div class="cards-grid">
        <?php foreach ($torneos as $t): ?>
            <article class="card torneo-card">
                <div class="card-head"><span class="badge"><?= htmlspecialchars($t['tipo_nombre']) ?></span><span class="badge"><?= htmlspecialchars($t['estado']) ?></span></div>
                <h3 class="card-title"><?= htmlspecialchars($t['nombre']) ?></h3>
                <p class="muted"><?= htmlspecialchars(mb_strimwidth($t['descripcion'] ?? 'Sin descripción', 0, 120, '...')) ?></p>
                <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/torneo?id=<?= (int)$t['id'] ?>">Ver detalle</a>
            </article>
        <?php endforeach; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
