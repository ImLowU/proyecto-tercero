-- =====================================================================
-- NEXTLEAGUE v2 - DATOS BASE
-- Catálogos que el sistema necesita para funcionar (roles, módulos,
-- juegos, configuración) y el usuario administrador inicial.
-- Se ejecuta después de 01_esquema.sql.
-- =====================================================================

SET NAMES utf8mb4;

-- ── Roles (sección 5 de la letra) ──
INSERT INTO roles (id_rol, codigo, nombre, descripcion) VALUES
(1, 'administrador', 'Administrador general', 'Control completo: usuarios, módulos, configuración, reportes y auditoría.'),
(2, 'organizador',   'Organizador de torneo', 'Crea y administra sus propios torneos: inscripciones, rondas, fechas y resultados.'),
(3, 'participante',  'Participante',          'Se inscribe a torneos, consulta su calendario, resultados y posiciones.');

-- ── Módulos de competencia (sección 4.3 de la letra) ──
INSERT INTO modulos_competencia (id_modulo, codigo, nombre, descripcion, habilitado) VALUES
(1, 'liga',                'Liga',                'Todos contra todos. Gana quien suma más puntos en la tabla.', 1),
(2, 'eliminacion_directa', 'Eliminación directa', 'Llaves: quien pierde queda afuera y el ganador avanza hasta la final.', 1),
(3, 'sistema_suizo',       'Sistema suizo',       'Rondas en las que se enfrentan participantes con puntajes parecidos, sin repetir rivales.', 1);

-- ── Configuración general del sistema ──
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

-- ── Juegos y disciplinas (mismo orden que la versión anterior) ──
INSERT INTO juegos (nombre, categoria, modalidad_sugerida) VALUES
-- Deportivos
('Fútbol', 'deportivo', 'equipos'),
('Futbol 5', 'deportivo', 'equipos'),
('Futsal', 'deportivo', 'equipos'),
('Básquetbol', 'deportivo', 'equipos'),
('Vóleibol', 'deportivo', 'equipos'),
('Handball', 'deportivo', 'equipos'),
('Rugby', 'deportivo', 'equipos'),
('Tenis', 'deportivo', 'individual'),
('Tenis de mesa', 'deportivo', 'individual'),
('Bádminton', 'deportivo', 'individual'),
('Pádel', 'deportivo', 'equipos'),
('Hockey sobre césped', 'deportivo', 'equipos'),
('Béisbol', 'deportivo', 'equipos'),
('Sóftbol', 'deportivo', 'equipos'),
('Cricket', 'deportivo', 'equipos'),
('Boxeo', 'deportivo', 'individual'),
('MMA', 'deportivo', 'individual'),
('Judo', 'deportivo', 'individual'),
('Karate', 'deportivo', 'individual'),
('Taekwondo', 'deportivo', 'individual'),
('Atletismo', 'deportivo', 'individual'),
('Natación', 'deportivo', 'individual'),
('Ciclismo', 'deportivo', 'individual'),
('Golf', 'deportivo', 'individual'),
('Levantamiento de pesas', 'deportivo', 'individual'),
('Gimnasia', 'deportivo', 'individual'),
('Waterpolo', 'deportivo', 'equipos'),
-- Mentales
('Ajedrez', 'mental', 'individual'),
('Damas', 'mental', 'individual'),
('Truco', 'mental', 'equipos'),
('Póker', 'mental', 'individual'),
('Dominó', 'mental', 'individual'),
-- Electrónicos
('Valorant', 'electronico', 'equipos'),
('League of Legends', 'electronico', 'equipos'),
('Counter-Strike 2', 'electronico', 'equipos'),
('FIFA / EA Sports FC', 'electronico', 'individual'),
('Fortnite', 'electronico', 'individual'),
('Rocket League', 'electronico', 'equipos');

-- =====================================================================
-- USUARIO ADMINISTRADOR INICIAL
-- Nadie puede elegir "administrador" al registrarse, así que hace falta
-- este usuario para entrar al panel la primera vez.
--   Correo:     admin@nextleague.com
--   Contraseña: Admin123!
-- Como esta contraseña está escrita acá (y por lo tanto es pública),
-- debe_cambiar_contrasena = 1 obliga a cambiarla en el primer ingreso.
-- =====================================================================
INSERT INTO usuarios (nombre_usuario, correo, contrasena_hash, id_rol, debe_cambiar_contrasena) VALUES
('admin', 'admin@nextleague.com', '$2y$10$Sav18ZWDPzVAI5fc4my9QOJpOkh13BK6kY8ddmcSI/rYxTwCcxxSe', 1, 1);
