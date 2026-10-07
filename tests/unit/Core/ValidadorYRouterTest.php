<?php
// Tests del Validador y del Router (no usan la base de datos).

use PHPUnit\Framework\TestCase;

final class ValidadorYRouterTest extends TestCase
{
    private function errores(array $datos, callable $reglas): array
    {
        $validador = new Validador($datos);
        $reglas($validador);
        return $validador->errores();
    }

    public function testPoliticaDeContrasenas(): void
    {
        $this->assertNull(Validador::problemaContrasena('Segura#2026'));
        $this->assertStringContainsString('8 caracteres', Validador::problemaContrasena('Ab1!'));
        $this->assertStringContainsString('mayúscula', Validador::problemaContrasena('segura#2026'));
        $this->assertStringContainsString('número', Validador::problemaContrasena('Segura#abcd'));
        $this->assertStringContainsString('especial', Validador::problemaContrasena('Segura2026'));
        $this->assertStringContainsString('72', Validador::problemaContrasena('A1!' . str_repeat('x', 80)), 'bcrypt solo usa 72 bytes');
    }

    public function testJuntaElPrimerErrorDeCadaCampo(): void
    {
        $errores = $this->errores(['nombre' => 'ab', 'cupo' => '500'], fn($v) => $v
            ->requerido('nombre', 'El nombre')->texto('nombre', 'El nombre', 3, 10)
            ->entero('cupo', 'El cupo', 2, 256)
            ->requerido('fecha', 'La fecha'));
        $this->assertSame(['nombre', 'cupo', 'fecha'], array_keys($errores));
        $this->assertStringContainsString('al menos 3', $errores['nombre']);
        $this->assertSame('La fecha es obligatoria.', $errores['fecha'], 'Concordancia con el artículo');
    }

    public function testLosCamposOpcionalesVaciosNoDanError(): void
    {
        $this->assertSame([], $this->errores(['cupo' => '', 'correo' => null], fn($v) => $v->entero('cupo', 'El cupo', 2, 9)->correo('correo')));
    }

    public function testFechasYFechaHora(): void
    {
        $this->assertSame([], $this->errores(['f' => '2026-02-28'], fn($v) => $v->fecha('f', 'La fecha')));
        $this->assertArrayHasKey('f', $this->errores(['f' => '2026-02-30'], fn($v) => $v->fecha('f', 'La fecha')));
        $this->assertSame('2026-10-17 19:00:00', Validador::normalizarFechaHora('2026-10-17T19:00'));
        $this->assertNull(Validador::normalizarFechaHora('17/10/2026 19:00'));
    }

    public function testElValidadorLanzaErrorConTodosLosCampos(): void
    {
        try {
            (new Validador([]))->requerido('a', 'A')->requerido('b', 'B')->validar();
            $this->fail('Tenía que lanzar ErrorValidacion');
        } catch (ErrorValidacion $e) {
            $this->assertSame(422, $e->estado);
            $this->assertSame(['a', 'b'], array_keys($e->errores));
        }
    }

    public function testElRouterExtraeLosParametrosYDistingueMetodos(): void
    {
        $router = new Router();
        $router->get('/eco/{id}/partes/{idParte}', [ControladorEcoDePrueba::class, 'eco']);
        $router->post('/eco/{id}', [ControladorEcoDePrueba::class, 'eco']);

        $respuesta = $router->despachar(new Peticion('GET', '/eco/7/partes/12'));
        $this->assertSame(['id' => '7', 'idParte' => '12'], $respuesta->cuerpo['parametros']);

        $this->assertSame(405, $router->despachar(new Peticion('DELETE', '/eco/7'))->estado);

        $this->expectException(ErrorHttp::class);
        $router->despachar(new Peticion('GET', '/eco/no-es-numero/partes/1'));
    }
}

final class ControladorEcoDePrueba extends Controlador
{
    public function eco(): array
    {
        return ['parametros' => $this->peticion->parametrosRuta];
    }
}
