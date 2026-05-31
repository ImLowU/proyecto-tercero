<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
$esEditar = ($modo ?? 'crear') === 'editar';
$accion = $esEditar ? BASE_URL . '/admin/usuarios/editar' : BASE_URL . '/admin/usuarios/nuevo';
$titulo = $esEditar ? 'Editar usuario' : 'Nuevo usuario';
$boton = $esEditar ? 'Guardar cambios' : 'Crear usuario';
if (!function_exists('v')) { function v(array $a, string $k, string $d = ''): string { return htmlspecialchars((string)($a[$k] ?? $d)); } }
if (!function_exists('err')) { function err(array $e, string $k): string { return !empty($e[$k]) ? '<span class="form-error">'.htmlspecialchars($e[$k]).'</span>' : '<span class="form-error"></span>'; } }
?>
<main class="main-content narrow-content">
    <a href="<?= BASE_URL ?>/admin/usuarios" class="back-link">← Volver a usuarios</a>
    <div class="page-header"><h1 class="page-title"><?= $titulo ?></h1><p class="page-subtitle">Las contraseñas se almacenan con hash seguro.</p></div>

    <form method="POST" action="<?= $accion ?>" class="card form-card" novalidate>
        <?php if ($esEditar): ?><input type="hidden" name="id" value="<?= (int)($usuarioForm['id'] ?? 0) ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="nombre">Nombre *</label>
                <input class="form-input" id="nombre" name="nombre" value="<?= v($usuarioForm, 'nombre') ?>" required minlength="3">
                <?= err($errores, 'nombre') ?>
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email *</label>
                <input class="form-input" type="email" id="email" name="email" value="<?= v($usuarioForm, 'email') ?>" required>
                <?= err($errores, 'email') ?>
            </div>
            <div class="form-group">
                <label class="form-label" for="rol_id">Rol *</label>
                <select class="form-input" id="rol_id" name="rol_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($roles as $rol): ?>
                        <option value="<?= (int)$rol['id'] ?>" <?= (int)($usuarioForm['rol_id'] ?? 0) === (int)$rol['id'] ? 'selected' : '' ?>><?= htmlspecialchars($rol['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= err($errores, 'rol_id') ?>
            </div>
            <?php if ($esEditar): ?>
            <div class="form-group">
                <label class="form-label" for="activo">Estado</label>
                <select class="form-input" id="activo" name="activo">
                    <option value="1" <?= (int)($usuarioForm['activo'] ?? 1) === 1 ? 'selected' : '' ?>>Activo</option>
                    <option value="0" <?= (int)($usuarioForm['activo'] ?? 1) === 0 ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label class="form-label" for="password">Contraseña <?= $esEditar ? '(solo si querés cambiarla)' : '*' ?></label>
                <input class="form-input" type="password" id="password" name="password" <?= $esEditar ? '' : 'required' ?> minlength="8">
                <?= err($errores, 'password') ?>
            </div>
            <div class="form-group">
                <label class="form-label" for="password_confirm">Confirmar contraseña <?= $esEditar ? '' : '*' ?></label>
                <input class="form-input" type="password" id="password_confirm" name="password_confirm" <?= $esEditar ? '' : 'required' ?> minlength="8">
                <?= err($errores, 'password_confirm') ?>
            </div>
        </div>
        <div class="form-actions"><a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/usuarios">Cancelar</a><button class="btn btn-primary"><?= $boton ?></button></div>
    </form>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
