<?php
// =====================================================================
// MODELO: INTENTOS DE INICIO DE SESIÓN
// Protección contra fuerza bruta (probar contraseñas una tras otra):
// después de N intentos fallidos seguidos con el mismo correo, o de
// muchos desde la misma IP, se bloquea por unos minutos.
// =====================================================================

class IntentoLogin
{
    // Una IP puede fallar más veces que un correo (puede haber varias
    // personas detrás de la misma red, ej: el liceo).
    private const MULTIPLICADOR_IP = 4;

    public static function registrar(string $correo, string $ip, bool $exitoso): void
    {
        BaseDatos::insertar(
            "INSERT INTO intentos_login (correo, ip, exitoso) VALUES (?, ?, ?)",
            [mb_strtolower(mb_substr($correo, 0, 100)), $ip, $exitoso ? 1 : 0]
        );
    }

    // Minutos que faltan para poder volver a intentar (0 = no está bloqueado).
    public static function minutosDeBloqueo(string $correo, string $ip): int
    {
        $maximo = ConfiguracionSistema::entero('max_intentos_login', 5);
        $minutos = ConfiguracionSistema::entero('minutos_bloqueo_login', 15);
        $porCorreo = self::fallidosDelCorreo($correo, $minutos);
        $porIp = BaseDatos::fila(
            "SELECT COUNT(*) AS cantidad, MAX(fecha) AS ultimo FROM intentos_login
             WHERE ip = ? AND exitoso = 0 AND fecha > NOW() - INTERVAL ? MINUTE",
            [$ip, $minutos]
        );

        foreach ([[$porCorreo, $maximo], [$porIp, $maximo * self::MULTIPLICADOR_IP]] as [$datos, $limite]) {
            if ((int)$datos['cantidad'] >= $limite) {
                $transcurridos = (time() - strtotime($datos['ultimo'])) / 60;
                return max(1, (int)ceil($minutos - $transcurridos));
            }
        }
        return 0;
    }

    // Cuántos intentos fallidos le quedan a un correo antes del bloqueo. Se
    // cuenta igual exista o no la cuenta, así el aviso no revela quién tiene una.
    public static function intentosRestantes(string $correo): int
    {
        $maximo = ConfiguracionSistema::entero('max_intentos_login', 5);
        $minutos = ConfiguracionSistema::entero('minutos_bloqueo_login', 15);
        return max(0, $maximo - (int)self::fallidosDelCorreo($correo, $minutos)['cantidad']);
    }

    // Fallidos del correo desde su último ingreso correcto, dentro de la ventana.
    private static function fallidosDelCorreo(string $correo, int $minutos): array
    {
        $correo = mb_strtolower($correo);
        return BaseDatos::fila(
            "SELECT COUNT(*) AS cantidad, MAX(fecha) AS ultimo FROM intentos_login
             WHERE correo = ? AND exitoso = 0 AND fecha > NOW() - INTERVAL ? MINUTE
               AND fecha > COALESCE((SELECT MAX(fecha) FROM intentos_login WHERE correo = ? AND exitoso = 1), '2000-01-01')",
            [$correo, $minutos, $correo]
        );
    }
}
