<?php
// =====================================================================
// CONTROLADOR: CATÁLOGOS Y DATOS PÚBLICOS GENERALES
// =====================================================================

class CatalogoControlador extends Controlador
{
    // GET /juegos
    public function juegos(): Respuesta
    {
        return Respuesta::exito('', ['juegos' => Juego::listar()]);
    }

    // GET /modulos — los habilitados (los que se ofrecen al crear un torneo)
    public function modulos(): Respuesta
    {
        return Respuesta::exito('', ['modulos' => Modulo::listar(true)]);
    }

    // GET /estadisticas — números reales para la portada
    public function estadisticas(): Respuesta
    {
        return Respuesta::exito('', ['estadisticas' => Estadisticas::generales()]);
    }
}
