<?php
// =====================================================================
// MODELO: JUEGO / DISCIPLINA
// =====================================================================

class Juego
{
    public static function listar(): array
    {
        return BaseDatos::filas(
            "SELECT id_juego, nombre, categoria, modalidad_sugerida FROM juegos ORDER BY categoria, nombre"
        );
    }

    public static function buscar(int $id): ?array
    {
        return BaseDatos::fila("SELECT id_juego, nombre, categoria, modalidad_sugerida FROM juegos WHERE id_juego = ?", [$id]);
    }
}
