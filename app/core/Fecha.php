<?php
// =====================================================================
// FECHAS EN LOS MENSAJES
// La base guarda "2026-10-06" o "2026-10-06 20:00:00"; en los mensajes
// para las personas se muestran como "06/10/2026" y "20:00".
// =====================================================================

class Fecha
{
    public static function legible(string $fecha): string
    {
        return date('d/m/Y', strtotime($fecha));
    }

    public static function hora(string $fechaHora): string
    {
        return date('H:i', strtotime($fechaHora));
    }
}
