<?php
// Tests del panel de administración (sección 5.1 de la letra).

final class AdminApiTest extends CasoApi
{
    public function testSoloElAdministradorEntraAlPanel(): void
    {
        $this->assertEstado(401, (new ClienteApi())->get('/admin/resumen'));
        $this->assertEstado(403, $this->cliente('ana')->get('/admin/resumen'));
        $this->assertEstado(403, $this->cliente('orga', Rol::ORGANIZADOR)->get('/admin/usuarios'));
        $this->assertOk($this->admin()->get('/admin/resumen'));
    }

    public function testCreaUsuariosAdministrativosQueDebenCambiarLaContrasena(): void
    {
        $respuesta = $this->assertEstado(201, $this->admin()->post('/admin/usuarios', [
            'nombre_usuario' => 'segundo_admin', 'correo' => 'admin2@prueba.uy', 'contrasena' => 'Temporal#2026', 'rol' => 'administrador']));
        $usuario = Usuario::buscarPorId($respuesta->cuerpo['id_usuario']);
        $this->assertSame(['administrador', 1], [$usuario['rol'], $usuario['debe_cambiar_contrasena']]);
    }

    public function testElListadoDeUsuariosFiltraYPagina(): void
    {
        $this->crearUsuario('maria_org', Rol::ORGANIZADOR);
        $this->crearUsuario('mario_jug');
        $respuesta = $this->admin()->get('/admin/usuarios?buscar=mari&rol=organizador')->cuerpo;
        $this->assertSame(['maria_org'], array_column($respuesta['usuarios'], 'nombre_usuario'));
        $this->assertSame(1, $respuesta['paginacion']['total']);
    }

    public function testElAdminNoSePuedeQuitarSuRolNiDesactivarse(): void
    {
        $this->assertEstado(409, $this->admin()->put('/admin/usuarios/1', ['rol' => 'participante']));
        $this->assertEstado(409, $this->admin()->put('/admin/usuarios/1', ['activo' => false]));
    }

    public function testSiempreQuedaAlMenosUnAdministradorActivo(): void
    {
        $otro = $this->crearUsuario('otro_admin', Rol::ADMINISTRADOR);
        $otroCliente = ClienteApi::como($otro);
        $this->assertOk($otroCliente->put('/admin/usuarios/1', ['activo' => false]));
        // Ahora "otro_admin" es el único activo: nadie puede dejarlo sin rol
        $this->assertEstado(409, $otroCliente->put("/admin/usuarios/$otro", ['rol' => 'organizador']));
    }

    public function testSoloSeEliminanUsuariosSinActividad(): void
    {
        $sinNada = $this->crearUsuario('sin_actividad');
        $organizador = $this->cliente('con_torneo', Rol::ORGANIZADOR);
        $this->crearTorneo($organizador);

        $this->assertEstado(409, $this->admin()->delete('/admin/usuarios/' . $organizador->sesion['id_usuario']));
        $this->assertOk($this->admin()->delete("/admin/usuarios/$sinNada"));
        $this->assertNull(Usuario::buscarPorId($sinNada));
        $this->assertNotNull(BaseDatos::fila("SELECT 1 FROM auditoria WHERE tabla = 'usuarios' AND accion = 'eliminar' AND id_registro = ?", [(string)$sinNada]));
    }

    public function testDesactivarBorraLosRecordarmeDelUsuario(): void
    {
        $id = $this->crearUsuario('ana');
        TokenUsuario::crear($id, 'recordarme', 60);
        $this->assertOk($this->admin()->put("/admin/usuarios/$id", ['activo' => '0']));
        $this->assertSame(0, (int)BaseDatos::valor("SELECT COUNT(*) FROM tokens_usuario WHERE id_usuario = ?", [$id]));
    }

    public function testModulosSeHabilitanYQuedaAlMenosUno(): void
    {
        $admin = $this->admin();
        $this->assertOk($admin->put('/admin/modulos/1', ['habilitado' => false]));
        $this->assertOk($admin->put('/admin/modulos/2', ['habilitado' => false]));
        $this->assertEstado(409, $admin->put('/admin/modulos/3', ['habilitado' => false]));
        $this->assertSame(['sistema_suizo'], array_column((new ClienteApi())->get('/modulos')->cuerpo['modulos'], 'codigo'));
    }

    public function testUnTorneoConUnModuloDeshabilitadoSigueFuncionando(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        $id = $this->torneoEnJuego($organizador, 'liga', 2);
        $this->assertOk($this->admin()->put('/admin/modulos/1', ['habilitado' => false]));
        $partido = $this->partidosJugables($organizador, $id, 1)[0];
        $this->assertOk($this->cargar($organizador, $partido['id_enfrentamiento'], 1, 0));
    }

    public function testLaConfiguracionValidaLosTiposYSeGuardaTodoONada(): void
    {
        $admin = $this->admin();
        $respuesta = $admin->put('/admin/configuracion', ['max_intentos_login' => '7', 'registro_abierto' => 'quizás', 'no_existe' => 'x']);
        $this->assertEstado(422, $respuesta);
        $this->assertEqualsCanonicalizing(['registro_abierto', 'no_existe'], array_keys($respuesta->cuerpo['errores']));
        $this->assertSame('5', ConfiguracionSistema::obtener('max_intentos_login'), 'No se guardó nada');

        $this->assertOk($admin->put('/admin/configuracion', ['max_intentos_login' => '7', 'nombre_sitio' => 'NextLeague ITS']));
        ConfiguracionSistema::olvidarCache();
        $this->assertSame('7', ConfiguracionSistema::obtener('max_intentos_login'));
    }

    public function testLaAuditoriaSeFiltraYMuestraElAntesYElDespues(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        $id = $this->crearTorneo($organizador, ['nombre' => 'Copa Original']);
        $organizador->post("/torneos/$id", ['nombre' => 'Copa Renombrada']);

        $registros = $this->admin()->get('/admin/auditoria?tabla=torneos&accion=editar')->cuerpo['registros'];
        $this->assertSame('Copa Original', $registros[0]['valores_anteriores']['nombre']);
        $this->assertSame('Copa Renombrada', $registros[0]['valores_nuevos']['nombre']);
        $this->assertSame('orga', $registros[0]['nombre_usuario']);
        $this->assertEstado(422, $this->admin()->get('/admin/auditoria?desde=ayer'));
    }

    public function testReportesEnCsv(): void
    {
        foreach (['usuarios', 'torneos', 'auditoria'] as $tipo) {
            $respuesta = $this->assertOk($this->admin()->get("/admin/reportes/$tipo"));
            $this->assertSame('text/csv; charset=UTF-8', $respuesta->cabeceras['Content-Type'], $tipo);
            $this->assertStringStartsWith("\xEF\xBB\xBF", $respuesta->contenidoCrudo, 'Con BOM para que Excel muestre bien los acentos');
        }
        $this->assertEstado(404, $this->admin()->get('/admin/reportes/inventado'));
    }
}
