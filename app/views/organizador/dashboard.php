<section class="page-header">
  <div class="page-title">
    <div class="eyebrow">Organizador</div>
    <h1>Panel del organizador</h1>
    <p class="muted">Torneos asignados y accesos rápidos.</p>
  </div>
</section>

<?php if (empty($torneos)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-icon">🏆</div>
      <h3>Sin torneos asignados</h3>
      <p>Cuando un administrador te asigne torneos, aparecerán acá.</p>
    </div>
  </div>
<?php else: ?>
  <div class="grid cols-3">
    <?php foreach ($torneos as $t): ?>
    <article class="card">
      <div class="between">
        <h3 class="mb-0"><?= View::e($t['nombre']) ?></h3>
        <?= View::estadoChip($t['estado']) ?>
      </div>
      <p class="muted"><?= View::e($t['tipo_nombre']) ?> · <?= View::e(ucfirst($t['modalidad'])) ?></p>
      <a class="btn primary" href="/organizador/torneos/<?= (int)$t['id'] ?>">Gestionar →</a>
    </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
