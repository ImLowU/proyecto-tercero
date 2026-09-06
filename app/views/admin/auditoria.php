<section class="page-header">
  <div class="page-title">
    <div class="eyebrow">Sistema</div>
    <h1>Auditoría</h1>
    <p class="muted">Historial de acciones administrativas — <?= (int)$total ?> registros.</p>
  </div>
</section>

<div class="table-wrap">
  <div class="table-scroll">
    <table>
      <thead>
        <tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Tabla</th><th>Descripción</th><th>IP</th></tr>
      </thead>
      <tbody>
        <?php if (empty($registros)): ?>
          <tr><td colspan="6">
            <div class="empty-state">
              <div class="empty-icon">📋</div>
              <h3>Sin actividad</h3>
              <p>Todavía no hay acciones administrativas registradas.</p>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($registros as $r): ?>
          <tr>
            <td style="white-space:nowrap"><?= View::e($r['created_at']) ?></td>
            <td><?= View::e($r['usuario_nombre'] ?? 'Sistema') ?></td>
            <td><span class="chip"><?= View::e($r['accion']) ?></span></td>
            <td class="muted"><?= View::e($r['tabla_afectada'] ?? '—') ?></td>
            <td><?= View::e($r['descripcion'] ?? '—') ?></td>
            <td class="muted"><?= View::e($r['ip'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php $totalPages = (int)ceil($total / $limit); ?>
<?php if ($totalPages > 1): ?>
<nav class="pagination" aria-label="Paginación de auditoría">
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a class="<?= $i === $page ? 'current' : '' ?>" href="/admin/auditoria?page=<?= $i ?>"><?= $i ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>
