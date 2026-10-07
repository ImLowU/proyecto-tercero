<?php
// =====================================================================
// ERRORES CON CÓDIGO HTTP
// Los controladores y modelos "lanzan" estos errores cuando algo no se
// puede hacer; la Aplicación los atrapa y los convierte en una respuesta
// JSON con el código y el mensaje. Así no hay que repetir en cada lugar
// el "responder y cortar la ejecución" de la versión anterior.
// =====================================================================

class ErrorHttp extends RuntimeException
{
    public function __construct(string $mensaje, public int $estado = 400, public array $extra = [])
    {
        parent::__construct($mensaje, $estado);
    }

    public static function noAutenticado(string $mensaje = 'Tenés que iniciar sesión para hacer esto.'): self
    {
        return new self($mensaje, 401);
    }

    public static function prohibido(string $mensaje = 'No tenés permiso para hacer esto.'): self
    {
        return new self($mensaje, 403);
    }

    public static function noEncontrado(string $mensaje = 'No se encontró lo que buscabas.'): self
    {
        return new self($mensaje, 404);
    }

    public static function conflicto(string $mensaje): self
    {
        return new self($mensaje, 409);
    }
}
