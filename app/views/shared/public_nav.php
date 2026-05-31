<?php
$usuarioPublico = $_SESSION['usuario'] ?? null;
$rolPublico = (int) ($usuarioPublico['rol_id'] ?? 0);
$panelPublico = match ($rolPublico) {
    ROL_ADMIN => '/admin/dashboard',
    ROL_ORGANIZADOR => '/organizador/dashboard',
    ROL_PARTICIPANTE => '/participante/dashboard',
    default => '/login',
};
?>
<nav class="public-nav">
    <a class="navbar-brand" href="<?= BASE_URL ?>/" aria-label="FlexArena">
        <img src="<?= BASE_URL ?>/assets/img/flexarena-logo.svg" alt="FlexArena" class="brand-logo brand-logo-nav">
        <span class="brand-word">FlexArena</span>
    </a>
    <div>
        <a href="<?= BASE_URL ?>/torneos">Torneos</a>
        <?php if ($usuarioPublico): ?>
            <a href="<?= BASE_URL ?><?= $panelPublico ?>" class="btn btn-primary btn-sm">Panel</a>
            <a href="<?= BASE_URL ?>/logout" class="btn btn-secondary btn-sm">Salir</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/login" class="btn btn-primary btn-sm">Ingresar</a>
        <?php endif; ?>
    </div>
</nav>
