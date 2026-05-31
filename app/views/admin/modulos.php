<?php
$bodyClass = 'admin-page';
require_once __DIR__ . '/../shared/header.php';
require_once __DIR__ . '/../shared/nav.php';
$modulos = [
    ['Usuarios y autenticación', 'Login, logout, roles, alta y edición de usuarios.'],
    ['Participantes y equipos', 'CRUD básico de participantes, equipos y miembros.'],
    ['Torneos', 'Creación, edición, estados, visibilidad y asignación de organizador.'],
    ['Liga', 'Generación automática todos contra todos y tabla de posiciones.'],
    ['Eliminación directa', 'Generación de llave inicial y carga de resultados.'],
    ['Sistema suizo', 'Generación de rondas por rendimiento evitando repeticiones cuando es posible.'],
    ['Resultados', 'Carga, edición y recalculo de posiciones.'],
    ['Consulta pública', 'Torneos publicados, calendario, resultados y posiciones.'],
    ['Auditoría', 'Registro de acciones relevantes del sistema.'],
];
?>
<main class="main-content">
    <div class="page-header"><h1 class="page-title">Administración del sistema</h1><p class="page-subtitle">Resumen de módulos implementados y estado funcional.</p></div>
    <div class="cards-grid">
        <?php foreach ($modulos as $m): ?>
            <article class="card"><span class="badge badge-success">Activo</span><h2 class="card-title mt-12"><?= htmlspecialchars($m[0]) ?></h2><p class="muted"><?= htmlspecialchars($m[1]) ?></p></article>
        <?php endforeach; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../shared/footer.php'; ?>
