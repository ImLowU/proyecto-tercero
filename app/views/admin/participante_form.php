<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
$esEditar = ($modo ?? 'crear') === 'editar';
$accion = $esEditar ? BASE_URL . '/admin/participantes/editar' : BASE_URL . '/admin/participantes/nuevo';
$titulo = $esEditar ? 'Editar participante' : 'Nuevo participante';
$boton = $esEditar ? 'Guardar cambios' : 'Crear participante';
if (!function_exists('v')) { function v(array $a, string $k, string $d = ''): string { return htmlspecialchars((string)($a[$k] ?? $d)); } }
if (!function_exists('err')) { function err(array $e, string $k): string { return !empty($e[$k]) ? '<span class="form-error">'.htmlspecialchars($e[$k]).'</span>' : '<span class="form-error"></span>'; } }
?>
<main class="main-content narrow-content">
    <a href="<?= BASE_URL ?>/admin/participantes" class="back-link">← Volver a participantes</a>
    <div class="page-header"><h1 class="page-title"><?= $titulo ?></h1><p class="page-subtitle">Podés vincularlo a un usuario participante o cargarlo manualmente.</p></div>
    <form method="POST" action="<?= $accion ?>" class="card form-card" novalidate>
        <?php if ($esEditar): ?><input type="hidden" name="id" value="<?= (int)($participante['id'] ?? 0) ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="nombre">Nombre *</label>
                <input class="form-input" id="nombre" name="nombre" value="<?= v($participante, 'nombre') ?>" required>
                <?= err($errores, 'nombre') ?>
            </div>
            <div class="form-group">
                <label class="form-label" for="tipo">Tipo *</label>
                <select class="form-input" id="tipo" name="tipo">
                    <option value="individual" <?= ($participante['tipo'] ?? 'individual') === 'individual' ? 'selected' : '' ?>>Individual</option>
                    <option value="equipo" <?= ($participante['tipo'] ?? '') === 'equipo' ? 'selected' : '' ?>>Equipo</option>
                </select>
                <?= err($errores, 'tipo') ?>
            </div>
            <div class="form-group">
                <label class="form-label" for="contacto">Contacto</label>
                <input class="form-input" id="contacto" name="contacto" value="<?= v($participante, 'contacto') ?>" placeholder="Email, Discord o teléfono">
                <?= err($errores, 'contacto') ?>
            </div>
            <div class="form-group">
                <label class="form-label" for="usuario_id">Usuario vinculado</label>
                <select class="form-input" id="usuario_id" name="usuario_id">
                    <option value="0">Sin usuario vinculado</option>
                    <?php foreach ($usuariosParticipantes as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= (int)($participante['usuario_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nombre'].' - '.$u['email']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($esEditar): ?>
            <div class="form-group">
                <label class="form-label" for="activo">Estado</label>
                <select class="form-input" id="activo" name="activo">
                    <option value="1" <?= (int)($participante['activo'] ?? 1) === 1 ? 'selected' : '' ?>>Activo</option>
                    <option value="0" <?= (int)($participante['activo'] ?? 1) === 0 ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="form-actions"><a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/participantes">Cancelar</a><button class="btn btn-primary"><?= $boton ?></button></div>
    </form>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
