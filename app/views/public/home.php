<section class="hero">
  <div>
    <div class="eyebrow">Sistema de Gestión Deportiva Modular</div>
    <h1>Organizá torneos con estructura clara.</h1>
    <p>Plataforma web para gestionar torneos deportivos, mentales y electrónicos. Liga, Eliminación Directa y Sistema Suizo.</p>
    <div class="hero-actions">
      <a class="btn primary" href="/torneos">Ver torneos</a>
      <a class="btn" href="/login">Ingresar al panel</a>
    </div>
  </div>

  <?php if ($destacado): ?>
  <div class="hero-card card">
    <div class="eyebrow">Torneo destacado</div>
    <h2 class="como-h3"><?= View::e($destacado['nombre']) ?></h2>
    <p><?= View::e($destacado['tipo_nombre']) ?> · <?= View::estadoChip($destacado['estado']) ?></p>
    <div class="grid cols-2 mt-075">
      <div class="stat-card">
        <strong><?= (int)$destacado['inscritos'] ?></strong>
        <span>Inscritos</span>
      </div>
      <?php if ($destacado['tipo_slug'] === 'suizo' && $destacado['rondas_suizo']): ?>
      <div class="stat-card">
        <strong><?= (int)$destacado['rondas_suizo'] ?></strong>
        <span>Rondas</span>
      </div>
      <?php endif; ?>
    </div>
    <a class="btn primary mt-075 d-inline-flex" href="/torneo/<?= (int)$destacado['id'] ?>">Ver detalle</a>
  </div>
  <?php endif; ?>
</section>

<section class="section">
  <div class="section-head">
    <div>
      <div class="eyebrow">Formatos de competencia</div>
      <h2 class="mb-0">Elegí el formato que mejor se adapta a tu torneo</h2>
    </div>
  </div>
  <div class="grid cols-3">
    <article class="card">
      <span class="chip">Liga</span>
      <h3 class="mt-2">Tabla de posiciones</h3>
      <p>Todos contra todos. Puntos configurables por victoria, empate y derrota. Criterios de desempate automáticos.</p>
    </article>
    <article class="card">
      <span class="chip warning">Eliminación Directa</span>
      <h3 class="mt-2">Llave / bracket</h3>
      <p>Los ganadores avanzan ronda a ronda hasta definir un campeón. Soporta byes para cantidades no potencia de 2.</p>
    </article>
    <article class="card">
      <span class="chip success">Sistema Suizo</span>
      <h3 class="mt-2">Rondas por rendimiento</h3>
      <p>Emparejamientos basados en puntaje acumulado. Sin eliminación. Ideal para torneos con muchos participantes.</p>
    </article>
  </div>
</section>

<section class="section">
  <div class="card text-center cta-tarjeta">
    <div class="eyebrow">Empezá ahora</div>
    <h2 class="mb-2">Organizá tu próximo torneo con <?= View::e($_sitio["nombre"]) ?></h2>
    <p class="mb-4 cta-texto">Creá torneos de liga, eliminación directa o sistema suizo y seguí posiciones, brackets y resultados en un solo lugar.</p>
    <div class="actions justify-center">
      <a class="btn primary" href="/torneos">Explorar torneos</a>
      <a class="btn" href="/login">Ingresar al panel</a>
    </div>
  </div>
</section>
