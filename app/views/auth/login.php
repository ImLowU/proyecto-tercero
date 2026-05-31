<?php
$pageTitle = 'Iniciar sesión';
$bodyClass = 'auth-page';
require_once __DIR__ . '/../shared/header.php';
?>

<main class="auth-main">
    <div class="auth-card">

        <!-- Logo / identidad -->
        <div class="auth-brand">
            <img src="<?= BASE_URL ?>/assets/img/flexarena-logo.svg" alt="FlexArena" class="brand-logo-auth">
            <h1 class="auth-title">FlexArena</h1>
            <p class="auth-subtitle">Sistema de Gestión Deportiva Modular</p>
        </div>

        <!-- Mensaje de error (si existe) -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert">
                <span class="alert-icon">⚠</span>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Formulario de login -->
        <form
            action="<?= BASE_URL ?>/login"
            method="POST"
            class="auth-form"
            novalidate
            id="loginForm"
        >
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    placeholder="tu@email.com"
                    autocomplete="email"
                    required
                    maxlength="150"
                >
                <span class="form-error" id="emailError"></span>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="Tu contraseña"
                        autocomplete="current-password"
                        required
                        maxlength="100"
                    >
                    <button
                        type="button"
                        class="toggle-password"
                        aria-label="Mostrar contraseña"
                        id="togglePass"
                    >Ver</button>
                </div>
                <span class="form-error" id="passError"></span>
            </div>

            <button type="submit" class="btn btn-primary btn-full" id="submitBtn">
                Ingresar al sistema
            </button>
        </form>

        <!-- Enlace público -->
        <div class="auth-footer">
            <a href="<?= BASE_URL ?>/torneos" class="link-secondary">
                Ver torneos sin iniciar sesión →
            </a>
        </div>

    </div>
</main>

<script>
// Validación básica en frontend antes de enviar
document.getElementById('loginForm').addEventListener('submit', function(e) {
    let valid = true;

    const email    = document.getElementById('email');
    const password = document.getElementById('password');
    const emailErr = document.getElementById('emailError');
    const passErr  = document.getElementById('passError');

    emailErr.textContent = '';
    passErr.textContent  = '';

    if (!email.value.trim()) {
        emailErr.textContent = 'El email es obligatorio.';
        valid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
        emailErr.textContent = 'Ingresá un email válido.';
        valid = false;
    }

    if (!password.value) {
        passErr.textContent = 'La contraseña es obligatoria.';
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
    }
});

// Mostrar / ocultar contraseña
document.getElementById('togglePass').addEventListener('click', function() {
    const input = document.getElementById('password');
    const mostrar = input.type === 'password';
    input.type = mostrar ? 'text' : 'password';
    this.textContent = mostrar ? 'Ocultar' : 'Ver';
    this.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
});
</script>

<?php require_once __DIR__ . '/../shared/footer.php'; ?>
