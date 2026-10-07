<?php
// Tests de registro, inicio de sesión, "recordarme" y recuperación de contraseña.

final class AutenticacionApiTest extends CasoApi
{
    private function registrar(array $datos): Respuesta
    {
        return (new ClienteApi())->post('/auth/registro', $datos + [
            'nombre_usuario' => 'nuevo_jugador', 'correo' => 'nuevo@prueba.uy', 'contrasena' => self::CLAVE, 'rol' => 'participante',
        ]);
    }

    private function login(ClienteApi $cliente, string $correo, string $clave, bool $recordar = false): Respuesta
    {
        return $cliente->post('/auth/login', ['correo' => $correo, 'contrasena' => $clave, 'recordar' => $recordar]);
    }

    // ── Registro ──

    public function testElRegistroCreaLaCuentaConElRolElegido(): void
    {
        $this->assertEstado(201, $this->registrar(['rol' => 'organizador']));
        $this->assertSame('organizador', Usuario::buscarPorNombre('nuevo_jugador')['rol']);
    }

    public function testElRegistroMarcaCadaCampoConError(): void
    {
        $respuesta = (new ClienteApi())->post('/auth/registro', ['nombre_usuario' => 'a b', 'correo' => 'no-es-correo', 'contrasena' => 'corta', 'rol' => '']);
        $this->assertEstado(422, $respuesta);
        $this->assertEqualsCanonicalizing(['nombre_usuario', 'correo', 'contrasena', 'rol'], array_keys($respuesta->cuerpo['errores']));
    }

    public function testNadiePuedeRegistrarseComoAdministrador(): void
    {
        $this->assertErrorEnCampo('rol', $this->registrar(['rol' => 'administrador']));
    }

    public function testNoSeRepitenNombreNiCorreo(): void
    {
        $this->crearUsuario('repetido');
        $respuesta = $this->registrar(['nombre_usuario' => 'repetido', 'correo' => 'repetido@prueba.uy']);
        $this->assertEstado(422, $respuesta);
        $this->assertEqualsCanonicalizing(['nombre_usuario', 'correo'], array_keys($respuesta->cuerpo['errores']));
    }

    public function testElAdministradorPuedeCerrarElRegistro(): void
    {
        $this->assertTrue((new ClienteApi())->get('/auth/sesion')->cuerpo['registro_abierto']);
        $this->assertOk($this->admin()->put('/admin/configuracion', ['registro_abierto' => '0']));
        $this->assertEstado(403, $this->registrar([]));
        ConfiguracionSistema::olvidarCache();
        $this->assertFalse((new ClienteApi())->get('/auth/sesion')->cuerpo['registro_abierto'], 'La página lo sabe antes de mostrar el formulario');
    }

    // ── Inicio de sesión ──

    public function testLoginCorrectoDevuelveElUsuarioYQuedaRegistrado(): void
    {
        $this->crearUsuario('ana');
        $respuesta = $this->assertOk($this->login(new ClienteApi(), 'ana@prueba.uy', self::CLAVE));
        $this->assertSame('ana', $respuesta->cuerpo['usuario']['nombre_usuario']);
        $this->assertArrayNotHasKey('contrasena_hash', $respuesta->cuerpo['usuario']);
        $this->assertSame('iniciar_sesion', $this->ultimaAuditoria('iniciar_sesion')['accion']);
    }

    public function testElErrorNoRevelaSiElCorreoExiste(): void
    {
        $this->crearUsuario('ana');
        $claveMal = $this->login(new ClienteApi(), 'ana@prueba.uy', 'Otra#Clave1');
        $noExiste = $this->login(new ClienteApi(), 'nadie@prueba.uy', 'Otra#Clave1');
        $this->assertSame([401, 401], [$claveMal->estado, $noExiste->estado]);
        $this->assertSame($claveMal->cuerpo['mensaje'], $noExiste->cuerpo['mensaje']);
    }

    public function testDespuesDeCincoFallosElCorreoQuedaBloqueado(): void
    {
        $this->crearUsuario('ana');
        for ($i = 1; $i <= 3; $i++) {
            $tercero = $this->assertEstado(401, $this->login(new ClienteApi("10.1.1.$i"), 'ana@prueba.uy', 'Mal#Clave1'));
        }
        $this->assertStringNotContainsString('intento', $tercero->cuerpo['mensaje']);
        // El 4.º avisa que queda uno solo; el 5.º es el que bloquea y ya lo dice
        $cuarto = $this->assertEstado(401, $this->login(new ClienteApi('10.1.1.4'), 'ana@prueba.uy', 'Mal#Clave1'));
        $this->assertStringContainsString('Te queda 1 intento', $cuarto->cuerpo['mensaje']);
        $quinto = $this->assertEstado(429, $this->login(new ClienteApi('10.1.1.5'), 'ana@prueba.uy', 'Mal#Clave1'));
        $this->assertStringContainsString('minuto', $quinto->cuerpo['mensaje']);
        // Ni siquiera con la contraseña correcta, ni desde otra IP
        $bloqueado = $this->login(new ClienteApi('10.9.9.9'), 'ana@prueba.uy', self::CLAVE);
        $this->assertEstado(429, $bloqueado);
        $this->assertStringContainsString('minuto', $bloqueado->cuerpo['mensaje']);
        $this->assertNotNull($this->ultimaAuditoria('bloqueo_login'));
    }

    // El aviso de "te queda 1 intento" sale igual para un correo que no existe:
    // así no sirve para averiguar quién tiene cuenta.
    public function testElAvisoDeIntentosNoRevelaSiElCorreoExiste(): void
    {
        $this->crearUsuario('ana');
        foreach (['ana@prueba.uy', 'nadie@prueba.uy'] as $correo) {
            for ($i = 1; $i <= 4; $i++) {
                $respuesta = $this->login(new ClienteApi("10.2.2.$i"), $correo, 'Mal#Clave1');
            }
            $mensajes[] = [$respuesta->estado, $respuesta->cuerpo['mensaje']];
        }
        $this->assertSame($mensajes[0], $mensajes[1]);
        $this->assertStringContainsString('Te queda 1 intento', $mensajes[0][1]);
    }

    public function testUnaCuentaDesactivadaNoPuedeEntrar(): void
    {
        $id = $this->crearUsuario('ana');
        Usuario::actualizar($id, ['activo' => 0]);
        $this->assertEstado(403, $this->login(new ClienteApi(), 'ana@prueba.uy', self::CLAVE));
    }

    // ── La sesión refleja cambios al instante (B5) ──

    public function testUnUsuarioDesactivadoPierdeLaSesionEnLaSiguientePeticion(): void
    {
        $ana = $this->cliente('ana');
        $this->assertTrue($ana->get('/auth/sesion')->cuerpo['logueado']);

        $idAna = $ana->sesion['id_usuario'];
        $this->assertOk($this->admin()->put("/admin/usuarios/$idAna", ['activo' => false]));

        $this->assertFalse($ana->get('/auth/sesion')->cuerpo['logueado']);
        $this->assertEstado(401, $ana->get('/perfil'));
    }

    public function testElCambioDeRolSeAplicaSinVolverAIniciarSesion(): void
    {
        $ana = $this->cliente('ana');
        $this->assertEstado(403, $ana->post('/torneos', ['nombre' => 'Mi torneo']));
        $this->assertOk($this->admin()->put('/admin/usuarios/' . $ana->sesion['id_usuario'], ['rol' => 'organizador']));
        $this->crearTorneo($ana); // ahora sí puede
    }

    public function testQuienDebeCambiarLaContrasenaSoloPuedeIrASuPerfil(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        Usuario::actualizar($organizador->sesion['id_usuario'], ['debe_cambiar_contrasena' => 1]);

        $bloqueado = $this->assertEstado(403, $organizador->post('/torneos', ['nombre' => 'X']));
        $this->assertTrue($bloqueado->cuerpo['debe_cambiar_contrasena']);
        $this->assertEstado(403, $organizador->get('/perfil/torneos'));
        $this->assertOk($organizador->get('/auth/sesion'));
        $this->assertOk($organizador->get('/perfil'));

        $this->assertOk($organizador->put('/perfil', ['contrasena_actual' => self::CLAVE, 'contrasena_nueva' => 'Nueva#Clave9']));
        $this->crearTorneo($organizador); // ya puede usar todo
    }

    public function testElAdminInicialEstaObligadoACambiarLaContrasena(): void
    {
        $admin = ClienteApi::como(1); // tal como queda después de instalar
        $this->assertEstado(403, $admin->get('/admin/usuarios'));
    }

    public function testLaSesionVencePorInactividad(): void
    {
        $ana = $this->cliente('ana');
        $ana->sesion['ultima_actividad'] = time() - 3 * 3600; // 3 horas sin actividad (límite: 120 minutos)
        $this->assertFalse($ana->get('/auth/sesion')->cuerpo['logueado']);
    }

    // ── Recordarme (M2) ──

    public function testRecordarmeMantieneLaSesionConUnaCookieQueSeRenueva(): void
    {
        $this->crearUsuario('ana');
        $navegador = new ClienteApi();
        $this->assertOk($this->login($navegador, 'ana@prueba.uy', self::CLAVE, true));
        $cookie = $navegador->cookies[Sesion::COOKIE_RECORDARME] ?? null;
        $this->assertNotNull($cookie, 'Se manda la cookie "recordarme"');

        // En la base solo está el hash del validador, no el validador.
        [$selector, $validador] = explode(':', $cookie);
        $hash = BaseDatos::valor("SELECT hash_validador FROM tokens_usuario WHERE selector = ?", [$selector]);
        $this->assertSame(hash('sha256', $validador), $hash);

        // Al día siguiente: se cerró el navegador (sin sesión), pero vuelve con la cookie.
        $manana = new ClienteApi();
        $manana->cookies = $navegador->cookies;
        $sesion = $manana->get('/auth/sesion');
        $this->assertTrue($sesion->cuerpo['logueado']);
        $this->assertNotSame($cookie, $manana->cookies[Sesion::COOKIE_RECORDARME], 'La cookie se cambia en cada uso');

        // La cookie vieja ya no sirve (si alguien la robó, no la puede reutilizar).
        $ladron = new ClienteApi();
        $ladron->cookies = [Sesion::COOKIE_RECORDARME => $cookie];
        $this->assertFalse($ladron->get('/auth/sesion')->cuerpo['logueado']);
    }

    public function testCerrarSesionBorraElRecordarmeYSoloSeHacePorPost(): void
    {
        $this->crearUsuario('ana');
        $navegador = new ClienteApi();
        $this->login($navegador, 'ana@prueba.uy', self::CLAVE, true);
        $this->assertEstado(405, $navegador->get('/auth/logout'));

        $this->assertOk($navegador->post('/auth/logout'));
        $this->assertFalse($navegador->get('/auth/sesion')->cuerpo['logueado']);
        $this->assertArrayNotHasKey(Sesion::COOKIE_RECORDARME, $navegador->cookies);
        $this->assertSame(0, (int)BaseDatos::valor("SELECT COUNT(*) FROM tokens_usuario WHERE tipo = 'recordarme'"));
    }

    // ── Recuperar contraseña (S1, S5, B6) ──

    public function testEnProduccionElLinkDeRecuperacionSoloViajaPorCorreo(): void
    {
        $this->crearUsuario('ana');
        Config::establecer(['entorno' => 'produccion']);
        try {
            $respuesta = $this->assertOk((new ClienteApi())->post('/auth/recuperar', ['correo' => 'ana@prueba.uy']));
        } finally {
            Config::establecer(['entorno' => 'desarrollo']);
        }
        $this->assertArrayNotHasKey('link_demo', $respuesta->cuerpo, 'Si la API devolviera el link, cualquiera tomaría la cuenta');
        $this->assertStringContainsString('restablecer?token=', Correo::$ultimo['texto']);
        $this->assertSame('ana@prueba.uy', Correo::$ultimo['para']);
    }

    public function testEnDesarrolloElLinkSeMuestraParaPoderProbarSinCorreo(): void
    {
        $this->crearUsuario('ana');
        $respuesta = $this->assertOk((new ClienteApi())->post('/auth/recuperar', ['correo' => 'ana@prueba.uy']));
        $this->assertStringContainsString('restablecer?token=', $respuesta->cuerpo['link_demo']);
    }

    public function testConUnCorreoQueNoExisteLaRespuestaEsLaMisma(): void
    {
        $this->crearUsuario('ana');
        $existe = (new ClienteApi())->post('/auth/recuperar', ['correo' => 'ana@prueba.uy']);
        $noExiste = (new ClienteApi())->post('/auth/recuperar', ['correo' => 'nadie@prueba.uy']);
        $this->assertSame($existe->cuerpo['mensaje'], $noExiste->cuerpo['mensaje']);
    }

    private function tokenDeRecuperacion(): string
    {
        $link = (new ClienteApi())->post('/auth/recuperar', ['correo' => 'ana@prueba.uy'])->cuerpo['link_demo'];
        return substr($link, strpos($link, 'token=') + 6);
    }

    public function testElLinkSirveUnaSolaVezYGuardaSoloElHash(): void
    {
        $this->crearUsuario('ana');
        $token = $this->tokenDeRecuperacion();
        [$selector, $validador] = explode('.', $token);
        $this->assertSame(hash('sha256', $validador),
            BaseDatos::valor("SELECT hash_validador FROM tokens_usuario WHERE selector = ?", [$selector]));

        $this->assertOk((new ClienteApi())->post('/auth/restablecer', ['token' => $token, 'contrasena' => 'Nueva#Clave9']));
        $this->assertOk($this->login(new ClienteApi(), 'ana@prueba.uy', 'Nueva#Clave9'));
        $this->assertEstado(400, (new ClienteApi())->post('/auth/restablecer', ['token' => $token, 'contrasena' => 'Otra#Clave99']));
    }

    public function testElLinkVenceALosTreintaMinutos(): void
    {
        $this->crearUsuario('ana');
        $token = $this->tokenDeRecuperacion();
        $selector = explode('.', $token)[0];
        $minutos = (int)BaseDatos::valor("SELECT TIMESTAMPDIFF(SECOND, NOW(), vence) FROM tokens_usuario WHERE selector = ?", [$selector]);
        $this->assertEqualsWithDelta(30 * 60, $minutos, 5, 'Vence en 30 minutos (con la hora de PHP y MySQL alineadas)');

        BaseDatos::ejecutar("UPDATE tokens_usuario SET vence = NOW() - INTERVAL 1 MINUTE WHERE selector = ?", [$selector]);
        $this->assertEstado(400, (new ClienteApi())->post('/auth/restablecer', ['token' => $token, 'contrasena' => 'Nueva#Clave9']));
    }

    public function testUnTokenInventadoNoSirve(): void
    {
        $this->assertEstado(400, (new ClienteApi())->post('/auth/restablecer', ['token' => str_repeat('a', 24) . '.' . str_repeat('b', 64), 'contrasena' => 'Nueva#Clave9']));
    }
}
