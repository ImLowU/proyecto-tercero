<?php
// =====================================================================
// MODELO: TOKENS DE USUARIO (recuperar contraseña y "recordarme")
// El token que recibe el usuario tiene dos partes "selector:validador".
// En la base se guarda el selector tal cual (para buscarlo) y solo el
// hash SHA-256 del validador. Así, aunque alguien lea la tabla, no puede
// armar un token válido. La comparación usa hash_equals (tiempo constante).
// =====================================================================

class TokenUsuario
{
    // Crea un token y devuelve [selector, validador] para mandar al usuario.
    public static function crear(int $idUsuario, string $tipo, int $minutos): array
    {
        $selector = bin2hex(random_bytes(12));   // 24 caracteres
        $validador = bin2hex(random_bytes(32));  // 64 caracteres
        BaseDatos::insertar(
            "INSERT INTO tokens_usuario (id_usuario, tipo, selector, hash_validador, vence)
             VALUES (?, ?, ?, ?, NOW() + INTERVAL ? MINUTE)",
            [$idUsuario, $tipo, $selector, hash('sha256', $validador), $minutos]
        );
        return [$selector, $validador];
    }

    // Devuelve el token si existe, no venció y el validador es correcto.
    public static function validar(string $selector, string $validador, string $tipo): ?array
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validador)) {
            return null;
        }
        $token = BaseDatos::fila(
            "SELECT id_token, id_usuario, hash_validador FROM tokens_usuario
             WHERE selector = ? AND tipo = ? AND vence > NOW()",
            [$selector, $tipo]
        );
        if (!$token || !hash_equals($token['hash_validador'], hash('sha256', $validador))) {
            return null;
        }
        return $token;
    }

    public static function eliminar(int $idToken): void
    {
        BaseDatos::ejecutar("DELETE FROM tokens_usuario WHERE id_token = ?", [$idToken]);
    }

    public static function eliminarPorSelector(string $selector): void
    {
        BaseDatos::ejecutar("DELETE FROM tokens_usuario WHERE selector = ?", [$selector]);
    }

    public static function eliminarDeUsuario(int $idUsuario, string $tipo): void
    {
        BaseDatos::ejecutar("DELETE FROM tokens_usuario WHERE id_usuario = ? AND tipo = ?", [$idUsuario, $tipo]);
    }
}
