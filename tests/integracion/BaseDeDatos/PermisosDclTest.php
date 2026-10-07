<?php
// Verifica los permisos (DCL): cada cuenta de MySQL puede hacer solo lo
// que necesita (principio de mínimo privilegio).

final class PermisosDclTest extends CasoBaseDeDatos
{
    // ── nl_app: la cuenta de la aplicación ──

    public function testLaAplicacionNoPuedeAlterarNiBorrarLaAuditoria(): void
    {
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_TABLA, "UPDATE auditoria SET detalle = 'tocado'");
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_TABLA, "DELETE FROM auditoria");
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_TABLA, "TRUNCATE TABLE auditoria");
    }

    public function testLaAplicacionSiPuedeAgregarYLeerAuditoria(): void
    {
        $id = $this->insertar("INSERT INTO auditoria (origen, accion, detalle) VALUES ('app', 'iniciar_sesion', 'prueba')");
        $this->assertSame('prueba', $this->valor("SELECT detalle FROM auditoria WHERE id_auditoria = ?", [$id]));
    }

    public function testLaAplicacionNoPuedeCambiarLaEstructura(): void
    {
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_TABLA, "DROP TABLE torneos");
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_TABLA, "ALTER TABLE torneos ADD COLUMN x INT");
        $this->assertFallaSql([self::ERROR_SIN_PERMISO_TABLA, 1044], "CREATE TABLE intrusa (id INT)");
    }

    public function testLosCatalogosFijosSonDeSoloLectura(): void
    {
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_TABLA, "INSERT INTO juegos (nombre, categoria) VALUES ('Nuevo', 'mental')");
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_TABLA, "UPDATE roles SET nombre = 'Jefe' WHERE id_rol = 1");
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_TABLA, "DELETE FROM modulos_competencia WHERE id_modulo = 3");
    }

    public function testElAdministradorPuedeHabilitarODeshabilitarModulos(): void
    {
        $this->pdo->exec("UPDATE modulos_competencia SET habilitado = 0 WHERE codigo = 'sistema_suizo'");
        $this->assertSame(0, (int)$this->valor("SELECT habilitado FROM modulos_competencia WHERE codigo = 'sistema_suizo'"));
    }

    public function testLaAplicacionPuedeLeerTodasLasVistas(): void
    {
        foreach (['vista_participantes', 'vista_torneos', 'vista_torneos_publicos', 'vista_resultados_vigentes',
                  'vista_calendario', 'vista_partidos_por_participante', 'vista_tabla_posiciones', 'vista_estadisticas'] as $vista) {
            $this->assertIsArray($this->filas("SELECT * FROM $vista LIMIT 1"), $vista);
        }
    }

    // ── nl_lectura: solo consultas ──

    public function testLaCuentaDeLecturaNoVeLasContrasenas(): void
    {
        $lectura = BaseDePruebas::lectura();
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_COLUMNA, "SELECT contrasena_hash FROM usuarios", [], $lectura);
        $this->assertSame('admin', $lectura->query("SELECT nombre_usuario FROM usuarios WHERE id_usuario = 1")->fetchColumn());
    }

    public function testLaCuentaDeLecturaNoVeLosTokensNiLosIntentosDeLogin(): void
    {
        $lectura = BaseDePruebas::lectura();
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_TABLA, "SELECT * FROM tokens_usuario", [], $lectura);
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_TABLA, "SELECT * FROM intentos_login", [], $lectura);
    }

    public function testLaCuentaDeLecturaNoPuedeModificarNada(): void
    {
        $lectura = BaseDePruebas::lectura();
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_TABLA, "UPDATE torneos SET nombre = 'x'", [], $lectura);
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_TABLA, "DELETE FROM equipos", [], $lectura);
        $this->assertFallaSql(self::ERROR_SIN_PERMISO_TABLA, "INSERT INTO auditoria (origen, accion) VALUES ('app', 'x')", [], $lectura);
    }

    public function testLaCuentaDeLecturaPuedeConsultarLasVistas(): void
    {
        $fila = BaseDePruebas::lectura()->query("SELECT usuarios FROM vista_estadisticas")->fetch();
        $this->assertGreaterThanOrEqual(1, (int)$fila['usuarios']);
    }
}
