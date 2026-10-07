<?php
// =====================================================================
// FÁBRICA DE MÓDULOS
// Devuelve el objeto del módulo según el código guardado en la base.
// =====================================================================

class FabricaModulos
{
    public static function crear(string $codigo): ModuloCompetencia
    {
        return match ($codigo) {
            'liga'                => new ModuloLiga(),
            'eliminacion_directa' => new ModuloEliminacionDirecta(),
            'sistema_suizo'       => new ModuloSuizo(),
            default               => throw new InvalidArgumentException("Módulo desconocido: $codigo"),
        };
    }
}
