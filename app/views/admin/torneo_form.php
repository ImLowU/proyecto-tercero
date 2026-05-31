<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
$esEditar = ($modo ?? 'crear') === 'editar';
$accion = $esEditar ? BASE_URL . '/admin/torneos/editar' : BASE_URL . '/admin/torneos/nuevo';
$titulo = $esEditar ? 'Editar torneo' : 'Nuevo torneo';
$boton = $esEditar ? 'Guardar cambios' : 'Crear torneo';
$estadoTexto = ['borrador'=>'Borrador','inscripcion'=>'Inscripción abierta','en_curso'=>'En curso','finalizado'=>'Finalizado'];
if (!function_exists('v')) { function v(array $a, string $k, string $d = ''): string { return htmlspecialchars((string)($a[$k] ?? $d)); } }
if (!function_exists('err')) { function err(array $e, string $k): string { return !empty($e[$k]) ? '<span class="form-error">'.htmlspecialchars($e[$k]).'</span>' : '<span class="form-error"></span>'; } }
?>
<main class="main-content narrow-content">
    <a href="<?= BASE_URL ?>/admin/torneos" class="back-link">← Volver a torneos</a>
    <div class="page-header"><h1 class="page-title"><?= $titulo ?></h1><p class="page-subtitle">Elegí el formato competitivo. Luego podrás inscribir participantes y generar rondas.</p></div>
    <form action="<?= $accion ?>" method="POST" class="card form-card" id="torneoForm" novalidate>
        <?php if ($esEditar): ?><input type="hidden" name="id" value="<?= (int)($torneo['id'] ?? 0) ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="form-group form-group-full">
                <label class="form-label" for="nombre">Nombre del torneo *</label>
                <input class="form-input" id="nombre" name="nombre" value="<?= v($torneo, 'nombre') ?>" required minlength="3">
                <?= err($errores, 'nombre') ?>
            </div>
            <div class="form-group form-group-full">
                <label class="form-label" for="descripcion">Descripción</label>
                <textarea class="form-input" id="descripcion" name="descripcion"><?= v($torneo, 'descripcion') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="tipo_torneo_id">Formato *</label>
                <select class="form-input" id="tipo_torneo_id" name="tipo_torneo_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($tiposTorneo as $tipo): ?>
                        <option value="<?= (int)$tipo['id'] ?>" <?= (int)($torneo['tipo_torneo_id'] ?? 0) === (int)$tipo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tipo['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= err($errores, 'tipo_torneo_id') ?>
            </div>
            <div class="form-group">
                <label class="form-label" for="organizador_id">Organizador *</label>
                <select class="form-input" id="organizador_id" name="organizador_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($organizadores as $org): ?>
                        <option value="<?= (int)$org['id'] ?>" <?= (int)($torneo['organizador_id'] ?? $_SESSION['usuario']['id']) === (int)$org['id'] ? 'selected' : '' ?>><?= htmlspecialchars($org['nombre'].' - '.$org['rol_nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= err($errores, 'organizador_id') ?>
            </div>
            <div class="form-group">
                <label class="form-label" for="estado">Estado</label>
                <select class="form-input" id="estado" name="estado">
                    <?php foreach ($estadoTexto as $valor => $texto): ?>
                        <option value="<?= $valor ?>" <?= ($torneo['estado'] ?? 'borrador') === $valor ? 'selected' : '' ?>><?= $texto ?></option>
                    <?php endforeach; ?>
                </select>
                <?= err($errores, 'estado') ?>
            </div>
            <div class="form-group">
                <label class="form-label" for="publico">Visibilidad</label>
                <select class="form-input" id="publico" name="publico">
                    <option value="1" <?= (int)($torneo['publico'] ?? 1) === 1 ? 'selected' : '' ?>>Público</option>
                    <option value="0" <?= (int)($torneo['publico'] ?? 1) === 0 ? 'selected' : '' ?>>Oculto</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="fecha_inicio">Fecha inicio</label>
                <input class="form-input" type="date" id="fecha_inicio" name="fecha_inicio" value="<?= v($torneo, 'fecha_inicio') ?>">
                <?= err($errores, 'fecha_inicio') ?>
            </div>
            <div class="form-group">
                <label class="form-label" for="fecha_fin">Fecha fin</label>
                <input class="form-input" type="date" id="fecha_fin" name="fecha_fin" value="<?= v($torneo, 'fecha_fin') ?>">
                <?= err($errores, 'fecha_fin') ?>
            </div>
        </div>
        <div class="form-actions"><a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/torneos">Cancelar</a><button class="btn btn-primary"><?= $boton ?></button></div>
    </form>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
