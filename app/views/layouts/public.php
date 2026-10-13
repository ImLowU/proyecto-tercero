<!DOCTYPE html>
<html lang="es">
<?php require APP_PATH . '/views/partials/head.php'; ?>
<body data-page="<?= isset($bodyPage) ? View::e($bodyPage) : '' ?>">
<a class="saltar" href="#contenido">Saltar al contenido</a>

<div class="public-shell">
  <?php require APP_PATH . '/views/partials/navbar_public.php'; ?>
  <main id="contenido" tabindex="-1">
    <?php require APP_PATH . '/views/partials/flash_messages.php'; ?>
    <?= $content ?>
  </main>
  <?php require APP_PATH . '/views/partials/footer.php'; ?>
</div>

<script src="<?= View::e(View::asset('/assets/js/app.js')) ?>"></script>
<?php if (isset($extraJs)): foreach ($extraJs as $js): ?>
  <script src="<?= View::e(View::asset('/assets/js/' . $js)) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
