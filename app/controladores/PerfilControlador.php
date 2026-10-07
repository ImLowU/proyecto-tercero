<?php
// =====================================================================
// CONTROLADOR: PERFIL
// El perfil propio (con correo, editable) y el perfil público de
// cualquier jugador (sin correo).
// =====================================================================

class PerfilControlador extends Controlador
{
    // GET /perfil
    public function propio(): Respuesta
    {
        $usuario = $this->requerirSesion();
        return Respuesta::exito('', ['usuario' => $usuario + ['estadisticas' => Usuario::perfilPublico((int)$usuario['id_usuario'])]]);
    }

    // GET /usuarios/{id} — perfil público. Antes esta consulta devolvía el
    // correo de cualquier usuario sin pedir sesión: cambiando el número de
    // la URL se podían juntar los correos de todos (IDOR). Ahora no.
    public function publico(): Respuesta
    {
        $perfil = Usuario::perfilPublico($this->peticion->idRuta());
        if (!$perfil) {
            throw ErrorHttp::noEncontrado('Ese usuario no existe.');
        }
        $id = (int)$perfil['id_usuario'];
        $perfil['torneos'] = array_values(array_filter(
            Torneo::dondeParticipa($id),
            fn($t) => $t['publicado'] && $t['estado'] !== 'cancelado'
        ));
        // Torneos que organiza (solo los públicos) y sus partidos públicos
        [$perfil['organiza']] = Torneo::listar(['id_organizador' => $id], 1, 12);
        $perfil['proximos'] = Enfrentamiento::proximosDelUsuario($id, 5);
        $perfil['recientes'] = Enfrentamiento::recientesDelUsuario($id, 5);
        return Respuesta::exito('', ['usuario' => $perfil]);
    }

    // PUT /perfil — para cambiar el correo o la contraseña se pide la
    // contraseña actual (si alguien deja la sesión abierta, otra persona
    // no puede adueñarse de la cuenta).
    public function actualizar(): Respuesta
    {
        $usuario = $this->requerirSesion();
        $id = (int)$usuario['id_usuario'];
        $datos = $this->peticion->cuerpo;

        $nombre = trim((string)($datos['nombre_usuario'] ?? $usuario['nombre_usuario']));
        $correo = mb_strtolower(trim((string)($datos['correo'] ?? $usuario['correo'])));
        $biografia = trim((string)($datos['biografia'] ?? $usuario['biografia'] ?? ''));
        $nueva = (string)($datos['contrasena_nueva'] ?? '');
        $actual = (string)($datos['contrasena_actual'] ?? '');

        $validador = (new Validador(['nombre_usuario' => $nombre, 'correo' => $correo, 'biografia' => $biografia, 'contrasena_nueva' => $nueva]))
            ->requerido('nombre_usuario', 'El nombre de usuario')->nombreUsuario('nombre_usuario')
            ->requerido('correo', 'El correo')->correo('correo')
            ->texto('biografia', 'La biografía', 0, 280)
            ->contrasena('contrasena_nueva');

        $cambiaCorreo = $correo !== mb_strtolower($usuario['correo']);
        if (($cambiaCorreo || $nueva !== '') && !Usuario::verificarContrasena($id, $actual)) {
            $validador->regla(false, 'contrasena_actual', $actual === ''
                ? 'Para cambiar el correo o la contraseña escribí tu contraseña actual.'
                : 'La contraseña actual no es correcta.');
        }
        $validador->validar();

        $conflictos = Usuario::conflictos($nombre, $correo, $id);
        if ($conflictos) {
            throw new ErrorValidacion($conflictos);
        }

        BaseDatos::transaccion(function () use ($id, $nombre, $correo, $biografia, $nueva) {
            Usuario::actualizar($id, ['nombre_usuario' => $nombre, 'correo' => $correo, 'biografia' => $biografia === '' ? null : $biografia]);
            if ($nueva !== '') {
                Usuario::cambiarContrasena($id, $nueva);
                TokenUsuario::eliminarDeUsuario($id, 'recordarme'); // cierra los "recordarme" de otros dispositivos
            }
        });
        return Respuesta::exito('Perfil actualizado.', ['usuario' => AuthControlador::datosPublicosDeSesion(Usuario::buscarPorId($id))]);
    }

    // POST /perfil/foto (multipart, campo "foto")
    public function subirFoto(): Respuesta
    {
        $usuario = $this->requerirSesion();
        $id = (int)$usuario['id_usuario'];
        $nombre = Archivo::guardar($this->peticion->archivo('foto'), 'foto', 'perfiles', Archivo::IMAGENES,
            ConfiguracionSistema::entero('max_tamano_imagen_mb', 3), "usuario_$id");
        if ($nombre === null) {
            throw ErrorValidacion::campo('foto', 'Elegí una imagen.');
        }
        try {
            Usuario::actualizar($id, ['foto_perfil' => $nombre]);
        } catch (Throwable $e) {
            Archivo::borrar('perfiles', $nombre); // no dejar archivos huérfanos
            throw $e;
        }
        // La foto anterior se borra recién cuando la nueva ya quedó guardada.
        Archivo::borrar('perfiles', $usuario['foto_perfil']);
        return Respuesta::exito('Foto actualizada.', ['foto_perfil' => $nombre]);
    }

    // DELETE /perfil/foto
    public function eliminarFoto(): Respuesta
    {
        $usuario = $this->requerirSesion();
        if (!$usuario['foto_perfil']) {
            throw ErrorHttp::conflicto('No tenés foto de perfil.');
        }
        Usuario::actualizar((int)$usuario['id_usuario'], ['foto_perfil' => null]);
        Archivo::borrar('perfiles', $usuario['foto_perfil']);
        return Respuesta::exito('Foto eliminada.');
    }

    // GET /perfil/torneos?tipo=creados|participo
    public function torneos(): Respuesta
    {
        $id = (int)$this->requerirSesion()['id_usuario'];
        $torneos = $this->peticion->param('tipo') === 'creados' ? Torneo::delOrganizador($id) : Torneo::dondeParticipa($id);
        return Respuesta::exito('', ['torneos' => $torneos]);
    }

    // GET /perfil/partidos — mis próximos partidos y mis últimos resultados
    public function proximosPartidos(): Respuesta
    {
        $id = (int)$this->requerirSesion()['id_usuario'];
        return Respuesta::exito('', [
            'partidos'  => Enfrentamiento::proximosDelUsuario($id),
            'recientes' => Enfrentamiento::recientesDelUsuario($id, 10),
        ]);
    }
}
