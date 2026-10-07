<?php
// Verifica los triggers de auditoría: "deberá quedar registrado cualquier
// cambio que se realice en la información de la Base de Datos".

final class AuditoriaTest extends CasoBaseDeDatos
{
    private function ultimoRegistro(string $tabla): array
    {
        $filas = $this->filas("SELECT * FROM auditoria WHERE tabla = ? ORDER BY id_auditoria DESC LIMIT 1", [$tabla]);
        $this->assertNotEmpty($filas, "No hay auditoría de $tabla");
        $fila = $filas[0];
        $fila['anteriores'] = $fila['valores_anteriores'] ? json_decode($fila['valores_anteriores'], true) : null;
        $fila['nuevos'] = $fila['valores_nuevos'] ? json_decode($fila['valores_nuevos'], true) : null;
        return $fila;
    }

    private function cantidad(string $tabla): int
    {
        return (int)$this->valor("SELECT COUNT(*) FROM auditoria WHERE tabla = ?", [$tabla]);
    }

    public function testRegistraLaCreacionConLosValoresNuevos(): void
    {
        $torneo = $this->crearTorneo(['nombre' => 'Copa Primavera']);
        $registro = $this->ultimoRegistro('torneos');
        $this->assertSame(['bd', 'crear', (string)$torneo], [$registro['origen'], $registro['accion'], $registro['id_registro']]);
        $this->assertNull($registro['anteriores']);
        $this->assertSame('Copa Primavera', $registro['nuevos']['nombre']);
    }

    public function testRegistraLaEdicionConValoresAnterioresYNuevos(): void
    {
        $torneo = $this->crearTorneo(['nombre' => 'Copa Primavera']);
        $this->pdo->prepare("UPDATE torneos SET nombre = 'Copa Verano', premio = 500 WHERE id_torneo = ?")->execute([$torneo]);
        $registro = $this->ultimoRegistro('torneos');
        $this->assertSame('editar', $registro['accion']);
        $this->assertSame(['Copa Primavera', 'Copa Verano'], [$registro['anteriores']['nombre'], $registro['nuevos']['nombre']]);
        $this->assertEquals(500, $registro['nuevos']['premio']);
    }

    public function testUnUpdateQueNoCambiaNadaNoSeRegistra(): void
    {
        $torneo = $this->crearTorneo(['nombre' => 'Copa Primavera']);
        $antes = $this->cantidad('torneos');
        $this->pdo->prepare("UPDATE torneos SET nombre = 'Copa Primavera' WHERE id_torneo = ?")->execute([$torneo]);
        $this->assertSame($antes, $this->cantidad('torneos'));
    }

    public function testRegistraElBorradoConLosValoresQueTenia(): void
    {
        $usuario = $this->crearUsuario('borrame');
        $this->pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?")->execute([$usuario]);
        $registro = $this->ultimoRegistro('usuarios');
        $this->assertSame('eliminar', $registro['accion']);
        $this->assertSame('borrame', $registro['anteriores']['nombre_usuario']);
        $this->assertNull($registro['nuevos']);
    }

    public function testRegistraQuienHizoElCambioYDesdeDonde(): void
    {
        $this->pdo->exec("SET @id_usuario_app = 1, @ip_app = '192.168.1.20'");
        $this->crearTorneo();
        $registro = $this->ultimoRegistro('torneos');
        $this->assertSame([1, '192.168.1.20'], [(int)$registro['id_usuario'], $registro['ip']]);
        $this->assertStringStartsWith('nl_app@', $registro['usuario_bd']);
    }

    public function testElCambioDeContrasenaSeRegistraSinGuardarElHash(): void
    {
        $usuario = $this->crearUsuario('ana');
        $this->pdo->prepare("UPDATE usuarios SET contrasena_hash = 'hash-secreto-nuevo' WHERE id_usuario = ?")->execute([$usuario]);
        $registro = $this->ultimoRegistro('usuarios');
        $this->assertTrue($registro['nuevos']['contrasena_cambiada']);
        $this->assertArrayNotHasKey('contrasena_hash', $registro['nuevos']);
        $this->assertStringNotContainsString('hash-secreto-nuevo', $registro['valores_nuevos']);
        $this->assertStringNotContainsString('$2y$', $registro['valores_anteriores']);
    }

    public function testElUltimoAccesoSoloNoGeneraRegistro(): void
    {
        $usuario = $this->crearUsuario('ana');
        $antes = $this->cantidad('usuarios');
        $this->pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?")->execute([$usuario]);
        $this->assertSame($antes, $this->cantidad('usuarios'));
    }

    public function testLasClavesCompuestasSeGuardanUnidasConGuion(): void
    {
        $torneo = $this->crearTorneo(['modalidad' => 'equipos']);
        $equipo = $this->insertar("INSERT INTO equipos (id_torneo, nombre) VALUES (?, 'Pumas')", [$torneo]);
        $usuario = $this->crearUsuario('jugador');
        $this->insertar("INSERT INTO equipo_miembros (id_equipo, id_usuario) VALUES (?, ?)", [$equipo, $usuario]);
        $this->assertSame("$equipo-$usuario", $this->ultimoRegistro('equipo_miembros')['id_registro']);
    }

    public function testCadaCorreccionDeResultadoQuedaEnElHistorial(): void
    {
        $torneo = $this->crearTorneo();
        $a = $this->inscribirUsuario($torneo, $this->crearUsuario('ana'));
        $b = $this->inscribirUsuario($torneo, $this->crearUsuario('beto'));
        $partido = $this->crearPartido($this->crearRonda($torneo, 1), 0, $a, $b);
        $this->cargarResultado($partido, 0, 1);
        $this->cargarResultado($partido, 2, 1);
        $acciones = array_column($this->filas(
            "SELECT accion FROM auditoria WHERE tabla = 'resultados' ORDER BY id_auditoria"), 'accion');
        // crear (0-1), editar (0-1 deja de ser vigente), crear (2-1)
        $this->assertSame(['crear', 'editar', 'crear'], array_slice($acciones, -3));
    }

    public function testUnCambioHechoPorFueraDeLaAplicacionTambienQuedaRegistrado(): void
    {
        // Simula alguien editando desde phpMyAdmin con root: no hay usuario de la app.
        $root = BaseDePruebas::root();
        $root->beginTransaction();
        try {
            $root->exec("SET @id_usuario_app = NULL, @ip_app = NULL");
            $root->exec("UPDATE configuracion_sistema SET valor = '7' WHERE clave = 'max_intentos_login'");
            $fila = $root->query("SELECT id_usuario, usuario_bd, valores_anteriores, valores_nuevos FROM auditoria
                                  WHERE tabla = 'configuracion_sistema' ORDER BY id_auditoria DESC LIMIT 1")->fetch();
            $this->assertNull($fila['id_usuario']);
            $this->assertStringStartsWith('root@', $fila['usuario_bd']);
            $this->assertSame('5', json_decode($fila['valores_anteriores'], true)['valor']);
            $this->assertSame('7', json_decode($fila['valores_nuevos'], true)['valor']);
        } finally {
            $root->rollBack();
        }
    }
}
