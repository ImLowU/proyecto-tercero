<?php
// =====================================================================
// PROTECCIÓN CSRF (Cross-Site Request Forgery)
// Sin esto, una página maliciosa podría hacer que el navegador de un
// usuario logueado mande una petición a NextLeague (por ejemplo, borrar
// un torneo) usando su sesión. Cada sesión tiene un token secreto que la
// página propia conoce (lo pide a /api/auth/sesion) y manda en la cabecera
// X-CSRF-Token. Una página ajena no puede leerlo, así que sus peticiones
// se rechazan con 403.
// =====================================================================

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function valido(Peticion $peticion): bool
    {
        $enviado = $peticion->cabecera('X-CSRF-Token') ?? $peticion->dato('_csrf');
        return !empty($_SESSION['csrf'])
            && is_string($enviado)
            && hash_equals($_SESSION['csrf'], $enviado);
    }
}
