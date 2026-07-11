<?php $user = Auth::user(); ?>
<header class="topbar">
  <div>
    <div class="eyebrow"><?= APP_NAME ?></div>
    <strong>
      <?php if (Auth::isAdmin()): ?>Panel de administración
      <?php elseif (Auth::isOrganizador()): ?>Panel del organizador
      <?php else: ?>Panel del participante
      <?php endif; ?>
    </strong>
  </div>
  <div class="actions">
    <?php if ($user): ?>
      <span class="inline-flex">
        <?= View::avatar($user['nombre'], $user['avatar_url'] ?? null, 34) ?>
        <span class="text-sm fw-700 nowrap"><?= View::e($user['nombre']) ?></span>
      </span>
      <span class="chip"><?= View::e(ucfirst($user['rol'])) ?></span>
    <?php endif; ?>
    <a class="btn small" href="/logout">Cerrar sesión</a>
    <button class="btn ghost mobile-menu-btn" data-sidebar-toggle aria-label="Abrir menú">☰</button>
  </div>
</header>
