-- =====================================================================
-- MIGRACIÓN A LA VERSIÓN 2 — PASO 2 DE 2: COPIAR LOS DATOS
-- Se ejecuta después de migracion_v7_preparar.sql y 01_esquema.sql.
-- Copia todo desde las tablas v6_ a las nuevas, adaptándolo al nuevo
-- modelo, y al final borra las tablas v6_. Se conservan los id para
-- que los links viejos (torneo.html?id=5) sigan funcionando.
-- =====================================================================

SET NAMES utf8mb4;

-- ── Catálogos nuevos (mismos valores que 02_datos_base.sql) ──
INSERT INTO roles (id_rol, codigo, nombre, descripcion) VALUES
(1, 'administrador', 'Administrador general', 'Control completo: usuarios, módulos, configuración, reportes y auditoría.'),
(2, 'organizador',   'Organizador de torneo', 'Crea y administra sus propios torneos: inscripciones, rondas, fechas y resultados.'),
(3, 'participante',  'Participante',          'Se inscribe a torneos, consulta su calendario, resultados y posiciones.');

INSERT INTO modulos_competencia (id_modulo, codigo, nombre, descripcion, habilitado) VALUES
(1, 'liga',                'Liga',                'Todos contra todos. Gana quien suma más puntos en la tabla.', 1),
(2, 'eliminacion_directa', 'Eliminación directa', 'Llaves: quien pierde queda afuera y el ganador avanza hasta la final.', 1),
(3, 'sistema_suizo',       'Sistema suizo',       'Rondas en las que se enfrentan participantes con puntajes parecidos, sin repetir rivales.', 1);

INSERT INTO configuracion_sistema (clave, valor, tipo, descripcion) VALUES
('nombre_sitio',               'NextLeague', 'texto',    'Nombre que se muestra en el sitio.'),
('registro_abierto',           '1',          'booleano', 'Si está en 1, cualquiera puede crearse una cuenta.'),
('max_intentos_login',         '5',          'numero',   'Intentos fallidos de inicio de sesión antes del bloqueo temporal.'),
('minutos_bloqueo_login',      '15',         'numero',   'Minutos que dura el bloqueo después de superar los intentos.'),
('minutos_inactividad_sesion', '120',        'numero',   'Minutos sin actividad después de los cuales se cierra la sesión.'),
('dias_recordarme',            '30',         'numero',   'Días que dura la opción "Recordarme" al iniciar sesión.'),
('minutos_token_recuperacion', '30',         'numero',   'Minutos de validez del link para restablecer la contraseña.'),
('torneos_por_pagina',         '12',         'numero',   'Cantidad de torneos por página en los listados.'),
('max_tamano_imagen_mb',       '3',          'numero',   'Tamaño máximo de logos y fotos de perfil, en MB.'),
('max_tamano_reglas_mb',       '5',          'numero',   'Tamaño máximo del PDF de reglas, en MB.');

-- ── Juegos: se conservan los id y se agrega la modalidad sugerida ──
INSERT INTO juegos (id_juego, nombre, categoria, modalidad_sugerida, icono)
SELECT id_juego, nombre, categoria,
       CASE WHEN nombre IN ('Tenis', 'Tenis de mesa', 'Bádminton', 'Boxeo', 'MMA', 'Judo', 'Karate',
                            'Taekwondo', 'Atletismo', 'Natación', 'Ciclismo', 'Golf', 'Levantamiento de pesas',
                            'Gimnasia', 'Ajedrez', 'Damas', 'Póker', 'Dominó', 'FIFA / EA Sports FC', 'Fortnite')
            THEN 'individual' ELSE 'equipos' END,
       icono
FROM v6_juegos;

-- ── Usuarios: el rol pasa de texto a la tabla roles ──
-- Las cuentas viejas "publico" pasan a participante. Quien siga usando la
-- contraseña inicial publicada en el repositorio (Admin123!) queda
-- obligado a cambiarla en el próximo ingreso.
INSERT INTO usuarios (id_usuario, nombre_usuario, correo, contrasena_hash, id_rol, foto_perfil,
                      biografia, activo, debe_cambiar_contrasena, fecha_registro)
SELECT id_usuario, nombre_usuario, correo, contrasena_hash,
       CASE rol WHEN 'administrador' THEN 1 WHEN 'organizador' THEN 2 ELSE 3 END,
       foto_perfil, biografia, activo,
       IF(contrasena_hash = '$2y$10$Sav18ZWDPzVAI5fc4my9QOJpOkh13BK6kY8ddmcSI/rYxTwCcxxSe', 1, 0),
       fecha_registro
FROM v6_usuarios;

-- ── Torneos: formato → módulo, num_equipos → cupo, y se deduce la modalidad ──
-- Si el torneo tiene equipos, es por equipos; si tiene inscriptos sueltos
-- o es de categoría mental, es individual (era la regla de la pantalla vieja).
INSERT INTO torneos (id_torneo, nombre, id_juego, id_modulo, id_organizador, modalidad, cupo, premio, moneda,
                     fecha_inicio, fecha_fin, descripcion, logo, reglas_pdf, estado, publicado, fecha_creacion)
SELECT t.id_torneo, t.nombre, t.id_juego,
       CASE t.formato WHEN 'liga' THEN 1 WHEN 'eliminacion_directa' THEN 2 ELSE 3 END,
       t.id_organizador,
       CASE WHEN EXISTS (SELECT 1 FROM v6_equipos e WHERE e.id_torneo = t.id_torneo) THEN 'equipos'
            WHEN EXISTS (SELECT 1 FROM v6_participantes p WHERE p.id_torneo = t.id_torneo AND p.id_usuario IS NOT NULL) THEN 'individual'
            WHEN j.categoria = 'mental' THEN 'individual'
            ELSE 'equipos' END,
       LEAST(256, GREATEST(2, t.num_equipos)),
       GREATEST(0, t.premio), t.moneda,
       t.fecha_inicio, GREATEST(t.fecha_fin, t.fecha_inicio),
       t.descripcion, t.logo, t.reglas_pdf, t.estado, 1, t.fecha_creacion
FROM v6_torneos t
JOIN v6_juegos j ON j.id_juego = t.id_juego;

-- La configuración por defecto: 3/1/0 puntos. En eliminación directa no hay empates.
INSERT INTO configuracion_torneo (id_torneo, permitir_empates)
SELECT id_torneo, IF(id_modulo = 2, 0, 1) FROM torneos;

-- ── Equipos, miembros y solicitudes: sin cambios de formato ──
INSERT INTO equipos (id_equipo, id_torneo, nombre, logo, id_capitan)
SELECT id_equipo, id_torneo, nombre, logo, id_capitan FROM v6_equipos;

INSERT INTO equipo_miembros (id_equipo, id_usuario, fecha_union)
SELECT id_equipo, id_usuario, fecha_union FROM v6_equipo_miembros;

INSERT INTO equipo_solicitudes (id_solicitud, id_equipo, id_usuario, estado, fecha_solicitud, fecha_respuesta)
SELECT id_solicitud, id_equipo, id_usuario, estado, fecha_solicitud, fecha_respuesta FROM v6_equipo_solicitudes;

-- ── Participantes: solo los que coinciden con la modalidad del torneo ──
-- (en la versión anterior se podía quedar "inscripto" a un torneo por
-- equipos sin equipo, y esa inscripción nunca llegaba a jugar).
INSERT INTO participantes (id_participante, id_torneo, id_usuario, id_equipo, fecha_inscripcion)
SELECT p.id_participante, p.id_torneo,
       IF(t.modalidad = 'individual', p.id_usuario, NULL),
       IF(t.modalidad = 'equipos', p.id_equipo, NULL),
       p.fecha_inscripcion
FROM v6_participantes p
JOIN torneos t ON t.id_torneo = p.id_torneo
WHERE (t.modalidad = 'individual' AND p.id_usuario IS NOT NULL)
   OR (t.modalidad = 'equipos' AND p.id_equipo IS NOT NULL);

-- Por las dudas: todo equipo tiene que ser participante de su torneo.
INSERT INTO participantes (id_torneo, id_equipo)
SELECT e.id_torneo, e.id_equipo
FROM equipos e
WHERE NOT EXISTS (SELECT 1 FROM participantes p WHERE p.id_equipo = e.id_equipo);

-- ── Rondas: antes eran solo un número en cada partido ──
-- Se marcan como publicadas porque en la versión anterior todo era visible.
INSERT INTO rondas (id_torneo, numero, estado)
SELECT id_torneo, ronda, 'publicada'
FROM v6_enfrentamientos
GROUP BY id_torneo, ronda;

-- ── Enfrentamientos: de equipos a participantes, y la posición de la llave ──
-- Un partido finalizado con un solo equipo era un pase libre.
INSERT INTO enfrentamientos (id_enfrentamiento, id_ronda, posicion, id_local, id_visitante, id_ganador,
                             es_pase_libre, fecha_hora, estado)
SELECT e.id_enfrentamiento,
       r.id_ronda,
       COALESCE(l.posicion, ROW_NUMBER() OVER (PARTITION BY e.id_torneo, e.ronda ORDER BY e.id_enfrentamiento) - 1),
       pl.id_participante,
       pv.id_participante,
       pg.id_participante,
       IF(e.estado = 'finalizado' AND ((e.id_equipo_local IS NULL) <> (e.id_equipo_visitante IS NULL)), 1, 0),
       e.fecha_hora,
       IF(e.estado = 'finalizado', 'finalizado', 'pendiente')
FROM v6_enfrentamientos e
JOIN rondas r ON r.id_torneo = e.id_torneo AND r.numero = e.ronda
LEFT JOIN v6_llaves l      ON l.id_enfrentamiento = e.id_enfrentamiento
LEFT JOIN participantes pl ON pl.id_equipo = e.id_equipo_local
LEFT JOIN participantes pv ON pv.id_equipo = e.id_equipo_visitante
LEFT JOIN participantes pg ON pg.id_equipo = e.id_ganador;

-- ── Resultados: pasan a su propia tabla ──
INSERT INTO resultados (id_enfrentamiento, puntaje_local, puntaje_visitante, vigente, observacion)
SELECT id_enfrentamiento, resultado_local, resultado_visitante, 1, 'Resultado migrado desde la versión anterior'
FROM v6_enfrentamientos
WHERE estado = 'finalizado'
  AND id_equipo_local IS NOT NULL AND id_equipo_visitante IS NOT NULL
  AND resultado_local IS NOT NULL AND resultado_visitante IS NOT NULL;

-- ── Auditoría: se conserva el historial de la versión anterior ──
INSERT INTO auditoria (fecha, origen, accion, tabla, id_registro, id_usuario, detalle)
SELECT fecha, 'app', accion, entidad, id_entidad, id_usuario, detalle
FROM v6_auditoria
ORDER BY id_auditoria;

INSERT INTO auditoria (origen, accion, tabla, usuario_bd, detalle)
VALUES ('app', 'migrar', 'sistema', USER(), 'Base de datos migrada a la versión 2');

-- ── Las tablas viejas ya no se necesitan ──
-- (tabla_posiciones y llaves no se copian: las posiciones ahora se
-- calculan con una vista y la llave usa la posición del enfrentamiento).
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE v6_auditoria, v6_llaves, v6_tabla_posiciones, v6_enfrentamientos, v6_participantes,
           v6_equipo_solicitudes, v6_equipo_miembros, v6_equipos, v6_torneos, v6_juegos, v6_usuarios;
SET FOREIGN_KEY_CHECKS = 1;
