-- =====================================================================
-- NEXTLEAGUE v2 - USUARIOS DE BASE DE DATOS Y PERMISOS (DCL)
-- Se ejecuta con una cuenta administradora (root) y con la base de la
-- aplicación seleccionada: los GRANT sin nombre de base ("ON usuarios")
-- se aplican a la base actual.
--
-- Principio de mínimo privilegio: cada cuenta tiene solo lo que necesita.
--   nl_app     → la usa la aplicación web. Puede leer y modificar datos,
--                pero NO puede crear ni borrar tablas, y sobre la
--                auditoría solo puede agregar y leer (no borrar ni editar
--                el historial).
--   nl_lectura → solo lectura, para reportes o consultas de soporte. No
--                ve las contraseñas hasheadas ni los tokens de sesión.
--
-- IMPORTANTE: las contraseñas de abajo son solo para desarrollo local.
-- En el servidor hay que cambiarlas, por ejemplo:
--   ALTER USER 'nl_app'@'localhost' IDENTIFIED BY 'una-clave-larga-y-secreta';
-- y poner la misma en config.php.
-- =====================================================================

CREATE USER IF NOT EXISTS 'nl_app'@'localhost' IDENTIFIED BY 'NlApp_Desarrollo_2026';
CREATE USER IF NOT EXISTS 'nl_lectura'@'localhost' IDENTIFIED BY 'NlLectura_Desarrollo_2026';

-- ---------------------------------------------------------------------
-- nl_app: la aplicación
-- ---------------------------------------------------------------------
-- Datos que la aplicación crea, modifica y borra
GRANT SELECT, INSERT, UPDATE, DELETE ON usuarios             TO 'nl_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON tokens_usuario       TO 'nl_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON intentos_login       TO 'nl_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON torneos              TO 'nl_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON configuracion_torneo TO 'nl_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON equipos              TO 'nl_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON equipo_miembros      TO 'nl_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON equipo_solicitudes   TO 'nl_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON participantes        TO 'nl_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON rondas               TO 'nl_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON enfrentamientos      TO 'nl_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON resultados           TO 'nl_app'@'localhost';

-- Configuración: el administrador cambia valores, pero no crea ni borra filas
GRANT SELECT, UPDATE ON modulos_competencia   TO 'nl_app'@'localhost';
GRANT SELECT, UPDATE ON configuracion_sistema TO 'nl_app'@'localhost';

-- Catálogos fijos: solo lectura
GRANT SELECT ON roles  TO 'nl_app'@'localhost';
GRANT SELECT ON juegos TO 'nl_app'@'localhost';

-- Auditoría: solo agregar y leer. El historial no se puede alterar.
GRANT SELECT, INSERT ON auditoria TO 'nl_app'@'localhost';

-- Vistas
GRANT SELECT ON vista_participantes            TO 'nl_app'@'localhost';
GRANT SELECT ON vista_torneos                  TO 'nl_app'@'localhost';
GRANT SELECT ON vista_torneos_publicos         TO 'nl_app'@'localhost';
GRANT SELECT ON vista_resultados_vigentes      TO 'nl_app'@'localhost';
GRANT SELECT ON vista_calendario               TO 'nl_app'@'localhost';
GRANT SELECT ON vista_partidos_por_participante TO 'nl_app'@'localhost';
GRANT SELECT ON vista_tabla_posiciones         TO 'nl_app'@'localhost';
GRANT SELECT ON vista_estadisticas             TO 'nl_app'@'localhost';

-- ---------------------------------------------------------------------
-- nl_lectura: solo consultas
-- ---------------------------------------------------------------------
-- En usuarios se dan permisos por columna: no puede ver contrasena_hash.
GRANT SELECT (id_usuario, nombre_usuario, correo, id_rol, activo, fecha_registro, ultimo_acceso)
      ON usuarios TO 'nl_lectura'@'localhost';
GRANT SELECT ON roles                 TO 'nl_lectura'@'localhost';
GRANT SELECT ON juegos                TO 'nl_lectura'@'localhost';
GRANT SELECT ON modulos_competencia   TO 'nl_lectura'@'localhost';
GRANT SELECT ON torneos               TO 'nl_lectura'@'localhost';
GRANT SELECT ON configuracion_torneo  TO 'nl_lectura'@'localhost';
GRANT SELECT ON equipos               TO 'nl_lectura'@'localhost';
GRANT SELECT ON equipo_miembros       TO 'nl_lectura'@'localhost';
GRANT SELECT ON equipo_solicitudes    TO 'nl_lectura'@'localhost';
GRANT SELECT ON participantes         TO 'nl_lectura'@'localhost';
GRANT SELECT ON rondas                TO 'nl_lectura'@'localhost';
GRANT SELECT ON enfrentamientos       TO 'nl_lectura'@'localhost';
GRANT SELECT ON resultados            TO 'nl_lectura'@'localhost';
GRANT SELECT ON configuracion_sistema TO 'nl_lectura'@'localhost';
GRANT SELECT ON auditoria             TO 'nl_lectura'@'localhost';
GRANT SELECT ON vista_participantes            TO 'nl_lectura'@'localhost';
GRANT SELECT ON vista_torneos                  TO 'nl_lectura'@'localhost';
GRANT SELECT ON vista_torneos_publicos         TO 'nl_lectura'@'localhost';
GRANT SELECT ON vista_resultados_vigentes      TO 'nl_lectura'@'localhost';
GRANT SELECT ON vista_calendario               TO 'nl_lectura'@'localhost';
GRANT SELECT ON vista_partidos_por_participante TO 'nl_lectura'@'localhost';
GRANT SELECT ON vista_tabla_posiciones         TO 'nl_lectura'@'localhost';
GRANT SELECT ON vista_estadisticas             TO 'nl_lectura'@'localhost';
