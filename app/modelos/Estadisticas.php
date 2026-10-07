<?php
// =====================================================================
// MODELO: ESTADÍSTICAS GENERALES
// Números reales para la portada y el panel de administración (antes
// la portada mostraba un "120+" escrito a mano que nunca cambiaba).
// =====================================================================

class Estadisticas
{
    public static function generales(): array
    {
        $fila = BaseDatos::fila("SELECT * FROM vista_estadisticas") ?? [];
        return array_map('intval', $fila);
    }

    // Datos extra para el resumen del administrador.
    public static function paraAdministrador(): array
    {
        return self::generales() + [
            // Los que todavía se van a jugar: sin resultado y de torneos no cancelados
            'partidos_esta_semana' => (int)BaseDatos::valor(
                "SELECT COUNT(*) FROM vista_calendario
                 WHERE estado = 'pendiente' AND estado_torneo <> 'cancelado' AND fecha_hora BETWEEN NOW() AND NOW() + INTERVAL 7 DAY"),
            'cambios_hoy' => (int)BaseDatos::valor("SELECT COUNT(*) FROM auditoria WHERE fecha >= CURDATE()"),
            'usuarios_por_rol' => BaseDatos::filas(
                "SELECT r.codigo AS rol, COUNT(u.id_usuario) AS cantidad
                 FROM roles r LEFT JOIN usuarios u ON u.id_rol = r.id_rol AND u.activo = 1
                 GROUP BY r.id_rol, r.codigo ORDER BY r.id_rol"),
            'torneos_por_estado' => BaseDatos::filas(
                "SELECT estado, COUNT(*) AS cantidad FROM torneos GROUP BY estado"),
        ];
    }
}
