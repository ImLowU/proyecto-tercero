-- ============================================================
-- SportTime — seed_sporttime.sql
-- Datos de prueba propios de SportTime (estructura idéntica al
-- esquema base del sistema, pero con identidades/contenido distintos).
-- Base destino: sporttime.
-- Incluye datos de demostración del sistema de bloqueo de cuentas
-- (failed_attempts / estado 'bloqueada') y del flujo de registro
-- público con aprobación (estado 'pendiente').
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── ROLES (referencia — idéntica al sistema) ────────────────
INSERT INTO roles (id, nombre, descripcion) VALUES
(1, 'administrador', 'Acceso total al sistema'),
(2, 'organizador',   'Gestiona torneos asignados'),
(3, 'participante',  'Accede a sus torneos y resultados')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- ─── TIPOS DE TORNEO (referencia) ────────────────────────────
INSERT INTO tipos_torneo (id, nombre, slug, descripcion) VALUES
(1, 'Liga',                'liga',                'Todos contra todos con tabla de posiciones'),
(2, 'Eliminación Directa', 'eliminacion_directa', 'Llave de eliminación directa con bracket'),
(3, 'Sistema Suizo',       'suizo',               'Rondas por rendimiento acumulado')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ─── MÓDULOS (referencia) ────────────────────────────────────
-- NOTA: se agrega el módulo 'torneos' (id 9) — faltaba en la versión anterior
-- de este seed, aunque el bloque de PERMISOS ya lo referenciaba. Con la FK
-- fk_permisos_modulo (permisos.modulo_slug -> modulos.slug) ya presente en
-- schema.sql, cargar el seed viejo en una base nueva dejaba un permiso
-- apuntando a un módulo inexistente. Corregido acá.
INSERT INTO modulos (id, nombre, slug, estado, descripcion) VALUES
(1,  'Liga',                'liga',                'activo', 'Módulo de torneos tipo liga'),
(2,  'Eliminación Directa', 'eliminacion_directa', 'activo', 'Módulo de brackets'),
(3,  'Sistema Suizo',       'suizo',               'activo', 'Módulo de rondas suizas'),
(4,  'Participantes',       'participantes',       'activo', 'Gestión de participantes individuales'),
(5,  'Equipos',             'equipos',             'activo', 'Gestión de equipos'),
(6,  'Auditoría',           'auditoria',           'activo', 'Registro de actividad del sistema'),
(7,  'Consulta pública',    'consulta_publica',    'activo', 'Vista pública sin autenticación'),
(8,  'Resultados',          'resultados',          'activo', 'Carga y corrección de resultados'),
(9,  'Torneos',             'torneos',             'activo', 'Gestión general de torneos')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ─── USUARIOS ────────────────────────────────────────────────
-- Contraseñas FUERTES y ÚNICAS por usuario (bcrypt cost 12).
-- Las contraseñas en texto plano NO se guardan en el repositorio;
-- se entregan por separado al responsable del proyecto.
--
-- Cuentas de demostración del sistema de bloqueo / registro (nuevas):
--   id 7 (martin.ibarra@sporttime.com) -> bloqueada tras 5 intentos fallidos
--                                          (failed_attempts=5, estado='bloqueada').
--   id 8 (sofia.duarte@sporttime.com)  -> autorregistro pendiente de aprobación
--                                          (estado='pendiente').
--   (contraseñas de estas dos cuentas entregadas por separado, igual que el resto)
--
-- ids 9-29: cuentas nuevas para vincular al resto de los participantes de prueba
-- (para poder probar "Bloquear cuenta" sobre cualquiera desde el panel admin).
-- A diferencia de las ids 1-8, estas SÍ tienen contraseña de demo conocida y
-- documentada: admin123 (mismo criterio que FlexArena). Ver docs/CREDENCIALES.md.
INSERT INTO usuarios (id, rol_id, nombre, email, password_hash, failed_attempts, estado) VALUES
(1, 1, 'Dirección SportTime', 'admin@sporttime.com',        '$2y$12$JCxL3QUImjg3m4T2Bp2jAukj077TaFVHLtKe5nsWp4ZtU4pPi2phO', 0, 'activo'),
(2, 2, 'Bruno Vega',          'bruno@sporttime.com',        '$2y$12$FB6F8W70k.tdQFiWKyFzA.2F/AqOVVhV9.AIc2SJX5rdgx/x/gZD.', 0, 'activo'),
(3, 2, 'Carla Ríos',          'carla@sporttime.com',        '$2y$12$Fk7FkgKymN19yeSijeKUouHxQuKzkZOzbaNKrTXpS.ooJP8qoQakC', 0, 'activo'),
(4, 3, 'Iván Morales',        'ivan@sporttime.com',         '$2y$12$jijiQHc1Calg0K1hbqTEhO1wtJN./CGOo7Jhqs1rScS3kBjp5BmT2', 0, 'activo'),
(5, 3, 'Lucía Ferrari',       'lucia@sporttime.com',        '$2y$12$w/cs43WdXKkfquIAyuSIT.5H/TJVqZHdz0eIe46CcQDs.J95IODWa', 0, 'activo'),
(6, 3, 'Diego Salas',         'diego@sporttime.com',        '$2y$12$BC0KleUn/Ni1/Dp522QgP.Ml4yCfDgo9nCTGZHfoRx19SS6XmVNt2', 0, 'activo'),
(7, 2, 'Martín Ibarra',       'martin.ibarra@sporttime.com','$2y$12$g/Q813JKT8ztAjwq7//xU.o58Z62PLblcld.Yhskh31YICj7fxL9a', 5, 'bloqueada'),
(8, 3, 'Sofía Duarte',        'sofia.duarte@sporttime.com', '$2y$12$QfM1KmC3BwaEF1efEal6Ou/6EiElzguBiGczLW5RxgDQGMJ95M7si', 0, 'pendiente'),
(9,  3, 'Camilo Ávila',       'camilo@sporttime.com',       '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(10, 3, 'Renata Ponce',       'renata@sporttime.com',       '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(11, 3, 'Bruno Ledesma',      'brunol@sporttime.com',       '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(12, 3, 'Aldana Vidal',       'aldana@sporttime.com',       '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(13, 3, 'Joaquín Ramos',      'joaquin@sporttime.com',      '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(14, 3, 'Milena Costa',       'milena@sporttime.com',       '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(15, 3, 'Thiago Núñez',       'thiago@sporttime.com',       '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(16, 3, 'Paula Bravo',        'paula@sporttime.com',        '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(17, 3, 'Ezequiel Ríos',      'ezequiel@sporttime.com',     '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(18, 3, 'Brenda Acosta',      'brenda@sporttime.com',       '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(19, 3, 'Facundo Gil',        'facundo@sporttime.com',      '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(20, 3, 'Carolina Paz',       'carolina@sporttime.com',     '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(21, 3, 'Ramiro Luna',        'ramiro@sporttime.com',       '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(22, 3, 'Daniela Cruz',       'daniela@sporttime.com',      '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(23, 3, 'Maximiliano Ortiz',  'maxi@sporttime.com',         '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(24, 3, 'Victoria Rey',       'victoria@sporttime.com',     '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(25, 3, 'Gonzalo Medina',     'gonzalo@sporttime.com',      '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(26, 3, 'Abril Sandoval',     'abril@sporttime.com',        '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(27, 3, 'Lautaro Vega',       'lautaro@sporttime.com',      '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(28, 3, 'Melina Soto',        'melina@sporttime.com',       '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo'),
(29, 3, 'Iñaki Romero',       'inaki@sporttime.com',        '$2y$12$UrwIRSDEIRFFmMl8VNXJiOwrFZ9pUoO4yiMKwN9RUQ2ITDohArHFW', 0, 'activo')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), password_hash = VALUES(password_hash), failed_attempts = VALUES(failed_attempts), estado = VALUES(estado);

-- ─── PARTICIPANTES (24 + 1 pendiente) ─────────────────────────
-- id 25: perfil vinculado al autorregistro pendiente (usuario 8).
-- Todos los participantes están vinculados a una cuenta de usuario, para poder
-- probar "Bloquear cuenta" sobre cualquiera de ellos desde el panel admin.
INSERT INTO participantes (id, usuario_id, nombre, documento, nick, email, estado) VALUES
(1,  4,   'Iván Morales',        '40002001', 'IvanM',     'ivan@sporttime.com',     'activo'),
(2,  5,   'Lucía Ferrari',       '40002002', 'LuFerrari', 'lucia@sporttime.com',    'activo'),
(3,  6,   'Diego Salas',         '40002003', 'DiegoS',    'diego@sporttime.com',    'activo'),
(4,  9,   'Camilo Ávila',        '40002004', 'CamiA',     'camilo@sporttime.com',   'activo'),
(5,  10,  'Renata Ponce',        '40002005', 'RenaP',     'renata@sporttime.com',   'activo'),
(6,  11,  'Bruno Ledesma',       '40002006', 'BrunoL',    'brunol@sporttime.com',   'activo'),
(7,  12,  'Aldana Vidal',        '40002007', 'AldaV',     'aldana@sporttime.com',   'activo'),
(8,  13,  'Joaquín Ramos',       '40002008', 'JoaR',      'joaquin@sporttime.com',  'activo'),
(9,  14,  'Milena Costa',        '40002009', 'MileC',     'milena@sporttime.com',   'activo'),
(10, 15,  'Thiago Núñez',        '40002010', 'ThiN',      'thiago@sporttime.com',   'activo'),
(11, 16,  'Paula Bravo',         '40002011', 'PauB',      'paula@sporttime.com',    'activo'),
(12, 17,  'Ezequiel Ríos',       '40002012', 'EzeR',      'ezequiel@sporttime.com', 'activo'),
(13, 18,  'Brenda Acosta',       '40002013', 'BreA',      'brenda@sporttime.com',   'activo'),
(14, 19,  'Facundo Gil',         '40002014', 'FacuG',     'facundo@sporttime.com',  'activo'),
(15, 20,  'Carolina Paz',        '40002015', 'CaroP',     'carolina@sporttime.com', 'activo'),
(16, 21,  'Ramiro Luna',         '40002016', 'RamiL',     'ramiro@sporttime.com',   'activo'),
(17, 22,  'Daniela Cruz',        '40002017', 'DaniC',     'daniela@sporttime.com',  'activo'),
(18, 23,  'Maximiliano Ortiz',   '40002018', 'MaxiO',     'maxi@sporttime.com',     'activo'),
(19, 24,  'Victoria Rey',        '40002019', 'VicR',      'victoria@sporttime.com', 'activo'),
(20, 25,  'Gonzalo Medina',      '40002020', 'GonM',      'gonzalo@sporttime.com',  'activo'),
(21, 26,  'Abril Sandoval',      '40002021', 'AbriS',     'abril@sporttime.com',    'activo'),
(22, 27,  'Lautaro Vega',        '40002022', 'LautV',     'lautaro@sporttime.com',  'activo'),
(23, 28,  'Melina Soto',         '40002023', 'MeliS',     'melina@sporttime.com',   'activo'),
(24, 29,  'Iñaki Romero',        '40002024', 'InaR',      'inaki@sporttime.com',    'activo'),
(25, 8,   'Sofía Duarte',        '40002025', 'SofiD',     'sofia.duarte@sporttime.com', 'pendiente')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ─── EQUIPOS (8) ─────────────────────────────────────────────
INSERT INTO equipos (id, nombre, categoria, disciplina, estado) VALUES
(1, 'Titanes FC',        'A', 'Fútbol',      'activo'),
(2, 'Vértigo United',    'A', 'Fútbol',      'activo'),
(3, 'Phantom Esports',   'B', 'Esports',     'activo'),
(4, 'Cóndores FC',       'A', 'Fútbol',      'activo'),
(5, 'Nova Squad',        'B', 'Esports',     'activo'),
(6, 'Reyes del Tablero', 'A', 'Ajedrez',     'activo'),
(7, 'Pixel Hunters',     'B', 'Esports',     'activo'),
(8, 'Trueno BC',         'A', 'Baloncesto',  'activo')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ─── EQUIPO-PARTICIPANTES ────────────────────────────────────
INSERT INTO equipo_participantes (equipo_id, participante_id, rol_en_equipo) VALUES
(1, 1,  'capitan'), (1, 2,  'jugador'), (1, 3,  'jugador'),
(2, 4,  'capitan'), (2, 5,  'jugador'), (2, 6,  'jugador'),
(3, 7,  'capitan'), (3, 8,  'jugador'), (3, 9,  'jugador'),
(4, 10, 'capitan'), (4, 11, 'jugador'), (4, 12, 'jugador'),
(5, 13, 'capitan'), (5, 14, 'jugador'),
(6, 15, 'capitan'), (6, 16, 'jugador'),
(7, 17, 'capitan'), (7, 18, 'jugador'),
(8, 19, 'capitan'), (8, 20, 'jugador')
ON DUPLICATE KEY UPDATE rol_en_equipo = VALUES(rol_en_equipo);

-- ─── PERMISOS (referencia) ───────────────────────────────────
INSERT INTO permisos (rol_id, modulo_slug, puede_ver, puede_crear, puede_editar, puede_eliminar) VALUES
(2, 'torneos',          1, 0, 1, 0),
(2, 'resultados',       1, 1, 1, 0),
(2, 'participantes',    1, 1, 1, 0),
(2, 'equipos',          1, 0, 0, 0),
(2, 'liga',             1, 1, 1, 0),
(2, 'eliminacion_directa', 1, 1, 1, 0),
(2, 'suizo',            1, 1, 1, 0),
(3, 'consulta_publica', 1, 0, 0, 0),
(3, 'resultados',       1, 0, 0, 0)
ON DUPLICATE KEY UPDATE puede_ver = VALUES(puede_ver);

-- ─── TORNEOS (6) ─────────────────────────────────────────────
INSERT INTO torneos (id, nombre, descripcion, tipo_torneo_id, modalidad, estado, fecha_inicio, fecha_fin, publico,
                     permite_empates, puntos_victoria, puntos_empate, puntos_derrota, usa_puntos_favor,
                     nombre_puntos, rondas_suizo, bye_suizo, creado_por) VALUES
(1, 'Liga Apertura SportTime 2026', 'Liga de fútbol amateur — temporada Apertura 2026.',        1, 'individual', 'en_curso',   '2026-03-01', '2026-06-30', 1, 1, 3, 1, 0, 1, 'puntos', NULL, NULL, 1),
(2, 'SportTime Esports Showdown',   'Eliminación directa de esports por equipos.',              2, 'equipos',    'en_curso',   '2026-04-01', '2026-04-30', 1, 0, 1, 0, 0, 1, 'mapas',  NULL, NULL, 1),
(3, 'Maratón de Ajedrez SportTime', 'Sistema Suizo de ajedrez a 5 rondas.',                     3, 'individual', 'en_curso',   '2026-05-01', '2026-05-31', 1, 1, 1, 0, 0, 0, 'puntos', 5, 'victoria', 1),
(4, 'Liga Basket SportTime',        'Liga abierta de baloncesto, todos contra todos.',          1, 'equipos',    'inscripcion','2026-07-01', '2026-09-30', 1, 0, 2, 0, 0, 1, 'puntos', NULL, NULL, 2),
(5, 'Copa Tenis de Mesa SportTime', 'Eliminación directa individual de tenis de mesa.',         2, 'individual', 'inscripcion','2026-06-15', '2026-07-15', 1, 0, 1, 0, 0, 1, 'sets',   NULL, NULL, 2),
(6, 'Desafío Estratégico SportTime','Suizo de juegos de estrategia, 4 rondas.',                 3, 'individual', 'borrador',   '2026-08-01', '2026-08-31', 1, 1, 1, 0, 0, 0, 'puntos', 4, 'sin_puntos', 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

UPDATE torneos SET min_integrantes_equipo = 3 WHERE modalidad = 'equipos';

-- ─── TORNEO ORGANIZADORES ────────────────────────────────────
-- (el organizador id=7 está bloqueado, por eso no se le asigna ningún torneo)
INSERT INTO torneo_organizadores (torneo_id, usuario_id) VALUES
(1, 2), (2, 2), (3, 3), (4, 2), (5, 3), (6, 1)
ON DUPLICATE KEY UPDATE torneo_id = VALUES(torneo_id);

-- ─── INSCRIPCIONES ───────────────────────────────────────────
INSERT INTO inscripciones (torneo_id, participante_id, equipo_id, estado, orden_seed) VALUES
(1,  1, NULL, 'activa', 1),
(1,  2, NULL, 'activa', 2),
(1,  3, NULL, 'activa', 3),
(1,  4, NULL, 'activa', 4),
(1,  5, NULL, 'activa', 5),
(1,  6, NULL, 'activa', 6),
(1,  7, NULL, 'activa', 7),
(1,  8, NULL, 'activa', 8),
(2, NULL, 1, 'activa', 1),
(2, NULL, 2, 'activa', 2),
(2, NULL, 3, 'activa', 3),
(2, NULL, 4, 'activa', 4),
(3,  9, NULL, 'activa', 1),
(3, 10, NULL, 'activa', 2),
(3, 11, NULL, 'activa', 3),
(3, 12, NULL, 'activa', 4),
(3, 13, NULL, 'activa', 5),
(3, 14, NULL, 'activa', 6),
(3, 15, NULL, 'activa', 7),
(4, NULL, 5, 'activa', 1),
(4, NULL, 6, 'activa', 2),
(4, NULL, 7, 'activa', 3),
(4, NULL, 8, 'activa', 4),
(5, 16, NULL, 'activa', 1),
(5, 17, NULL, 'activa', 2),
(5, 18, NULL, 'activa', 3),
(5, 19, NULL, 'activa', 4)
ON DUPLICATE KEY UPDATE estado = VALUES(estado);

-- ─── RONDAS ──────────────────────────────────────────────────
INSERT INTO rondas (id, torneo_id, numero, nombre, estado) VALUES
(1, 1, 1, 'Fecha 1', 'cerrada'),
(2, 1, 2, 'Fecha 2', 'cerrada'),
(3, 1, 3, 'Fecha 3', 'en_curso'),
(4, 1, 4, 'Fecha 4', 'pendiente'),
(5, 1, 5, 'Fecha 5', 'pendiente'),
(6, 1, 6, 'Fecha 6', 'pendiente'),
(7, 1, 7, 'Fecha 7', 'pendiente'),
(8,  2, 1, 'Semifinales', 'cerrada'),
(9,  2, 2, 'Final',       'en_curso'),
(10, 3, 1, 'Ronda 1', 'cerrada'),
(11, 3, 2, 'Ronda 2', 'en_curso')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ─── ENFRENTAMIENTOS ─────────────────────────────────────────
INSERT INTO enfrentamientos (id, torneo_id, ronda_id, participante_a_id, participante_b_id, ganador_participante_id, perdedor_participante_id, estado, es_bye, orden) VALUES
(1,  1, 1, 1, 5, 1, 5, 'finalizado', 0, 1),
(2,  1, 1, 2, 6, 2, 6, 'finalizado', 0, 2),
(3,  1, 1, 3, 7, 7, 3, 'finalizado', 0, 3),
(4,  1, 1, 4, 8, 4, 8, 'finalizado', 0, 4),
(5,  1, 2, 1, 6, 1, 6, 'finalizado', 0, 1),
(6,  1, 2, 2, 7, 7, 2, 'finalizado', 0, 2),
(7,  1, 2, 3, 8, 3, 8, 'finalizado', 0, 3),
(8,  1, 2, 4, 5, 4, 5, 'finalizado', 0, 4),
(9,  1, 3, 1, 7, NULL, NULL, 'pendiente', 0, 1),
(10, 1, 3, 2, 8, NULL, NULL, 'pendiente', 0, 2),
(11, 1, 3, 3, 5, NULL, NULL, 'pendiente', 0, 3),
(12, 1, 3, 4, 6, NULL, NULL, 'pendiente', 0, 4),
(13, 2, 8, NULL, NULL, NULL, NULL, 'finalizado', 0, 1),
(14, 2, 8, NULL, NULL, NULL, NULL, 'finalizado', 0, 2),
(15, 2, 9, NULL, NULL, NULL, NULL, 'pendiente', 0, 1),
(16, 3, 10, 9,  13, 9,  13, 'finalizado', 0, 1),
(17, 3, 10, 10, 14, 14, 10, 'finalizado', 0, 2),
(18, 3, 10, 11, 12, 11, 12, 'finalizado', 0, 3),
(19, 3, 10, 15, NULL, 15, NULL, 'bye', 1, 4),
(20, 3, 11, 9,  14, NULL, NULL, 'pendiente', 0, 1),
(21, 3, 11, 15, 11, NULL, NULL, 'pendiente', 0, 2),
(22, 3, 11, 10, NULL, 10, NULL, 'bye', 1, 3)
ON DUPLICATE KEY UPDATE estado = VALUES(estado);

UPDATE enfrentamientos SET equipo_a_id=1, equipo_b_id=4, ganador_equipo_id=1, perdedor_equipo_id=4 WHERE id=13;
UPDATE enfrentamientos SET equipo_a_id=2, equipo_b_id=3, ganador_equipo_id=2, perdedor_equipo_id=3 WHERE id=14;
UPDATE enfrentamientos SET equipo_a_id=1, equipo_b_id=2 WHERE id=15;

-- ─── RESULTADOS ──────────────────────────────────────────────
INSERT INTO resultados (enfrentamiento_id, puntos_a, puntos_b, ganador_participante_id, ganador_equipo_id, estado, cargado_por) VALUES
(1, 3, 1, 1, NULL, 'cargado', 1),
(2, 2, 0, 2, NULL, 'cargado', 1),
(3, 1, 2, 7, NULL, 'cargado', 1),
(4, 3, 1, 4, NULL, 'cargado', 1),
(5, 2, 1, 1, NULL, 'cargado', 1),
(6, 0, 3, 7, NULL, 'cargado', 1),
(7, 2, 1, 3, NULL, 'cargado', 1),
(8, 4, 0, 4, NULL, 'cargado', 1),
(13, 2, 1, NULL, 1, 'cargado', 1),
(14, 2, 0, NULL, 2, 'cargado', 1),
(16, 2, 1, 9,  NULL, 'cargado', 1),
(17, 0, 1, 14, NULL, 'cargado', 1),
(18, 1, 0, 11, NULL, 'cargado', 1),
(19, 1, 0, 15, NULL, 'cargado', 1),
(22, 1, 0, 10, NULL, 'cargado', 1)
ON DUPLICATE KEY UPDATE estado = VALUES(estado);

-- ─── TABLA POSICIONES — Torneo 1 ─────────────────────────────
INSERT INTO tabla_posiciones (torneo_id, participante_id, pj, pg, pe, pp, pf, pc, diferencia, puntos, posicion) VALUES
(1, 1, 2, 2, 0, 0,  5, 2,  3, 6, 1),
(1, 4, 2, 2, 0, 0,  7, 1,  6, 6, 2),
(1, 7, 2, 1, 0, 1,  5, 4,  1, 3, 3),
(1, 3, 2, 1, 0, 1,  3, 2,  1, 3, 4),
(1, 2, 2, 1, 0, 1,  2, 2,  0, 3, 5),
(1, 8, 2, 0, 0, 2,  2, 8, -6, 0, 6),
(1, 5, 2, 0, 0, 2,  1, 6, -5, 0, 7),
(1, 6, 2, 0, 0, 2,  0, 5, -5, 0, 8)
ON DUPLICATE KEY UPDATE posicion = VALUES(posicion);

-- ─── AUDITORÍA (muestra) ─────────────────────────────────────
INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, descripcion, ip) VALUES
(1, 'login_exitoso',        'usuarios',     1, 'Inicio de sesión: admin@sporttime.com',        '127.0.0.1'),
(1, 'crear_torneo',         'torneos',      1, 'Torneo creado: Liga Apertura SportTime 2026',  '127.0.0.1'),
(1, 'crear_torneo',         'torneos',      2, 'Torneo creado: SportTime Esports Showdown',    '127.0.0.1'),
(1, 'crear_torneo',         'torneos',      3, 'Torneo creado: Maratón de Ajedrez SportTime',  '127.0.0.1'),
(1, 'generar_fixture',      'torneos',      1, 'Fixture Liga generado para torneo 1',          '127.0.0.1'),
(1, 'generar_bracket',      'torneos',      2, 'Bracket Eliminación generado',                 '127.0.0.1'),
(1, 'generar_ronda_suiza',  'torneos',      3, 'Ronda 1 (Suizo) generada',                     '127.0.0.1'),
(1, 'cargar_resultado',     'resultados',   1, 'Resultado: Enfrentamiento 1: 3-1',             '127.0.0.1'),
(1, 'cargar_resultado',     'resultados',   2, 'Resultado: Enfrentamiento 2: 2-0',             '127.0.0.1'),
(2, 'login_exitoso',        'usuarios',     2, 'Inicio de sesión: bruno@sporttime.com',        '127.0.0.1'),
(2, 'crear_usuario',        'usuarios',     5, 'Usuario creado: lucia@sporttime.com',          '127.0.0.1'),
(1, 'inscribir_participante','inscripciones',1,'Participante 1 inscrito en torneo 1',          '127.0.0.1'),
(1, 'inscribir_participante','inscripciones',1,'Participante 2 inscrito en torneo 1',          '127.0.0.1'),
-- Demostración: bloqueo de cuenta tras intentos fallidos (usuario id 7)
(7, 'login_fallido',        'usuarios',     7, 'Intento fallido para usuario id: 7',           '203.0.113.15'),
(7, 'login_fallido',        'usuarios',     7, 'Intento fallido para usuario id: 7',           '203.0.113.15'),
(7, 'cuenta_bloqueada',     'usuarios',     7, 'Cuenta bloqueada por excesivos intentos de login', '203.0.113.15'),
-- Demostración: autorregistro pendiente de aprobación (usuario id 8)
(8, 'registro_participante','usuarios',     8, 'Auto-registro de participante: sofia.duarte@sporttime.com (pendiente de aprobación)', '198.51.100.22');

SET FOREIGN_KEY_CHECKS = 1;
