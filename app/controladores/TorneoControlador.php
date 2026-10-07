<?php
// =====================================================================
// CONTROLADOR: TORNEOS
// =====================================================================

class TorneoControlador extends Controlador
{
    // Campos que solo se pueden cambiar mientras el torneo tiene las
    // inscripciones abiertas (después cambiarían el calendario ya armado).
    private const CAMPOS_ESTRUCTURALES = ['id_juego', 'id_modulo', 'modalidad', 'cupo'];
    private const CONFIGURACION_ESTRUCTURAL = ['puntos_victoria', 'puntos_empate', 'puntos_derrota', 'ida_y_vuelta',
                                               'rondas_suizo', 'permitir_empates', 'max_integrantes_equipo'];

    // GET /torneos — listado público con filtros y paginación
    public function listar(): Respuesta
    {
        [$pagina, $porPagina] = $this->paginacion(ConfiguracionSistema::entero('torneos_por_pagina', 12));
        $filtros = [
            'buscar'    => mb_substr((string)$this->peticion->param('buscar', ''), 0, 100),
            'id_juego'  => $this->peticion->param('id_juego') ? (int)$this->peticion->param('id_juego') : null,
            'categoria' => $this->peticion->param('categoria'),
            'estado'    => $this->peticion->param('estado'),
            'modulo'    => $this->peticion->param('modulo'),
            'modalidad' => $this->peticion->param('modalidad'),
        ];
        [$torneos, $total] = Torneo::listar($filtros, $pagina, $porPagina);
        return $this->listado('torneos', $torneos, $total, $pagina, $porPagina);
    }

    // GET /torneos/destacado
    public function destacado(): Respuesta
    {
        return Respuesta::exito('', ['torneo' => Torneo::destacado()]);
    }

    // GET /torneos/{id} — incluye lo que el usuario actual puede hacer en él,
    // para que la página muestre solo los botones que corresponden.
    public function ver(): Respuesta
    {
        $torneo = $this->torneoVisible($this->peticion->idRuta());
        $idUsuario = $this->idUsuario();
        $gestiona = $this->gestiona($torneo);
        $miParticipacion = $idUsuario ? Participante::delUsuarioEnTorneo((int)$torneo['id_torneo'], $idUsuario) : null;
        $estoyEnTorneo = $idUsuario && Participante::usuarioEstaEnTorneo((int)$torneo['id_torneo'], $idUsuario);
        $abierto = $torneo['estado'] === 'pendiente' && !$torneo['inscripcion_vencida'] && (int)$torneo['inscriptos'] < (int)$torneo['cupo'];

        $torneo['permisos'] = [
            'gestionar'                => $gestiona,
            'inscribirse'              => $idUsuario !== null && $abierto && !$estoyEnTorneo && (int)$torneo['id_organizador'] !== $idUsuario,
            'estoy_inscripto'          => (bool)$estoyEnTorneo,
            'mi_participacion'         => $miParticipacion,
            'generar_calendario'       => $gestiona && $torneo['estado'] === 'pendiente' && (int)$torneo['inscriptos'] >= 2,
            'generar_siguiente_ronda'  => $gestiona && GestorCompetencia::puedeGenerarSiguienteRonda($torneo),
        ];
        return Respuesta::exito('', ['torneo' => $torneo]);
    }

    // POST /torneos (multipart: datos + archivos "logo" y "reglas", ambos opcionales)
    public function crear(): Respuesta
    {
        $usuario = $this->requerirRol(Rol::ORGANIZADOR, Rol::ADMINISTRADOR);
        [$datos, $configuracion] = $this->validarDatos(null);

        // El administrador puede crear el torneo y asignárselo a un organizador
        // (letra, 5.1 y 5.2). Si no elige a nadie, queda a cargo de quien lo crea.
        $asignado = $this->organizadorPedido(null);
        $datos['id_organizador'] = $asignado ? (int)$asignado['id_usuario'] : (int)$usuario['id_usuario'];
        $datos['logo'] = $this->guardarLogo();
        $datos['reglas_pdf'] = $this->guardarReglas();

        try {
            $id = Torneo::crear($datos, $configuracion);
        } catch (Throwable $e) {
            Archivo::borrar('torneos/logos', $datos['logo']);
            Archivo::borrar('torneos/reglas', $datos['reglas_pdf']);
            throw $e;
        }
        if ($asignado) {
            Auditoria::registrarEvento('asignar_organizador', "Torneo \"{$datos['nombre']}\" creado y asignado a {$asignado['nombre_usuario']}", 'torneos', $id);
        }
        return Respuesta::exito('Torneo creado.', ['id_torneo' => $id], 201);
    }

    // POST /torneos/{id} (multipart, para poder cambiar el logo o las reglas;
    // quitar_logo / quitar_reglas = 1 los quita)
    public function actualizar(): Respuesta
    {
        $torneo = $this->torneoGestionable($this->peticion->idRuta());
        if ($torneo['estado'] === 'cancelado') {
            throw ErrorHttp::conflicto('Un torneo cancelado no se puede editar.');
        }
        [$datos, $configuracion] = $this->validarDatos($torneo);

        if ($torneo['estado'] !== 'pendiente') {
            // Ya hay calendario: lo que cambiaría los partidos queda fijo.
            foreach (self::CAMPOS_ESTRUCTURALES as $campo) {
                if (isset($datos[$campo]) && (string)$datos[$campo] !== (string)$torneo[$campo]) {
                    throw ErrorValidacion::campo($campo, 'Este dato no se puede cambiar después de generar el calendario.');
                }
                unset($datos[$campo]);
            }
            $configuracion = array_diff_key($configuracion, array_flip(self::CONFIGURACION_ESTRUCTURAL));
        }
        // Con inscriptos, cambiar la modalidad dejaría jugadores sueltos en un
        // torneo por equipos (o equipos en uno individual).
        if (isset($datos['modalidad']) && $datos['modalidad'] !== $torneo['modalidad'] && (int)$torneo['inscriptos'] > 0) {
            throw ErrorValidacion::campo('modalidad', 'Ya hay inscriptos: la modalidad no se puede cambiar.');
        }
        if (isset($datos['cupo']) && $datos['cupo'] < (int)$torneo['inscriptos']) {
            throw ErrorValidacion::campo('cupo', "Ya hay {$torneo['inscriptos']} inscriptos: el cupo no puede ser menor.");
        }
        $this->verificarPartidosDentroDeLasFechas($torneo, $datos);
        $asignado = $this->organizadorPedido($torneo);
        if ($asignado) {
            $datos['id_organizador'] = (int)$asignado['id_usuario'];
        }

        $logoNuevo = $this->guardarLogo();
        $reglasNuevas = $this->guardarReglas();
        // También se pueden quitar (sin logo, el torneo usa la portada genérica)
        $quitarLogo = !$logoNuevo && $torneo['logo'] && self::booleano($this->peticion->dato('quitar_logo'));
        $quitarReglas = !$reglasNuevas && $torneo['reglas_pdf'] && self::booleano($this->peticion->dato('quitar_reglas'));
        if ($logoNuevo || $quitarLogo) {
            $datos['logo'] = $logoNuevo;
        }
        if ($reglasNuevas || $quitarReglas) {
            $datos['reglas_pdf'] = $reglasNuevas;
        }

        BaseDatos::transaccion(function () use ($torneo, $datos, $configuracion, $asignado) {
            Torneo::actualizar((int)$torneo['id_torneo'], $datos);
            Torneo::actualizarConfiguracion((int)$torneo['id_torneo'], $configuracion);
            if ($asignado) {
                Auditoria::registrarEvento('asignar_organizador',
                    "Torneo \"{$torneo['nombre']}\" asignado a {$asignado['nombre_usuario']} (antes a cargo de {$torneo['organizador']})",
                    'torneos', (int)$torneo['id_torneo']);
            }
        });
        // Los archivos viejos se borran recién cuando el cambio quedó guardado
        if ($logoNuevo || $quitarLogo) {
            Archivo::borrar('torneos/logos', $torneo['logo']);
        }
        if ($reglasNuevas || $quitarReglas) {
            Archivo::borrar('torneos/reglas', $torneo['reglas_pdf']);
        }
        return Respuesta::exito('Torneo actualizado.', ['torneo' => Torneo::buscar((int)$torneo['id_torneo'])]);
    }

    // PUT /torneos/{id}/publicacion — {publicado: true|false}
    public function publicacion(): Respuesta
    {
        $torneo = $this->torneoGestionable($this->peticion->idRuta());
        $publicado = self::booleano($this->peticion->dato('publicado'));
        Torneo::actualizar((int)$torneo['id_torneo'], ['publicado' => $publicado ? 1 : 0]);
        return Respuesta::exito($publicado ? 'El torneo ahora es público.' : 'El torneo quedó oculto: solo lo ven vos y los administradores.');
    }

    // POST /torneos/{id}/cancelar — no borra nada: el historial se conserva.
    public function cancelar(): Respuesta
    {
        $torneo = $this->torneoGestionable($this->peticion->idRuta());
        if (in_array($torneo['estado'], ['finalizado', 'cancelado'], true)) {
            throw ErrorHttp::conflicto('El torneo ya está ' . ($torneo['estado'] === 'finalizado' ? 'finalizado' : 'cancelado') . '.');
        }
        Torneo::cambiarEstado((int)$torneo['id_torneo'], 'cancelado');
        Auditoria::registrarEvento('cancelar_torneo', "Torneo \"{$torneo['nombre']}\" cancelado", 'torneos', (int)$torneo['id_torneo']);
        return Respuesta::exito('Torneo cancelado. Sus datos se conservan como historial.');
    }

    // DELETE /torneos/{id} — solo torneos que todavía no empezaron.
    // Uno en curso o terminado se cancela: borrarlo perdería el historial,
    // y además MySQL no registraría en la auditoría los partidos borrados
    // en cascada (los triggers no se disparan en los borrados en cascada).
    public function eliminar(): Respuesta
    {
        $torneo = $this->torneoGestionable($this->peticion->idRuta());
        if ($torneo['estado'] !== 'pendiente' || Torneo::tieneCalendario((int)$torneo['id_torneo'])) {
            throw ErrorHttp::conflicto('Este torneo ya tiene calendario o resultados: no se puede borrar, solo cancelar.');
        }
        Torneo::eliminar((int)$torneo['id_torneo']);
        Archivo::borrar('torneos/logos', $torneo['logo']);
        Archivo::borrar('torneos/reglas', $torneo['reglas_pdf']);
        return Respuesta::exito('Torneo eliminado.');
    }

    // GET /torneos/{id}/reporte — CSV con posiciones y partidos
    public function reporte(): Respuesta
    {
        $torneo = $this->torneoGestionable($this->peticion->idRuta());
        $id = (int)$torneo['id_torneo'];
        $filas = [];
        foreach (TablaPosiciones::delTorneo($id, $torneo['modulo'] === 'sistema_suizo') as $f) {
            $filas[] = ['Posiciones', $f['posicion'], $f['nombre'], $f['pj'], $f['pg'], $f['pe'], $f['pp'], $f['gf'], $f['gc'], $f['puntos'], ''];
        }
        foreach (Enfrentamiento::delTorneo($id, false) as $p) {
            $filas[] = ['Partido', $p['ronda'], ($p['nombre_local'] ?? '-') . ' vs ' . ($p['nombre_visitante'] ?? ($p['es_pase_libre'] ? 'pase libre' : '-')),
                        $p['puntaje_local'] ?? '', $p['puntaje_visitante'] ?? '', $p['estado'], $p['fecha_hora'] ?? '', $p['lugar'] ?? '', '', '', $p['estado_ronda']];
        }
        return Respuesta::csv("torneo_{$id}.csv",
            ['Tipo', 'Posición / Ronda', 'Participante / Partido', 'PJ / Local', 'PG / Visitante', 'PE / Estado', 'PP / Fecha', 'GF / Lugar', 'GC', 'Puntos', 'Estado ronda'],
            $filas);
    }

    // ── Auxiliares ──

    // Valida los datos del formulario. Devuelve [datos del torneo, configuración].
    private function validarDatos(?array $actual): array
    {
        $d = $this->peticion->cuerpo;
        $esNuevo = $actual === null;

        $validador = (new Validador($d))
            ->texto('nombre', 'El nombre', 3, 150)
            ->entero('id_juego', 'El juego', 1, PHP_INT_MAX)
            ->enLista('modalidad', 'La modalidad', Torneo::MODALIDADES)
            ->entero('cupo', 'El cupo', 2, 256)
            ->decimal('premio', 'El premio', 0, 99999999)
            ->enLista('moneda', 'La moneda', Torneo::MONEDAS)
            ->fecha('fecha_inicio', 'La fecha de inicio')
            ->fecha('fecha_fin', 'La fecha de fin')
            ->texto('descripcion', 'La descripción', 0, 2000)
            ->entero('puntos_victoria', 'Los puntos por victoria', 0, 20)
            ->entero('puntos_empate', 'Los puntos por empate', 0, 20)
            ->entero('puntos_derrota', 'Los puntos por derrota', 0, 20)
            ->entero('rondas_suizo', 'La cantidad de rondas', 1, 20)
            ->entero('max_integrantes_equipo', 'El máximo de integrantes', 1, 50);
        if ($esNuevo) {
            foreach (['nombre' => 'El nombre', 'id_juego' => 'El juego', 'modulo' => 'El formato', 'cupo' => 'El cupo',
                      'fecha_inicio' => 'La fecha de inicio', 'fecha_fin' => 'La fecha de fin'] as $campo => $nombre) {
                $validador->requerido($campo, $nombre);
            }
        }

        $juego = !empty($d['id_juego']) ? Juego::buscar((int)$d['id_juego']) : null;
        $validador->regla(empty($d['id_juego']) || $juego !== null, 'id_juego', 'Elegí un juego de la lista.');

        $modulo = null;
        if (!empty($d['modulo'])) {
            $modulo = Modulo::buscarPorCodigo((string)$d['modulo']);
            $mismoQueAntes = $actual && $modulo && (int)$modulo['id_modulo'] === (int)$actual['id_modulo'];
            $validador->regla($modulo !== null && ($modulo['habilitado'] || $mismoQueAntes), 'modulo',
                'Ese formato no está disponible. Elegí otro.');
        }

        $inicio = $d['fecha_inicio'] ?? $actual['fecha_inicio'] ?? null;
        $fin = $d['fecha_fin'] ?? $actual['fecha_fin'] ?? null;
        if ($inicio && $fin) {
            $validador->regla($fin >= $inicio, 'fecha_fin', 'La fecha de fin no puede ser anterior a la de inicio.');
        }
        // Un torneo que todavía no empezó no puede tener el inicio en el pasado:
        // al crearlo, y también si al editarlo se cambia esa fecha (antes, al
        // editar, se podía poner cualquiera).
        $cambiaInicio = !empty($d['fecha_inicio'])
            && ($esNuevo || ($actual['estado'] === 'pendiente' && $d['fecha_inicio'] !== $actual['fecha_inicio']));
        if ($cambiaInicio) {
            $validador->regla($d['fecha_inicio'] >= date('Y-m-d'), 'fecha_inicio', 'La fecha de inicio no puede ser anterior a hoy.');
        }
        $victoria = (int)($d['puntos_victoria'] ?? $actual['configuracion']['puntos_victoria'] ?? 3);
        $empate = (int)($d['puntos_empate'] ?? $actual['configuracion']['puntos_empate'] ?? 1);
        $derrota = (int)($d['puntos_derrota'] ?? $actual['configuracion']['puntos_derrota'] ?? 0);
        $validador->regla($victoria >= $empate && $empate >= $derrota, 'puntos_empate',
            'Los puntos tienen que ir de mayor a menor: victoria ≥ empate ≥ derrota.');
        $validador->validar();

        $datos = [];
        foreach (['nombre', 'modalidad', 'moneda', 'fecha_inicio', 'fecha_fin'] as $campo) {
            if (isset($d[$campo]) && $d[$campo] !== '') {
                $datos[$campo] = trim((string)$d[$campo]);
            }
        }
        if (array_key_exists('descripcion', $d)) {
            $datos['descripcion'] = trim((string)$d['descripcion']) ?: null;
        }
        if (!empty($d['id_juego'])) $datos['id_juego'] = (int)$d['id_juego'];
        if ($modulo) $datos['id_modulo'] = (int)$modulo['id_modulo'];
        if (isset($d['cupo']) && $d['cupo'] !== '') $datos['cupo'] = (int)$d['cupo'];
        if (isset($d['premio']) && $d['premio'] !== '') $datos['premio'] = round((float)$d['premio'], 2);
        if (array_key_exists('publicado', $d)) $datos['publicado'] = self::booleano($d['publicado']) ? 1 : 0;
        if ($esNuevo && empty($datos['modalidad'])) {
            $datos['modalidad'] = $juego['modalidad_sugerida']; // si no eligió, la sugerida por el juego
        }

        $configuracion = [];
        foreach (['puntos_victoria', 'puntos_empate', 'puntos_derrota', 'rondas_suizo', 'max_integrantes_equipo'] as $campo) {
            if (array_key_exists($campo, $d)) {
                $configuracion[$campo] = $d[$campo] === '' || $d[$campo] === null ? null : (int)$d[$campo];
            }
        }
        foreach (['puntos_victoria', 'puntos_empate', 'puntos_derrota'] as $campo) {
            if (array_key_exists($campo, $configuracion) && $configuracion[$campo] === null) {
                unset($configuracion[$campo]); // vacío = dejar el valor por defecto
            }
        }
        foreach (['ida_y_vuelta', 'permitir_empates', 'permitir_correcciones'] as $campo) {
            if (array_key_exists($campo, $d)) {
                $configuracion[$campo] = self::booleano($d[$campo]) ? 1 : 0;
            }
        }
        // La letra dice que el organizador corrige resultados "si cuenta con
        // autorización" (5.2): esa autorización la da el administrador. Los
        // torneos empiezan sin ella y el organizador no la puede cambiar.
        if (!$this->esAdmin()) {
            $tiene = $actual ? (int)$actual['configuracion']['permitir_correcciones'] : 0;
            if (array_key_exists('permitir_correcciones', $configuracion) && $configuracion['permitir_correcciones'] !== $tiene) {
                throw ErrorHttp::prohibido('La autorización para corregir resultados la da el administrador.');
            }
            unset($configuracion['permitir_correcciones']);
        }
        $codigoModulo = $modulo['codigo'] ?? $actual['modulo'] ?? null;
        if ($esNuevo && $codigoModulo === 'eliminacion_directa') {
            $configuracion['permitir_empates'] = 0;
        }
        return [$datos, $configuracion];
    }

    // Organizador que eligió el administrador (campo id_organizador).
    // Devuelve null si no se pidió un cambio. Solo el administrador puede
    // asignar un torneo, y solo a un organizador (o administrador) activo.
    private function organizadorPedido(?array $actual): ?array
    {
        $valor = $this->peticion->dato('id_organizador');
        if ($valor === null || $valor === '') {
            return null;
        }
        $id = (int)$valor;
        if ($id === ($actual ? (int)$actual['id_organizador'] : (int)$this->idUsuario())) {
            return null;
        }
        if (!$this->esAdmin()) {
            throw ErrorHttp::prohibido('Solo un administrador puede asignar el torneo a otro organizador.');
        }
        $usuario = Usuario::buscarPorId($id);
        if (!$usuario || !$usuario['activo'] || !in_array($usuario['rol'], [Rol::ORGANIZADOR, Rol::ADMINISTRADOR], true)) {
            throw ErrorValidacion::campo('id_organizador', 'Elegí un organizador activo de la lista.');
        }
        return $usuario;
    }

    // Si cambian las fechas del torneo, los partidos y las rondas que ya
    // tienen fecha tienen que seguir quedando adentro.
    private function verificarPartidosDentroDeLasFechas(array $torneo, array $datos): void
    {
        [$primero, $ultimo] = Enfrentamiento::rangoProgramado((int)$torneo['id_torneo']);
        $inicio = $datos['fecha_inicio'] ?? $torneo['fecha_inicio'];
        $fin = $datos['fecha_fin'] ?? $torneo['fecha_fin'];
        if ($primero && $primero < $inicio) {
            throw ErrorValidacion::campo('fecha_inicio', 'Hay partidos programados el ' . Fecha::legible($primero) . ': el torneo no puede empezar después.');
        }
        if ($ultimo && $ultimo > $fin) {
            throw ErrorValidacion::campo('fecha_fin', 'Hay partidos programados el ' . Fecha::legible($ultimo) . ': el torneo no puede terminar antes.');
        }
    }

    private function guardarLogo(): ?string
    {
        return Archivo::guardar($this->peticion->archivo('logo'), 'logo', 'torneos/logos', Archivo::IMAGENES,
            ConfiguracionSistema::entero('max_tamano_imagen_mb', 3), 'torneo');
    }

    private function guardarReglas(): ?string
    {
        return Archivo::guardar($this->peticion->archivo('reglas'), 'reglas', 'torneos/reglas', Archivo::PDF,
            ConfiguracionSistema::entero('max_tamano_reglas_mb', 5), 'reglas');
    }

    // Los formularios mandan "1", "true", "on"... y JSON manda true/false.
    public static function booleano(mixed $valor): bool
    {
        return in_array(is_string($valor) ? mb_strtolower(trim($valor)) : $valor, [true, 1, '1', 'true', 'on', 'si', 'sí'], true);
    }
}
