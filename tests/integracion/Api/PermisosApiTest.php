<?php
// Matriz de permisos (sección 5 de la letra): cada acción se prueba con
// cada tipo de usuario y se verifica el código HTTP que recibe.
//   401 = tiene que iniciar sesión   403 = no tiene permiso
// Un código distinto de 401/403 significa que pasó el control de permisos.

final class PermisosApiTest extends CasoApi
{
    private const ANONIMO = 'anónimo';
    private const PARTICIPANTE = 'participante';
    private const ORGANIZADOR_AJENO = 'organizador ajeno';
    private const ORGANIZADOR_DUENO = 'organizador dueño';
    private const ADMIN = 'administrador';

    private array $clientes;
    private int $torneo;
    private int $partido;
    private int $ronda;

    protected function setUp(): void
    {
        parent::setUp();
        $dueno = $this->cliente('dueno', Rol::ORGANIZADOR);
        $this->torneo = $this->torneoEnJuego($dueno, 'liga', 2);
        $this->partido = $this->partidosJugables($dueno, $this->torneo, 1)[0]['id_enfrentamiento'];
        $this->ronda = $this->calendario($dueno, $this->torneo)[0]['id_ronda'];
        $this->clientes = [
            self::ANONIMO           => new ClienteApi(),
            self::PARTICIPANTE      => $this->cliente('part'),
            self::ORGANIZADOR_AJENO => $this->cliente('ajeno', Rol::ORGANIZADOR),
            self::ORGANIZADOR_DUENO => $dueno,
            self::ADMIN             => $this->admin(),
        ];
    }

    // [método, ruta, datos, [quién => ¿puede?]]  (null = no se prueba ese caso)
    private function matriz(): array
    {
        $t = $this->torneo;
        $soloGestion = [self::ANONIMO => false, self::PARTICIPANTE => false, self::ORGANIZADOR_AJENO => false, self::ORGANIZADOR_DUENO => true, self::ADMIN => true];
        $soloAdmin = [self::ANONIMO => false, self::PARTICIPANTE => false, self::ORGANIZADOR_AJENO => false, self::ORGANIZADOR_DUENO => false, self::ADMIN => true];
        $todos = array_fill_keys([self::ANONIMO, self::PARTICIPANTE, self::ORGANIZADOR_AJENO, self::ORGANIZADOR_DUENO, self::ADMIN], true);
        $logueados = [self::ANONIMO => false] + array_fill_keys([self::PARTICIPANTE, self::ORGANIZADOR_AJENO, self::ORGANIZADOR_DUENO, self::ADMIN], true);

        return [
            // Consultas públicas
            ['GET', "/torneos/$t", [], $todos],
            ['GET', "/torneos/$t/calendario", [], $todos],
            ['GET', "/torneos/$t/posiciones", [], $todos],
            ['GET', "/torneos/$t/participantes", [], $todos],
            // Crear torneos: organizadores y admin (5.2), no participantes (5.3)
            ['POST', '/torneos', ['nombre' => 'X'], [self::ANONIMO => false, self::PARTICIPANTE => false, self::ORGANIZADOR_AJENO => true, self::ADMIN => true]],
            // Gestionar un torneo: solo su organizador y el admin
            ['POST', "/torneos/$t", ['descripcion' => 'nueva'], $soloGestion],
            ['PUT', "/torneos/$t/publicacion", ['publicado' => true], $soloGestion],
            ['GET', "/torneos/$t/reporte", [], $soloGestion],
            ['POST', "/torneos/$t/calendario", [], $soloGestion],
            ['POST', "/torneos/$t/participantes", ['nombre_usuario' => 'part'], $soloGestion],
            ['PUT', "/rondas/{$this->ronda}", ['fecha' => '2026-12-01'], $soloGestion],
            ['PUT', "/enfrentamientos/{$this->partido}", ['lugar' => 'Cancha 1'], $soloGestion],
            ['POST', "/enfrentamientos/{$this->partido}/resultado", ['puntaje_local' => 1, 'puntaje_visitante' => 0], $soloGestion],
            ['GET', "/enfrentamientos/{$this->partido}/resultados", [], $soloGestion],
            // Perfil propio: cualquier usuario logueado
            ['GET', '/perfil', [], $logueados],
            ['GET', '/perfil/partidos', [], $logueados],
            // Administración: solo el admin (5.1)
            ['GET', '/admin/usuarios', [], $soloAdmin],
            ['GET', '/admin/auditoria', [], $soloAdmin],
            ['GET', '/admin/configuracion', [], $soloAdmin],
            ['PUT', '/admin/modulos/1', ['habilitado' => true], $soloAdmin],
            ['GET', '/admin/reportes/usuarios', [], $soloAdmin],
        ];
    }

    public function testCadaRolPuedeHacerSoloLoQueLeCorresponde(): void
    {
        $fallas = [];
        foreach ($this->matriz() as [$metodo, $ruta, $datos, $permisos]) {
            foreach ($permisos as $quien => $puede) {
                $estado = $this->clientes[$quien]->pedir($metodo, $ruta, $datos)->estado;
                $bloqueado = in_array($estado, [401, 403], true);
                if ($puede === $bloqueado) {
                    $fallas[] = sprintf('%-6s %-42s %-18s → %d (%s)', $metodo, $ruta, $quien, $estado, $puede ? 'debía poder' : 'NO debía poder');
                }
            }
        }
        $this->assertSame([], $fallas, "Permisos incorrectos:\n" . implode("\n", $fallas));
    }

    public function testElAnonimoRecibe401YNo403(): void
    {
        $this->assertEstado(401, $this->clientes[self::ANONIMO]->get('/perfil'));
        $this->assertEstado(401, $this->clientes[self::ANONIMO]->post("/torneos/{$this->torneo}/calendario"));
    }
}
