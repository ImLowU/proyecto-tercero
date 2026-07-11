<section class="section">
  <div class="section-head">
    <div>
      <div class="eyebrow">Vista pública</div>
      <h1 class="mb-0">Torneos</h1>
    </div>
    <form method="GET" action="/torneos" class="cluster" style="align-items:flex-end">
      <div class="field">
        <label>Formato</label>
        <select name="tipo" onchange="this.form.submit()">
          <option value="">Todos</option>
          <?php foreach ($tipos as $t): ?>
            <option value="<?= View::e($t['slug']) ?>" <?= $filtroTipo === $t['slug'] ? 'selected' : '' ?>>
              <?= View::e($t['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Estado</label>
        <select name="estado" onchange="this.form.submit()">
          <option value="">Todos</option>
          <?php foreach (['en_curso','inscripcion','finalizado','borrador'] as $est): ?>
            <option value="<?= $est ?>" <?= $filtroEstado === $est ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$est)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>

  <?php if (empty($torneos)): ?>
    <div class="card">
      <div class="empty-state">
        <div class="empty-icon">🏆</div>
        <h3>No hay torneos disponibles</h3>
        <p>Cuando se publiquen torneos, aparecerán acá.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="grid cols-3">
      <?php foreach ($torneos as $t): ?>
      <article class="card" style="display:flex;flex-direction:column;gap:.6rem">
        <div style="display:flex;justify-content:space-between;align-items:start;gap:.5rem">
          <strong><?= View::e($t['nombre']) ?></strong>
          <?= View::estadoChip($t['estado']) ?>
        </div>
        <div>
          <span class="chip"><?= View::e($t['tipo_nombre']) ?></span>
          <span class="muted" style="margin-left:.4rem"><?= View::e(ucfirst($t['modalidad'])) ?></span>
        </div>
        <div class="muted"><?= (new TorneoModel())->contarInscritos((int)$t['id']) ?> participantes</div>
        <a class="btn small primary" href="/torneo/<?= (int)$t['id'] ?>" style="margin-top:auto">Ver detalle →</a>
      </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
