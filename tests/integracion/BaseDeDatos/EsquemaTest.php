<?php
// Verifica que la instalación crea todos los objetos de la base:
// tablas, vistas, triggers y datos base.

final class EsquemaTest extends CasoBaseDeDatos
{
    // information_schema solo muestra los triggers a quien tiene permiso
    // sobre ellos, por eso este test se conecta como root.
    protected function conexion(): PDO
    {
        return BaseDePruebas::root();
    }

    private function objetos(string $tipo): array
    {
        return $this->filas(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ? ORDER BY TABLE_NAME",
            [$tipo]
        );
    }

    public function testExistenTodasLasTablas(): void
    {
        $tablas = array_column($this->objetos('BASE TABLE'), 'TABLE_NAME');
        $this->assertEqualsCanonicalizing([
            'auditoria', 'configuracion_sistema', 'configuracion_torneo', 'enfrentamientos', 'equipo_miembros',
            'equipo_solicitudes', 'equipos', 'intentos_login', 'juegos', 'modulos_competencia', 'participantes',
            'resultados', 'roles', 'rondas', 'tokens_usuario', 'torneos', 'usuarios',
        ], $tablas);
    }

    public function testExistenTodasLasVistas(): void
    {
        $vistas = array_column($this->objetos('VIEW'), 'TABLE_NAME');
        $this->assertEqualsCanonicalizing([
            'vista_calendario', 'vista_estadisticas', 'vista_participantes', 'vista_partidos_por_participante',
            'vista_resultados_vigentes', 'vista_tabla_posiciones', 'vista_torneos', 'vista_torneos_publicos',
        ], $vistas);
    }

    public function testCadaTablaAuditadaTieneSusTresTriggers(): void
    {
        $porTabla = $this->filas(
            "SELECT EVENT_OBJECT_TABLE AS tabla, GROUP_CONCAT(EVENT_MANIPULATION ORDER BY EVENT_MANIPULATION) AS eventos
             FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() GROUP BY EVENT_OBJECT_TABLE"
        );
        $this->assertCount(14, $porTabla);
        foreach ($porTabla as $fila) {
            $this->assertSame('DELETE,INSERT,UPDATE', $fila['eventos'], "Triggers de {$fila['tabla']}");
        }
        // Las tablas de seguridad no se auditan (guardan hashes y tienen mucho movimiento).
        $tablas = array_column($porTabla, 'tabla');
        $this->assertNotContains('tokens_usuario', $tablas);
        $this->assertNotContains('intentos_login', $tablas);
        $this->assertNotContains('auditoria', $tablas);
    }

    public function testTodasLasTablasSonInnodbConUtf8mb4(): void
    {
        $distintas = $this->filas(
            "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
               AND (ENGINE <> 'InnoDB' OR TABLE_COLLATION <> 'utf8mb4_unicode_ci')"
        );
        $this->assertSame([], $distintas);
    }

    public function testLosDatosBaseEstanCargados(): void
    {
        $this->assertSame(['administrador', 'organizador', 'participante'],
            array_column($this->filas("SELECT codigo FROM roles ORDER BY id_rol"), 'codigo'));
        $this->assertSame(['liga', 'eliminacion_directa', 'sistema_suizo'],
            array_column($this->filas("SELECT codigo FROM modulos_competencia WHERE habilitado = 1 ORDER BY id_modulo"), 'codigo'));
        $this->assertSame(38, (int)$this->valor("SELECT COUNT(*) FROM juegos"));
        $this->assertSame('individual', $this->valor("SELECT modalidad_sugerida FROM juegos WHERE nombre = 'Ajedrez'"));
        $this->assertSame('equipos', $this->valor("SELECT modalidad_sugerida FROM juegos WHERE nombre = 'Fútbol'"));
        $this->assertSame('5', $this->valor("SELECT valor FROM configuracion_sistema WHERE clave = 'max_intentos_login'"));
    }

    public function testElAdministradorInicialDebeCambiarSuContrasena(): void
    {
        $admin = $this->filas("SELECT id_rol, debe_cambiar_contrasena, contrasena_hash FROM usuarios WHERE correo = 'admin@nextleague.com'")[0];
        $this->assertSame(1, (int)$admin['id_rol']);
        $this->assertSame(1, (int)$admin['debe_cambiar_contrasena']);
        $this->assertTrue(password_verify('Admin123!', $admin['contrasena_hash']));
    }
}
