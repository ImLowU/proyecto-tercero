<?php
// Tests del separador de sentencias SQL (no usan la base de datos).

use PHPUnit\Framework\TestCase;

final class EjecutorSqlTest extends TestCase
{
    public function testSeparaSentenciasPorPuntoYComa(): void
    {
        $this->assertSame(['SELECT 1', 'SELECT 2'], EjecutorSql::dividir("SELECT 1;\nSELECT 2;"));
    }

    public function testIncluyeLaUltimaSentenciaAunqueNoTengaPuntoYComa(): void
    {
        $this->assertSame(['SELECT 1', 'SELECT 2'], EjecutorSql::dividir("SELECT 1; SELECT 2"));
    }

    public function testNoCortaPuntoYComaDentroDeTextos(): void
    {
        $sentencias = EjecutorSql::dividir("INSERT INTO t VALUES ('a;b', \"c;d\"); SELECT `raro;nombre` FROM t;");
        $this->assertCount(2, $sentencias);
        $this->assertSame("INSERT INTO t VALUES ('a;b', \"c;d\")", $sentencias[0]);
    }

    public function testRespetaComillasEscapadas(): void
    {
        $sentencias = EjecutorSql::dividir("SELECT 'it''s; ok'; SELECT 'barra \\'; sigue';");
        $this->assertSame(["SELECT 'it''s; ok'", "SELECT 'barra \\'; sigue'"], $sentencias);
    }

    public function testDescartaComentariosNormales(): void
    {
        $sql = "-- comentario; con punto y coma\nSELECT 1; # otro; comentario\n/* bloque; */ SELECT 2;";
        $this->assertSame(['SELECT 1', 'SELECT 2'], EjecutorSql::dividir($sql));
    }

    public function testConservaLosComentariosEjecutablesDeMysql(): void
    {
        $sentencias = EjecutorSql::dividir("/*!40101 SET NAMES utf8mb4 */;\n/*M!999999\\- enable the sandbox mode */\nSELECT 1;");
        $this->assertSame(['/*!40101 SET NAMES utf8mb4 */', 'SELECT 1'], $sentencias);
    }

    public function testIgnoraSentenciasVacias(): void
    {
        $this->assertSame(['SELECT 1'], EjecutorSql::dividir(";;\n  ;SELECT 1;;"));
    }

    // Si alguien edita un script y rompe la separación, esto lo detecta.
    public function testLosScriptsDelProyectoSeSeparanEnLaCantidadEsperada(): void
    {
        $carpeta = __DIR__ . '/../../database/';
        $esperado = [
            '01_esquema.sql'        => 18, // SET NAMES + 17 tablas
            '03_vistas.sql'         => 9,  // SET NAMES + 8 vistas
            '04_triggers.sql'       => 43, // SET NAMES + 14 tablas x 3 triggers
        ];
        foreach ($esperado as $archivo => $cantidad) {
            $this->assertCount($cantidad, EjecutorSql::dividir(file_get_contents($carpeta . $archivo)), $archivo);
        }
    }
}
