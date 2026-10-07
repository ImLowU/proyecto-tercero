<?php
// =====================================================================
// CONTROLADOR BASE
// En el patrón MVC el controlador recibe la petición, controla permisos,
// valida los datos, le pide al modelo lo que necesita y arma la
// respuesta. No escribe SQL (eso es del modelo) ni HTML (eso es de la
// vista: las páginas de public/).
// =====================================================================

abstract class Controlador
{
    public function __construct(protected Peticion $peticion)
    {
    }

    protected function usuario(): ?array
    {
        return Sesion::usuario();
    }

    protected function idUsuario(): ?int
    {
        return Sesion::idUsuario();
    }

    protected function esAdmin(): bool
    {
        return Sesion::rol() === 'administrador';
    }

    // Corta con 401 si no hay sesión iniciada. Devuelve el usuario.
    protected function requerirSesion(): array
    {
        $usuario = Sesion::usuario();
        if (!$usuario) {
            throw ErrorHttp::noAutenticado();
        }
        return $usuario;
    }

    // Corta con 403 si el rol del usuario no está entre los permitidos.
    protected function requerirRol(string ...$roles): array
    {
        $usuario = $this->requerirSesion();
        if (!in_array($usuario['rol'], $roles, true)) {
            throw ErrorHttp::prohibido('Tu rol no tiene permiso para hacer esto.');
        }
        return $usuario;
    }

    // ¿El usuario actual organiza este torneo o es administrador?
    protected function gestiona(array $torneo): bool
    {
        return $this->esAdmin() || (int)$torneo['id_organizador'] === $this->idUsuario();
    }

    // El torneo, si el usuario actual lo puede ver. Un torneo sin publicar
    // solo lo ven su organizador y el administrador (para el resto, 404).
    protected function torneoVisible(int $idTorneo): array
    {
        $torneo = Torneo::buscar($idTorneo);
        if (!$torneo || (!$torneo['publicado'] && !$this->gestiona($torneo))) {
            throw ErrorHttp::noEncontrado('Torneo no encontrado.');
        }
        return $torneo;
    }

    // El torneo, si el usuario actual lo puede gestionar (organizador o admin).
    protected function torneoGestionable(int $idTorneo): array
    {
        $this->requerirSesion();
        $torneo = Torneo::buscar($idTorneo);
        if (!$torneo) {
            throw ErrorHttp::noEncontrado('Torneo no encontrado.');
        }
        if (!$this->gestiona($torneo)) {
            throw ErrorHttp::prohibido('Solo el organizador del torneo o un administrador pueden hacer esto.');
        }
        return $torneo;
    }

    // Página y tamaño de página pedidos en la URL (?pagina=2&por_pagina=12).
    protected function paginacion(int $porDefecto = 12, int $maximo = 100): array
    {
        $pagina = max(1, (int)$this->peticion->param('pagina', 1));
        $porPagina = (int)$this->peticion->param('por_pagina', $porDefecto);
        $porPagina = max(1, min($maximo, $porPagina));
        return [$pagina, $porPagina];
    }

    // Respuesta estándar para listados paginados.
    protected function listado(string $clave, array $filas, int $total, int $pagina, int $porPagina): Respuesta
    {
        return Respuesta::exito('', [
            $clave => $filas,
            'paginacion' => [
                'pagina'     => $pagina,
                'por_pagina' => $porPagina,
                'total'      => $total,
                'paginas'    => max(1, (int)ceil($total / $porPagina)),
            ],
        ]);
    }
}
