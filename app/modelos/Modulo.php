<?php
// =====================================================================
// MODELO: MÓDULO DE COMPETENCIA (liga, eliminación directa, suizo)
// =====================================================================

class Modulo
{
    public static function listar(bool $soloHabilitados = false): array
    {
        $donde = $soloHabilitados ? 'WHERE m.habilitado = 1' : '';
        return BaseDatos::filas(
            "SELECT m.id_modulo, m.codigo, m.nombre, m.descripcion, m.habilitado,
                    (SELECT COUNT(*) FROM torneos t WHERE t.id_modulo = m.id_modulo) AS torneos
             FROM modulos_competencia m $donde ORDER BY m.id_modulo"
        );
    }

    public static function buscar(int $id): ?array
    {
        return BaseDatos::fila("SELECT id_modulo, codigo, nombre, habilitado FROM modulos_competencia WHERE id_modulo = ?", [$id]);
    }

    public static function buscarPorCodigo(string $codigo): ?array
    {
        return BaseDatos::fila("SELECT id_modulo, codigo, nombre, habilitado FROM modulos_competencia WHERE codigo = ?", [$codigo]);
    }

    public static function cambiarHabilitado(int $id, bool $habilitado): void
    {
        BaseDatos::ejecutar("UPDATE modulos_competencia SET habilitado = ? WHERE id_modulo = ?", [$habilitado ? 1 : 0, $id]);
    }
}
