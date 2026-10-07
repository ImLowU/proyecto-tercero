<?php
// =====================================================================
// APLICACIÓN
// Punto central por el que pasa cada petición a la API:
//   1. inicia la sesión y carga el usuario actual,
//   2. le avisa a la base quién está operando (para la auditoría),
//   3. controla el token CSRF en las peticiones que modifican datos,
//   4. le pasa la petición al router, que llama al controlador,
//   5. convierte cualquier error en una respuesta JSON clara
//      (manejo centralizado de errores, punto 4.2 de la letra).
// =====================================================================

require_once __DIR__ . '/autoload.php';

class Aplicacion
{
    private static ?Router $router = null;

    public static function router(): Router
    {
        if (self::$router === null) {
            $router = new Router();
            require __DIR__ . '/rutas.php';
            self::$router = $router;
        }
        return self::$router;
    }

    public static function manejar(Peticion $peticion): Respuesta
    {
        date_default_timezone_set(Config::obtener('zona_horaria', 'America/Montevideo'));

        try {
            Sesion::iniciar();
            Sesion::cargar($peticion);
            BaseDatos::fijarUsuarioAuditoria(Sesion::idUsuario(), $peticion->ip);

            if (!in_array($peticion->metodo, ['GET', 'HEAD', 'OPTIONS'], true) && !Csrf::valido($peticion)) {
                throw new ErrorHttp('La página quedó desactualizada o la sesión venció. Recargá la página e intentá de nuevo.', 403);
            }
            self::exigirCambioDeContrasena($peticion);

            $respuesta = self::router()->despachar($peticion);
        } catch (ErrorHttp $e) {
            $respuesta = Respuesta::error($e->getMessage(), $e->estado, $e->extra);
        } catch (PDOException $e) {
            Registro::error($e, $peticion);
            $sinConexion = in_array((int)($e->errorInfo[1] ?? $e->getCode()), [1044, 1045, 1049, 2002, 2006], true);
            $respuesta = Respuesta::error(
                $sinConexion ? 'El sistema no está disponible en este momento (no hay conexión con la base de datos).'
                             : 'Ocurrió un error inesperado al guardar los datos. Probá de nuevo en un momento.',
                $sinConexion ? 503 : 500,
                Config::esDesarrollo() ? ['detalle' => $e->getMessage()] : []
            );
        } catch (Throwable $e) {
            Registro::error($e, $peticion);
            $respuesta = Respuesta::error('Ocurrió un error inesperado. Probá de nuevo en un momento.', 500,
                Config::esDesarrollo() ? ['detalle' => $e->getMessage()] : []);
        }

        $respuesta->cookies = array_merge($respuesta->cookies, Sesion::tomarCookies());
        // Cabeceras de seguridad para todas las respuestas de la API.
        $respuesta->cabeceras += [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'DENY',
            'Referrer-Policy'        => 'same-origin',
            'Cache-Control'          => 'no-store',
        ];
        return $respuesta;
    }

    // Si la cuenta tiene que cambiar la contraseña (ej: el admin inicial,
    // cuya contraseña está publicada), la API solo deja ver la sesión, el
    // perfil y cambiarla. Controlarlo acá y no solo en la página evita que
    // alguien use la API directamente salteándose el aviso.
    private static function exigirCambioDeContrasena(Peticion $peticion): void
    {
        $usuario = Sesion::usuario();
        if (!$usuario || !$usuario['debe_cambiar_contrasena']) {
            return;
        }
        $permitidas = ['GET /auth/sesion', 'POST /auth/logout', 'GET /perfil', 'PUT /perfil'];
        if (!in_array("{$peticion->metodo} {$peticion->ruta}", $permitidas, true)) {
            throw new ErrorHttp('Antes de seguir tenés que cambiar tu contraseña desde tu perfil.', 403, ['debe_cambiar_contrasena' => true]);
        }
    }
}
