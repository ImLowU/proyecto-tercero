<?php
// =====================================================================
// CONTROLADOR: CALENDARIO, RONDAS Y POSICIONES
// =====================================================================

class CalendarioControlador extends Controlador
{
    // GET /torneos/{id}/calendario — rondas con sus partidos.
    // El público solo ve las rondas publicadas o cerradas; el organizador
    // y el administrador ven también los borradores.
    public function ver(): Respuesta
    {
        $torneo = $this->torneoVisible($this->peticion->idRuta());
        $id = (int)$torneo['id_torneo'];
        $gestiona = $this->gestiona($torneo);

        $partidosPorRonda = [];
        foreach (Enfrentamiento::delTorneo($id, !$gestiona) as $partido) {
            $partidosPorRonda[$partido['id_ronda']][] = $partido;
        }
        $rondas = [];
        foreach (Ronda::delTorneo($id) as $ronda) {
            if ($gestiona || $ronda['estado'] !== 'borrador') {
                $ronda['partidos'] = $partidosPorRonda[$ronda['id_ronda']] ?? [];
                $rondas[] = $ronda;
            }
        }
        $modulo = FabricaModulos::crear($torneo['modulo']);
        return Respuesta::exito('', [
            'rondas'                  => $rondas,
            'total_rondas'            => Ronda::cantidad($id) ? $modulo->totalRondas((int)$torneo['inscriptos'], $torneo['configuracion']) : null,
            'puede_generar_siguiente' => $gestiona && GestorCompetencia::puedeGenerarSiguienteRonda($torneo),
        ]);
    }

    // POST /torneos/{id}/calendario — genera el calendario (una sola vez).
    public function generar(): Respuesta
    {
        $torneo = $this->torneoGestionable($this->peticion->idRuta());
        $resultado = GestorCompetencia::generarCalendario($torneo);
        return Respuesta::exito('Calendario generado. Las rondas quedaron en borrador: publicalas para que las vea todo el mundo.', $resultado, 201);
    }

    // POST /torneos/{id}/rondas — sistema suizo: genera la ronda siguiente.
    public function siguienteRonda(): Respuesta
    {
        $torneo = $this->torneoGestionable($this->peticion->idRuta());
        $resultado = GestorCompetencia::generarSiguienteRonda($torneo);
        return Respuesta::exito("Ronda {$resultado['ronda']} generada (en borrador).", $resultado, 201);
    }

    // POST /torneos/{id}/rondas/publicar — publica todas las rondas en borrador.
    public function publicarTodas(): Respuesta
    {
        $torneo = $this->torneoGestionable($this->peticion->idRuta());
        $publicadas = 0;
        foreach (Ronda::delTorneo((int)$torneo['id_torneo']) as $ronda) {
            if ($ronda['estado'] === 'borrador') {
                Ronda::actualizar((int)$ronda['id_ronda'], ['estado' => 'publicada']);
                $publicadas++;
            }
        }
        return Respuesta::exito($publicadas ? "Se publicaron $publicadas ronda(s)." : 'No había rondas en borrador.', ['publicadas' => $publicadas]);
    }

    // PUT /rondas/{id} — {estado: borrador|publicada|cerrada, fecha: AAAA-MM-DD}
    public function actualizarRonda(): Respuesta
    {
        $ronda = Ronda::buscar($this->peticion->idRuta());
        if (!$ronda) {
            throw ErrorHttp::noEncontrado('Ronda no encontrada.');
        }
        $torneo = $this->torneoGestionable((int)$ronda['id_torneo']);
        if ($torneo['estado'] === 'cancelado') {
            throw ErrorHttp::conflicto('El torneo está cancelado.');
        }

        $datos = $this->peticion->cuerpo;
        (new Validador($datos))->enLista('estado', 'El estado', Ronda::ESTADOS)->fecha('fecha', 'La fecha')->validar();
        $cambios = [];

        if (isset($datos['estado']) && $datos['estado'] !== $ronda['estado']) {
            $nuevo = $datos['estado'];
            $this->verificarCambioDeEstado($ronda, $torneo, $nuevo);
            $cambios['estado'] = $nuevo;
        }
        if (array_key_exists('fecha', $datos)) {
            $cambios['fecha'] = $datos['fecha'] ?: null;
            if ($cambios['fecha']) {
                self::verificarDentroDelTorneo($cambios['fecha'], $torneo, 'fecha');
            }
        }
        Ronda::actualizar((int)$ronda['id_ronda'], $cambios);

        $mensajes = ['publicada' => 'Ronda publicada: ya la ve todo el mundo.', 'cerrada' => 'Ronda cerrada: sus resultados quedaron firmes.',
                     'borrador' => 'La ronda volvió a borrador: el público ya no la ve.'];
        return Respuesta::exito($mensajes[$cambios['estado'] ?? ''] ?? 'Ronda actualizada.');
    }

    // PUT /enfrentamientos/{id} — {fecha_hora, lugar}: programar un partido.
    public function programar(): Respuesta
    {
        $partido = Enfrentamiento::buscar($this->peticion->idRuta());
        if (!$partido) {
            throw ErrorHttp::noEncontrado('Partido no encontrado.');
        }
        $torneo = $this->torneoGestionable((int)$partido['id_torneo']);
        if (in_array($torneo['estado'], ['cancelado', 'finalizado'], true)) {
            throw ErrorHttp::conflicto('El torneo ya terminó o está cancelado.');
        }
        if ($partido['es_pase_libre']) {
            throw ErrorHttp::conflicto('Un pase libre no se juega: no hace falta programarlo.');
        }

        $datos = $this->peticion->cuerpo;
        (new Validador($datos))->fechaHora('fecha_hora', 'La fecha y hora')->texto('lugar', 'El lugar', 0, 100)->validar();
        $fechaHora = !empty($datos['fecha_hora']) ? Validador::normalizarFechaHora((string)$datos['fecha_hora']) : null;
        $lugar = trim((string)($datos['lugar'] ?? '')) ?: null;
        if ($fechaHora) {
            self::verificarDentroDelTorneo(substr($fechaHora, 0, 10), $torneo, 'fecha_hora');
        }

        Enfrentamiento::programar((int)$partido['id_enfrentamiento'], $fechaHora, $lugar);
        return Respuesta::exito('Partido programado.');
    }

    // GET /torneos/{id}/posiciones — en el suizo incluye el desempate Buchholz.
    public function posiciones(): Respuesta
    {
        $torneo = $this->torneoVisible($this->peticion->idRuta());
        $esSuizo = $torneo['modulo'] === 'sistema_suizo';
        return Respuesta::exito('', [
            'posiciones' => TablaPosiciones::delTorneo((int)$torneo['id_torneo'], $esSuizo),
            'buchholz'   => $esSuizo,
        ]);
    }

    // GET /partidos/proximos y /partidos/recientes — calendario público
    // (portada y página de partidos), paginado y con filtro por juego.
    public function proximos(): Respuesta
    {
        [$pagina, $porPagina] = $this->paginacion(10, 50);
        [$partidos, $total] = Enfrentamiento::proximos($this->idJuego(), $pagina, $porPagina);
        return $this->listado('partidos', $partidos, $total, $pagina, $porPagina);
    }

    public function recientes(): Respuesta
    {
        [$pagina, $porPagina] = $this->paginacion(10, 50);
        [$partidos, $total] = Enfrentamiento::recientes($this->idJuego(), $pagina, $porPagina);
        return $this->listado('partidos', $partidos, $total, $pagina, $porPagina);
    }

    // ── Auxiliares ──

    private function verificarCambioDeEstado(array $ronda, array $torneo, string $nuevo): void
    {
        $actual = $ronda['estado'];
        $idRonda = (int)$ronda['id_ronda'];

        if ($nuevo === 'cerrada') {
            if ($actual !== 'publicada') {
                throw ErrorHttp::conflicto('Solo se puede cerrar una ronda publicada.');
            }
            if (Ronda::partidosSinTerminar($idRonda) > 0) {
                throw ErrorHttp::conflicto('Faltan resultados de esta ronda: cargalos antes de cerrarla.');
            }
        }
        if ($nuevo === 'borrador') {
            $conResultado = array_filter(Enfrentamiento::deRonda($idRonda), fn($p) => $p['estado'] === 'finalizado' && !$p['es_pase_libre']);
            if ($actual !== 'publicada' || $conResultado) {
                throw ErrorHttp::conflicto('Solo vuelve a borrador una ronda publicada que todavía no tiene resultados.');
            }
        }
        if ($nuevo === 'publicada' && $actual === 'cerrada'
            && !$torneo['configuracion']['permitir_correcciones'] && !$this->esAdmin()) {
            throw ErrorHttp::prohibido('Este torneo no permite reabrir rondas para corregir. Pedíselo al administrador.');
        }
    }

    // La fecha de un partido o de una ronda tiene que estar dentro de las
    // fechas del torneo (antes se aceptaba cualquiera, incluso después del fin).
    private static function verificarDentroDelTorneo(string $dia, array $torneo, string $campo): void
    {
        if ($dia < $torneo['fecha_inicio'] || $dia > $torneo['fecha_fin']) {
            throw ErrorValidacion::campo($campo, 'Tiene que ser entre el ' . Fecha::legible($torneo['fecha_inicio']) . ' y el '
                . Fecha::legible($torneo['fecha_fin']) . ', las fechas del torneo. Si el torneo se corrió, cambiá primero sus fechas.');
        }
    }

    private function idJuego(): ?int
    {
        $id = (int)$this->peticion->param('id_juego', 0);
        return $id > 0 ? $id : null;
    }
}
