<?php
// =====================================================================
// CONFIGURACIÓN
// Lee config.php (o config.ejemplo.php si no existe) una sola vez y
// permite pedir valores con puntos: Config::obtener('bd.nombre').
// =====================================================================

class Config
{
    private static ?array $valores = null;

    public static function obtener(string $clave, mixed $defecto = null): mixed
    {
        $valor = self::todos();
        foreach (explode('.', $clave) as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return $defecto;
            }
            $valor = $valor[$parte];
        }
        return $valor;
    }

    public static function esDesarrollo(): bool
    {
        return self::obtener('entorno') === 'desarrollo';
    }

    // Los tests usan esto para apuntar a la base de pruebas.
    public static function establecer(array $valores): void
    {
        self::$valores = array_replace_recursive(self::todos(), $valores);
    }

    private static function todos(): array
    {
        if (self::$valores === null) {
            $raiz = dirname(__DIR__, 2);
            $archivo = is_file("$raiz/config.php") ? "$raiz/config.php" : "$raiz/config.ejemplo.php";
            self::$valores = require $archivo;
        }
        return self::$valores;
    }
}
