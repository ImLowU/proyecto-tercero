<?php
// =====================================================================
// MODELO: ROL
// =====================================================================

class Rol
{
    public const ADMINISTRADOR = 'administrador';
    public const ORGANIZADOR = 'organizador';
    public const PARTICIPANTE = 'participante';

    public static function listar(): array
    {
        return BaseDatos::filas("SELECT id_rol, codigo, nombre, descripcion FROM roles ORDER BY id_rol");
    }

    public static function idPorCodigo(string $codigo): ?int
    {
        $id = BaseDatos::valor("SELECT id_rol FROM roles WHERE codigo = ?", [$codigo]);
        return $id === null ? null : (int)$id;
    }

    public static function codigos(): array
    {
        return array_column(self::listar(), 'codigo');
    }
}
