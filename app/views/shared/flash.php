<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['tipo']) ?>">
        <span class="alert-icon"><?= $flash['tipo'] === 'success' ? '✓' : '!' ?></span>
        <span><?= htmlspecialchars($flash['mensaje']) ?></span>
    </div>
<?php endif; ?>
