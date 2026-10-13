<?php
/**
 * El <head> de todas las páginas. Variable opcional: $conTokenCsrf (el panel lo pide: los pedidos por fetch
 * mandan el token en una cabecera y lo leen de esta etiqueta).
 */
?>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? View::e($pageTitle) . ' · ' : '' ?><?= View::e($_sitio['nombre']) ?></title>
  <meta name="theme-color" content="#0A1018">
<?php if (!empty($conTokenCsrf)): ?>
  <meta name="csrf-token" content="<?= View::e(Csrf::generate()) ?>">
<?php endif; ?>
  <link rel="icon" type="image/svg+xml" href="/assets/img/sporttime-logo.svg">
  <link rel="preload" as="font" type="font/woff2" href="/assets/fonts/inter-400.woff2" crossorigin>
  <link rel="preload" as="font" type="font/woff2" href="/assets/fonts/inter-600.woff2" crossorigin>
<?php include APP_PATH . '/views/partials/estilos.php'; ?>
</head>
