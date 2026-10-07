-- =====================================================================
-- NEXTLEAGUE v2 - TRIGGERS DE AUDITORÍA
-- ARCHIVO GENERADO por database/herramientas/generar_triggers.php.
-- No editar a mano: si cambia una tabla, volver a correr el generador.
--
-- Cada INSERT, UPDATE o DELETE en las tablas de negocio agrega una fila
-- en "auditoria" con los valores anteriores y nuevos en formato JSON.
-- @id_usuario_app y @ip_app son variables que la aplicación fija al
-- conectarse; si el cambio se hace desde phpMyAdmin quedan en NULL y
-- usuario_bd muestra qué cuenta de MySQL lo hizo.
--
-- Cada trigger es una sola sentencia (sin BEGIN...END), así el archivo
-- se importa en phpMyAdmin sin tener que cambiar el DELIMITER.
-- Los UPDATE solo se registran si de verdad cambió algún valor.
-- Nota: MySQL no dispara triggers en los borrados en cascada de las
-- claves foráneas; queda registrado el borrado de la fila principal.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- roles
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_roles_ai AFTER INSERT ON roles FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'roles', NEW.id_rol, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_rol', NEW.id_rol, 'codigo', NEW.codigo, 'nombre', NEW.nombre, 'descripcion', NEW.descripcion));

CREATE TRIGGER trg_roles_au AFTER UPDATE ON roles FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'roles', NEW.id_rol, @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('id_rol', OLD.id_rol, 'codigo', OLD.codigo, 'nombre', OLD.nombre, 'descripcion', OLD.descripcion),
       JSON_OBJECT('id_rol', NEW.id_rol, 'codigo', NEW.codigo, 'nombre', NEW.nombre, 'descripcion', NEW.descripcion)
FROM DUAL
WHERE NOT (    OLD.id_rol <=> NEW.id_rol
           AND OLD.codigo <=> NEW.codigo
           AND OLD.nombre <=> NEW.nombre
           AND OLD.descripcion <=> NEW.descripcion);

CREATE TRIGGER trg_roles_ad AFTER DELETE ON roles FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'roles', OLD.id_rol, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_rol', OLD.id_rol, 'codigo', OLD.codigo, 'nombre', OLD.nombre, 'descripcion', OLD.descripcion));

-- ---------------------------------------------------------------------
-- usuarios
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_usuarios_ai AFTER INSERT ON usuarios FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'usuarios', NEW.id_usuario, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_usuario', NEW.id_usuario, 'nombre_usuario', NEW.nombre_usuario, 'correo', NEW.correo, 'id_rol', NEW.id_rol, 'foto_perfil', NEW.foto_perfil, 'biografia', NEW.biografia, 'activo', NEW.activo, 'debe_cambiar_contrasena', NEW.debe_cambiar_contrasena, 'fecha_registro', NEW.fecha_registro));

CREATE TRIGGER trg_usuarios_au AFTER UPDATE ON usuarios FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'usuarios', NEW.id_usuario, @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('id_usuario', OLD.id_usuario, 'nombre_usuario', OLD.nombre_usuario, 'correo', OLD.correo, 'id_rol', OLD.id_rol, 'foto_perfil', OLD.foto_perfil, 'biografia', OLD.biografia, 'activo', OLD.activo, 'debe_cambiar_contrasena', OLD.debe_cambiar_contrasena, 'fecha_registro', OLD.fecha_registro),
       JSON_OBJECT('id_usuario', NEW.id_usuario, 'nombre_usuario', NEW.nombre_usuario, 'correo', NEW.correo, 'id_rol', NEW.id_rol, 'foto_perfil', NEW.foto_perfil, 'biografia', NEW.biografia, 'activo', NEW.activo, 'debe_cambiar_contrasena', NEW.debe_cambiar_contrasena, 'fecha_registro', NEW.fecha_registro, 'contrasena_cambiada', NOT (OLD.contrasena_hash <=> NEW.contrasena_hash))
FROM DUAL
WHERE NOT (    OLD.id_usuario <=> NEW.id_usuario
           AND OLD.nombre_usuario <=> NEW.nombre_usuario
           AND OLD.correo <=> NEW.correo
           AND OLD.contrasena_hash <=> NEW.contrasena_hash
           AND OLD.id_rol <=> NEW.id_rol
           AND OLD.foto_perfil <=> NEW.foto_perfil
           AND OLD.biografia <=> NEW.biografia
           AND OLD.activo <=> NEW.activo
           AND OLD.debe_cambiar_contrasena <=> NEW.debe_cambiar_contrasena
           AND OLD.fecha_registro <=> NEW.fecha_registro);

CREATE TRIGGER trg_usuarios_ad AFTER DELETE ON usuarios FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'usuarios', OLD.id_usuario, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_usuario', OLD.id_usuario, 'nombre_usuario', OLD.nombre_usuario, 'correo', OLD.correo, 'id_rol', OLD.id_rol, 'foto_perfil', OLD.foto_perfil, 'biografia', OLD.biografia, 'activo', OLD.activo, 'debe_cambiar_contrasena', OLD.debe_cambiar_contrasena, 'fecha_registro', OLD.fecha_registro));

-- ---------------------------------------------------------------------
-- juegos
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_juegos_ai AFTER INSERT ON juegos FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'juegos', NEW.id_juego, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_juego', NEW.id_juego, 'nombre', NEW.nombre, 'categoria', NEW.categoria, 'modalidad_sugerida', NEW.modalidad_sugerida, 'icono', NEW.icono));

CREATE TRIGGER trg_juegos_au AFTER UPDATE ON juegos FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'juegos', NEW.id_juego, @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('id_juego', OLD.id_juego, 'nombre', OLD.nombre, 'categoria', OLD.categoria, 'modalidad_sugerida', OLD.modalidad_sugerida, 'icono', OLD.icono),
       JSON_OBJECT('id_juego', NEW.id_juego, 'nombre', NEW.nombre, 'categoria', NEW.categoria, 'modalidad_sugerida', NEW.modalidad_sugerida, 'icono', NEW.icono)
FROM DUAL
WHERE NOT (    OLD.id_juego <=> NEW.id_juego
           AND OLD.nombre <=> NEW.nombre
           AND OLD.categoria <=> NEW.categoria
           AND OLD.modalidad_sugerida <=> NEW.modalidad_sugerida
           AND OLD.icono <=> NEW.icono);

CREATE TRIGGER trg_juegos_ad AFTER DELETE ON juegos FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'juegos', OLD.id_juego, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_juego', OLD.id_juego, 'nombre', OLD.nombre, 'categoria', OLD.categoria, 'modalidad_sugerida', OLD.modalidad_sugerida, 'icono', OLD.icono));

-- ---------------------------------------------------------------------
-- modulos_competencia
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_modulos_competencia_ai AFTER INSERT ON modulos_competencia FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'modulos_competencia', NEW.id_modulo, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_modulo', NEW.id_modulo, 'codigo', NEW.codigo, 'nombre', NEW.nombre, 'descripcion', NEW.descripcion, 'habilitado', NEW.habilitado));

CREATE TRIGGER trg_modulos_competencia_au AFTER UPDATE ON modulos_competencia FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'modulos_competencia', NEW.id_modulo, @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('id_modulo', OLD.id_modulo, 'codigo', OLD.codigo, 'nombre', OLD.nombre, 'descripcion', OLD.descripcion, 'habilitado', OLD.habilitado),
       JSON_OBJECT('id_modulo', NEW.id_modulo, 'codigo', NEW.codigo, 'nombre', NEW.nombre, 'descripcion', NEW.descripcion, 'habilitado', NEW.habilitado)
FROM DUAL
WHERE NOT (    OLD.id_modulo <=> NEW.id_modulo
           AND OLD.codigo <=> NEW.codigo
           AND OLD.nombre <=> NEW.nombre
           AND OLD.descripcion <=> NEW.descripcion
           AND OLD.habilitado <=> NEW.habilitado);

CREATE TRIGGER trg_modulos_competencia_ad AFTER DELETE ON modulos_competencia FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'modulos_competencia', OLD.id_modulo, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_modulo', OLD.id_modulo, 'codigo', OLD.codigo, 'nombre', OLD.nombre, 'descripcion', OLD.descripcion, 'habilitado', OLD.habilitado));

-- ---------------------------------------------------------------------
-- torneos
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_torneos_ai AFTER INSERT ON torneos FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'torneos', NEW.id_torneo, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_torneo', NEW.id_torneo, 'nombre', NEW.nombre, 'id_juego', NEW.id_juego, 'id_modulo', NEW.id_modulo, 'id_organizador', NEW.id_organizador, 'modalidad', NEW.modalidad, 'cupo', NEW.cupo, 'premio', NEW.premio, 'moneda', NEW.moneda, 'fecha_inicio', NEW.fecha_inicio, 'fecha_fin', NEW.fecha_fin, 'descripcion', NEW.descripcion, 'logo', NEW.logo, 'reglas_pdf', NEW.reglas_pdf, 'estado', NEW.estado, 'publicado', NEW.publicado, 'fecha_creacion', NEW.fecha_creacion));

CREATE TRIGGER trg_torneos_au AFTER UPDATE ON torneos FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'torneos', NEW.id_torneo, @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('id_torneo', OLD.id_torneo, 'nombre', OLD.nombre, 'id_juego', OLD.id_juego, 'id_modulo', OLD.id_modulo, 'id_organizador', OLD.id_organizador, 'modalidad', OLD.modalidad, 'cupo', OLD.cupo, 'premio', OLD.premio, 'moneda', OLD.moneda, 'fecha_inicio', OLD.fecha_inicio, 'fecha_fin', OLD.fecha_fin, 'descripcion', OLD.descripcion, 'logo', OLD.logo, 'reglas_pdf', OLD.reglas_pdf, 'estado', OLD.estado, 'publicado', OLD.publicado, 'fecha_creacion', OLD.fecha_creacion),
       JSON_OBJECT('id_torneo', NEW.id_torneo, 'nombre', NEW.nombre, 'id_juego', NEW.id_juego, 'id_modulo', NEW.id_modulo, 'id_organizador', NEW.id_organizador, 'modalidad', NEW.modalidad, 'cupo', NEW.cupo, 'premio', NEW.premio, 'moneda', NEW.moneda, 'fecha_inicio', NEW.fecha_inicio, 'fecha_fin', NEW.fecha_fin, 'descripcion', NEW.descripcion, 'logo', NEW.logo, 'reglas_pdf', NEW.reglas_pdf, 'estado', NEW.estado, 'publicado', NEW.publicado, 'fecha_creacion', NEW.fecha_creacion)
FROM DUAL
WHERE NOT (    OLD.id_torneo <=> NEW.id_torneo
           AND OLD.nombre <=> NEW.nombre
           AND OLD.id_juego <=> NEW.id_juego
           AND OLD.id_modulo <=> NEW.id_modulo
           AND OLD.id_organizador <=> NEW.id_organizador
           AND OLD.modalidad <=> NEW.modalidad
           AND OLD.cupo <=> NEW.cupo
           AND OLD.premio <=> NEW.premio
           AND OLD.moneda <=> NEW.moneda
           AND OLD.fecha_inicio <=> NEW.fecha_inicio
           AND OLD.fecha_fin <=> NEW.fecha_fin
           AND OLD.descripcion <=> NEW.descripcion
           AND OLD.logo <=> NEW.logo
           AND OLD.reglas_pdf <=> NEW.reglas_pdf
           AND OLD.estado <=> NEW.estado
           AND OLD.publicado <=> NEW.publicado
           AND OLD.fecha_creacion <=> NEW.fecha_creacion);

CREATE TRIGGER trg_torneos_ad AFTER DELETE ON torneos FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'torneos', OLD.id_torneo, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_torneo', OLD.id_torneo, 'nombre', OLD.nombre, 'id_juego', OLD.id_juego, 'id_modulo', OLD.id_modulo, 'id_organizador', OLD.id_organizador, 'modalidad', OLD.modalidad, 'cupo', OLD.cupo, 'premio', OLD.premio, 'moneda', OLD.moneda, 'fecha_inicio', OLD.fecha_inicio, 'fecha_fin', OLD.fecha_fin, 'descripcion', OLD.descripcion, 'logo', OLD.logo, 'reglas_pdf', OLD.reglas_pdf, 'estado', OLD.estado, 'publicado', OLD.publicado, 'fecha_creacion', OLD.fecha_creacion));

-- ---------------------------------------------------------------------
-- configuracion_torneo
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_configuracion_torneo_ai AFTER INSERT ON configuracion_torneo FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'configuracion_torneo', NEW.id_torneo, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_torneo', NEW.id_torneo, 'puntos_victoria', NEW.puntos_victoria, 'puntos_empate', NEW.puntos_empate, 'puntos_derrota', NEW.puntos_derrota, 'ida_y_vuelta', NEW.ida_y_vuelta, 'rondas_suizo', NEW.rondas_suizo, 'permitir_empates', NEW.permitir_empates, 'permitir_correcciones', NEW.permitir_correcciones, 'max_integrantes_equipo', NEW.max_integrantes_equipo));

CREATE TRIGGER trg_configuracion_torneo_au AFTER UPDATE ON configuracion_torneo FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'configuracion_torneo', NEW.id_torneo, @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('id_torneo', OLD.id_torneo, 'puntos_victoria', OLD.puntos_victoria, 'puntos_empate', OLD.puntos_empate, 'puntos_derrota', OLD.puntos_derrota, 'ida_y_vuelta', OLD.ida_y_vuelta, 'rondas_suizo', OLD.rondas_suizo, 'permitir_empates', OLD.permitir_empates, 'permitir_correcciones', OLD.permitir_correcciones, 'max_integrantes_equipo', OLD.max_integrantes_equipo),
       JSON_OBJECT('id_torneo', NEW.id_torneo, 'puntos_victoria', NEW.puntos_victoria, 'puntos_empate', NEW.puntos_empate, 'puntos_derrota', NEW.puntos_derrota, 'ida_y_vuelta', NEW.ida_y_vuelta, 'rondas_suizo', NEW.rondas_suizo, 'permitir_empates', NEW.permitir_empates, 'permitir_correcciones', NEW.permitir_correcciones, 'max_integrantes_equipo', NEW.max_integrantes_equipo)
FROM DUAL
WHERE NOT (    OLD.id_torneo <=> NEW.id_torneo
           AND OLD.puntos_victoria <=> NEW.puntos_victoria
           AND OLD.puntos_empate <=> NEW.puntos_empate
           AND OLD.puntos_derrota <=> NEW.puntos_derrota
           AND OLD.ida_y_vuelta <=> NEW.ida_y_vuelta
           AND OLD.rondas_suizo <=> NEW.rondas_suizo
           AND OLD.permitir_empates <=> NEW.permitir_empates
           AND OLD.permitir_correcciones <=> NEW.permitir_correcciones
           AND OLD.max_integrantes_equipo <=> NEW.max_integrantes_equipo);

CREATE TRIGGER trg_configuracion_torneo_ad AFTER DELETE ON configuracion_torneo FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'configuracion_torneo', OLD.id_torneo, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_torneo', OLD.id_torneo, 'puntos_victoria', OLD.puntos_victoria, 'puntos_empate', OLD.puntos_empate, 'puntos_derrota', OLD.puntos_derrota, 'ida_y_vuelta', OLD.ida_y_vuelta, 'rondas_suizo', OLD.rondas_suizo, 'permitir_empates', OLD.permitir_empates, 'permitir_correcciones', OLD.permitir_correcciones, 'max_integrantes_equipo', OLD.max_integrantes_equipo));

-- ---------------------------------------------------------------------
-- equipos
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_equipos_ai AFTER INSERT ON equipos FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'equipos', NEW.id_equipo, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_equipo', NEW.id_equipo, 'id_torneo', NEW.id_torneo, 'nombre', NEW.nombre, 'logo', NEW.logo, 'id_capitan', NEW.id_capitan, 'fecha_creacion', NEW.fecha_creacion));

CREATE TRIGGER trg_equipos_au AFTER UPDATE ON equipos FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'equipos', NEW.id_equipo, @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('id_equipo', OLD.id_equipo, 'id_torneo', OLD.id_torneo, 'nombre', OLD.nombre, 'logo', OLD.logo, 'id_capitan', OLD.id_capitan, 'fecha_creacion', OLD.fecha_creacion),
       JSON_OBJECT('id_equipo', NEW.id_equipo, 'id_torneo', NEW.id_torneo, 'nombre', NEW.nombre, 'logo', NEW.logo, 'id_capitan', NEW.id_capitan, 'fecha_creacion', NEW.fecha_creacion)
FROM DUAL
WHERE NOT (    OLD.id_equipo <=> NEW.id_equipo
           AND OLD.id_torneo <=> NEW.id_torneo
           AND OLD.nombre <=> NEW.nombre
           AND OLD.logo <=> NEW.logo
           AND OLD.id_capitan <=> NEW.id_capitan
           AND OLD.fecha_creacion <=> NEW.fecha_creacion);

CREATE TRIGGER trg_equipos_ad AFTER DELETE ON equipos FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'equipos', OLD.id_equipo, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_equipo', OLD.id_equipo, 'id_torneo', OLD.id_torneo, 'nombre', OLD.nombre, 'logo', OLD.logo, 'id_capitan', OLD.id_capitan, 'fecha_creacion', OLD.fecha_creacion));

-- ---------------------------------------------------------------------
-- equipo_miembros
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_equipo_miembros_ai AFTER INSERT ON equipo_miembros FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'equipo_miembros', CONCAT_WS('-', NEW.id_equipo, NEW.id_usuario), @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_equipo', NEW.id_equipo, 'id_usuario', NEW.id_usuario, 'fecha_union', NEW.fecha_union));

CREATE TRIGGER trg_equipo_miembros_au AFTER UPDATE ON equipo_miembros FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'equipo_miembros', CONCAT_WS('-', NEW.id_equipo, NEW.id_usuario), @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('id_equipo', OLD.id_equipo, 'id_usuario', OLD.id_usuario, 'fecha_union', OLD.fecha_union),
       JSON_OBJECT('id_equipo', NEW.id_equipo, 'id_usuario', NEW.id_usuario, 'fecha_union', NEW.fecha_union)
FROM DUAL
WHERE NOT (    OLD.id_equipo <=> NEW.id_equipo
           AND OLD.id_usuario <=> NEW.id_usuario
           AND OLD.fecha_union <=> NEW.fecha_union);

CREATE TRIGGER trg_equipo_miembros_ad AFTER DELETE ON equipo_miembros FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'equipo_miembros', CONCAT_WS('-', OLD.id_equipo, OLD.id_usuario), @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_equipo', OLD.id_equipo, 'id_usuario', OLD.id_usuario, 'fecha_union', OLD.fecha_union));

-- ---------------------------------------------------------------------
-- equipo_solicitudes
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_equipo_solicitudes_ai AFTER INSERT ON equipo_solicitudes FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'equipo_solicitudes', NEW.id_solicitud, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_solicitud', NEW.id_solicitud, 'id_equipo', NEW.id_equipo, 'id_usuario', NEW.id_usuario, 'estado', NEW.estado, 'fecha_solicitud', NEW.fecha_solicitud, 'fecha_respuesta', NEW.fecha_respuesta));

CREATE TRIGGER trg_equipo_solicitudes_au AFTER UPDATE ON equipo_solicitudes FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'equipo_solicitudes', NEW.id_solicitud, @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('id_solicitud', OLD.id_solicitud, 'id_equipo', OLD.id_equipo, 'id_usuario', OLD.id_usuario, 'estado', OLD.estado, 'fecha_solicitud', OLD.fecha_solicitud, 'fecha_respuesta', OLD.fecha_respuesta),
       JSON_OBJECT('id_solicitud', NEW.id_solicitud, 'id_equipo', NEW.id_equipo, 'id_usuario', NEW.id_usuario, 'estado', NEW.estado, 'fecha_solicitud', NEW.fecha_solicitud, 'fecha_respuesta', NEW.fecha_respuesta)
FROM DUAL
WHERE NOT (    OLD.id_solicitud <=> NEW.id_solicitud
           AND OLD.id_equipo <=> NEW.id_equipo
           AND OLD.id_usuario <=> NEW.id_usuario
           AND OLD.estado <=> NEW.estado
           AND OLD.fecha_solicitud <=> NEW.fecha_solicitud
           AND OLD.fecha_respuesta <=> NEW.fecha_respuesta);

CREATE TRIGGER trg_equipo_solicitudes_ad AFTER DELETE ON equipo_solicitudes FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'equipo_solicitudes', OLD.id_solicitud, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_solicitud', OLD.id_solicitud, 'id_equipo', OLD.id_equipo, 'id_usuario', OLD.id_usuario, 'estado', OLD.estado, 'fecha_solicitud', OLD.fecha_solicitud, 'fecha_respuesta', OLD.fecha_respuesta));

-- ---------------------------------------------------------------------
-- participantes
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_participantes_ai AFTER INSERT ON participantes FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'participantes', NEW.id_participante, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_participante', NEW.id_participante, 'id_torneo', NEW.id_torneo, 'id_usuario', NEW.id_usuario, 'id_equipo', NEW.id_equipo, 'fecha_inscripcion', NEW.fecha_inscripcion));

CREATE TRIGGER trg_participantes_au AFTER UPDATE ON participantes FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'participantes', NEW.id_participante, @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('id_participante', OLD.id_participante, 'id_torneo', OLD.id_torneo, 'id_usuario', OLD.id_usuario, 'id_equipo', OLD.id_equipo, 'fecha_inscripcion', OLD.fecha_inscripcion),
       JSON_OBJECT('id_participante', NEW.id_participante, 'id_torneo', NEW.id_torneo, 'id_usuario', NEW.id_usuario, 'id_equipo', NEW.id_equipo, 'fecha_inscripcion', NEW.fecha_inscripcion)
FROM DUAL
WHERE NOT (    OLD.id_participante <=> NEW.id_participante
           AND OLD.id_torneo <=> NEW.id_torneo
           AND OLD.id_usuario <=> NEW.id_usuario
           AND OLD.id_equipo <=> NEW.id_equipo
           AND OLD.fecha_inscripcion <=> NEW.fecha_inscripcion);

CREATE TRIGGER trg_participantes_ad AFTER DELETE ON participantes FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'participantes', OLD.id_participante, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_participante', OLD.id_participante, 'id_torneo', OLD.id_torneo, 'id_usuario', OLD.id_usuario, 'id_equipo', OLD.id_equipo, 'fecha_inscripcion', OLD.fecha_inscripcion));

-- ---------------------------------------------------------------------
-- rondas
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_rondas_ai AFTER INSERT ON rondas FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'rondas', NEW.id_ronda, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_ronda', NEW.id_ronda, 'id_torneo', NEW.id_torneo, 'numero', NEW.numero, 'nombre', NEW.nombre, 'estado', NEW.estado, 'fecha', NEW.fecha));

CREATE TRIGGER trg_rondas_au AFTER UPDATE ON rondas FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'rondas', NEW.id_ronda, @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('id_ronda', OLD.id_ronda, 'id_torneo', OLD.id_torneo, 'numero', OLD.numero, 'nombre', OLD.nombre, 'estado', OLD.estado, 'fecha', OLD.fecha),
       JSON_OBJECT('id_ronda', NEW.id_ronda, 'id_torneo', NEW.id_torneo, 'numero', NEW.numero, 'nombre', NEW.nombre, 'estado', NEW.estado, 'fecha', NEW.fecha)
FROM DUAL
WHERE NOT (    OLD.id_ronda <=> NEW.id_ronda
           AND OLD.id_torneo <=> NEW.id_torneo
           AND OLD.numero <=> NEW.numero
           AND OLD.nombre <=> NEW.nombre
           AND OLD.estado <=> NEW.estado
           AND OLD.fecha <=> NEW.fecha);

CREATE TRIGGER trg_rondas_ad AFTER DELETE ON rondas FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'rondas', OLD.id_ronda, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_ronda', OLD.id_ronda, 'id_torneo', OLD.id_torneo, 'numero', OLD.numero, 'nombre', OLD.nombre, 'estado', OLD.estado, 'fecha', OLD.fecha));

-- ---------------------------------------------------------------------
-- enfrentamientos
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_enfrentamientos_ai AFTER INSERT ON enfrentamientos FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'enfrentamientos', NEW.id_enfrentamiento, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_enfrentamiento', NEW.id_enfrentamiento, 'id_ronda', NEW.id_ronda, 'posicion', NEW.posicion, 'id_local', NEW.id_local, 'id_visitante', NEW.id_visitante, 'id_ganador', NEW.id_ganador, 'es_pase_libre', NEW.es_pase_libre, 'fecha_hora', NEW.fecha_hora, 'lugar', NEW.lugar, 'estado', NEW.estado));

CREATE TRIGGER trg_enfrentamientos_au AFTER UPDATE ON enfrentamientos FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'enfrentamientos', NEW.id_enfrentamiento, @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('id_enfrentamiento', OLD.id_enfrentamiento, 'id_ronda', OLD.id_ronda, 'posicion', OLD.posicion, 'id_local', OLD.id_local, 'id_visitante', OLD.id_visitante, 'id_ganador', OLD.id_ganador, 'es_pase_libre', OLD.es_pase_libre, 'fecha_hora', OLD.fecha_hora, 'lugar', OLD.lugar, 'estado', OLD.estado),
       JSON_OBJECT('id_enfrentamiento', NEW.id_enfrentamiento, 'id_ronda', NEW.id_ronda, 'posicion', NEW.posicion, 'id_local', NEW.id_local, 'id_visitante', NEW.id_visitante, 'id_ganador', NEW.id_ganador, 'es_pase_libre', NEW.es_pase_libre, 'fecha_hora', NEW.fecha_hora, 'lugar', NEW.lugar, 'estado', NEW.estado)
FROM DUAL
WHERE NOT (    OLD.id_enfrentamiento <=> NEW.id_enfrentamiento
           AND OLD.id_ronda <=> NEW.id_ronda
           AND OLD.posicion <=> NEW.posicion
           AND OLD.id_local <=> NEW.id_local
           AND OLD.id_visitante <=> NEW.id_visitante
           AND OLD.id_ganador <=> NEW.id_ganador
           AND OLD.es_pase_libre <=> NEW.es_pase_libre
           AND OLD.fecha_hora <=> NEW.fecha_hora
           AND OLD.lugar <=> NEW.lugar
           AND OLD.estado <=> NEW.estado);

CREATE TRIGGER trg_enfrentamientos_ad AFTER DELETE ON enfrentamientos FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'enfrentamientos', OLD.id_enfrentamiento, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_enfrentamiento', OLD.id_enfrentamiento, 'id_ronda', OLD.id_ronda, 'posicion', OLD.posicion, 'id_local', OLD.id_local, 'id_visitante', OLD.id_visitante, 'id_ganador', OLD.id_ganador, 'es_pase_libre', OLD.es_pase_libre, 'fecha_hora', OLD.fecha_hora, 'lugar', OLD.lugar, 'estado', OLD.estado));

-- ---------------------------------------------------------------------
-- resultados
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_resultados_ai AFTER INSERT ON resultados FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'resultados', NEW.id_resultado, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_resultado', NEW.id_resultado, 'id_enfrentamiento', NEW.id_enfrentamiento, 'puntaje_local', NEW.puntaje_local, 'puntaje_visitante', NEW.puntaje_visitante, 'id_usuario_carga', NEW.id_usuario_carga, 'fecha_carga', NEW.fecha_carga, 'vigente', NEW.vigente, 'observacion', NEW.observacion));

CREATE TRIGGER trg_resultados_au AFTER UPDATE ON resultados FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'resultados', NEW.id_resultado, @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('id_resultado', OLD.id_resultado, 'id_enfrentamiento', OLD.id_enfrentamiento, 'puntaje_local', OLD.puntaje_local, 'puntaje_visitante', OLD.puntaje_visitante, 'id_usuario_carga', OLD.id_usuario_carga, 'fecha_carga', OLD.fecha_carga, 'vigente', OLD.vigente, 'observacion', OLD.observacion),
       JSON_OBJECT('id_resultado', NEW.id_resultado, 'id_enfrentamiento', NEW.id_enfrentamiento, 'puntaje_local', NEW.puntaje_local, 'puntaje_visitante', NEW.puntaje_visitante, 'id_usuario_carga', NEW.id_usuario_carga, 'fecha_carga', NEW.fecha_carga, 'vigente', NEW.vigente, 'observacion', NEW.observacion)
FROM DUAL
WHERE NOT (    OLD.id_resultado <=> NEW.id_resultado
           AND OLD.id_enfrentamiento <=> NEW.id_enfrentamiento
           AND OLD.puntaje_local <=> NEW.puntaje_local
           AND OLD.puntaje_visitante <=> NEW.puntaje_visitante
           AND OLD.id_usuario_carga <=> NEW.id_usuario_carga
           AND OLD.fecha_carga <=> NEW.fecha_carga
           AND OLD.vigente <=> NEW.vigente
           AND OLD.observacion <=> NEW.observacion);

CREATE TRIGGER trg_resultados_ad AFTER DELETE ON resultados FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'resultados', OLD.id_resultado, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('id_resultado', OLD.id_resultado, 'id_enfrentamiento', OLD.id_enfrentamiento, 'puntaje_local', OLD.puntaje_local, 'puntaje_visitante', OLD.puntaje_visitante, 'id_usuario_carga', OLD.id_usuario_carga, 'fecha_carga', OLD.fecha_carga, 'vigente', OLD.vigente, 'observacion', OLD.observacion));

-- ---------------------------------------------------------------------
-- configuracion_sistema
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_configuracion_sistema_ai AFTER INSERT ON configuracion_sistema FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_nuevos)
VALUES ('bd', 'crear', 'configuracion_sistema', NEW.clave, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('clave', NEW.clave, 'valor', NEW.valor, 'tipo', NEW.tipo, 'descripcion', NEW.descripcion));

CREATE TRIGGER trg_configuracion_sistema_au AFTER UPDATE ON configuracion_sistema FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores, valores_nuevos)
SELECT 'bd', 'editar', 'configuracion_sistema', NEW.clave, @id_usuario_app, USER(), @ip_app,
       JSON_OBJECT('clave', OLD.clave, 'valor', OLD.valor, 'tipo', OLD.tipo, 'descripcion', OLD.descripcion),
       JSON_OBJECT('clave', NEW.clave, 'valor', NEW.valor, 'tipo', NEW.tipo, 'descripcion', NEW.descripcion)
FROM DUAL
WHERE NOT (    OLD.clave <=> NEW.clave
           AND OLD.valor <=> NEW.valor
           AND OLD.tipo <=> NEW.tipo
           AND OLD.descripcion <=> NEW.descripcion);

CREATE TRIGGER trg_configuracion_sistema_ad AFTER DELETE ON configuracion_sistema FOR EACH ROW
INSERT INTO auditoria (origen, accion, tabla, id_registro, id_usuario, usuario_bd, ip, valores_anteriores)
VALUES ('bd', 'eliminar', 'configuracion_sistema', OLD.clave, @id_usuario_app, USER(), @ip_app,
        JSON_OBJECT('clave', OLD.clave, 'valor', OLD.valor, 'tipo', OLD.tipo, 'descripcion', OLD.descripcion));
