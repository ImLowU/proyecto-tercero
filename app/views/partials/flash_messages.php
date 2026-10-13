<?php
// $_flash es inyectado por BaseController::render()
$_flash = isset($_flash) && is_array($_flash) ? $_flash : [];
$_tipos = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
$_icos  = ['success' => '✓', 'error' => '⚠', 'warning' => '⚠', 'info' => 'ℹ'];
$_msgs  = array_filter(array_keys($_tipos), fn($k) => !empty($_flash[$k]));
?>
<?php if ($_msgs): ?>
<div class="flash-stack">
  <?php foreach ($_msgs as $key): ?>
    <?php /* Los errores se anuncian a los lectores de pantalla como alerta; el resto, como estado. */ ?>
    <div class="flash-msg alert <?= $_tipos[$key] ?>" role="<?= $key === 'error' ? 'alert' : 'status' ?>">
      <span class="alert-ico" aria-hidden="true"><?= $_icos[$key] ?></span>
      <span><?= View::e($_flash[$key]) ?></span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
