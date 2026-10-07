<?php
// =====================================================================
// CONTROLADOR: PANEL DE ADMINISTRACIÓN
// Todas las acciones exigen el rol administrador (sección 5.1 de la letra).
// =====================================================================

class AdminControlador extends Controlador
{
    public function __construct(Peticion $peticion)
    {
        parent::__construct($peticion);
        $this->requerirRol(Rol::ADMINISTRADOR);
    }

    // GET /admin/resumen
    public function resumen(): Respuesta
    {
        return Respuesta::exito('', ['resumen' => Estadisticas::paraAdministrador()]);
    }

    // GET /admin/usuarios?buscar=&rol=&activo=&pagina=
    public function usuarios(): Respuesta
    {
        [$pagina, $porPagina] = $this->paginacion(20);
        [$usuarios, $total] = Usuario::listar([
            'buscar' => mb_substr((string)$this->peticion->param('buscar', ''), 0, 100),
            'rol'    => $this->peticion->param('rol'),
            'activo' => $this->peticion->param('activo'),
        ], $pagina, $porPagina);
        return $this->listado('usuarios', $usuarios, $total, $pagina, $porPagina);
    }

    // POST /admin/usuarios — crear cualquier tipo de usuario (también administradores).
    // La contraseña la elige el admin, pero el usuario la tiene que cambiar al entrar.
    public function crearUsuario(): Respuesta
    {
        $datos = $this->peticion->cuerpo;
        (new Validador($datos))
            ->requerido('nombre_usuario', 'El nombre de usuario')->nombreUsuario('nombre_usuario')
            ->requerido('correo', 'El correo')->correo('correo')
            ->requerido('contrasena', 'La contraseña')->contrasena('contrasena')
            ->requerido('rol', 'El rol')->enLista('rol', 'El rol', Rol::codigos())
            ->validar();

        $nombre = trim($datos['nombre_usuario']);
        $correo = mb_strtolower(trim($datos['correo']));
        if ($conflictos = Usuario::conflictos($nombre, $correo)) {
            throw new ErrorValidacion($conflictos);
        }
        $id = BaseDatos::transaccion(function () use ($nombre, $correo, $datos) {
            $id = Usuario::crear($nombre, $correo, $datos['contrasena'], $datos['rol']);
            Usuario::actualizar($id, ['debe_cambiar_contrasena' => 1]);
            return $id;
        });
        return Respuesta::exito("Usuario $nombre creado.", ['id_usuario' => $id], 201);
    }

    // PUT /admin/usuarios/{id} — {rol, activo, nombre_usuario, correo}
    public function actualizarUsuario(): Respuesta
    {
        $usuario = $this->usuarioDeRuta();
        $id = (int)$usuario['id_usuario'];
        $datos = $this->peticion->cuerpo;

        (new Validador($datos))
            ->nombreUsuario('nombre_usuario')->correo('correo')
            ->enLista('rol', 'El rol', Rol::codigos())
            ->validar();

        $cambios = [];
        if (isset($datos['rol']) && $datos['rol'] !== $usuario['rol']) {
            $cambios['id_rol'] = Rol::idPorCodigo($datos['rol']);
        }
        if (array_key_exists('activo', $datos)) {
            $cambios['activo'] = TorneoControlador::booleano($datos['activo']) ? 1 : 0;
        }
        foreach (['nombre_usuario', 'correo'] as $campo) {
            if (!empty($datos[$campo])) {
                $cambios[$campo] = $campo === 'correo' ? mb_strtolower(trim($datos[$campo])) : trim($datos[$campo]);
            }
        }

        $esElMismo = $id === $this->idUsuario();
        $dejaDeSerAdmin = isset($cambios['id_rol']) && $usuario['rol'] === Rol::ADMINISTRADOR;
        $seDesactiva = isset($cambios['activo']) && $cambios['activo'] === 0 && $usuario['activo'];
        if ($esElMismo && ($dejaDeSerAdmin || $seDesactiva)) {
            throw ErrorHttp::conflicto('No podés quitarte el rol de administrador ni desactivar tu propia cuenta.');
        }
        if (($dejaDeSerAdmin || ($seDesactiva && $usuario['rol'] === Rol::ADMINISTRADOR)) && $this->administradoresActivos() <= 1) {
            throw ErrorHttp::conflicto('Tiene que quedar al menos un administrador activo.');
        }
        if (isset($cambios['nombre_usuario']) || isset($cambios['correo'])) {
            $conflictos = Usuario::conflictos($cambios['nombre_usuario'] ?? $usuario['nombre_usuario'], $cambios['correo'] ?? $usuario['correo'], $id);
            if ($conflictos) {
                throw new ErrorValidacion($conflictos);
            }
        }

        BaseDatos::transaccion(function () use ($id, $cambios, $seDesactiva) {
            Usuario::actualizar($id, $cambios);
            if ($seDesactiva) {
                TokenUsuario::eliminarDeUsuario($id, 'recordarme'); // que no vuelva a entrar con "recordarme"
            }
        });
        return Respuesta::exito('Usuario actualizado.', ['usuario' => Usuario::buscarPorId($id)]);
    }

    // DELETE /admin/usuarios/{id} — solo usuarios sin actividad; al resto se los desactiva.
    public function eliminarUsuario(): Respuesta
    {
        $usuario = $this->usuarioDeRuta();
        $id = (int)$usuario['id_usuario'];
        if ($id === $this->idUsuario()) {
            throw ErrorHttp::conflicto('No podés eliminar tu propia cuenta.');
        }
        if (Usuario::tieneActividad($id)) {
            throw ErrorHttp::conflicto('Este usuario tiene torneos, equipos o resultados asociados: desactivalo en lugar de eliminarlo para no perder el historial.');
        }
        if ($usuario['rol'] === Rol::ADMINISTRADOR && $usuario['activo'] && $this->administradoresActivos() <= 1) {
            throw ErrorHttp::conflicto('Tiene que quedar al menos un administrador activo.');
        }
        Usuario::eliminar($id);
        return Respuesta::exito("Usuario {$usuario['nombre_usuario']} eliminado.");
    }

    // GET /admin/torneos — todos, incluidos los ocultos y cancelados
    public function torneos(): Respuesta
    {
        [$pagina, $porPagina] = $this->paginacion(20);
        [$torneos, $total] = Torneo::listar([
            'buscar' => mb_substr((string)$this->peticion->param('buscar', ''), 0, 100),
            'estado' => $this->peticion->param('estado'),
            'modulo' => $this->peticion->param('modulo'),
        ], $pagina, $porPagina, false);
        return $this->listado('torneos', $torneos, $total, $pagina, $porPagina);
    }

    // GET /admin/modulos
    public function modulos(): Respuesta
    {
        return Respuesta::exito('', ['modulos' => Modulo::listar()]);
    }

    // PUT /admin/modulos/{id} — {habilitado}
    public function actualizarModulo(): Respuesta
    {
        $modulo = Modulo::buscar($this->peticion->idRuta());
        if (!$modulo) {
            throw ErrorHttp::noEncontrado('Módulo no encontrado.');
        }
        $habilitado = TorneoControlador::booleano($this->peticion->dato('habilitado'));
        if (!$habilitado) {
            $otrosHabilitados = array_filter(Modulo::listar(true), fn($m) => (int)$m['id_modulo'] !== (int)$modulo['id_modulo']);
            if (!$otrosHabilitados) {
                throw ErrorHttp::conflicto('Tiene que quedar al menos un formato habilitado para poder crear torneos.');
            }
        }
        Modulo::cambiarHabilitado((int)$modulo['id_modulo'], $habilitado);
        return Respuesta::exito($habilitado
            ? "{$modulo['nombre']} habilitado."
            : "{$modulo['nombre']} deshabilitado: ya no se ofrece al crear torneos (los que ya existen siguen funcionando).");
    }

    // GET /admin/configuracion
    public function configuracion(): Respuesta
    {
        return Respuesta::exito('', ['configuracion' => ConfiguracionSistema::todas()]);
    }

    // PUT /admin/configuracion — {clave: valor, ...}
    public function actualizarConfiguracion(): Respuesta
    {
        $valores = $this->peticion->cuerpo;
        unset($valores['_csrf']);
        if (!$valores) {
            throw ErrorValidacion::campo('configuracion', 'No se mandó ningún valor para cambiar.');
        }
        $errores = [];
        BaseDatos::transaccion(function () use ($valores, &$errores) {
            foreach ($valores as $clave => $valor) {
                try {
                    ConfiguracionSistema::actualizar((string)$clave, $valor);
                } catch (ErrorValidacion $e) {
                    $errores += $e->errores;
                }
            }
            if ($errores) {
                throw new ErrorValidacion($errores); // deshace todo: o se guardan todos o ninguno
            }
        });
        return Respuesta::exito('Configuración guardada.', ['configuracion' => ConfiguracionSistema::todas()]);
    }

    // GET /admin/auditoria?tabla=&accion=&origen=&id_usuario=&desde=&hasta=&buscar=&pagina=
    public function auditoria(): Respuesta
    {
        [$pagina, $porPagina] = $this->paginacion(25);
        $filtros = [];
        foreach (['tabla', 'accion', 'origen', 'id_usuario', 'desde', 'hasta', 'buscar'] as $filtro) {
            $filtros[$filtro] = mb_substr((string)$this->peticion->param($filtro, ''), 0, 100);
        }
        foreach (['desde', 'hasta'] as $fecha) {
            if ($filtros[$fecha] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtros[$fecha])) {
                throw ErrorValidacion::campo($fecha, 'La fecha tiene que tener el formato AAAA-MM-DD.');
            }
        }
        [$registros, $total] = Auditoria::listar($filtros, $pagina, $porPagina);
        return $this->listado('registros', $registros, $total, $pagina, $porPagina);
    }

    // GET /admin/reportes/{tipo} — usuarios | torneos | auditoria, en CSV
    public function reporte(): Respuesta
    {
        $tipo = $this->peticion->parametrosRuta['tipo'] ?? '';
        $fecha = date('Y-m-d');
        switch ($tipo) {
            case 'usuarios':
                [$filas] = Usuario::listar([], 1, 100000);
                return Respuesta::csv("usuarios_$fecha.csv",
                    ['ID', 'Usuario', 'Correo', 'Rol', 'Activo', 'Registro', 'Último acceso', 'Torneos creados'],
                    array_map(fn($u) => [$u['id_usuario'], $u['nombre_usuario'], $u['correo'], $u['rol'], $u['activo'] ? 'Sí' : 'No',
                                         $u['fecha_registro'], $u['ultimo_acceso'] ?? '', $u['torneos_creados']], $filas));
            case 'torneos':
                [$filas] = Torneo::listar([], 1, 100000, false);
                return Respuesta::csv("torneos_$fecha.csv",
                    ['ID', 'Nombre', 'Juego', 'Formato', 'Modalidad', 'Inscriptos', 'Cupo', 'Premio', 'Moneda', 'Inicio', 'Fin', 'Estado', 'Público', 'Organizador'],
                    array_map(fn($t) => [$t['id_torneo'], $t['nombre'], $t['juego'], $t['nombre_modulo'], $t['modalidad'], $t['inscriptos'], $t['cupo'],
                                         $t['premio'], $t['moneda'], $t['fecha_inicio'], $t['fecha_fin'], $t['estado'], $t['publicado'] ? 'Sí' : 'No', $t['organizador']], $filas));
            case 'auditoria':
                [$filas] = Auditoria::listar([], 1, 100000);
                return Respuesta::csv("auditoria_$fecha.csv",
                    ['ID', 'Fecha', 'Origen', 'Acción', 'Tabla', 'Registro', 'Usuario', 'Cuenta BD', 'IP', 'Detalle', 'Antes', 'Después'],
                    array_map(fn($a) => [$a['id_auditoria'], $a['fecha'], $a['origen'], $a['accion'], $a['tabla'] ?? '', $a['id_registro'] ?? '',
                                         $a['nombre_usuario'] ?? '', $a['usuario_bd'] ?? '', $a['ip'] ?? '', $a['detalle'] ?? '',
                                         $a['valores_anteriores'] ? json_encode($a['valores_anteriores'], JSON_UNESCAPED_UNICODE) : '',
                                         $a['valores_nuevos'] ? json_encode($a['valores_nuevos'], JSON_UNESCAPED_UNICODE) : ''], $filas));
            default:
                throw ErrorHttp::noEncontrado('Ese reporte no existe. Opciones: usuarios, torneos, auditoria.');
        }
    }

    // ── Auxiliares ──

    private function usuarioDeRuta(): array
    {
        $usuario = Usuario::buscarPorId($this->peticion->idRuta());
        if (!$usuario) {
            throw ErrorHttp::noEncontrado('Usuario no encontrado.');
        }
        return $usuario;
    }

    private function administradoresActivos(): int
    {
        [, $total] = Usuario::listar(['rol' => Rol::ADMINISTRADOR, 'activo' => 1], 1, 1);
        return $total;
    }
}
