<?php
// =====================================================================
// ENVÍO DE CORREOS
// En producción usa mail() de PHP (el servidor tiene que tener un
// servicio de correo configurado). En desarrollo NO manda nada: guarda
// el correo como archivo de texto en storage/correos/ para poder ver qué
// se habría enviado (XAMPP no tiene servidor de correo).
// =====================================================================

class Correo
{
    // Último correo "enviado" (lo usan los tests para leer el link).
    public static ?array $ultimo = null;
    // En los tests solo se guarda en $ultimo: no se escribe ni se manda nada.
    public static bool $modoPrueba = false;

    public static function enviar(string $para, string $asunto, string $texto): bool
    {
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        self::$ultimo = ['para' => $para, 'asunto' => $asunto, 'texto' => $texto];
        if (self::$modoPrueba) {
            return true;
        }

        if (Config::esDesarrollo()) {
            $carpeta = dirname(__DIR__, 2) . '/storage/correos';
            if (!is_dir($carpeta)) {
                mkdir($carpeta, 0755, true);
            }
            $archivo = $carpeta . '/' . date('Ymd_His') . '_' . preg_replace('/[^a-z0-9]/i', '_', $para) . '.txt';
            return file_put_contents($archivo, "Para: $para\nAsunto: $asunto\n\n$texto") !== false;
        }

        $cabeceras = 'From: ' . Config::obtener('correo.remitente') . "\r\n"
                   . "MIME-Version: 1.0\r\n"
                   . "Content-Type: text/plain; charset=UTF-8\r\n";
        $enviado = @mail($para, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $texto, $cabeceras);
        if (!$enviado) {
            // No se corta la operación: se registra para que el administrador lo revise.
            Registro::error(new RuntimeException("No se pudo enviar el correo a $para (revisar la configuración de correo del servidor)."));
        }
        return $enviado;
    }
}
