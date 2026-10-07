<?php
// =====================================================================
// MODELO: CONFIGURACIÓN GENERAL DEL SISTEMA
// Valores que el administrador cambia desde su panel. Se leen una vez
// por petición y se guardan en memoria.
// =====================================================================

class ConfiguracionSistema
{
    private static ?array $cache = null;

    public static function todas(): array
    {
        return BaseDatos::filas("SELECT clave, valor, tipo, descripcion FROM configuracion_sistema ORDER BY clave");
    }

    public static function obtener(string $clave, ?string $defecto = null): ?string
    {
        if (self::$cache === null) {
            self::$cache = array_column(self::todas(), 'valor', 'clave');
        }
        return self::$cache[$clave] ?? $defecto;
    }

    public static function entero(string $clave, int $defecto): int
    {
        return (int)self::obtener($clave, (string)$defecto);
    }

    public static function booleano(string $clave, bool $defecto): bool
    {
        return self::obtener($clave, $defecto ? '1' : '0') === '1';
    }

    // Valida el valor según el tipo de la clave y lo guarda.
    public static function actualizar(string $clave, mixed $valor): void
    {
        $tipo = BaseDatos::valor("SELECT tipo FROM configuracion_sistema WHERE clave = ?", [$clave]);
        if ($tipo === null) {
            throw ErrorValidacion::campo($clave, 'Esa opción de configuración no existe.');
        }
        $texto = is_bool($valor) ? ($valor ? '1' : '0') : trim((string)$valor);
        $valido = match ($tipo) {
            'numero'   => filter_var($texto, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) !== false,
            'booleano' => in_array($texto, ['0', '1'], true),
            default    => $texto !== '' && mb_strlen($texto) <= 255,
        };
        if (!$valido) {
            $mensaje = match ($tipo) {
                'numero'   => 'Tiene que ser un número entero mayor que 0.',
                'booleano' => 'Tiene que ser sí o no.',
                default    => 'No puede quedar vacío ni superar los 255 caracteres.',
            };
            throw ErrorValidacion::campo($clave, $mensaje);
        }
        BaseDatos::ejecutar("UPDATE configuracion_sistema SET valor = ? WHERE clave = ?", [$texto, $clave]);
        self::$cache = null;
    }

    public static function olvidarCache(): void
    {
        self::$cache = null;
    }
}
