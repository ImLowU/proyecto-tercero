<?php
// =====================================================================
// VALIDADOR DE DATOS
// Reúne las reglas de validación en un solo lugar (antes la política de
// contraseñas estaba copiada en tres archivos distintos). Junta el
// primer error de cada campo y, si hay alguno, lanza ErrorValidacion con
// todos, para que la pantalla los muestre al lado de cada campo.
//
//   (new Validador($datos))
//       ->requerido('nombre', 'El nombre')
//       ->texto('nombre', 'El nombre', 3, 150)
//       ->validar();
// =====================================================================

class Validador
{
    private array $errores = [];

    public function __construct(private array $datos)
    {
    }

    public static function problemaContrasena(string $clave): ?string
    {
        if (mb_strlen($clave) < 8) return 'La contraseña tiene que tener al menos 8 caracteres.';
        if (mb_strlen($clave) > 72) return 'La contraseña puede tener como máximo 72 caracteres.';
        if (!preg_match('/[A-Z]/', $clave)) return 'La contraseña tiene que incluir al menos una mayúscula.';
        if (!preg_match('/[0-9]/', $clave)) return 'La contraseña tiene que incluir al menos un número.';
        if (!preg_match('/[^a-zA-Z0-9]/', $clave)) return 'La contraseña tiene que incluir al menos un carácter especial (ej: !@#$%).';
        return null;
    }

    private function valor(string $campo): mixed
    {
        $valor = $this->datos[$campo] ?? null;
        return is_string($valor) ? trim($valor) : $valor;
    }

    private function vacio(string $campo): bool
    {
        $valor = $this->valor($campo);
        return $valor === null || $valor === '' || $valor === [];
    }

    private function error(string $campo, string $mensaje): self
    {
        $this->errores[$campo] ??= $mensaje;
        return $this;
    }

    public function requerido(string $campo, string $nombre): self
    {
        // Concordancia: "La fecha es obligatoria", "El nombre es obligatorio"
        $obligatorio = preg_match('/^Las? /u', $nombre) ? 'obligatoria' : 'obligatorio';
        return $this->vacio($campo) ? $this->error($campo, "$nombre es $obligatorio.") : $this;
    }

    public function texto(string $campo, string $nombre, int $minimo, int $maximo): self
    {
        if ($this->vacio($campo)) return $this;
        $largo = mb_strlen((string)$this->valor($campo));
        if ($largo < $minimo) return $this->error($campo, "$nombre tiene que tener al menos $minimo caracteres.");
        if ($largo > $maximo) return $this->error($campo, "$nombre puede tener como máximo $maximo caracteres.");
        return $this;
    }

    public function entero(string $campo, string $nombre, int $minimo, int $maximo): self
    {
        if ($this->vacio($campo)) return $this;
        $valor = filter_var($this->valor($campo), FILTER_VALIDATE_INT);
        if ($valor === false || $valor < $minimo || $valor > $maximo) {
            return $this->error($campo, "$nombre tiene que ser un número entero entre $minimo y $maximo.");
        }
        return $this;
    }

    public function decimal(string $campo, string $nombre, float $minimo, float $maximo): self
    {
        if ($this->vacio($campo)) return $this;
        $valor = filter_var($this->valor($campo), FILTER_VALIDATE_FLOAT);
        if ($valor === false || $valor < $minimo || $valor > $maximo) {
            return $this->error($campo, "$nombre tiene que ser un número entre $minimo y $maximo.");
        }
        return $this;
    }

    public function fecha(string $campo, string $nombre): self
    {
        if ($this->vacio($campo)) return $this;
        $fecha = DateTime::createFromFormat('!Y-m-d', (string)$this->valor($campo));
        if (!$fecha || $fecha->format('Y-m-d') !== $this->valor($campo)) {
            return $this->error($campo, "$nombre no es una fecha válida.");
        }
        return $this;
    }

    // Acepta "2026-10-17 19:00" o el formato de <input type="datetime-local"> "2026-10-17T19:00".
    public function fechaHora(string $campo, string $nombre): self
    {
        if ($this->vacio($campo)) return $this;
        if (self::normalizarFechaHora((string)$this->valor($campo)) === null) {
            return $this->error($campo, "$nombre no es una fecha y hora válida.");
        }
        return $this;
    }

    public static function normalizarFechaHora(string $valor): ?string
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s'] as $formato) {
            $fecha = DateTime::createFromFormat("!$formato", $valor);
            if ($fecha && $fecha->format($formato) === $valor) {
                return $fecha->format('Y-m-d H:i:s');
            }
        }
        return null;
    }

    public function enLista(string $campo, string $nombre, array $opciones): self
    {
        if ($this->vacio($campo)) return $this;
        return in_array($this->valor($campo), $opciones, true) ? $this : $this->error($campo, "$nombre no es una opción válida.");
    }

    public function correo(string $campo): self
    {
        if ($this->vacio($campo)) return $this;
        $valor = (string)$this->valor($campo);
        if (mb_strlen($valor) > 100 || !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            return $this->error($campo, 'Ingresá un correo válido (ej: nombre@correo.com).');
        }
        return $this;
    }

    public function nombreUsuario(string $campo): self
    {
        if ($this->vacio($campo)) return $this;
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', (string)$this->valor($campo))) {
            return $this->error($campo, 'El nombre de usuario tiene que tener de 3 a 50 caracteres: letras, números o guion bajo.');
        }
        return $this;
    }

    public function contrasena(string $campo): self
    {
        if ($this->vacio($campo)) return $this;
        $problema = self::problemaContrasena((string)$this->datos[$campo]);
        return $problema ? $this->error($campo, $problema) : $this;
    }

    // Para reglas propias: ->regla($fin >= $inicio, 'endDate', 'La fecha de fin...')
    public function regla(bool $cumple, string $campo, string $mensaje): self
    {
        return $cumple ? $this : $this->error($campo, $mensaje);
    }

    public function errores(): array
    {
        return $this->errores;
    }

    public function validar(): void
    {
        if ($this->errores) {
            throw new ErrorValidacion($this->errores);
        }
    }
}
