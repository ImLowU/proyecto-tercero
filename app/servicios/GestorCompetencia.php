<?php
// =====================================================================
// GESTOR DE COMPETENCIA (servicio)
// Une los módulos de competencia (que solo calculan) con los modelos
// (que guardan): genera y guarda calendarios, hace avanzar a los
// ganadores en la llave, aplica las reglas de carga de resultados y
// marca el torneo como finalizado cuando corresponde.
// Está separado del controlador porque son reglas del negocio que no
// dependen de HTTP; así el controlador queda corto y esto se prueba solo.
// =====================================================================

class GestorCompetencia
{
    // Función que mezcla a los participantes antes de armar el calendario
    // (sorteo). Los tests la reemplazan para tener resultados repetibles.
    /** @var callable */
    public static $mezclar = 'shuffle';

    // ── Generar el calendario inicial ──
    public static function generarCalendario(array $torneo): array
    {
        $idTorneo = (int)$torneo['id_torneo'];
        if ($torneo['estado'] !== 'pendiente') {
            throw ErrorHttp::conflicto('El calendario solo se genera una vez, mientras el torneo tiene las inscripciones abiertas.');
        }
        if (Torneo::tieneCalendario($idTorneo)) {
            throw ErrorHttp::conflicto('Este torneo ya tiene el calendario generado.');
        }
        $participantes = Participante::idsDelTorneo($idTorneo);
        if (count($participantes) < 2) {
            throw ErrorHttp::conflicto('Hacen falta al menos 2 participantes inscriptos para generar el calendario.');
        }

        $mezclar = self::$mezclar;
        $mezclar($participantes);
        $modulo = FabricaModulos::crear($torneo['modulo']);
        $rondas = $modulo->generarCalendario($participantes, $torneo['configuracion']);

        return BaseDatos::transaccion(function () use ($idTorneo, $rondas, $modulo, $torneo, $participantes) {
            self::guardarRondas($idTorneo, $rondas, 1);
            if ($modulo instanceof ModuloEliminacionDirecta) {
                self::avanzarPasesLibres($idTorneo, $modulo);
            }
            Torneo::cambiarEstado($idTorneo, 'en_curso');
            Auditoria::registrarEvento('generar_calendario',
                "Calendario de \"{$torneo['nombre']}\": " . count($rondas) . ' ronda(s), ' . count($participantes) . ' participantes',
                'torneos', $idTorneo);
            return ['rondas' => count($rondas), 'total_rondas' => $modulo->totalRondas(count($participantes), $torneo['configuracion'])];
        });
    }

    // ── Suizo: generar la ronda siguiente ──
    public static function generarSiguienteRonda(array $torneo): array
    {
        $idTorneo = (int)$torneo['id_torneo'];
        if ($torneo['modulo'] !== 'sistema_suizo') {
            throw ErrorHttp::conflicto('Solo el sistema suizo genera las rondas de a una; los otros formatos arman todo el calendario al principio.');
        }
        if ($torneo['estado'] !== 'en_curso') {
            throw ErrorHttp::conflicto('El torneo no está en curso.');
        }
        $ultima = Ronda::ultima($idTorneo);
        if (!$ultima) {
            throw ErrorHttp::conflicto('Primero hay que generar el calendario.');
        }
        if (Ronda::partidosSinTerminar((int)$ultima['id_ronda']) > 0) {
            throw ErrorHttp::conflicto("Faltan resultados de la ronda {$ultima['numero']}. Cargalos antes de generar la siguiente.");
        }

        $modulo = new ModuloSuizo();
        $cantidad = Participante::cantidad($idTorneo);
        $total = $modulo->totalRondas($cantidad, $torneo['configuracion']);
        if ((int)$ultima['numero'] >= $total) {
            throw ErrorHttp::conflicto("Ya se jugaron las $total rondas del torneo.");
        }

        $cruces = [];
        $conPaseLibre = [];
        foreach (Enfrentamiento::crucesDelTorneo($idTorneo) as $c) {
            if ($c['es_pase_libre']) {
                $conPaseLibre[] = (int)$c['id_local'];
            } else {
                $cruces[] = [(int)$c['id_local'], (int)$c['id_visitante']];
            }
        }
        $partidos = $modulo->generarRonda(TablaPosiciones::ordenDeParticipantes($idTorneo), $cruces, $conPaseLibre);
        $numero = (int)$ultima['numero'] + 1;

        return BaseDatos::transaccion(function () use ($idTorneo, $ultima, $numero, $partidos, $total) {
            Ronda::actualizar((int)$ultima['id_ronda'], ['estado' => 'cerrada']);
            self::guardarRondas($idTorneo, [['nombre' => "Ronda $numero", 'partidos' => $partidos]], $numero);
            Auditoria::registrarEvento('generar_ronda', "Ronda $numero de $total generada", 'torneos', $idTorneo);
            return ['ronda' => $numero, 'total_rondas' => $total];
        });
    }

    // ¿Se puede pedir la ronda siguiente del suizo? (para mostrar el botón)
    public static function puedeGenerarSiguienteRonda(array $torneo): bool
    {
        if ($torneo['modulo'] !== 'sistema_suizo' || $torneo['estado'] !== 'en_curso') {
            return false;
        }
        $ultima = Ronda::ultima((int)$torneo['id_torneo']);
        $total = (new ModuloSuizo())->totalRondas((int)$torneo['inscriptos'], $torneo['configuracion']);
        return $ultima && (int)$ultima['numero'] < $total && Ronda::partidosSinTerminar((int)$ultima['id_ronda']) === 0;
    }

    // ── Cargar o corregir un resultado ──
    public static function registrarResultado(array $partido, array $torneo, int $local, int $visitante, ?string $observacion, bool $esAdmin): array
    {
        if ($torneo['estado'] === 'cancelado') {
            throw ErrorHttp::conflicto('El torneo está cancelado.');
        }
        if ($partido['estado_ronda'] === 'borrador') {
            throw ErrorHttp::conflicto('Publicá la ronda antes de cargar resultados: así el resultado es oficial y lo ven todos.');
        }
        if ($partido['estado_ronda'] === 'cerrada') {
            throw ErrorHttp::conflicto('La ronda está cerrada y sus resultados ya son definitivos.');
        }
        if ($partido['es_pase_libre']) {
            throw ErrorHttp::conflicto('Un pase libre no tiene resultado para cargar.');
        }
        if (!$partido['id_local'] || !$partido['id_visitante']) {
            throw ErrorHttp::conflicto('Todavía no están definidos los dos rivales de este partido.');
        }
        // Un partido que, según su fecha, todavía no se jugó no puede tener
        // resultado. Si se adelantó, primero se corrige la fecha.
        if ($partido['fecha_hora'] && strtotime($partido['fecha_hora']) > time()) {
            throw ErrorHttp::conflicto('Este partido está programado para el ' . Fecha::legible($partido['fecha_hora']) . ' a las '
                . Fecha::hora($partido['fecha_hora']) . '. Si ya se jugó, cambiá primero la fecha.');
        }

        $modulo = FabricaModulos::crear($torneo['modulo']);
        if ($local === $visitante && !$modulo->admiteEmpates($torneo['configuracion'])) {
            throw ErrorValidacion::campo('puntaje_local', $modulo instanceof ModuloEliminacionDirecta
                ? 'En eliminación directa el partido no puede terminar empatado: tiene que haber un ganador que avance.'
                : 'Este torneo no permite empates.');
        }

        $esCorreccion = $partido['estado'] === 'finalizado';
        if ($esCorreccion && !$torneo['configuracion']['permitir_correcciones'] && !$esAdmin) {
            throw ErrorHttp::prohibido('Este torneo no permite corregir resultados. Si hubo un error, pedíselo al administrador.');
        }

        $idEnfrentamiento = (int)$partido['id_enfrentamiento'];
        $siguiente = null;
        if ($modulo instanceof ModuloEliminacionDirecta) {
            $destino = $modulo->destinoDelGanador((int)$partido['posicion']);
            $siguiente = Enfrentamiento::enPosicion((int)$torneo['id_torneo'], (int)$partido['ronda'] + 1, $destino['posicion']);
            if ($esCorreccion && $siguiente && $siguiente['estado'] === 'finalizado') {
                throw ErrorHttp::conflicto('No se puede corregir: el ganador ya jugó el partido siguiente de la llave.');
            }
        }

        $ganador = $local > $visitante ? (int)$partido['id_local'] : ($visitante > $local ? (int)$partido['id_visitante'] : null);

        return BaseDatos::transaccion(function () use ($idEnfrentamiento, $local, $visitante, $observacion, $ganador, $siguiente, $modulo, $partido, $torneo, $esCorreccion) {
            Resultado::registrar($idEnfrentamiento, $local, $visitante, Sesion::idUsuario(), $observacion);
            Enfrentamiento::finalizar($idEnfrentamiento, $ganador);
            if ($siguiente) {
                $destino = $modulo->destinoDelGanador((int)$partido['posicion']);
                Enfrentamiento::ubicar((int)$siguiente['id_enfrentamiento'], $destino['lado'], $ganador);
            }
            $finalizado = self::verificarFinalizacion($torneo, $modulo);
            return ['es_correccion' => $esCorreccion, 'torneo_finalizado' => $finalizado];
        });
    }

    // ── Auxiliares ──

    private static function guardarRondas(int $idTorneo, array $rondas, int $numeroInicial): void
    {
        foreach ($rondas as $i => $ronda) {
            $idRonda = Ronda::crear($idTorneo, $numeroInicial + $i, $ronda['nombre'] ?? null);
            foreach ($ronda['partidos'] as $posicion => $p) {
                Enfrentamiento::crear($idRonda, $posicion, $p['local'], $p['visitante'], $p['pase_libre']);
            }
        }
    }

    // En la llave, quien tuvo pase libre en la primera ronda ya pasa a la segunda.
    private static function avanzarPasesLibres(int $idTorneo, ModuloEliminacionDirecta $modulo): void
    {
        $primera = Ronda::delTorneo($idTorneo)[0];
        foreach (Enfrentamiento::deRonda((int)$primera['id_ronda']) as $p) {
            if ($p['es_pase_libre']) {
                $destino = $modulo->destinoDelGanador((int)$p['posicion']);
                $siguiente = Enfrentamiento::enPosicion($idTorneo, 2, $destino['posicion']);
                if ($siguiente) {
                    Enfrentamiento::ubicar((int)$siguiente['id_enfrentamiento'], $destino['lado'], (int)$p['id_ganador']);
                }
            }
        }
    }

    // Marca el torneo como finalizado si ya no queda nada por jugar
    // (antes solo pasaba en eliminación directa; la liga y el suizo
    // quedaban "en curso" para siempre). Devuelve true solo si terminó con
    // este resultado: una corrección en un torneo ya terminado no es noticia
    // (antes repetía "¡el torneo terminó!").
    private static function verificarFinalizacion(array $torneo, ModuloCompetencia $modulo): bool
    {
        $idTorneo = (int)$torneo['id_torneo'];
        if (Enfrentamiento::pendientesDelTorneo($idTorneo) > 0) {
            return false;
        }
        if ($modulo instanceof ModuloSuizo
            && Ronda::cantidad($idTorneo) < $modulo->totalRondas(Participante::cantidad($idTorneo), $torneo['configuracion'])) {
            return false;
        }
        if ($torneo['estado'] === 'finalizado') {
            return false;
        }
        Torneo::cambiarEstado($idTorneo, 'finalizado');
        // Los resultados oficiales quedan firmes: todas las rondas se cierran.
        // Para corregir algo después hay que reabrir la ronda, con autorización.
        Ronda::cerrarTodas($idTorneo);
        Auditoria::registrarEvento('finalizar_torneo', "Torneo \"{$torneo['nombre']}\" finalizado", 'torneos', $idTorneo);
        return true;
    }
}
