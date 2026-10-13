<section class="section pagina-estado">
  <div class="stack pagina-estado-contenido">
    <span class="logo-mark logo-mark-56"><img src="/assets/img/sporttime-logo.svg" class="logo-img-34" alt="<?= View::e($_sitio["nombre"]) ?>"></span>
    <div class="eyebrow text-danger">Error 403</div>
    <h1 class="mb-0">Sin permisos</h1>
    <p class="mb-0"><?= View::e($mensaje ?? 'No tenés permisos para acceder a esta sección.') ?></p>
    <div class="actions mt-2 justify-center">
      <?php if (Auth::isLoggedIn()): ?>
        <a class="btn primary" href="<?= Auth::isAdmin() ? '/admin' : (Auth::isOrganizador() ? '/organizador' : '/participante') ?>">Ir al panel</a>
      <?php endif; ?>
      <a class="btn" href="/">Ir al inicio</a>
    </div>
  </div>
</section>
