<?php $pageTitle = 'Crear cuenta'; ?>
<!DOCTYPE html>
<html lang="es">
<?php require APP_PATH . '/views/partials/head.php'; ?>
<body>
<main class="auth-layout" id="contenido">
  <div class="auth-card auth-540">
    <a class="brand" href="/">
      <span class="logo-mark"><img src="/assets/img/sporttime-logo.svg" alt="<?= APP_NAME ?>"></span>
      <span class="brand-name"><?= APP_NAME ?></span>
    </a>

    <h1 class="como-h2 mb-1">Crear cuenta de participante</h1>
    <p class="muted mb-4">Tu cuenta quedará <strong>pendiente de aprobación</strong> hasta que un administrador la habilite.</p>

    <?php if ($msg = ($_flash['error'] ?? null)): ?>
      <div class="alert danger mb-4"><span class="alert-ico">⚠️</span><span><?= View::e($msg) ?></span></div>
    <?php endif; ?>

    <form method="POST" action="/registro" autocomplete="on" id="registroForm">
      <?= Csrf::field() ?>
      <div class="form-grid">
        <div class="field">
          <label for="nombre">Nombre completo *</label>
          <input type="text" id="nombre" name="nombre" required value="<?= View::e($old['nombre'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="nick">Nick / Alias</label>
          <input type="text" id="nick" name="nick" value="<?= View::e($old['nick'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="email">Email *</label>
          <input type="email" id="email" name="email" required autocomplete="email" value="<?= View::e($old['email'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="telefono">Teléfono</label>
          <input type="text" id="telefono" name="telefono" value="<?= View::e($old['telefono'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="password">Contraseña *</label>
          <input type="password" id="password" name="password" required autocomplete="new-password">
        </div>
        <div class="field">
          <label for="password_confirm">Confirmar contraseña *</label>
          <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
        </div>
      </div>

      <ul id="pwHints" class="text-sm pw-lista">
        <li data-rule="len">• Al menos 8 caracteres</li>
        <li data-rule="upper">• Una letra mayúscula</li>
        <li data-rule="lower">• Una letra minúscula</li>
        <li data-rule="num">• Un número</li>
        <li data-rule="sym">• Un símbolo (!@#$%&*…)</li>
        <li data-rule="match">• Las contraseñas coinciden</li>
      </ul>

      <div class="form-actions mt-4">
        <a class="btn ghost" href="/login">Ya tengo cuenta</a>
        <button type="submit" class="btn primary" id="submitBtn">Crear cuenta</button>
      </div>
    </form>
  </div>
</main>

<script src="<?= View::e(View::asset('/assets/js/registro.js')) ?>"></script>
</body>
</html>
