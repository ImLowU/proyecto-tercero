<section class="section" style="min-height:68vh;display:grid;place-items:center;text-align:center">
  <div class="stack" style="max-width:440px;justify-items:center">
    <span class="logo-mark" style="width:56px;height:56px"><img src="/assets/img/sporttime-logo.svg" alt="<?= APP_NAME ?>" style="width:34px;height:34px"></span>
    <div class="eyebrow text-danger">Error 403</div>
    <h1 class="mb-0">Sin permisos</h1>
    <p class="mb-0">No tenés permisos para acceder a esta sección.</p>
    <div class="actions mt-2" style="justify-content:center">
      <?php if (Auth::isLoggedIn()): ?>
        <a class="btn primary" href="<?= Auth::isAdmin() ? '/admin' : (Auth::isOrganizador() ? '/organizador' : '/participante') ?>">Ir al panel</a>
      <?php endif; ?>
      <a class="btn" href="/">Ir al inicio</a>
    </div>
  </div>
</section>
