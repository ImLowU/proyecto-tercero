<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/flexarena-logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">
