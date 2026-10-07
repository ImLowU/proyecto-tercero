<?php
// =====================================================================
// RUTAS DE LA API
// Cada línea relaciona "MÉTODO /ruta" con el método del controlador que
// la atiende. Todas cuelgan de /api (ej: GET /api/torneos/5).
//   GET    → consultar          POST   → crear o ejecutar una acción
//   PUT    → modificar          DELETE → borrar
// Los permisos se controlan dentro de cada controlador.
// =====================================================================

/** @var Router $router */

// ── Autenticación ──
$router->get('/auth/sesion', [AuthControlador::class, 'sesion']);
$router->post('/auth/registro', [AuthControlador::class, 'registro']);
$router->post('/auth/login', [AuthControlador::class, 'login']);
$router->post('/auth/logout', [AuthControlador::class, 'logout']);
$router->post('/auth/recuperar', [AuthControlador::class, 'recuperar']);
$router->post('/auth/restablecer', [AuthControlador::class, 'restablecer']);

// ── Perfil ──
$router->get('/perfil', [PerfilControlador::class, 'propio']);
$router->put('/perfil', [PerfilControlador::class, 'actualizar']);
$router->post('/perfil/foto', [PerfilControlador::class, 'subirFoto']);
$router->delete('/perfil/foto', [PerfilControlador::class, 'eliminarFoto']);
$router->get('/perfil/torneos', [PerfilControlador::class, 'torneos']);
$router->get('/perfil/partidos', [PerfilControlador::class, 'proximosPartidos']);
$router->get('/usuarios/{id}', [PerfilControlador::class, 'publico']);

// ── Catálogos y datos generales ──
$router->get('/juegos', [CatalogoControlador::class, 'juegos']);
$router->get('/modulos', [CatalogoControlador::class, 'modulos']);
$router->get('/estadisticas', [CatalogoControlador::class, 'estadisticas']);
$router->get('/partidos/proximos', [CalendarioControlador::class, 'proximos']);
$router->get('/partidos/recientes', [CalendarioControlador::class, 'recientes']);

// ── Torneos ──
$router->get('/torneos', [TorneoControlador::class, 'listar']);
$router->get('/torneos/destacado', [TorneoControlador::class, 'destacado']);
$router->post('/torneos', [TorneoControlador::class, 'crear']);
$router->get('/torneos/{id}', [TorneoControlador::class, 'ver']);
$router->post('/torneos/{id}', [TorneoControlador::class, 'actualizar']);
$router->delete('/torneos/{id}', [TorneoControlador::class, 'eliminar']);
$router->put('/torneos/{id}/publicacion', [TorneoControlador::class, 'publicacion']);
$router->post('/torneos/{id}/cancelar', [TorneoControlador::class, 'cancelar']);
$router->get('/torneos/{id}/reporte', [TorneoControlador::class, 'reporte']);

// ── Inscripciones y equipos ──
$router->get('/torneos/{id}/participantes', [InscripcionControlador::class, 'listar']);
$router->post('/torneos/{id}/participantes', [InscripcionControlador::class, 'agregar']);
$router->delete('/torneos/{id}/participantes/{idParticipante}', [InscripcionControlador::class, 'quitar']);
$router->post('/torneos/{id}/inscripcion', [InscripcionControlador::class, 'inscribirme']);
$router->delete('/torneos/{id}/inscripcion', [InscripcionControlador::class, 'darmeDeBaja']);
$router->post('/torneos/{id}/equipos', [EquipoControlador::class, 'crear']);
$router->get('/equipos/{id}', [EquipoControlador::class, 'ver']);
$router->get('/equipos/{id}/solicitudes', [EquipoControlador::class, 'solicitudes']);
$router->post('/equipos/{id}/solicitudes', [EquipoControlador::class, 'solicitar']);
$router->post('/solicitudes/{id}/respuesta', [EquipoControlador::class, 'responder']);

// ── Calendario, rondas, resultados y posiciones ──
$router->get('/torneos/{id}/calendario', [CalendarioControlador::class, 'ver']);
$router->post('/torneos/{id}/calendario', [CalendarioControlador::class, 'generar']);
$router->post('/torneos/{id}/rondas', [CalendarioControlador::class, 'siguienteRonda']);
$router->post('/torneos/{id}/rondas/publicar', [CalendarioControlador::class, 'publicarTodas']);
$router->get('/torneos/{id}/posiciones', [CalendarioControlador::class, 'posiciones']);
$router->put('/rondas/{id}', [CalendarioControlador::class, 'actualizarRonda']);
$router->put('/enfrentamientos/{id}', [CalendarioControlador::class, 'programar']);
$router->post('/enfrentamientos/{id}/resultado', [ResultadoControlador::class, 'cargar']);
$router->get('/enfrentamientos/{id}/resultados', [ResultadoControlador::class, 'historial']);

// ── Administración ──
$router->get('/admin/resumen', [AdminControlador::class, 'resumen']);
$router->get('/admin/usuarios', [AdminControlador::class, 'usuarios']);
$router->post('/admin/usuarios', [AdminControlador::class, 'crearUsuario']);
$router->put('/admin/usuarios/{id}', [AdminControlador::class, 'actualizarUsuario']);
$router->delete('/admin/usuarios/{id}', [AdminControlador::class, 'eliminarUsuario']);
$router->get('/admin/torneos', [AdminControlador::class, 'torneos']);
$router->get('/admin/modulos', [AdminControlador::class, 'modulos']);
$router->put('/admin/modulos/{id}', [AdminControlador::class, 'actualizarModulo']);
$router->get('/admin/configuracion', [AdminControlador::class, 'configuracion']);
$router->put('/admin/configuracion', [AdminControlador::class, 'actualizarConfiguracion']);
$router->get('/admin/auditoria', [AdminControlador::class, 'auditoria']);
$router->get('/admin/reportes/{tipo}', [AdminControlador::class, 'reporte']);
