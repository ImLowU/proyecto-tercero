<section class="page-header">
  <div class="page-title">
    <div class="eyebrow">Administración</div>
    <h1>Panel general</h1>
    <p>Resumen operativo del sistema <?= APP_NAME ?>.</p>
  </div>
  <a class="btn primary" href="/admin/torneos/crear">+ Nuevo torneo</a>
</section>

<div class="kpi-row">
  <div class="stat-card"><strong><?= (int)$totalTorneos ?></strong><span>Torneos</span></div>
  <div class="stat-card accent"><strong><?= (int)$totalParticipantes ?></strong><span>Participantes</span></div>
  <div class="stat-card"><strong><?= (int)$totalEquipos ?></strong><span>Equipos</span></div>
  <div class="stat-card"><strong><?= count($actividad) ?></strong><span>Acciones recientes</span></div>
</div>

<!-- Accesos rápidos: acciones primarias visibles de un vistazo -->
<div class="card mb-4">
  <h3 class="mb-3">Accesos rápidos</h3>
  <div class="cluster">
    <a class="btn primary" href="/admin/torneos/crear">+ Nuevo torneo</a>
    <a class="btn" href="/admin/equipos/crear">+ Nuevo equipo</a>
    <a class="btn" href="/admin/usuarios/crear">+ Nuevo organizador</a>
    <a class="btn" href="/admin/registros">Registros de participantes</a>
  </div>
</div>

<!-- Actividad reciente a ancho completo (más legible que media columna) -->
<article class="card">
  <div class="between mb-3">
    <h3 class="mb-0">Actividad reciente</h3>
    <a class="btn small" href="/admin/auditoria">Ver auditoría completa</a>
  </div>
  <div class="timeline">
    <?php if (empty($actividad)): ?>
      <div class="empty-state">
        <div class="empty-icon">📋</div>
        <h3>Sin actividad</h3>
        <p>Las acciones administrativas aparecerán acá.</p>
      </div>
    <?php else: ?>
      <?php foreach (array_slice($actividad, 0, 10) as $log): ?>
        <div class="timeline-item">
          <strong><?= View::e($log['accion']) ?></strong>
          <?php if ($log['descripcion']): ?>
            <div class="muted"><?= View::e($log['descripcion']) ?></div>
          <?php endif; ?>
          <div class="text-dim text-xs mt-1"><?= View::e($log['created_at']) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</article>
