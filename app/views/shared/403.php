<?php
$pageTitle = 'Acceso denegado';
$bodyClass = 'error-page';
require_once __DIR__ . '/header.php';
?>
<main class="error-container">
    <div class="error-box">
        <span class="error-code">403</span>
        <h1>Acceso denegado</h1>
        <p>No tenés permiso para acceder a esta sección.</p>
        <a href="<?= BASE_URL ?>" class="btn btn-primary">Volver al inicio</a>
    </div>
</main>
<?php require_once __DIR__ . '/footer.php'; ?>
