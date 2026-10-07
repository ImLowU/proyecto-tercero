<?php
// =====================================================================
// INTERFAZ DE LOS MÓDULOS DE COMPETENCIA
// Cada forma de organizar un torneo (liga, eliminación directa, suizo)
// es una clase que cumple este contrato. El resto del sistema no sabe
// qué módulo está usando: le pide el calendario y lo guarda. Para sumar
// un formato nuevo (ej: doble eliminación) alcanza con otra clase que
// implemente esta interfaz y registrarla en FabricaModulos.
//
// Los módulos NO usan la base de datos: reciben datos y devuelven el
// calendario armado. Por eso se pueden probar con tests unitarios.
//
// Formato de un calendario: lista de rondas, cada una con sus partidos
// en orden (el índice del partido es su "posición" en la ronda):
//   [ ['nombre' => 'Fecha 1', 'partidos' => [
//         ['local' => 12, 'visitante' => 15, 'pase_libre' => false],
//         ['local' => 18, 'visitante' => null, 'pase_libre' => true], ...]],
//     ... ]
// =====================================================================

interface ModuloCompetencia
{
    // Calendario inicial. $participantes son los id en orden de siembra.
    public function generarCalendario(array $participantes, array $configuracion): array;

    // Cuántas rondas tiene en total el torneo.
    public function totalRondas(int $cantidadParticipantes, array $configuracion): int;

    // ¿Un partido puede terminar empatado?
    public function admiteEmpates(array $configuracion): bool;
}
