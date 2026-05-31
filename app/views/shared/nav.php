<?php
$usuarioNav = $_SESSION['usuario'] ?? null;
$rolNav = (int) ($usuarioNav['rol_id'] ?? 0);
$basePanel = $rolNav === ROL_ORGANIZADOR ? '/organizador' : '/admin';
?>
<nav class="navbar">
    <div class="navbar-inner">
        <a href="<?= BASE_URL ?><?= $basePanel ?>/dashboard" class="navbar-brand" aria-label="FlexArena">
            <img src="<?= BASE_URL ?>/assets/img/flexarena-logo.svg" alt="FlexArena" class="brand-logo brand-logo-nav">
            <span class="brand-word">FlexArena</span>
        </a>
        <div class="navbar-nav">
            <a href="<?= BASE_URL ?><?= $basePanel ?>/dashboard" class="nav-link">Dashboard</a>
            <?php if ($rolNav === ROL_ADMIN): ?>
                <a href="<?= BASE_URL ?>/admin/usuarios" class="nav-link">Usuarios</a>
                <a href="<?= BASE_URL ?>/admin/participantes" class="nav-link">Participantes</a>
                <a href="<?= BASE_URL ?>/admin/equipos" class="nav-link">Equipos</a>
                <a href="<?= BASE_URL ?>/admin/torneos" class="nav-link">Torneos</a>
                <a href="<?= BASE_URL ?>/admin/auditoria" class="nav-link">Auditoría</a>
                <a href="<?= BASE_URL ?>/admin/modulos" class="nav-link">Sistema</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/organizador/torneos" class="nav-link">Mis torneos</a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/torneos" class="nav-link">Vista pública</a>
        </div>
        <div class="navbar-user">
            <span><?= htmlspecialchars($usuarioNav['nombre'] ?? '') ?></span>
            <a href="<?= BASE_URL ?>/logout" class="btn btn-secondary btn-sm">Salir</a>
        </div>
        <button class="navbar-toggle" id="navbarToggle" aria-label="Menu" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
    <div class="navbar-mobile-menu" id="navbarMobileMenu">
        <a href="<?= BASE_URL ?><?= $basePanel ?>/dashboard" class="nav-link">Dashboard</a>
        <?php if ($rolNav === ROL_ADMIN): ?>
            <a href="<?= BASE_URL ?>/admin/usuarios" class="nav-link">Usuarios</a>
            <a href="<?= BASE_URL ?>/admin/participantes" class="nav-link">Participantes</a>
            <a href="<?= BASE_URL ?>/admin/equipos" class="nav-link">Equipos</a>
            <a href="<?= BASE_URL ?>/admin/torneos" class="nav-link">Torneos</a>
            <a href="<?= BASE_URL ?>/admin/auditoria" class="nav-link">Auditoría</a>
            <a href="<?= BASE_URL ?>/admin/modulos" class="nav-link">Sistema</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/organizador/torneos" class="nav-link">Mis torneos</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/torneos" class="nav-link">Vista pública</a>
        <a href="<?= BASE_URL ?>/logout" class="nav-link">Salir</a>
    </div>
</nav>
