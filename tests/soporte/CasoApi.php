<?php
// =====================================================================
// CLASE BASE PARA LOS TESTS DE LA API
// Cada test corre en una transacción que se deshace al terminar (la
// aplicación usa SAVEPOINT para sus transacciones internas). Trae
// atajos para crear usuarios con su rol y torneos listos para jugar.
// =====================================================================

use PHPUnit\Framework\TestCase;

abstract class CasoApi extends TestCase
{
    protected const CLAVE = 'Clave#2026';
    protected PDO $pdo;
    protected static ?string $carpetaSubidas = null;

    protected function setUp(): void
    {
        $this->pdo = BaseDePruebas::app();
        BaseDatos::usar($this->pdo);
        $this->pdo->beginTransaction();

        self::$carpetaSubidas ??= sys_get_temp_dir() . '/nextleague_test_subidas';
        Config::establecer(['entorno' => 'desarrollo', 'costo_hash' => 4, 'carpeta_subidas' => self::$carpetaSubidas,
                            'url_publica' => 'http://localhost/nextleague/public']);
        Archivo::$aceptarArchivosLocales = true;
        GestorCompetencia::$mezclar = function (array &$lista): void {}; // sin sorteo: resultados repetibles
        Correo::$ultimo = null;
        Correo::$modoPrueba = true;
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $_SESSION = [];
    }

    // ── Usuarios ──

    protected function crearUsuario(string $nombre, string $rol = Rol::PARTICIPANTE): int
    {
        return Usuario::crear($nombre, "$nombre@prueba.uy", self::CLAVE, $rol);
    }

    protected function cliente(string $nombre, string $rol = Rol::PARTICIPANTE): ClienteApi
    {
        return ClienteApi::como($this->crearUsuario($nombre, $rol));
    }

    // El administrador inicial de 02_datos_base.sql. Viene marcado para cambiar
    // la contraseña; en los tests se da por cambiada (dentro de la transacción).
    protected function admin(): ClienteApi
    {
        Usuario::actualizar(1, ['debe_cambiar_contrasena' => 0]);
        return ClienteApi::como(1);
    }

    // ── Torneos ──

    // Crea un torneo con la API, como lo haría la página. Devuelve su id.
    protected function crearTorneo(ClienteApi $organizador, array $datos = []): int
    {
        $respuesta = $organizador->post('/torneos', $datos + [
            'nombre' => 'Torneo de prueba', 'id_juego' => 28, 'modulo' => 'liga', 'modalidad' => 'individual',
            'cupo' => 16, 'fecha_inicio' => date('Y-m-d', strtotime('+7 days')), 'fecha_fin' => date('Y-m-d', strtotime('+30 days')),
        ]);
        $this->assertSame(201, $respuesta->estado, json_encode($respuesta->cuerpo, JSON_UNESCAPED_UNICODE));
        return $respuesta->cuerpo['id_torneo'];
    }

    // Cambia las fechas de un torneo directamente en la base (en días desde
    // hoy), para probar lo que depende de la fecha sin esperar.
    protected function fechasDelTorneo(int $idTorneo, int $inicio, int $fin): void
    {
        BaseDatos::ejecutar('UPDATE torneos SET fecha_inicio = CURDATE() + INTERVAL ? DAY, fecha_fin = CURDATE() + INTERVAL ? DAY WHERE id_torneo = ?',
            [$inicio, $fin, $idTorneo]);
    }

    // El administrador autoriza al organizador a corregir resultados (letra, 5.2).
    protected function autorizarCorrecciones(int $idTorneo): void
    {
        $this->assertOk($this->admin()->post("/torneos/$idTorneo", ['permitir_correcciones' => 1]));
    }

    // Inscribe $cantidad jugadores nuevos a un torneo individual; devuelve sus clientes.
    protected function inscribirJugadores(int $idTorneo, int $cantidad, string $prefijo = 'jugador'): array
    {
        $clientes = [];
        for ($i = 1; $i <= $cantidad; $i++) {
            $cliente = $this->cliente("{$prefijo}{$i}");
            $this->assertOk($cliente->post("/torneos/$idTorneo/inscripcion"));
            $clientes[] = $cliente;
        }
        return $clientes;
    }

    // Torneo con calendario generado y todas las rondas publicadas.
    protected function torneoEnJuego(ClienteApi $organizador, string $modulo, int $jugadores, array $datos = []): int
    {
        $id = $this->crearTorneo($organizador, ['modulo' => $modulo] + $datos);
        $this->inscribirJugadores($id, $jugadores);
        $this->assertOk($organizador->post("/torneos/$id/calendario"));
        $this->assertOk($organizador->post("/torneos/$id/rondas/publicar"));
        return $id;
    }

    protected function calendario(ClienteApi $cliente, int $idTorneo): array
    {
        return $this->assertOk($cliente->get("/torneos/$idTorneo/calendario"))->cuerpo['rondas'];
    }

    // Partidos de una ronda que se pueden jugar (con dos rivales).
    protected function partidosJugables(ClienteApi $cliente, int $idTorneo, int $numeroRonda): array
    {
        foreach ($this->calendario($cliente, $idTorneo) as $ronda) {
            if ((int)$ronda['numero'] === $numeroRonda) {
                return array_values(array_filter($ronda['partidos'], fn($p) => $p['id_local'] && $p['id_visitante'] && !$p['es_pase_libre']));
            }
        }
        return [];
    }

    protected function cargar(ClienteApi $cliente, int $idPartido, int $local, int $visitante): Respuesta
    {
        return $cliente->post("/enfrentamientos/$idPartido/resultado", ['puntaje_local' => $local, 'puntaje_visitante' => $visitante]);
    }

    protected function estadoTorneo(int $id): string
    {
        return (string)BaseDatos::valor("SELECT estado FROM torneos WHERE id_torneo = ?", [$id]);
    }

    // ── Aserciones ──

    protected function assertOk(Respuesta $respuesta): Respuesta
    {
        $this->assertTrue($respuesta->estado < 300 && ($respuesta->cuerpo['exito'] ?? true),
            "Se esperaba éxito y llegó {$respuesta->estado}: " . json_encode($respuesta->cuerpo, JSON_UNESCAPED_UNICODE));
        return $respuesta;
    }

    protected function assertEstado(int $esperado, Respuesta $respuesta): Respuesta
    {
        $this->assertSame($esperado, $respuesta->estado, json_encode($respuesta->cuerpo, JSON_UNESCAPED_UNICODE));
        return $respuesta;
    }

    protected function assertErrorEnCampo(string $campo, Respuesta $respuesta): void
    {
        $this->assertSame(422, $respuesta->estado, json_encode($respuesta->cuerpo, JSON_UNESCAPED_UNICODE));
        $this->assertArrayHasKey($campo, $respuesta->cuerpo['errores'] ?? []);
    }

    protected function ultimaAuditoria(string $accion): ?array
    {
        return BaseDatos::fila("SELECT * FROM auditoria WHERE accion = ? ORDER BY id_auditoria DESC LIMIT 1", [$accion]);
    }
}
