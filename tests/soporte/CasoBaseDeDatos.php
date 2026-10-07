<?php
// =====================================================================
// CLASE BASE PARA LOS TESTS QUE USAN LA BASE DE DATOS
// Cada test corre dentro de una transacción que se deshace al terminar,
// así los tests no se pisan entre sí y la base queda siempre igual.
// Trae "constructores" cortos para armar escenarios (usuarios, torneos,
// participantes, rondas, partidos y resultados).
// =====================================================================

use PHPUnit\Framework\TestCase;

abstract class CasoBaseDeDatos extends TestCase
{
    protected PDO $pdo;

    // Por defecto se trabaja con la cuenta de la aplicación (nl_app),
    // que es la que va a usar el sistema real.
    protected function conexion(): PDO
    {
        return BaseDePruebas::app();
    }

    protected function setUp(): void
    {
        $this->pdo = $this->conexion();
        $this->pdo->beginTransaction();
        $this->pdo->exec("SET @id_usuario_app = NULL, @ip_app = NULL");
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    // Ejecuta una sentencia y devuelve el último id insertado.
    protected function insertar(string $sql, array $parametros = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);
        return (int)$this->pdo->lastInsertId();
    }

    protected function valor(string $sql, array $parametros = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchColumn();
    }

    protected function filas(string $sql, array $parametros = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    // Códigos de error de MySQL/MariaDB que se usan en los tests.
    // (El de CHECK es distinto en MariaDB y en MySQL 8, se aceptan los dos.)
    protected const ERROR_CHECK = [4025, 3819];
    protected const ERROR_DUPLICADO = 1062;
    protected const ERROR_FK_HIJO = 1452;      // la fila referenciada no existe
    protected const ERROR_FK_PADRE = 1451;     // no se puede borrar: hay filas que la referencian
    protected const ERROR_DATO_INVALIDO = 1265; // modo estricto: valor que no entra en la columna
    protected const ERROR_SIN_PERMISO_TABLA = 1142;
    protected const ERROR_SIN_PERMISO_COLUMNA = 1143;

    // Verifica que la sentencia falle con alguno de los códigos de error esperados.
    protected function assertFallaSql(int|array $codigosEsperados, string $sql, array $parametros = [], ?PDO $pdo = null): void
    {
        $codigos = (array)$codigosEsperados;
        try {
            $stmt = ($pdo ?? $this->pdo)->prepare($sql);
            $stmt->execute($parametros);
        } catch (PDOException $e) {
            $this->assertContains((int)$e->errorInfo[1], $codigos, "Falló con otro error: " . $e->getMessage());
            return;
        }
        $this->fail("Se esperaba el error " . implode(' o ', $codigos) . " pero la sentencia se ejecutó: $sql");
    }

    // ── Constructores de escenarios ──

    protected function crearUsuario(string $nombre, int $idRol = 3): int
    {
        return $this->insertar(
            "INSERT INTO usuarios (nombre_usuario, correo, contrasena_hash, id_rol) VALUES (?, ?, ?, ?)",
            [$nombre, "$nombre@prueba.uy", password_hash('Clave123!', PASSWORD_DEFAULT, ['cost' => 4]), $idRol]
        );
    }

    // $modulo: 1 liga, 2 eliminación directa, 3 suizo
    protected function crearTorneo(array $datos = []): int
    {
        $d = $datos + [
            'nombre' => 'Torneo de prueba', 'id_juego' => 28, 'id_modulo' => 1, 'id_organizador' => 1,
            'modalidad' => 'individual', 'cupo' => 8, 'estado' => 'pendiente', 'publicado' => 1,
            'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-30',
        ];
        $id = $this->insertar(
            "INSERT INTO torneos (nombre, id_juego, id_modulo, id_organizador, modalidad, cupo, estado, publicado, fecha_inicio, fecha_fin)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$d['nombre'], $d['id_juego'], $d['id_modulo'], $d['id_organizador'], $d['modalidad'], $d['cupo'],
             $d['estado'], $d['publicado'], $d['fecha_inicio'], $d['fecha_fin']]
        );
        $config = $datos['configuracion'] ?? [];
        $columnas = array_merge(['id_torneo'], array_keys($config));
        $this->insertar(
            "INSERT INTO configuracion_torneo (" . implode(', ', $columnas) . ") VALUES (" . rtrim(str_repeat('?, ', count($columnas)), ', ') . ")",
            array_merge([$id], array_values($config))
        );
        return $id;
    }

    protected function inscribirUsuario(int $idTorneo, int $idUsuario): int
    {
        return $this->insertar("INSERT INTO participantes (id_torneo, id_usuario) VALUES (?, ?)", [$idTorneo, $idUsuario]);
    }

    protected function inscribirEquipo(int $idTorneo, string $nombre, ?int $idCapitan = null): int
    {
        $idEquipo = $this->insertar("INSERT INTO equipos (id_torneo, nombre, id_capitan) VALUES (?, ?, ?)", [$idTorneo, $nombre, $idCapitan]);
        return $this->insertar("INSERT INTO participantes (id_torneo, id_equipo) VALUES (?, ?)", [$idTorneo, $idEquipo]);
    }

    protected function crearRonda(int $idTorneo, int $numero, string $estado = 'publicada'): int
    {
        return $this->insertar("INSERT INTO rondas (id_torneo, numero, estado) VALUES (?, ?, ?)", [$idTorneo, $numero, $estado]);
    }

    protected function crearPartido(int $idRonda, int $posicion, ?int $local, ?int $visitante): int
    {
        return $this->insertar(
            "INSERT INTO enfrentamientos (id_ronda, posicion, id_local, id_visitante) VALUES (?, ?, ?, ?)",
            [$idRonda, $posicion, $local, $visitante]
        );
    }

    protected function crearPaseLibre(int $idRonda, int $posicion, int $participante): int
    {
        return $this->insertar(
            "INSERT INTO enfrentamientos (id_ronda, posicion, id_local, id_ganador, es_pase_libre, estado) VALUES (?, ?, ?, ?, 1, 'finalizado')",
            [$idRonda, $posicion, $participante, $participante]
        );
    }

    // Carga (o corrige) un resultado tal como lo hará la aplicación:
    // el anterior queda como historial y el nuevo pasa a ser el vigente.
    protected function cargarResultado(int $idEnfrentamiento, int $local, int $visitante): void
    {
        $this->pdo->prepare("UPDATE resultados SET vigente = 0 WHERE id_enfrentamiento = ?")->execute([$idEnfrentamiento]);
        $this->insertar(
            "INSERT INTO resultados (id_enfrentamiento, puntaje_local, puntaje_visitante) VALUES (?, ?, ?)",
            [$idEnfrentamiento, $local, $visitante]
        );
        $partido = $this->filas("SELECT id_local, id_visitante FROM enfrentamientos WHERE id_enfrentamiento = ?", [$idEnfrentamiento])[0];
        $ganador = $local > $visitante ? $partido['id_local'] : ($visitante > $local ? $partido['id_visitante'] : null);
        $this->pdo->prepare("UPDATE enfrentamientos SET estado = 'finalizado', id_ganador = ? WHERE id_enfrentamiento = ?")
                  ->execute([$ganador, $idEnfrentamiento]);
    }

    protected function tablaDe(int $idTorneo): array
    {
        $filas = $this->filas(
            "SELECT nombre, pj, pg, pe, pp, gf, gc, diferencia, puntos FROM vista_tabla_posiciones
             WHERE id_torneo = ? ORDER BY puntos DESC, diferencia DESC, gf DESC, nombre",
            [$idTorneo]
        );
        // Se pasan a enteros para comparar fácil (PDO devuelve texto).
        return array_map(fn($f) => array_map(fn($v) => is_numeric($v) ? (int)$v : $v, $f), $filas);
    }
}
