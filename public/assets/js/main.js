// ============================================================
//  FlexArena — main.js
//  Utilidades generales del frontend
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // ----------------------------------------------------------
    // Navbar mobile: abrir/cerrar menú con el botón hamburguesa
    // ----------------------------------------------------------
    const toggle = document.getElementById('navbarToggle');
    const mobileMenu = document.getElementById('navbarMobileMenu');

    if (toggle && mobileMenu) {
        toggle.addEventListener('click', function () {
            const isOpen = mobileMenu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen);
        });
    }

    // ----------------------------------------------------------
    // Cerrar el menú mobile si se hace click fuera
    // ----------------------------------------------------------
    document.addEventListener('click', function (e) {
        if (mobileMenu && mobileMenu.classList.contains('open')) {
            if (!mobileMenu.contains(e.target) && !toggle.contains(e.target)) {
                mobileMenu.classList.remove('open');
            }
        }
    });

    // ----------------------------------------------------------
    // Marcar el nav-link activo según la URL actual
    // ----------------------------------------------------------
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-link').forEach(function (link) {
        if (link.getAttribute('href') && currentPath.startsWith(link.getAttribute('href'))) {
            link.classList.add('active');
        }
    });

    // ----------------------------------------------------------
    // Auto-cerrar alertas después de 5 segundos
    // ----------------------------------------------------------
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 400);
        }, 5000);
    });

});

// ----------------------------------------------------------
// Utilidad: confirmar antes de ejecutar acciones destructivas
// Uso: <button onclick="return confirmar('¿Eliminar?')">
// ----------------------------------------------------------
function confirmar(mensaje) {
    return window.confirm(mensaje || '¿Estás seguro de realizar esta acción?');
}
