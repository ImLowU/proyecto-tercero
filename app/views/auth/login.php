<?php $pageTitle = 'Iniciar sesión'; ?>
<!DOCTYPE html>
<html lang="es">
<?php require APP_PATH . '/views/partials/head.php'; ?>
<body>
<main class="auth-layout" id="contenido">
  <div class="auth-card">
    <a class="brand" href="/">
      <span class="logo-mark"><img src="/assets/img/sporttime-logo.svg" alt="<?= APP_NAME ?>"></span>
      <span class="brand-name"><?= APP_NAME ?></span>
    </a>

    <h1 class="como-h2 mb-1">Iniciar sesión</h1>
    <p class="muted mb-4">Accedé al panel de gestión de torneos.</p>

    <?php if (($_GET['msg'] ?? '') === 'datos_eliminados'): ?>
      <div class="alert success mb-4"><span class="alert-ico">✓</span><span>Tus datos personales fueron eliminados correctamente (ley 18331 — Habeas Data). Tu sesión fue cerrada.</span></div>
    <?php endif; ?>
    <?php if ($msg = ($_flash['warning'] ?? null)): ?>
      <?php /* El motivo por el que se cerró la sesión (inactividad, cuenta bloqueada…) */ ?>
      <div class="chip warning aviso-auth">
        <?= View::e($msg) ?>
      </div>
    <?php endif; ?>
    <?php if ($msg = ($_flash['error'] ?? null)): ?>
      <div class="alert danger mb-4"><span class="alert-ico">⚠️</span><span><?= View::e($msg) ?></span></div>
    <?php endif; ?>
    <?php if ($msg = ($_flash['success'] ?? null)): ?>
      <div class="alert success mb-4"><span class="alert-ico">✓</span><span><?= View::e($msg) ?></span></div>
    <?php endif; ?>

    <form method="POST" action="/login" autocomplete="on">
      <?= Csrf::field() ?>
      <div class="form-grid single">
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required autocomplete="email"
                 placeholder="tu@email.com"
                 value="<?= View::e($_POST['email'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password" required autocomplete="current-password"
                 placeholder="••••••••">
        </div>
      </div>
      <div class="form-actions mt-4">
        <a class="btn ghost" href="/">Volver</a>
        <button type="submit" class="btn primary">Ingresar</button>
      </div>
    </form>

    <p class="muted text-center text-sm mt-4">
      ¿No tenés cuenta? <a href="/registro" class="text-primary fw-700">Registrate como participante</a>
    </p>
  </div>
</main>
<script src="<?= View::e(View::asset('/assets/js/app.js')) ?>"></script>
</body>
</html>
