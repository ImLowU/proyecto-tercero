<?php
/**
 * Registros de participantes pendientes de aprobación.
 * Variables: $pendientes (la página actual), $filtros, $pag (Paginador).
 */
$barra = [
    'accion' => '/admin/registros',
    'campos' => [
        ['nombre' => 'q', 'tipo' => 'buscar', 'etiqueta' => 'Buscar', 'placeholder' => 'Nombre, apodo o correo'],
    ],
];
$hayFiltros = array_filter($filtros, fn($v) => $v !== '' && $v !== null) !== [];
?>
<section class="page-header">
  <div class="page-title">
    <div class="eyebrow">Administración</div>
    <h1>Registros pendientes</h1>
    <p class="muted">Solicitudes de participantes que se registraron por cuenta propia.</p>
  </div>
</section>

<div class="table-wrap">
  <div class="table-header"><?php include APP_PATH . '/views/partials/filtros.php'; ?></div>
  <div class="table-scroll">
    <table aria-label="Registros pendientes de aprobación">
      <thead>
        <tr><th scope="col">Nombre</th><th scope="col">Nick</th><th scope="col">Email</th><th scope="col">Teléfono</th><th scope="col">Fecha</th><th scope="col">Acciones</th></tr>
      </thead>
      <tbody>
        <?php if (empty($pendientes)): ?>
          <tr><td colspan="6">
            <div class="empty-state">
              <div class="empty-icon" aria-hidden="true"><?= $hayFiltros ? '🔎' : '✅' ?></div>
              <h2 class="como-h3"><?= $hayFiltros ? 'Sin resultados' : 'Todo al día' ?></h2>
              <p><?= $hayFiltros ? 'Ningún registro coincide con la búsqueda.' : 'No hay solicitudes de registro pendientes de aprobación.' ?></p>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($pendientes as $u): ?>
          <tr>
            <td><strong><?= View::e($u['nombre']) ?></strong></td>
            <td><?= View::e($u['nick'] ?? '—') ?></td>
            <td><?= View::e($u['email']) ?></td>
            <td><?= View::e($u['telefono'] ?? '—') ?></td>
            <td class="muted u-nowrap"><?= View::e($u['created_at']) ?></td>
            <td>
              <form class="d-inline" method="POST" action="/admin/registros/<?= (int)$u['id'] ?>/aprobar"
                    data-confirmar="¿Aprobar a <?= View::e($u['nombre']) ?>? Podrá iniciar sesión.">
                <?= Csrf::field() ?>
                <button class="btn small primary">Aprobar</button>
              </form>
              <form class="d-inline" method="POST" action="/admin/registros/<?= (int)$u['id'] ?>/rechazar"
                    data-confirmar="¿Rechazar esta solicitud?">
                <?= Csrf::field() ?>
                <button class="btn small danger">Rechazar</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php $base = '/admin/registros'; $unidad = 'registros'; include APP_PATH . '/views/partials/paginacion.php'; ?>
</div>
