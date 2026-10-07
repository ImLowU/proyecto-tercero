<?php
// =====================================================================
// MODELO: AUDITORÍA
// Los cambios de datos los registran solos los triggers de la base
// (origen 'bd'). Desde la aplicación se registran los EVENTOS que no son
// un cambio de fila: inicios de sesión, intentos fallidos, generación de
// calendarios, etc. (origen 'app'). La aplicación solo puede agregar y
// leer: no tiene permiso para editar ni borrar el historial.
// =====================================================================

class Auditoria
{
    public static function registrarEvento(string $accion, ?string $detalle = null, ?string $tabla = null, int|string|null $idRegistro = null): void
    {
        BaseDatos::insertar(
            "INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, detalle)
             VALUES ('app', ?, ?, ?, @id_usuario_app, USER(), @ip_app, ?)",
            [$accion, $tabla, $idRegistro === null ? null : (string)$idRegistro, $detalle === null ? null : mb_substr($detalle, 0, 255)]
        );
    }

    public static function listar(array $filtros, int $pagina, int $porPagina): array
    {
        $donde = ['1 = 1'];
        $parametros = [];
        foreach (['tabla' => 'a.tabla', 'accion' => 'a.accion', 'origen' => 'a.origen', 'id_usuario' => 'a.id_usuario'] as $filtro => $columna) {
            if (isset($filtros[$filtro]) && $filtros[$filtro] !== '') {
                $donde[] = "$columna = ?";
                $parametros[] = $filtros[$filtro];
            }
        }
        if (!empty($filtros['desde'])) {
            $donde[] = 'a.fecha >= ?';
            $parametros[] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $donde[] = 'a.fecha <= ?';
            $parametros[] = $filtros['hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['buscar'])) {
            $donde[] = '(a.detalle LIKE ? OR a.valores_anteriores LIKE ? OR a.valores_nuevos LIKE ?)';
            array_push($parametros, ...array_fill(0, 3, BaseDatos::patronContiene($filtros['buscar'])));
        }

        $condicion = implode(' AND ', $donde);
        $total = (int)BaseDatos::valor("SELECT COUNT(*) FROM auditoria a WHERE $condicion", $parametros);
        $filas = BaseDatos::filas(
            "SELECT a.id_auditoria, a.fecha, a.origen, a.accion, a.tabla, a.id_registro, a.id_usuario,
                    u.nombre_usuario, a.usuario_bd, a.ip, a.valores_anteriores, a.valores_nuevos, a.detalle
             FROM auditoria a LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
             WHERE $condicion
             ORDER BY a.id_auditoria DESC
             LIMIT ? OFFSET ?",
            [...$parametros, $porPagina, ($pagina - 1) * $porPagina]
        );
        foreach ($filas as &$fila) {
            $fila['valores_anteriores'] = $fila['valores_anteriores'] ? json_decode($fila['valores_anteriores'], true) : null;
            $fila['valores_nuevos'] = $fila['valores_nuevos'] ? json_decode($fila['valores_nuevos'], true) : null;
        }
        return [$filas, $total];
    }
}
