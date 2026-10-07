<?php
// =====================================================================
// EJECUTOR DE ARCHIVOS SQL
// Lee un archivo .sql y lo ejecuta sentencia por sentencia con PDO.
// Lo usan el instalador, el generador de triggers y los tests, así no
// hace falta tener el cliente "mysql" de consola ni phpMyAdmin.
//
// PDO ejecuta una sentencia por vez, así que hay que separar el archivo
// en sentencias: se corta en cada ";" que no esté dentro de un texto
// ('...' o "..."), de un nombre entre `...` o de un comentario.
// Los comentarios normales se descartan; los "ejecutables" de MySQL
// (/*!40101 ... */) se conservan porque el servidor sí los interpreta.
// =====================================================================

class EjecutorSql
{
    // Ejecuta todas las sentencias del archivo y devuelve cuántas fueron.
    public static function ejecutarArchivo(PDO $pdo, string $ruta): int
    {
        if (!is_file($ruta)) {
            throw new RuntimeException("No existe el archivo SQL: $ruta");
        }
        return self::ejecutarTexto($pdo, file_get_contents($ruta), basename($ruta));
    }

    public static function ejecutarTexto(PDO $pdo, string $sql, string $origen = 'sql'): int
    {
        $sentencias = self::dividir($sql);
        foreach ($sentencias as $numero => $sentencia) {
            try {
                $pdo->exec($sentencia);
            } catch (PDOException $e) {
                $inicio = mb_substr(preg_replace('/\s+/', ' ', $sentencia), 0, 120);
                throw new RuntimeException(
                    "Error en $origen, sentencia " . ($numero + 1) . ": " . $e->getMessage() . "\n  → $inicio...",
                    0,
                    $e
                );
            }
        }
        return count($sentencias);
    }

    // Separa el texto en sentencias individuales (sin el ";" final).
    public static function dividir(string $sql): array
    {
        $sentencias = [];
        $actual = '';
        $largo = strlen($sql);
        $i = 0;

        while ($i < $largo) {
            $c = $sql[$i];
            $siguiente = $i + 1 < $largo ? $sql[$i + 1] : '';

            // Comentario de línea: "-- " o "#" hasta el fin de línea
            if (($c === '-' && $siguiente === '-' && ($i + 2 >= $largo || ctype_space($sql[$i + 2]))) || $c === '#') {
                $fin = strpos($sql, "\n", $i);
                $i = $fin === false ? $largo : $fin + 1;
                $actual .= "\n";
                continue;
            }

            // Comentario de bloque: se descarta salvo que sea ejecutable (/*! ... */)
            if ($c === '/' && $siguiente === '*') {
                $fin = strpos($sql, '*/', $i + 2);
                $fin = $fin === false ? $largo : $fin + 2;
                if ($i + 2 < $largo && $sql[$i + 2] === '!') {
                    $actual .= substr($sql, $i, $fin - $i);
                }
                $i = $fin;
                continue;
            }

            // Textos y nombres entre comillas: se copian enteros
            if ($c === "'" || $c === '"' || $c === '`') {
                $j = $i + 1;
                while ($j < $largo) {
                    if ($sql[$j] === '\\' && $c !== '`') {
                        $j += 2;
                        continue;
                    }
                    if ($sql[$j] === $c) {
                        // Comilla duplicada ('' dentro de un texto) = comilla escapada
                        if ($j + 1 < $largo && $sql[$j + 1] === $c) {
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $j++;
                }
                $actual .= substr($sql, $i, $j - $i + 1);
                $i = $j + 1;
                continue;
            }

            if ($c === ';') {
                self::agregar($sentencias, $actual);
                $actual = '';
                $i++;
                continue;
            }

            $actual .= $c;
            $i++;
        }

        self::agregar($sentencias, $actual);
        return $sentencias;
    }

    private static function agregar(array &$sentencias, string $sentencia): void
    {
        $sentencia = trim($sentencia);
        if ($sentencia !== '') {
            $sentencias[] = $sentencia;
        }
    }
}
