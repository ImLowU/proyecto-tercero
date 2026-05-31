<?php
$bodyClass = 'public-page';
require_once __DIR__ . '/../shared/header.php';
?>
<header class="public-mini-header">
    <?php require __DIR__ . "/../shared/public_nav.php"; ?>
</header>
<main class="main-content">
    <a href="<?= BASE_URL ?>/torneos" class="back-link">← Volver a torneos</a>
    <div class="page-header">
        <h1 class="page-title"><?= htmlspecialchars($torneo['nombre']) ?></h1>
        <p class="page-subtitle"><?= htmlspecialchars($torneo['tipo_nombre']) ?> · <?= htmlspecialchars($torneo['estado']) ?> · Organizador: <?= htmlspecialchars($torneo['organizador_nombre']) ?></p>
    </div>
    <section class="card"><h2 class="card-title">Descripción</h2><p><?= nl2br(htmlspecialchars($torneo['descripcion'] ?? 'Sin descripción.')) ?></p></section>

    <section class="card mt-24"><h2 class="card-title">Participantes</h2><div class="pill-list"><?php foreach ($inscriptos as $p): ?><span class="pill"><?= htmlspecialchars($p['nombre']) ?></span><?php endforeach; ?></div></section>

    <section class="card mt-24">
        <h2 class="card-title">Calendario y resultados</h2>
        <div class="table-responsive"><table class="data-table"><thead><tr><th>Ronda</th><th>Partido</th><th>Resultado</th><th>Estado</th></tr></thead><tbody>
        <?php foreach ($enfrentamientos as $e): ?>
            <tr><td><?= htmlspecialchars($e['ronda_nombre'] ?: 'Ronda '.$e['ronda_numero']) ?></td><td><?= htmlspecialchars($e['participante_a_nombre']) ?> vs <?= htmlspecialchars($e['participante_b_nombre'] ?? 'Descanso') ?></td><td><?= $e['puntos_a'] !== null ? (int)$e['puntos_a'].' - '.(int)$e['puntos_b'] : 'Pendiente' ?></td><td><?= htmlspecialchars($e['estado']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>

    <section class="card mt-24">
        <h2 class="card-title">Tabla de posiciones</h2>
        <div class="table-responsive"><table class="data-table"><thead><tr><th>#</th><th>Participante</th><th>Pts</th><th>PJ</th><th>G</th><th>E</th><th>P</th><th>PF</th><th>PC</th></tr></thead><tbody>
        <?php foreach ($tabla as $i => $pos): ?>
            <tr><td><?= $i+1 ?></td><td><?= htmlspecialchars($pos['participante_nombre']) ?></td><td><strong><?= (int)$pos['puntos'] ?></strong></td><td><?= (int)$pos['partidos_jugados'] ?></td><td><?= (int)$pos['victorias'] ?></td><td><?= (int)$pos['empates'] ?></td><td><?= (int)$pos['derrotas'] ?></td><td><?= (int)$pos['puntos_favor'] ?></td><td><?= (int)$pos['puntos_contra'] ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
