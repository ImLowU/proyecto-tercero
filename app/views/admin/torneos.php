<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
$estadoTexto = ['borrador'=>'Borrador','inscripcion'=>'Inscripción','en_curso'=>'En curso','finalizado'=>'Finalizado'];
?>
<main class="main-content">
    <div class="page-header row-between">
        <div>
            <h1 class="page-title">Torneos</h1>
            <p class="page-subtitle">Creación, configuración, inscripciones, rondas y resultados.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/torneos/nuevo" class="btn btn-primary">+ Nuevo torneo</a>
    </div>
    <?php require __DIR__ . '/../shared/flash.php'; ?>
    <div class="cards-grid">
        <?php foreach ($torneos as $t): ?>
        <article class="card torneo-card">
            <div class="card-head">
                <span class="badge"><?= htmlspecialchars($t['tipo_nombre']) ?></span>
                <span class="badge <?= $t['publico'] ? 'badge-success' : 'badge-muted' ?>"><?= $t['publico'] ? 'Público' : 'Oculto' ?></span>
            </div>
            <h2 class="card-title"><?= htmlspecialchars($t['nombre']) ?></h2>
            <p class="muted"><?= htmlspecialchars(mb_strimwidth($t['descripcion'] ?? 'Sin descripción', 0, 130, '...')) ?></p>
            <div class="meta-list">
                <span>Organizador: <?= htmlspecialchars($t['organizador_nombre']) ?></span>
                <span>Estado: <?= htmlspecialchars($estadoTexto[$t['estado']] ?? $t['estado']) ?></span>
                <span>Inscriptos: <?= (int)$t['total_inscriptos'] ?></span>
            </div>
            <div class="card-actions">
                <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/admin/torneos/gestionar?id=<?= (int)$t['id'] ?>">Gestionar</a>
                <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/admin/torneos/editar?id=<?= (int)$t['id'] ?>">Editar</a>
                <form method="POST" action="<?= BASE_URL ?>/admin/torneos/estado" class="inline-form">
                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                    <select class="form-input compact-select" name="estado" onchange="this.form.submit()">
                        <?php foreach ($estadoTexto as $valor => $texto): ?>
                            <option value="<?= $valor ?>" <?= $t['estado'] === $valor ? 'selected' : '' ?>><?= $texto ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
