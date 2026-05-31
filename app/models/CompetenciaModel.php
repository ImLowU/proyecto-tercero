<?php
// ============================================================
// app/models/CompetenciaModel.php
// Inscripciones, rondas, enfrentamientos, resultados y posiciones
// ============================================================

require_once __DIR__ . '/Database.php';

class CompetenciaModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listarInscriptos(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.id AS inscripcion_id, i.estado, p.*
             FROM inscripciones i
             JOIN participantes p ON p.id = i.participante_id
             WHERE i.torneo_id = :torneo_id AND i.estado = "confirmada"
             ORDER BY p.nombre ASC'
        );
        $stmt->execute([':torneo_id' => $torneoId]);
        return $stmt->fetchAll();
    }

    public function participantesDisponibles(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*
             FROM participantes p
             WHERE p.activo = 1
               AND p.id NOT IN (
                   SELECT i.participante_id FROM inscripciones i
                   WHERE i.torneo_id = :torneo_id AND i.estado = "confirmada"
               )
             ORDER BY p.nombre ASC'
        );
        $stmt->execute([':torneo_id' => $torneoId]);
        return $stmt->fetchAll();
    }

    public function inscribir(int $torneoId, int $participanteId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO inscripciones (torneo_id, participante_id, estado)
             VALUES (:torneo_id, :participante_id, "confirmada")
             ON DUPLICATE KEY UPDATE estado = "confirmada"'
        );
        $stmt->execute([':torneo_id' => $torneoId, ':participante_id' => $participanteId]);
        $this->asegurarFilaPosicion($torneoId, $participanteId);
    }

    public function darDeBaja(int $torneoId, int $participanteId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE inscripciones SET estado = "baja"
             WHERE torneo_id = :torneo_id AND participante_id = :participante_id'
        );
        $stmt->execute([':torneo_id' => $torneoId, ':participante_id' => $participanteId]);
    }

    public function listarRondas(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM rondas WHERE torneo_id = :torneo_id ORDER BY numero ASC'
        );
        $stmt->execute([':torneo_id' => $torneoId]);
        return $stmt->fetchAll();
    }

    public function listarEnfrentamientos(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT e.*, r.numero AS ronda_numero, r.nombre AS ronda_nombre,
                    pa.nombre AS participante_a_nombre,
                    pb.nombre AS participante_b_nombre,
                    res.puntos_a, res.puntos_b, res.ganador_id, res.observaciones
             FROM enfrentamientos e
             JOIN rondas r ON r.id = e.ronda_id
             JOIN participantes pa ON pa.id = e.participante_a
             LEFT JOIN participantes pb ON pb.id = e.participante_b
             LEFT JOIN resultados res ON res.enfrentamiento_id = e.id
             WHERE r.torneo_id = :torneo_id
             ORDER BY r.numero ASC, e.id ASC'
        );
        $stmt->execute([':torneo_id' => $torneoId]);
        return $stmt->fetchAll();
    }

    public function buscarEnfrentamiento(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT e.*, r.torneo_id
             FROM enfrentamientos e
             JOIN rondas r ON r.id = e.ronda_id
             WHERE e.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $enfrentamiento = $stmt->fetch();
        return $enfrentamiento ?: null;
    }

    public function tieneRondas(int $torneoId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM rondas WHERE torneo_id = :torneo_id LIMIT 1');
        $stmt->execute([':torneo_id' => $torneoId]);
        return (bool) $stmt->fetch();
    }

    public function generarRondas(array $torneo): string
    {
        $torneoId = (int) $torneo['id'];
        if ($this->tieneRondas($torneoId)) {
            throw new RuntimeException('Este torneo ya tiene rondas generadas.');
        }

        $participantes = array_values($this->listarInscriptos($torneoId));
        if (count($participantes) < 2) {
            throw new RuntimeException('Se necesitan al menos 2 participantes confirmados.');
        }

        return match ((int) $torneo['tipo_torneo_id']) {
            TIPO_LIGA => $this->generarLiga($torneoId, $participantes),
            TIPO_ELIMINACION_DIRECTA => $this->generarEliminacion($torneoId, $participantes),
            TIPO_SUIZO => $this->generarSuizo($torneoId, $participantes),
            default => throw new RuntimeException('Tipo de torneo no soportado.'),
        };
    }

    public function generarSiguienteRondaSuiza(array $torneo): string
    {
        if ((int) $torneo['tipo_torneo_id'] !== TIPO_SUIZO) {
            throw new RuntimeException('La generación por rondas solo aplica al sistema suizo.');
        }

        $torneoId = (int) $torneo['id'];
        $participantes = $this->ordenarParticipantesParaSuizo($torneoId);
        if (count($participantes) < 2) {
            throw new RuntimeException('Se necesitan al menos 2 participantes confirmados.');
        }

        return $this->generarSuizo($torneoId, $participantes);
    }

    public function guardarResultado(int $enfrentamientoId, int $puntosA, int $puntosB, string $observaciones, int $usuarioId): int
    {
        $enfrentamiento = $this->buscarEnfrentamiento($enfrentamientoId);
        if (!$enfrentamiento) {
            throw new RuntimeException('El enfrentamiento no existe.');
        }

        $ganadorId = null;
        if ($enfrentamiento['participante_b'] === null || $puntosA > $puntosB) {
            $ganadorId = (int) $enfrentamiento['participante_a'];
        } elseif ($puntosB > $puntosA) {
            $ganadorId = (int) $enfrentamiento['participante_b'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO resultados (enfrentamiento_id, puntos_a, puntos_b, ganador_id, observaciones, cargado_por)
             VALUES (:enfrentamiento_id, :puntos_a, :puntos_b, :ganador_id, :observaciones, :cargado_por)
             ON DUPLICATE KEY UPDATE
                puntos_a = VALUES(puntos_a),
                puntos_b = VALUES(puntos_b),
                ganador_id = VALUES(ganador_id),
                observaciones = VALUES(observaciones),
                cargado_por = VALUES(cargado_por)'
        );
        $stmt->execute([
            ':enfrentamiento_id' => $enfrentamientoId,
            ':puntos_a'          => $puntosA,
            ':puntos_b'          => $puntosB,
            ':ganador_id'        => $ganadorId,
            ':observaciones'     => $observaciones ?: null,
            ':cargado_por'       => $usuarioId,
        ]);

        $this->db->prepare('UPDATE enfrentamientos SET estado = "finalizado" WHERE id = :id')->execute([':id' => $enfrentamientoId]);
        $this->recalcularTabla((int) $enfrentamiento['torneo_id']);

        return (int) $enfrentamiento['torneo_id'];
    }

    public function obtenerTabla(int $torneoId, bool $recalcular = true): array
    {
        if ($recalcular) {
            $this->recalcularTabla($torneoId);
        }
        $stmt = $this->db->prepare(
            'SELECT tp.*, p.nombre AS participante_nombre, p.tipo
             FROM tabla_posiciones tp
             JOIN participantes p ON p.id = tp.participante_id
             WHERE tp.torneo_id = :torneo_id
             ORDER BY tp.puntos DESC, (tp.puntos_favor - tp.puntos_contra) DESC, tp.puntos_favor DESC, p.nombre ASC'
        );
        $stmt->execute([':torneo_id' => $torneoId]);
        return $stmt->fetchAll();
    }

    private function generarLiga(int $torneoId, array $participantes): string
    {
        $items = array_map(fn($p) => (int) $p['id'], $participantes);
        if (count($items) % 2 !== 0) {
            $items[] = null;
        }

        $total = count($items);
        $rondas = $total - 1;
        $mitad = intdiv($total, 2);

        for ($r = 1; $r <= $rondas; $r++) {
            $rondaId = $this->crearRonda($torneoId, $r, 'Fecha ' . $r);
            for ($i = 0; $i < $mitad; $i++) {
                $a = $items[$i];
                $b = $items[$total - 1 - $i];
                if ($a === null || $b === null) {
                    continue;
                }
                $this->crearEnfrentamiento($rondaId, $a, $b);
            }

            $fijo = array_shift($items);
            $ultimo = array_pop($items);
            array_unshift($items, $fijo, $ultimo);
        }

        return 'Se generó el calendario de liga todos contra todos.';
    }

    private function generarEliminacion(int $torneoId, array $participantes): string
    {
        $items = array_map(fn($p) => (int) $p['id'], $participantes);
        $potencia = 1;
        while ($potencia < count($items)) {
            $potencia *= 2;
        }
        while (count($items) < $potencia) {
            $items[] = null;
        }

        $rondaId = $this->crearRonda($torneoId, 1, 'Primera ronda');
        for ($i = 0; $i < count($items); $i += 2) {
            $a = $items[$i];
            $b = $items[$i + 1] ?? null;
            if ($a === null && $b !== null) {
                $a = $b;
                $b = null;
            }
            if ($a !== null) {
                $this->crearEnfrentamiento($rondaId, $a, $b);
            }
        }

        return 'Se generó la llave inicial de eliminación directa.';
    }

    private function generarSuizo(int $torneoId, array $participantes): string
    {
        $numero = $this->siguienteNumeroRonda($torneoId);
        $rondaId = $this->crearRonda($torneoId, $numero, 'Ronda suiza ' . $numero);
        $usados = [];
        $previos = $this->paresPrevios($torneoId);

        for ($i = 0; $i < count($participantes); $i++) {
            $aId = (int) $participantes[$i]['id'];
            if (isset($usados[$aId])) {
                continue;
            }

            $bIndex = null;
            for ($j = $i + 1; $j < count($participantes); $j++) {
                $bId = (int) $participantes[$j]['id'];
                if (isset($usados[$bId])) {
                    continue;
                }
                $clave = $this->clavePar($aId, $bId);
                if (!isset($previos[$clave])) {
                    $bIndex = $j;
                    break;
                }
                if ($bIndex === null) {
                    $bIndex = $j;
                }
            }

            if ($bIndex === null) {
                $this->crearEnfrentamiento($rondaId, $aId, null);
                $usados[$aId] = true;
                continue;
            }

            $bId = (int) $participantes[$bIndex]['id'];
            $this->crearEnfrentamiento($rondaId, $aId, $bId);
            $usados[$aId] = true;
            $usados[$bId] = true;
        }

        return 'Se generó una ronda de sistema suizo.';
    }

    private function crearRonda(int $torneoId, int $numero, string $nombre): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO rondas (torneo_id, numero, nombre, estado)
             VALUES (:torneo_id, :numero, :nombre, "pendiente")'
        );
        $stmt->execute([':torneo_id' => $torneoId, ':numero' => $numero, ':nombre' => $nombre]);
        return (int) $this->db->lastInsertId();
    }

    private function crearEnfrentamiento(int $rondaId, int $a, ?int $b): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO enfrentamientos (ronda_id, participante_a, participante_b, estado)
             VALUES (:ronda_id, :a, :b, :estado)'
        );
        $stmt->execute([
            ':ronda_id' => $rondaId,
            ':a'        => $a,
            ':b'        => $b,
            ':estado'   => $b === null ? 'finalizado' : 'pendiente',
        ]);

        if ($b === null) {
            $id = (int) $this->db->lastInsertId();
            $stmtRes = $this->db->prepare(
                'INSERT INTO resultados (enfrentamiento_id, puntos_a, puntos_b, ganador_id, observaciones)
                 VALUES (:id, 1, 0, :ganador, "Descanso")'
            );
            $stmtRes->execute([':id' => $id, ':ganador' => $a]);
        }
    }

    private function siguienteNumeroRonda(int $torneoId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(numero), 0) + 1 AS siguiente FROM rondas WHERE torneo_id = :torneo_id');
        $stmt->execute([':torneo_id' => $torneoId]);
        return (int) $stmt->fetch()['siguiente'];
    }

    private function paresPrevios(int $torneoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT e.participante_a, e.participante_b
             FROM enfrentamientos e
             JOIN rondas r ON r.id = e.ronda_id
             WHERE r.torneo_id = :torneo_id AND e.participante_b IS NOT NULL'
        );
        $stmt->execute([':torneo_id' => $torneoId]);
        $pares = [];
        foreach ($stmt->fetchAll() as $fila) {
            $pares[$this->clavePar((int) $fila['participante_a'], (int) $fila['participante_b'])] = true;
        }
        return $pares;
    }

    private function clavePar(int $a, int $b): string
    {
        $ids = [$a, $b];
        sort($ids);
        return $ids[0] . '-' . $ids[1];
    }

    private function ordenarParticipantesParaSuizo(int $torneoId): array
    {
        $this->recalcularTabla($torneoId);
        $stmt = $this->db->prepare(
            'SELECT p.id, p.nombre
             FROM inscripciones i
             JOIN participantes p ON p.id = i.participante_id
             LEFT JOIN tabla_posiciones tp ON tp.participante_id = p.id AND tp.torneo_id = i.torneo_id
             WHERE i.torneo_id = :torneo_id AND i.estado = "confirmada"
             ORDER BY COALESCE(tp.puntos, 0) DESC, p.nombre ASC'
        );
        $stmt->execute([':torneo_id' => $torneoId]);
        return $stmt->fetchAll();
    }

    private function asegurarFilaPosicion(int $torneoId, int $participanteId): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO tabla_posiciones (torneo_id, participante_id)
             VALUES (:torneo_id, :participante_id)'
        );
        $stmt->execute([':torneo_id' => $torneoId, ':participante_id' => $participanteId]);
    }

    private function recalcularTabla(int $torneoId): void
    {
        $inscriptos = $this->listarInscriptos($torneoId);
        foreach ($inscriptos as $p) {
            $this->asegurarFilaPosicion($torneoId, (int) $p['id']);
        }

        $stmt = $this->db->prepare(
            'UPDATE tabla_posiciones
             SET puntos = 0, partidos_jugados = 0, victorias = 0, empates = 0,
                 derrotas = 0, puntos_favor = 0, puntos_contra = 0
             WHERE torneo_id = :torneo_id'
        );
        $stmt->execute([':torneo_id' => $torneoId]);

        $stmt = $this->db->prepare(
            'SELECT e.participante_a, e.participante_b, res.puntos_a, res.puntos_b, res.ganador_id
             FROM resultados res
             JOIN enfrentamientos e ON e.id = res.enfrentamiento_id
             JOIN rondas r ON r.id = e.ronda_id
             WHERE r.torneo_id = :torneo_id'
        );
        $stmt->execute([':torneo_id' => $torneoId]);
        $resultados = $stmt->fetchAll();

        foreach ($resultados as $res) {
            $a = (int) $res['participante_a'];
            $b = $res['participante_b'] !== null ? (int) $res['participante_b'] : null;
            $pa = (int) $res['puntos_a'];
            $pb = (int) $res['puntos_b'];

            if ($b === null) {
                $this->sumarPosicion($torneoId, $a, 3, 1, 1, 0, 0, $pa, $pb);
                continue;
            }

            if ($pa > $pb) {
                $this->sumarPosicion($torneoId, $a, 3, 1, 1, 0, 0, $pa, $pb);
                $this->sumarPosicion($torneoId, $b, 0, 1, 0, 0, 1, $pb, $pa);
            } elseif ($pb > $pa) {
                $this->sumarPosicion($torneoId, $a, 0, 1, 0, 0, 1, $pa, $pb);
                $this->sumarPosicion($torneoId, $b, 3, 1, 1, 0, 0, $pb, $pa);
            } else {
                $this->sumarPosicion($torneoId, $a, 1, 1, 0, 1, 0, $pa, $pb);
                $this->sumarPosicion($torneoId, $b, 1, 1, 0, 1, 0, $pb, $pa);
            }
        }
    }

    private function sumarPosicion(int $torneoId, int $participanteId, int $puntos, int $pj, int $victorias, int $empates, int $derrotas, int $pf, int $pc): void
    {
        $this->asegurarFilaPosicion($torneoId, $participanteId);
        $stmt = $this->db->prepare(
            'UPDATE tabla_posiciones
             SET puntos = puntos + :puntos,
                 partidos_jugados = partidos_jugados + :pj,
                 victorias = victorias + :victorias,
                 empates = empates + :empates,
                 derrotas = derrotas + :derrotas,
                 puntos_favor = puntos_favor + :pf,
                 puntos_contra = puntos_contra + :pc
             WHERE torneo_id = :torneo_id AND participante_id = :participante_id'
        );
        $stmt->execute([
            ':puntos'          => $puntos,
            ':pj'              => $pj,
            ':victorias'       => $victorias,
            ':empates'         => $empates,
            ':derrotas'        => $derrotas,
            ':pf'              => $pf,
            ':pc'              => $pc,
            ':torneo_id'       => $torneoId,
            ':participante_id' => $participanteId,
        ]);
    }
}
