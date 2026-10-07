<?php
// =====================================================================
// CONTROLADOR: INSCRIPCIONES (participantes de un torneo)
// =====================================================================

class InscripcionControlador extends Controlador
{
    // GET /torneos/{id}/participantes
    public function listar(): Respuesta
    {
        $torneo = $this->torneoVisible($this->peticion->idRuta());
        return Respuesta::exito('', [
            'participantes' => Participante::delTorneo((int)$torneo['id_torneo']),
            'cupo'          => (int)$torneo['cupo'],
        ]);
    }

    // POST /torneos/{id}/inscripcion — inscribirse uno mismo (torneos individuales).
    // En los torneos por equipos se entra creando un equipo o pidiendo unirse a uno.
    public function inscribirme(): Respuesta
    {
        $usuario = $this->requerirSesion();
        $torneo = $this->torneoVisible($this->peticion->idRuta());
        $idUsuario = (int)$usuario['id_usuario'];

        self::verificarInscripcionAbierta($torneo);
        if ($torneo['modalidad'] !== 'individual') {
            throw ErrorHttp::conflicto('Este torneo es por equipos: creá un equipo o pedí unirte a uno.');
        }
        if ((int)$torneo['id_organizador'] === $idUsuario) {
            throw ErrorHttp::conflicto('Sos el organizador de este torneo: no podés jugarlo.');
        }
        if (Participante::usuarioEstaEnTorneo((int)$torneo['id_torneo'], $idUsuario)) {
            throw ErrorHttp::conflicto('Ya estás inscripto en este torneo.');
        }

        $id = Participante::inscribirUsuario((int)$torneo['id_torneo'], $idUsuario);
        return Respuesta::exito('¡Listo! Ya estás inscripto.', ['id_participante' => $id], 201);
    }

    // DELETE /torneos/{id}/inscripcion — darse de baja mientras no empezó.
    public function darmeDeBaja(): Respuesta
    {
        $usuario = $this->requerirSesion();
        $torneo = $this->torneoVisible($this->peticion->idRuta());
        $idUsuario = (int)$usuario['id_usuario'];
        if ($torneo['estado'] !== 'pendiente') {
            throw ErrorHttp::conflicto('El torneo ya empezó: no te podés dar de baja.');
        }

        $individual = Participante::delUsuarioEnTorneo((int)$torneo['id_torneo'], $idUsuario);
        if ($individual) {
            Participante::eliminar((int)$individual['id_participante']);
            return Respuesta::exito('Te diste de baja del torneo.');
        }

        $equipo = Equipo::delUsuarioEnTorneo((int)$torneo['id_torneo'], $idUsuario);
        if (!$equipo) {
            throw ErrorHttp::conflicto('No estás inscripto en este torneo.');
        }
        if ((int)$equipo['id_capitan'] === $idUsuario) {
            if (Equipo::cantidadMiembros((int)$equipo['id_equipo']) > 1) {
                throw ErrorHttp::conflicto('Sos el capitán y el equipo tiene más integrantes: no lo podés abandonar.');
            }
            Equipo::eliminar((int)$equipo['id_equipo']);
            return Respuesta::exito('Te diste de baja y el equipo se eliminó (eras el único integrante).');
        }
        Equipo::quitarMiembro((int)$equipo['id_equipo'], $idUsuario);
        return Respuesta::exito('Saliste del equipo.');
    }

    // POST /torneos/{id}/participantes — el organizador inscribe a alguien.
    //   individual: {nombre_usuario}
    //   equipos:    {nombre_equipo, capitan (nombre de usuario, opcional)}
    public function agregar(): Respuesta
    {
        $torneo = $this->torneoGestionable($this->peticion->idRuta());
        self::verificarInscripcionAbierta($torneo);
        $idTorneo = (int)$torneo['id_torneo'];

        if ($torneo['modalidad'] === 'individual') {
            $nombre = (string)$this->peticion->dato('nombre_usuario', '');
            (new Validador(['nombre_usuario' => $nombre]))->requerido('nombre_usuario', 'El nombre de usuario')->validar();
            $jugador = $this->usuarioActivo($nombre, 'nombre_usuario');
            if ((int)$jugador['id_usuario'] === (int)$torneo['id_organizador']) {
                throw ErrorValidacion::campo('nombre_usuario', 'El organizador no puede jugar su propio torneo.');
            }
            if (Participante::usuarioEstaEnTorneo($idTorneo, (int)$jugador['id_usuario'])) {
                throw ErrorValidacion::campo('nombre_usuario', 'Esa persona ya está inscripta.');
            }
            $id = Participante::inscribirUsuario($idTorneo, (int)$jugador['id_usuario']);
            return Respuesta::exito("{$jugador['nombre_usuario']} quedó inscripto.", ['id_participante' => $id], 201);
        }

        $nombreEquipo = (string)$this->peticion->dato('nombre_equipo', '');
        (new Validador(['nombre_equipo' => $nombreEquipo]))
            ->requerido('nombre_equipo', 'El nombre del equipo')->texto('nombre_equipo', 'El nombre del equipo', 2, 100)->validar();
        if (Equipo::existeNombre($idTorneo, $nombreEquipo)) {
            throw ErrorValidacion::campo('nombre_equipo', 'Ya hay un equipo con ese nombre en el torneo.');
        }
        $idCapitan = null;
        if ($nombreCapitan = (string)$this->peticion->dato('capitan', '')) {
            $capitan = $this->usuarioActivo($nombreCapitan, 'capitan');
            if (Participante::usuarioEstaEnTorneo($idTorneo, (int)$capitan['id_usuario'])) {
                throw ErrorValidacion::campo('capitan', 'Esa persona ya juega en otro equipo de este torneo.');
            }
            $idCapitan = (int)$capitan['id_usuario'];
        }
        [$idEquipo, $id] = Equipo::crear($idTorneo, $nombreEquipo, $idCapitan);
        return Respuesta::exito("El equipo $nombreEquipo quedó inscripto.", ['id_participante' => $id, 'id_equipo' => $idEquipo], 201);
    }

    // DELETE /torneos/{id}/participantes/{idParticipante}
    public function quitar(): Respuesta
    {
        $torneo = $this->torneoGestionable($this->peticion->idRuta());
        if ($torneo['estado'] !== 'pendiente') {
            throw ErrorHttp::conflicto('El torneo ya empezó: no se pueden quitar participantes.');
        }
        $participante = Participante::buscar($this->peticion->idRuta('idParticipante'));
        if (!$participante || (int)$participante['id_torneo'] !== (int)$torneo['id_torneo']) {
            throw ErrorHttp::noEncontrado('Ese participante no está en este torneo.');
        }
        Participante::eliminar((int)$participante['id_participante']);
        return Respuesta::exito("{$participante['nombre']} ya no participa del torneo.");
    }

    // ── Auxiliares ──

    // Inscripciones abiertas y con lugar (antes el cupo solo se controlaba
    // para equipos: en los individuales se podía superar).
    public static function verificarInscripcionAbierta(array $torneo): void
    {
        self::verificarPlazo($torneo);
        if (Participante::cantidad((int)$torneo['id_torneo']) >= (int)$torneo['cupo']) {
            throw ErrorHttp::conflicto('El torneo ya completó su cupo.');
        }
    }

    // El plazo para anotarse (también para sumarse a un equipo): el torneo
    // no empezó y no pasó su fecha de inicio. Se puede anotar hasta ese día
    // inclusive; si el organizador no generó el calendario, al día siguiente
    // la inscripción se cierra sola (antes seguía abierta para siempre).
    public static function verificarPlazo(array $torneo): void
    {
        if ($torneo['estado'] !== 'pendiente') {
            throw ErrorHttp::conflicto('Las inscripciones de este torneo están cerradas.');
        }
        if ($torneo['inscripcion_vencida']) {
            throw ErrorHttp::conflicto('Las inscripciones se cerraron el ' . Fecha::legible($torneo['fecha_inicio'])
                . ', la fecha de inicio del torneo. Para reabrirlas, el organizador tiene que cambiar esa fecha.');
        }
    }

    private function usuarioActivo(string $nombre, string $campo): array
    {
        $usuario = Usuario::buscarPorNombre($nombre);
        if (!$usuario || !$usuario['activo']) {
            throw ErrorValidacion::campo($campo, "No existe un usuario activo llamado \"$nombre\".");
        }
        return $usuario;
    }
}
