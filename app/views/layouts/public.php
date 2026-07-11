<!DOCTYPE html>
<html lang="es">
<?php require APP_PATH . '/views/partials/head.php'; ?>
<body data-page="<?= isset($bodyPage) ? View::e($bodyPage) : '' ?>">

<div class="public-shell">
  <?php require APP_PATH . '/views/partials/navbar_public.php'; ?>
  <?php require APP_PATH . '/views/partials/flash_messages.php'; ?>
  <?= $content ?>
  <?php require APP_PATH . '/views/partials/footer.php'; ?>
</div>

<script src="/assets/js/app.js"></script>
<?php if (isset($extraJs)): foreach ($extraJs as $js): ?>
  <script src="/assets/js/<?= View::e($js) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
