<?php
// Tests de seguridad (OWASP): CSRF, inyección SQL, XSS, archivos,
// cabeceras, errores sin detalles y CSV sin fórmulas.

final class SeguridadApiTest extends CasoApi
{
    public function testSinTokenCsrfSeRechazaCualquierModificacion(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        $id = $this->crearTorneo($organizador);
        $organizador->enviarCsrf = false;
        foreach ([['POST', '/torneos'], ['PUT', "/torneos/$id/publicacion"], ['DELETE', "/torneos/$id"], ['POST', '/auth/logout']] as [$metodo, $ruta]) {
            $this->assertEstado(403, $organizador->pedir($metodo, $ruta));
        }
        $this->assertNotNull(Torneo::buscar($id), 'El torneo sigue existiendo');
    }

    public function testUnTokenCsrfDeOtraSesionNoSirve(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        $id = $this->crearTorneo($organizador);
        $organizador->enviarCsrf = false;
        $respuesta = $organizador->pedir('DELETE', "/torneos/$id", [], [], ['X-CSRF-Token' => bin2hex(random_bytes(32))]);
        $this->assertEstado(403, $respuesta);
    }

    public function testLaInyeccionSqlEnLosFiltrosNoDevuelveDatosDeMas(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        $this->crearTorneo($organizador, ['nombre' => 'Visible']);
        $this->crearTorneo($organizador, ['nombre' => 'Oculto', 'publicado' => '0']);
        $publico = new ClienteApi();

        foreach (["' OR 1=1 -- ", "x%' UNION SELECT contrasena_hash FROM usuarios -- ", '"; DROP TABLE torneos; --'] as $ataque) {
            $respuesta = $this->assertOk($publico->get('/torneos?buscar=' . urlencode($ataque)));
            $this->assertSame(0, $respuesta->cuerpo['paginacion']['total'], "Ataque: $ataque");
        }
        $respuesta = $this->assertOk($publico->get('/torneos?estado=' . urlencode("pendiente' OR '1'='1")));
        $this->assertSame(0, $respuesta->cuerpo['paginacion']['total']);
        $this->assertNotNull(BaseDatos::valor("SELECT COUNT(*) FROM torneos"), 'La tabla sigue existiendo');
    }

    // En LIKE, "%" y "_" son comodines: lo que escribe la persona se busca como
    // texto común (antes "_" devolvía todos los torneos y "100%" encontraba "1000").
    public function testLosComodinesDeLaBusquedaSeTomanComoTexto(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        $this->crearTorneo($organizador, ['nombre' => 'Copa 100% Ajedrez']);
        $this->crearTorneo($organizador, ['nombre' => 'Copa 1000 de Primavera']);
        $publico = new ClienteApi();
        $total = fn(string $texto) => $this->assertOk($publico->get('/torneos?buscar=' . urlencode($texto)))->cuerpo['paginacion']['total'];

        $this->assertSame(1, $total('100%'), 'El % es parte del nombre, no "cualquier cosa"');
        $this->assertSame(0, $total('_'), 'Ningún torneo tiene un guion bajo');
        $this->assertSame(1, $total('%'), 'Un % solo encuentra el único nombre que lo tiene, no todos');
        $this->assertSame(2, $total('Copa'), 'La búsqueda común sigue igual');

        $usuarios = $this->assertOk($this->admin()->get('/admin/usuarios?buscar=' . urlencode('%')))->cuerpo;
        $this->assertSame(0, $usuarios['paginacion']['total'], 'Lo mismo en el listado de usuarios');
    }

    public function testLaInyeccionSqlEnElLoginNoEntra(): void
    {
        $respuesta = (new ClienteApi())->post('/auth/login', ['correo' => "admin@nextleague.com' -- ", 'contrasena' => "' OR '1'='1"]);
        $this->assertEstado(401, $respuesta);
    }

    public function testUnNombreConCodigoSeGuardaComoTextoYViajaComoJson(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        $id = $this->crearTorneo($organizador, ['nombre' => '<script>alert("xss")</script>', 'modalidad' => 'equipos']);
        $respuesta = (new ClienteApi())->get("/torneos/$id");
        $this->assertSame('<script>alert("xss")</script>', $respuesta->cuerpo['torneo']['nombre'], 'Se guarda tal cual: la pantalla lo muestra como texto');
        $this->assertSame('nosniff', $respuesta->cabeceras['X-Content-Type-Options'], 'El navegador no puede interpretarlo como HTML');
    }

    public function testLasRespuestasLlevanCabecerasDeSeguridad(): void
    {
        $respuesta = (new ClienteApi())->get('/juegos');
        $this->assertSame('DENY', $respuesta->cabeceras['X-Frame-Options']);
        $this->assertSame('no-store', $respuesta->cabeceras['Cache-Control']);
        $this->assertSame('same-origin', $respuesta->cabeceras['Referrer-Policy']);
    }

    public function testEnProduccionUnErrorInternoNoMuestraDetalles(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        $id = $this->crearTorneo($organizador);
        $this->inscribirJugadores($id, 2);
        GestorCompetencia::$mezclar = function (array &$lista): void {
            throw new RuntimeException('SELECT secreto FROM tabla_interna');
        };

        Config::establecer(['entorno' => 'produccion']);
        try {
            $respuesta = $organizador->post("/torneos/$id/calendario");
        } finally {
            Config::establecer(['entorno' => 'desarrollo']);
        }
        $this->assertEstado(500, $respuesta);
        $this->assertArrayNotHasKey('detalle', $respuesta->cuerpo);
        $this->assertStringNotContainsString('SELECT', json_encode($respuesta->cuerpo));
    }

    public function testUnPhpDisfrazadoDeLogoSeRechaza(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        $respuesta = $organizador->post('/torneos', [
            'nombre' => 'Con logo trucho', 'id_juego' => 1, 'modulo' => 'liga', 'cupo' => 4,
            'fecha_inicio' => date('Y-m-d', strtotime('+1 day')), 'fecha_fin' => date('Y-m-d', strtotime('+2 days')),
        ], ['logo' => PerfilApiTest::archivo('logo.png', '<?php system($_GET["c"]); ?>')]);
        $this->assertErrorEnCampo('logo', $respuesta);
    }

    public function testLaCarpetaDeSubidasNoEjecutaPhp(): void
    {
        $reglas = file_get_contents(__DIR__ . '/../../../public/uploads/.htaccess');
        $this->assertStringContainsString('RemoveHandler .php', $reglas);
        $this->assertStringContainsString('Require all denied', $reglas);
    }

    public function testElCsvNoEjecutaFormulasDeExcel(): void
    {
        $organizador = $this->cliente('orga', Rol::ORGANIZADOR);
        $id = $this->crearTorneo($organizador, ['modalidad' => 'equipos']);
        $this->assertOk($organizador->post("/torneos/$id/participantes", ['nombre_equipo' => '=HYPERLINK("http://malo")']));
        $csv = $organizador->get("/torneos/$id/reporte")->contenidoCrudo;
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'La celda empieza con comilla: Excel la muestra como texto');
    }
}
