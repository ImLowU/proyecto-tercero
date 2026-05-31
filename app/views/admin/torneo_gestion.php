<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
$esOrg = !empty($modoOrganizador);
$baseGestion = $esOrg ? '/organizador/torneos' : '/admin/torneos';
$baseResultado = $esOrg ? '/organizador/resultados' : '/admin/resultados';
?>
<main class="main-content">
    <a href="<?= BASE_URL ?><?= $baseGestion ?>" class="back-link">← Volver a torneos</a>
    <div class="page-header row-between">
        <div>
            <h1 class="page-title"><?= htmlspecialchars($torneo['nombre']) ?></h1>
            <p class="page-subtitle"><?= htmlspecialchars($torneo['tipo_nombre']) ?> · <?= htmlspecialchars($torneo['estado']) ?> · Organizador: <?= htmlspecialchars($torneo['organizador_nombre']) ?></p>
        </div>
        <a class="btn btn-secondary" href="<?= BASE_URL ?>/torneo?id=<?= (int)$torneo['id'] ?>">Ver público</a>
    </div>
    <?php require __DIR__ . '/../shared/flash.php'; ?>

    <div class="admin-grid-2">
        <section class="card">
            <h2 class="card-title">Inscribir participante</h2>
            <form method="POST" action="<?= BASE_URL ?><?= $baseGestion ?>/inscribir" class="form-inline-stack">
                <input type="hidden" name="torneo_id" value="<?= (int)$torneo['id'] ?>">
                <select name="participante_id" class="form-input" required>
                    <option value="">Seleccionar participante</option>
                    <?php foreach ($disponibles as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nombre'].' ('.$p['tipo'].')') ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary">Inscribir</button>
            </form>
        </section>

        <section class="card">
            <h2 class="card-title">Generar competencia</h2>
            <p class="muted">Liga genera todos contra todos. Eliminación directa genera la primera llave. Suizo genera una ronda por rendimiento.</p>
            <form method="POST" action="<?= BASE_URL ?><?= $baseGestion ?>/generar-rondas" class="form-inline-stack">
                <input type="hidden" name="torneo_id" value="<?= (int)$torneo['id'] ?>">
                <input type="hidden" name="modo" value="inicial">
                <button class="btn btn-primary" onclick="return confirmar('¿Generar rondas para este torneo?')">Generar rondas</button>
            </form>
            <?php if ((int)$torneo['tipo_torneo_id'] === TIPO_SUIZO): ?>
                <form method="POST" action="<?= BASE_URL ?><?= $baseGestion ?>/generar-rondas" class="form-inline-stack mt-12">
                    <input type="hidden" name="torneo_id" value="<?= (int)$torneo['id'] ?>">
                    <input type="hidden" name="modo" value="suizo_siguiente">
                    <button class="btn btn-secondary" onclick="return confirmar('¿Generar siguiente ronda suiza?')">+ Siguiente ronda suiza</button>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <section class="card mt-24">
        <h2 class="card-title">Inscriptos</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Nombre</th><th>Tipo</th><th>Acción</th></tr></thead>
                <tbody>
                <?php foreach ($inscriptos as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nombre']) ?></td>
                        <td><?= htmlspecialchars($p['tipo']) ?></td>
                        <td>
                            <form method="POST" action="<?= BASE_URL ?><?= $baseGestion ?>/quitar" class="inline-form">
                                <input type="hidden" name="torneo_id" value="<?= (int)$torneo['id'] ?>">
                                <input type="hidden" name="participante_id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn-danger btn-sm" onclick="return confirmar('¿Quitar participante del torneo?')">Quitar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card mt-24">
        <h2 class="card-title">Enfrentamientos y resultados</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Ronda</th><th>Enfrentamiento</th><th>Resultado</th><th>Cargar</th></tr></thead>
                <tbody>
                <?php foreach ($enfrentamientos as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['ronda_nombre'] ?: 'Ronda '.$e['ronda_numero']) ?></td>
                        <td><?= htmlspecialchars($e['participante_a_nombre']) ?> vs <?= htmlspecialchars($e['participante_b_nombre'] ?? 'Descanso') ?></td>
                        <td>
                            <?php if ($e['puntos_a'] !== null): ?>
                                <?= (int)$e['puntos_a'] ?> - <?= (int)$e['puntos_b'] ?>
                            <?php else: ?>
                                Pendiente
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($e['participante_b'] !== null): ?>
                            <form method="POST" action="<?= BASE_URL ?><?= $baseResultado ?>/guardar" class="result-form">
                                <input type="hidden" name="torneo_id" value="<?= (int)$torneo['id'] ?>">
                                <input type="hidden" name="enfrentamiento_id" value="<?= (int)$e['id'] ?>">
                                <input class="form-input score-input" type="number" name="puntos_a" min="0" value="<?= htmlspecialchars((string)($e['puntos_a'] ?? 0)) ?>">
                                <span>-</span>
                                <input class="form-input score-input" type="number" name="puntos_b" min="0" value="<?= htmlspecialchars((string)($e['puntos_b'] ?? 0)) ?>">
                                <input class="form-input obs-input" name="observaciones" placeholder="Obs." value="<?= htmlspecialchars((string)($e['observaciones'] ?? '')) ?>">
                                <button class="btn btn-primary btn-sm">Guardar</button>
                            </form>
                            <?php else: ?>
                                <span class="badge badge-success">Bye</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card mt-24">
        <h2 class="card-title">Tabla de posiciones</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>#</th><th>Participante</th><th>Pts</th><th>PJ</th><th>G</th><th>E</th><th>P</th><th>PF</th><th>PC</th></tr></thead>
                <tbody>
                <?php foreach ($tabla as $i => $pos): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($pos['participante_nombre']) ?></td>
                        <td><strong><?= (int)$pos['puntos'] ?></strong></td>
                        <td><?= (int)$pos['partidos_jugados'] ?></td>
                        <td><?= (int)$pos['victorias'] ?></td>
                        <td><?= (int)$pos['empates'] ?></td>
                        <td><?= (int)$pos['derrotas'] ?></td>
                        <td><?= (int)$pos['puntos_favor'] ?></td>
                        <td><?= (int)$pos['puntos_contra'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
