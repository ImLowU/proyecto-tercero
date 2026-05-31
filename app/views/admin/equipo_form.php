<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
$esEditar = ($modo ?? 'crear') === 'editar';
$accion = $esEditar ? BASE_URL . '/admin/equipos/editar' : BASE_URL . '/admin/equipos/nuevo';
$titulo = $esEditar ? 'Editar equipo' : 'Nuevo equipo';
$boton = $esEditar ? 'Guardar cambios' : 'Crear equipo';
if (!function_exists('v')) { function v(array $a, string $k, string $d = ''): string { return htmlspecialchars((string)($a[$k] ?? $d)); } }
if (!function_exists('err')) { function err(array $e, string $k): string { return !empty($e[$k]) ? '<span class="form-error">'.htmlspecialchars($e[$k]).'</span>' : '<span class="form-error"></span>'; } }
?>
<main class="main-content narrow-content">
    <a href="<?= BASE_URL ?>/admin/equipos" class="back-link">← Volver a equipos</a>
    <div class="page-header"><h1 class="page-title"><?= $titulo ?></h1><p class="page-subtitle">Cada línea de miembros puede tener el formato: Nombre - Rol.</p></div>
    <form method="POST" action="<?= $accion ?>" class="card form-card" novalidate>
        <?php if ($esEditar): ?><input type="hidden" name="id" value="<?= (int)($equipo['id'] ?? 0) ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="nombre">Nombre del equipo *</label>
                <input class="form-input" id="nombre" name="nombre" value="<?= v($equipo, 'nombre') ?>" required>
                <?= err($errores, 'nombre') ?>
            </div>
            <div class="form-group">
                <label class="form-label" for="contacto">Contacto</label>
                <input class="form-input" id="contacto" name="contacto" value="<?= v($equipo, 'contacto') ?>" placeholder="Email, Discord o teléfono">
            </div>
            <?php if ($esEditar): ?>
            <div class="form-group">
                <label class="form-label" for="activo">Estado</label>
                <select class="form-input" id="activo" name="activo">
                    <option value="1" <?= (int)($equipo['activo'] ?? 1) === 1 ? 'selected' : '' ?>>Activo</option>
                    <option value="0" <?= (int)($equipo['activo'] ?? 1) === 0 ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group form-group-full">
                <label class="form-label" for="descripcion">Descripción</label>
                <textarea class="form-input" id="descripcion" name="descripcion"><?= v($equipo, 'descripcion') ?></textarea>
                <?= err($errores, 'descripcion') ?>
            </div>
            <div class="form-group form-group-full">
                <label class="form-label" for="miembros">Miembros del equipo</label>
                <textarea class="form-input" id="miembros" name="miembros" placeholder="Ana García - Capitán&#10;Luis Pérez - Jugador"><?= htmlspecialchars($miembrosTexto ?? '') ?></textarea>
            </div>
        </div>
        <div class="form-actions"><a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/equipos">Cancelar</a><button class="btn btn-primary"><?= $boton ?></button></div>
    </form>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
