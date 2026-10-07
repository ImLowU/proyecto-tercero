<?php
// =====================================================================
// REGISTRO DE ERRORES (log)
// Los errores inesperados se guardan con todo su detalle en
// storage/logs/aplicacion.log; al usuario solo se le muestra un mensaje
// claro, sin rutas ni consultas SQL que le sirvan a un atacante.
// =====================================================================

class Registro
{
    public static function error(Throwable $e, ?Peticion $peticion = null): void
    {
        $carpeta = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($carpeta)) {
            @mkdir($carpeta, 0755, true);
        }
        $linea = sprintf(
            "[%s] %s %s | %s: %s en %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            $peticion?->metodo ?? '-',
            $peticion?->ruta ?? '-',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        @file_put_contents("$carpeta/aplicacion.log", $linea, FILE_APPEND);
    }
}
