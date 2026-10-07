<?php
// Tests del perfil propio y del perfil público.

final class PerfilApiTest extends CasoApi
{
    private const PNG_1x1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    // Arma un "archivo subido" como lo entrega PHP en $_FILES.
    public static function archivo(string $nombre, string $contenido): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'nl');
        file_put_contents($ruta, $contenido);
        return ['name' => $nombre, 'type' => 'application/octet-stream', 'tmp_name' => $ruta, 'error' => UPLOAD_ERR_OK, 'size' => strlen($contenido)];
    }

    public static function png(): string
    {
        return base64_decode(self::PNG_1x1);
    }

    public function testElPerfilPublicoNoMuestraElCorreo(): void
    {
        $id = $this->crearUsuario('ana');
        $perfil = $this->assertOk((new ClienteApi())->get("/usuarios/$id"))->cuerpo['usuario'];
        $this->assertSame('ana', $perfil['nombre_usuario']);
        $this->assertArrayNotHasKey('correo', $perfil, 'Antes cualquiera podía juntar los correos de todos cambiando el id');
    }

    public function testElPerfilPropioSiMuestraElCorreo(): void
    {
        $this->assertSame('ana@prueba.uy', $this->assertOk($this->cliente('ana')->get('/perfil'))->cuerpo['usuario']['correo']);
    }

    public function testCambiarElCorreoOLaContrasenaPideLaContrasenaActual(): void
    {
        $ana = $this->cliente('ana');
        $this->assertErrorEnCampo('contrasena_actual', $ana->put('/perfil', ['correo' => 'otro@prueba.uy']));
        $this->assertErrorEnCampo('contrasena_actual', $ana->put('/perfil', ['contrasena_nueva' => 'Nueva#Clave9', 'contrasena_actual' => 'Mal#Clave1']));
        // Cambiar solo la biografía no la pide
        $this->assertOk($ana->put('/perfil', ['biografia' => 'Juego al ajedrez']));
    }

    public function testCambiarLaContrasenaConLaActualCorrectaQuitaElCambioObligatorio(): void
    {
        $ana = $this->cliente('ana');
        $id = $ana->sesion['id_usuario'];
        Usuario::actualizar($id, ['debe_cambiar_contrasena' => 1]);
        $this->assertOk($ana->put('/perfil', ['contrasena_actual' => self::CLAVE, 'contrasena_nueva' => 'Nueva#Clave9']));
        $this->assertTrue(Usuario::verificarContrasena($id, 'Nueva#Clave9'));
        $this->assertSame(0, Usuario::buscarPorId($id)['debe_cambiar_contrasena']);
        $ultimo = BaseDatos::valor("SELECT valores_nuevos FROM auditoria WHERE tabla = 'usuarios' AND accion = 'editar' ORDER BY id_auditoria DESC LIMIT 1");
        $this->assertTrue(json_decode($ultimo, true)['contrasena_cambiada'], 'La auditoría registra que cambió, sin guardar el hash');
    }

    public function testLaFotoSeValidaPorSuContenidoReal(): void
    {
        $ana = $this->cliente('ana');
        // Un PHP disfrazado de imagen se rechaza
        $falsa = $ana->post('/perfil/foto', [], ['foto' => self::archivo('foto.png', '<?php echo "hackeado"; ?>')]);
        $this->assertErrorEnCampo('foto', $falsa);

        $ok = $this->assertOk($ana->post('/perfil/foto', [], ['foto' => self::archivo('yo.png', self::png())]));
        $nombre = $ok->cuerpo['foto_perfil'];
        $this->assertMatchesRegularExpression('/^usuario_\d+_[a-f0-9]{16}\.png$/', $nombre, 'Nombre generado al azar, no el que mandó el usuario');
        $this->assertFileExists(Archivo::carpeta('perfiles') . "/$nombre");

        $this->assertOk($ana->delete('/perfil/foto'));
        $this->assertFileDoesNotExist(Archivo::carpeta('perfiles') . "/$nombre");
    }

    public function testMisTorneosCreadosYEnLosQueJuego(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        $id = $this->crearTorneo($organizador, ['nombre' => 'Abierto de ajedrez']);
        [$jugador] = $this->inscribirJugadores($id, 1);

        $this->assertSame(['Abierto de ajedrez'], array_column($organizador->get('/perfil/torneos?tipo=creados')->cuerpo['torneos'], 'nombre'));
        $this->assertSame(['Abierto de ajedrez'], array_column($jugador->get('/perfil/torneos?tipo=participo')->cuerpo['torneos'], 'nombre'));
    }

    public function testElPerfilPublicoMuestraEstadisticasPartidosYTorneosQueOrganiza(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        $id = $this->crearTorneo($organizador, ['nombre' => 'Liga del barrio']);
        [$ana, $beto] = $this->inscribirJugadores($id, 2);
        $this->assertOk($organizador->post("/torneos/$id/calendario"));
        $this->assertOk($organizador->post("/torneos/$id/rondas/publicar"));
        $publico = new ClienteApi();
        $idAna = (int)$ana->sesion['id_usuario'];

        $perfil = $publico->get("/usuarios/$idAna")->cuerpo['usuario'];
        $this->assertCount(1, $perfil['proximos']);
        $this->assertSame(['pj' => 0, 'pg' => 0, 'pe' => 0, 'pp' => 0], $perfil['partidos']);

        // Ana gana su partido (sea local o visitante)
        $partido = $ana->get('/perfil/partidos')->cuerpo['partidos'][0];
        $anaEsLocal = (int)$partido['id_local'] === (int)$partido['id_propio'];
        $this->assertOk($organizador->post("/enfrentamientos/{$partido['id_enfrentamiento']}/resultado",
            $anaEsLocal ? ['puntaje_local' => 2, 'puntaje_visitante' => 0] : ['puntaje_local' => 0, 'puntaje_visitante' => 2]));

        $perfil = $publico->get("/usuarios/$idAna")->cuerpo['usuario'];
        $this->assertSame(['pj' => 1, 'pg' => 1, 'pe' => 0, 'pp' => 0], $perfil['partidos']);
        $this->assertCount(1, $perfil['recientes']);
        $this->assertCount(0, $perfil['proximos']);
        $this->assertCount(1, $beto->get('/perfil/partidos')->cuerpo['recientes']);

        $perfilOrganizador = $publico->get('/usuarios/' . $organizador->sesion['id_usuario'])->cuerpo['usuario'];
        $this->assertSame(['Liga del barrio'], array_column($perfilOrganizador['organiza'], 'nombre'));
    }

    public function testMisProximosPartidosSoloMuestraRondasPublicadas(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        $id = $this->crearTorneo($organizador);
        [$ana] = $this->inscribirJugadores($id, 2);
        $this->assertOk($organizador->post("/torneos/$id/calendario"));
        $this->assertCount(0, $ana->get('/perfil/partidos')->cuerpo['partidos'], 'Ronda en borrador: todavía no se ve');
        $this->assertOk($organizador->post("/torneos/$id/rondas/publicar"));
        $this->assertCount(1, $ana->get('/perfil/partidos')->cuerpo['partidos']);
    }
}
