<?php
// =====================================================================
// CONTROLADOR: EQUIPOS Y SOLICITUDES PARA UNIRSE
// =====================================================================

class EquipoControlador extends Controlador
{
    // POST /torneos/{id}/equipos — crea un equipo; quien lo crea es el capitán.
    public function crear(): Respuesta
    {
        $usuario = $this->requerirSesion();
        $torneo = $this->torneoVisible($this->peticion->idRuta());
        $idTorneo = (int)$torneo['id_torneo'];
        $idUsuario = (int)$usuario['id_usuario'];

        InscripcionControlador::verificarInscripcionAbierta($torneo);
        if ($torneo['modalidad'] !== 'equipos') {
            throw ErrorHttp::conflicto('Este torneo es individual: inscribite directamente.');
        }
        if ((int)$torneo['id_organizador'] === $idUsuario) {
            throw ErrorHttp::conflicto('Sos el organizador de este torneo: no podés jugarlo.');
        }
        if (Participante::usuarioEstaEnTorneo($idTorneo, $idUsuario)) {
            throw ErrorHttp::conflicto('Ya formás parte de un equipo de este torneo.');
        }

        $nombre = (string)$this->peticion->dato('nombre', '');
        (new Validador(['nombre' => $nombre]))
            ->requerido('nombre', 'El nombre del equipo')->texto('nombre', 'El nombre del equipo', 2, 100)->validar();
        if (Equipo::existeNombre($idTorneo, $nombre)) {
            throw ErrorValidacion::campo('nombre', 'Ya hay un equipo con ese nombre en el torneo.');
        }

        [$idEquipo, $idParticipante] = Equipo::crear($idTorneo, $nombre, $idUsuario);
        return Respuesta::exito('Equipo creado: ya está inscripto y sos el capitán.',
            ['id_equipo' => $idEquipo, 'id_participante' => $idParticipante], 201);
    }

    // GET /equipos/{id} — perfil público del equipo
    public function ver(): Respuesta
    {
        $equipo = Equipo::buscar($this->peticion->idRuta());
        if (!$equipo) {
            throw ErrorHttp::noEncontrado('Equipo no encontrado.');
        }
        $this->torneoVisible((int)$equipo['id_torneo']); // si el torneo está oculto, el equipo también
        $equipo['miembros'] = Equipo::miembros((int)$equipo['id_equipo']);
        $equipo['partidos'] = array_values(array_filter(
            Enfrentamiento::delTorneo((int)$equipo['id_torneo'], !$this->gestiona(Torneo::buscar((int)$equipo['id_torneo']))),
            fn($p) => in_array((int)$equipo['id_participante'], [(int)$p['id_local'], (int)$p['id_visitante']], true)
        ));
        return Respuesta::exito('', ['equipo' => $equipo]);
    }

    // POST /equipos/{id}/solicitudes — pedir unirse
    public function solicitar(): Respuesta
    {
        $usuario = $this->requerirSesion();
        $equipo = $this->equipoDeTorneoAbierto();
        $idUsuario = (int)$usuario['id_usuario'];

        if ((int)$equipo['id_organizador'] === $idUsuario) {
            throw ErrorHttp::conflicto('Sos el organizador de este torneo: no podés jugarlo.');
        }
        if (Participante::usuarioEstaEnTorneo((int)$equipo['id_torneo'], $idUsuario)) {
            throw ErrorHttp::conflicto('Ya formás parte de un equipo de este torneo.');
        }
        // Un equipo que el organizador inscribió sin capitán no tiene quién
        // acepte solicitudes: antes la solicitud quedaba pendiente para siempre.
        if (!$equipo['id_capitan']) {
            throw ErrorHttp::conflicto('Este equipo no tiene capitán que acepte solicitudes: pedile al organizador que te sume.');
        }
        if (SolicitudEquipo::tienePendiente((int)$equipo['id_equipo'], $idUsuario)) {
            throw ErrorHttp::conflicto('Ya tenés una solicitud pendiente para este equipo.');
        }
        $this->verificarLugarEnEquipo($equipo);

        $id = SolicitudEquipo::crear((int)$equipo['id_equipo'], $idUsuario);
        return Respuesta::exito('Solicitud enviada. El capitán del equipo tiene que aceptarla.', ['id_solicitud' => $id], 201);
    }

    // GET /equipos/{id}/solicitudes — solo el capitán
    public function solicitudes(): Respuesta
    {
        $usuario = $this->requerirSesion();
        $equipo = Equipo::buscar($this->peticion->idRuta());
        if (!$equipo) {
            throw ErrorHttp::noEncontrado('Equipo no encontrado.');
        }
        if ((int)$equipo['id_capitan'] !== (int)$usuario['id_usuario']) {
            throw ErrorHttp::prohibido('Solo el capitán del equipo puede ver sus solicitudes.');
        }
        return Respuesta::exito('', ['solicitudes' => SolicitudEquipo::pendientesDelEquipo((int)$equipo['id_equipo'])]);
    }

    // POST /solicitudes/{id}/respuesta — {accion: "aceptar" | "rechazar"}
    public function responder(): Respuesta
    {
        $usuario = $this->requerirSesion();
        $accion = (string)$this->peticion->dato('accion', '');
        (new Validador(['accion' => $accion]))->requerido('accion', 'La respuesta')
            ->enLista('accion', 'La respuesta', ['aceptar', 'rechazar'])->validar();

        $solicitud = SolicitudEquipo::buscar($this->peticion->idRuta());
        if (!$solicitud) {
            throw ErrorHttp::noEncontrado('Solicitud no encontrada.');
        }
        if ((int)$solicitud['id_capitan'] !== (int)$usuario['id_usuario']) {
            throw ErrorHttp::prohibido('Solo el capitán del equipo puede responder esta solicitud.');
        }
        if ($solicitud['estado'] !== 'pendiente') {
            throw ErrorHttp::conflicto('Esta solicitud ya fue respondida.');
        }

        if ($accion === 'aceptar') {
            $equipo = Equipo::buscar((int)$solicitud['id_equipo']);
            if ($equipo['estado_torneo'] !== 'pendiente') {
                throw ErrorHttp::conflicto('El torneo ya empezó: no se pueden sumar integrantes.');
            }
            InscripcionControlador::verificarPlazo(Torneo::buscar((int)$equipo['id_torneo']));
            if (Participante::usuarioEstaEnTorneo((int)$solicitud['id_torneo'], (int)$solicitud['id_usuario'])) {
                SolicitudEquipo::responder((int)$solicitud['id_solicitud'], 'rechazada');
                throw ErrorHttp::conflicto('Esa persona ya se sumó a otro equipo del torneo; la solicitud se rechazó.');
            }
            $this->verificarLugarEnEquipo($equipo);
        }

        BaseDatos::transaccion(function () use ($solicitud, $accion) {
            SolicitudEquipo::responder((int)$solicitud['id_solicitud'], $accion === 'aceptar' ? 'aceptada' : 'rechazada');
            if ($accion === 'aceptar') {
                Equipo::agregarMiembro((int)$solicitud['id_equipo'], (int)$solicitud['id_usuario']);
            }
        });
        return Respuesta::exito($accion === 'aceptar' ? 'Solicitud aceptada: ya es parte del equipo.' : 'Solicitud rechazada.');
    }

    // ── Auxiliares ──

    private function equipoDeTorneoAbierto(): array
    {
        $equipo = Equipo::buscar($this->peticion->idRuta());
        if (!$equipo) {
            throw ErrorHttp::noEncontrado('Equipo no encontrado.');
        }
        InscripcionControlador::verificarPlazo($this->torneoVisible((int)$equipo['id_torneo']));
        return $equipo;
    }

    private function verificarLugarEnEquipo(array $equipo): void
    {
        $maximo = Torneo::configuracion((int)$equipo['id_torneo'])['max_integrantes_equipo'] ?? null;
        if ($maximo && Equipo::cantidadMiembros((int)$equipo['id_equipo']) >= (int)$maximo) {
            throw ErrorHttp::conflicto("El equipo ya tiene el máximo de $maximo integrantes.");
        }
    }
}
