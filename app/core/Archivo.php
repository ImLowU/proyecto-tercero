<?php
// =====================================================================
// ARCHIVOS SUBIDOS (fotos de perfil, logos, reglas en PDF)
// Viene de guardar_archivo() de la versión anterior. Se valida el tipo
// REAL del archivo leyendo su contenido (no la extensión ni lo que dice
// el navegador, que se pueden falsificar) y el tamaño máximo. El nombre
// se genera al azar, así nadie puede pisar un archivo ajeno.
// Los archivos quedan en public/uploads/, donde Apache no ejecuta PHP.
// =====================================================================

class Archivo
{
    public const IMAGENES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    public const PDF = ['application/pdf' => 'pdf'];

    // Solo para tests: permite "subir" archivos que ya están en el disco.
    public static bool $aceptarArchivosLocales = false;

    public static function carpeta(string $subcarpeta): string
    {
        $base = Config::obtener('carpeta_subidas') ?? dirname(__DIR__, 2) . '/public/uploads';
        return rtrim($base, '/\\') . '/' . trim($subcarpeta, '/');
    }

    // Devuelve el nombre del archivo guardado, o null si no se mandó ninguno.
    public static function guardar(?array $archivo, string $campo, string $subcarpeta, array $tipos, int $maximoMb, string $prefijo): ?string
    {
        if ($archivo === null) {
            return null;
        }
        if (($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $mensaje = in_array($archivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? "El archivo supera el tamaño máximo de $maximoMb MB."
                : 'No se pudo subir el archivo. Probá de nuevo.';
            throw ErrorValidacion::campo($campo, $mensaje);
        }
        if ($archivo['size'] > $maximoMb * 1024 * 1024) {
            throw ErrorValidacion::campo($campo, "El archivo supera el tamaño máximo de $maximoMb MB.");
        }

        $tipoReal = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        if (!isset($tipos[$tipoReal])) {
            $permitidos = strtoupper(implode(', ', array_unique($tipos)));
            throw ErrorValidacion::campo($campo, "Formato no permitido. Usá $permitidos.");
        }

        $carpeta = self::carpeta($subcarpeta);
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0755, true);
        }
        $nombre = $prefijo . '_' . bin2hex(random_bytes(8)) . '.' . $tipos[$tipoReal];
        $destino = "$carpeta/$nombre";

        $movido = self::$aceptarArchivosLocales
            ? copy($archivo['tmp_name'], $destino)
            : move_uploaded_file($archivo['tmp_name'], $destino);
        if (!$movido) {
            throw new RuntimeException("No se pudo guardar el archivo en $carpeta");
        }
        return $nombre;
    }

    public static function borrar(string $subcarpeta, ?string $nombre): void
    {
        // basename() evita que un nombre como "../../config.php" salga de la carpeta.
        if ($nombre && basename($nombre) === $nombre) {
            $ruta = self::carpeta($subcarpeta) . '/' . $nombre;
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
    }
}
