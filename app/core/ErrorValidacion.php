<?php
// =====================================================================
// ERROR DE VALIDACIÓN (HTTP 422)
// Datos de un formulario que no pasan la validación. Lleva el error de
// cada campo para que la pantalla lo muestre al lado del campo, en vez de
// un único mensaje general.
// =====================================================================

class ErrorValidacion extends ErrorHttp
{
    public function __construct(public array $errores, string $mensaje = 'Revisá los datos marcados.')
    {
        parent::__construct($mensaje, 422, ['errores' => $errores]);
    }

    // Atajo para un solo campo.
    public static function campo(string $campo, string $mensaje): self
    {
        return new self([$campo => $mensaje], $mensaje);
    }
}
