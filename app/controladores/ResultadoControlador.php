<?php
// =====================================================================
// CONTROLADOR: RESULTADOS
// =====================================================================

class ResultadoControlador extends Controlador
{
    // POST /enfrentamientos/{id}/resultado — {puntaje_local, puntaje_visitante, observacion}
    // Si el partido ya tenía resultado, es una corrección: el anterior
    // queda guardado en el historial.
    public function cargar(): Respuesta
    {
        $partido = $this->partido();
        $torneo = $this->torneoGestionable((int)$partido['id_torneo']);

        $datos = $this->peticion->cuerpo;
        (new Validador($datos))
            ->requerido('puntaje_local', 'El resultado del local')->entero('puntaje_local', 'El resultado del local', 0, 999)
            ->requerido('puntaje_visitante', 'El resultado del visitante')->entero('puntaje_visitante', 'El resultado del visitante', 0, 999)
            ->texto('observacion', 'La observación', 0, 255)
            ->validar();

        $resultado = GestorCompetencia::registrarResultado(
            $partido, $torneo,
            (int)$datos['puntaje_local'], (int)$datos['puntaje_visitante'],
            trim((string)($datos['observacion'] ?? '')) ?: null,
            $this->esAdmin()
        );

        $mensaje = $resultado['es_correccion'] ? 'Resultado corregido.' : 'Resultado guardado.';
        if ($resultado['torneo_finalizado']) {
            $mensaje .= ' ¡Era el último partido: el torneo terminó!';
        }
        return Respuesta::exito($mensaje, $resultado);
    }

    // GET /enfrentamientos/{id}/resultados — historial de cargas y correcciones
    public function historial(): Respuesta
    {
        $partido = $this->partido();
        $this->torneoGestionable((int)$partido['id_torneo']);
        return Respuesta::exito('', ['resultados' => Resultado::historial((int)$partido['id_enfrentamiento'])]);
    }

    private function partido(): array
    {
        $partido = Enfrentamiento::buscar($this->peticion->idRuta());
        if (!$partido) {
            throw ErrorHttp::noEncontrado('Partido no encontrado.');
        }
        return $partido;
    }
}
