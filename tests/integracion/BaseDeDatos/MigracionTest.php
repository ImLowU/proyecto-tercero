<?php
// Verifica la migración desde la versión anterior usando el volcado real
// del servidor (database/legado/seed_datos_prueba.sql): no se pierde nada
// y queda en el formato nuevo.

use PHPUnit\Framework\TestCase;

final class MigracionTest extends TestCase
{
    private const BASE = 'nextleague_migracion_test';
    private static PDO $pdo;
    private static array $antes = [];

    // La base vieja se arma y se migra una sola vez para todos los tests de esta clase.
    public static function setUpBeforeClass(): void
    {
        self::$pdo = BaseDePruebas::admin();
        self::$pdo->exec("DROP DATABASE IF EXISTS `" . self::BASE . "`");
        self::$pdo->exec("CREATE DATABASE `" . self::BASE . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        self::$pdo->exec("USE `" . self::BASE . "`");

        $legado = __DIR__ . '/../../../database/legado/';
        // El script viejo trae "CREATE DATABASE nextleague" y "USE nextleague":
        // se descartan para que no toque ninguna otra base.
        foreach (EjecutorSql::dividir(file_get_contents($legado . 'nextleague.sql')) as $sentencia) {
            if (!preg_match('/^(CREATE DATABASE|USE)\b/i', $sentencia)) {
                self::$pdo->exec($sentencia);
            }
        }
        EjecutorSql::ejecutarArchivo(self::$pdo, $legado . 'seed_datos_prueba.sql');

        foreach (['usuarios', 'torneos', 'equipos', 'equipo_miembros', 'equipo_solicitudes', 'participantes', 'enfrentamientos', 'auditoria'] as $tabla) {
            self::$antes[$tabla] = (int)self::$pdo->query("SELECT COUNT(*) FROM $tabla")->fetchColumn();
        }

        (new Instalador(self::$pdo))->migrar(self::BASE);
        self::$pdo->exec("USE `" . self::BASE . "`");
    }

    public static function tearDownAfterClass(): void
    {
        self::$pdo->exec("DROP DATABASE IF EXISTS `" . self::BASE . "`");
    }

    private function valor(string $sql): mixed
    {
        return self::$pdo->query($sql)->fetchColumn();
    }

    public function testNoSePierdeNingunRegistro(): void
    {
        foreach (self::$antes as $tabla => $cantidad) {
            $esperado = $tabla === 'auditoria' ? $cantidad + 1 : $cantidad; // + el evento "migrar"
            $this->assertSame($esperado, (int)$this->valor("SELECT COUNT(*) FROM $tabla"), $tabla);
        }
    }

    public function testNoQuedanTablasViejas(): void
    {
        $this->assertSame(0, (int)$this->valor(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . self::BASE . "'
             AND (TABLE_NAME LIKE 'v6\\_%' OR TABLE_NAME IN ('tabla_posiciones', 'llaves'))"));
    }

    public function testLosRolesPasanALaTablaDeRoles(): void
    {
        $roles = self::$pdo->query("SELECT u.nombre_usuario, r.codigo FROM usuarios u JOIN roles r USING (id_rol)
                                    WHERE u.nombre_usuario IN ('admin', 'usuario1', 'usuario5')
                                    ORDER BY u.id_usuario")->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame(['admin' => 'administrador', 'usuario1' => 'organizador', 'usuario5' => 'participante'], $roles);
    }

    public function testQuienSigueConLaContrasenaPublicaDebeCambiarla(): void
    {
        $this->assertSame(1, (int)$this->valor("SELECT debe_cambiar_contrasena FROM usuarios WHERE nombre_usuario = 'admin'"));
        $this->assertSame(0, (int)$this->valor("SELECT debe_cambiar_contrasena FROM usuarios WHERE nombre_usuario = 'usuario1'"));
    }

    public function testElTorneoConservaSuFormatoYSeDetectaLaModalidad(): void
    {
        $torneo = self::$pdo->query("SELECT nombre, modulo, modalidad, cupo, inscriptos, estado FROM vista_torneos WHERE id_torneo = 1")->fetch();
        $this->assertSame(['Copa Demostración NextLeague', 'eliminacion_directa', 'equipos', 6, 6, 'en_curso'],
            [$torneo['nombre'], $torneo['modulo'], $torneo['modalidad'], (int)$torneo['cupo'], (int)$torneo['inscriptos'], $torneo['estado']]);
    }

    public function testLaLlaveQuedaIgualConPasesLibresYResultados(): void
    {
        $partidos = self::$pdo->query("SELECT ronda, posicion, nombre_local, nombre_visitante, puntaje_local, puntaje_visitante, es_pase_libre, estado
                                       FROM vista_calendario WHERE id_torneo = 1 ORDER BY ronda, posicion")->fetchAll();
        $resumen = array_map(fn($p) => sprintf('R%d.%d %s vs %s [%s] %s',
            $p['ronda'], $p['posicion'], $p['nombre_local'] ?? '?', $p['nombre_visitante'] ?? '?',
            $p['puntaje_local'] === null ? '' : $p['puntaje_local'] . '-' . $p['puntaje_visitante'],
            $p['es_pase_libre'] ? 'pase libre' : $p['estado']), $partidos);

        $this->assertSame([
            'R1.0 Halcones vs ? [] pase libre',
            'R1.1 Panteras vs ? [] pase libre',
            'R1.2 Leones vs Aguilas [2-1] finalizado',
            'R1.3 Lobos vs Tigres [] pendiente',
            'R2.0 Halcones vs Panteras [] pendiente',
            'R2.1 Leones vs ? [] pendiente',
            'R3.0 ? vs ? [] pendiente',
        ], $resumen);
    }

    public function testElGanadorDeCadaPartidoApuntaAlParticipanteCorrecto(): void
    {
        $this->assertSame('Leones', $this->valor(
            "SELECT p.nombre FROM enfrentamientos e JOIN vista_participantes p ON p.id_participante = e.id_ganador
             JOIN rondas r ON r.id_ronda = e.id_ronda WHERE r.id_torneo = 1 AND r.numero = 1 AND e.posicion = 2"));
    }

    public function testSeConservaElHistorialDeAuditoriaYSeRegistraLaMigracion(): void
    {
        $this->assertSame(30, (int)$this->valor("SELECT COUNT(*) FROM auditoria WHERE origen = 'app' AND accion <> 'migrar'"));
        $this->assertSame(1, (int)$this->valor("SELECT COUNT(*) FROM auditoria WHERE accion = 'migrar'"));
    }

    public function testDespuesDeMigrarFuncionanLosTriggers(): void
    {
        self::$pdo->beginTransaction();
        self::$pdo->exec("UPDATE torneos SET nombre = 'Copa Migrada' WHERE id_torneo = 1");
        $origen = $this->valor("SELECT origen FROM auditoria WHERE tabla = 'torneos' ORDER BY id_auditoria DESC LIMIT 1");
        self::$pdo->rollBack();
        $this->assertSame('bd', $origen);
    }

    public function testNoSePuedeMigrarDosVeces(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('formato de la versión anterior');
        (new Instalador(self::$pdo))->migrar(self::BASE);
    }
}
