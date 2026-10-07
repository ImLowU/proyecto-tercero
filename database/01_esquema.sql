-- =====================================================================
-- NEXTLEAGUE v2 - ESQUEMA DE LA BASE DE DATOS
-- Motor: MySQL 8 / MariaDB 10.4 o superior (InnoDB, utf8mb4)
--
-- Este script NO crea la base ni hace USE: se ejecuta dentro de una base
-- vacía ya seleccionada. Así el mismo script sirve para la base real, la
-- de pruebas y la del servidor. Orden de instalación:
--   01_esquema.sql → 02_datos_base.sql → 03_vistas.sql → 04_triggers.sql
--   → 05_usuarios_dcl.sql → (opcional) 06_datos_prueba.sql
-- Lo más simple es usar el instalador: php database/instalar.php --ayuda
--
-- Decisiones de diseño principales:
-- * El competidor de un torneo es siempre un PARTICIPANTE, que puede ser
--   una persona (torneos individuales) o un equipo (torneos por equipos).
--   Los enfrentamientos y la tabla de posiciones apuntan a participantes,
--   así ajedrez o tenis funcionan igual que fútbol.
-- * Los resultados se guardan en su propia tabla con historial: corregir
--   un resultado agrega uno nuevo "vigente" y deja el anterior guardado.
-- * La tabla de posiciones NO se guarda: es una vista que se calcula a
--   partir de los resultados vigentes (ver 03_vistas.sql), así nunca
--   puede quedar desactualizada ni con puntos duplicados.
-- =====================================================================

SET NAMES utf8mb4;

-- =====================================================================
-- 1. ROLES
-- Catálogo de roles del sistema. El "usuario público" de la letra no es
-- un rol guardado: es cualquier visitante que no inició sesión.
-- =====================================================================
CREATE TABLE roles (
    id_rol       TINYINT      NOT NULL,
    codigo       VARCHAR(20)  NOT NULL,
    nombre       VARCHAR(40)  NOT NULL,
    descripcion  VARCHAR(255) NOT NULL,
    CONSTRAINT pk_roles PRIMARY KEY (id_rol),
    CONSTRAINT uq_roles_codigo UNIQUE (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. USUARIOS
-- La foto de perfil guarda solo el nombre del archivo; el archivo vive
-- en public/uploads/perfiles. La contraseña se guarda hasheada (bcrypt).
-- =====================================================================
CREATE TABLE usuarios (
    id_usuario               INT          NOT NULL AUTO_INCREMENT,
    nombre_usuario           VARCHAR(50)  NOT NULL,
    correo                   VARCHAR(100) NOT NULL,
    contrasena_hash          VARCHAR(255) NOT NULL,
    id_rol                   TINYINT      NOT NULL DEFAULT 3,
    foto_perfil              VARCHAR(255) NULL,
    biografia                VARCHAR(280) NULL,
    activo                   TINYINT(1)   NOT NULL DEFAULT 1,
    debe_cambiar_contrasena  TINYINT(1)   NOT NULL DEFAULT 0,
    fecha_registro           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso            DATETIME     NULL,
    CONSTRAINT pk_usuarios PRIMARY KEY (id_usuario),
    CONSTRAINT uq_usuarios_nombre UNIQUE (nombre_usuario),
    CONSTRAINT uq_usuarios_correo UNIQUE (correo),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) REFERENCES roles (id_rol),
    CONSTRAINT chk_usuarios_nombre CHECK (CHAR_LENGTH(nombre_usuario) >= 3),
    CONSTRAINT chk_usuarios_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. TOKENS DE USUARIO
-- Sirve para "recuperar contraseña" y para "recordarme". Cada token tiene
-- dos partes: el selector (público, para encontrarlo) y el validador
-- (secreto). Del validador solo se guarda su hash SHA-256, así que si
-- alguien lee esta tabla no puede usar los tokens.
-- =====================================================================
CREATE TABLE tokens_usuario (
    id_token        INT         NOT NULL AUTO_INCREMENT,
    id_usuario      INT         NOT NULL,
    tipo            ENUM('recuperacion', 'recordarme') NOT NULL,
    selector        CHAR(24)    NOT NULL,
    hash_validador  CHAR(64)    NOT NULL,
    vence           DATETIME    NOT NULL,
    fecha_creacion  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_tokens_usuario PRIMARY KEY (id_token),
    CONSTRAINT uq_tokens_selector UNIQUE (selector),
    CONSTRAINT fk_tokens_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE CASCADE,
    INDEX idx_tokens_usuario_tipo (id_usuario, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. INTENTOS DE INICIO DE SESIÓN
-- Permite bloquear temporalmente un correo o una IP después de varios
-- intentos fallidos seguidos (protección contra fuerza bruta).
-- =====================================================================
CREATE TABLE intentos_login (
    id_intento  BIGINT       NOT NULL AUTO_INCREMENT,
    correo      VARCHAR(100) NOT NULL,
    ip          VARCHAR(45)  NOT NULL,
    exitoso     TINYINT(1)   NOT NULL,
    fecha       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_intentos_login PRIMARY KEY (id_intento),
    INDEX idx_intentos_correo (correo, fecha),
    INDEX idx_intentos_ip (ip, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5. JUEGOS / DISCIPLINAS
-- Deportes tradicionales, disciplinas mentales y videojuegos. La
-- modalidad sugerida se propone al crear un torneo (el organizador puede
-- cambiarla: hay ajedrez por equipos y fútbol 1 contra 1 en videojuegos).
-- =====================================================================
CREATE TABLE juegos (
    id_juego            INT          NOT NULL AUTO_INCREMENT,
    nombre              VARCHAR(100) NOT NULL,
    categoria           ENUM('deportivo', 'mental', 'electronico') NOT NULL,
    modalidad_sugerida  ENUM('individual', 'equipos') NOT NULL DEFAULT 'equipos',
    icono               VARCHAR(255) NULL,
    CONSTRAINT pk_juegos PRIMARY KEY (id_juego),
    CONSTRAINT uq_juegos_nombre UNIQUE (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6. MÓDULOS DE COMPETENCIA
-- Cada módulo es una forma de organizar un torneo (liga, eliminación
-- directa, sistema suizo). El administrador puede deshabilitar uno y
-- deja de ofrecerse al crear torneos nuevos.
-- =====================================================================
CREATE TABLE modulos_competencia (
    id_modulo    TINYINT      NOT NULL,
    codigo       VARCHAR(30)  NOT NULL,
    nombre       VARCHAR(60)  NOT NULL,
    descripcion  VARCHAR(255) NOT NULL,
    habilitado   TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT pk_modulos_competencia PRIMARY KEY (id_modulo),
    CONSTRAINT uq_modulos_codigo UNIQUE (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7. TORNEOS
-- "cupo" es la cantidad máxima de participantes (personas o equipos,
-- según la modalidad). "publicado" decide si el público lo puede ver.
-- Un torneo en curso no se borra: se cancela, para no perder historial.
-- =====================================================================
CREATE TABLE torneos (
    id_torneo       INT           NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(150)  NOT NULL,
    id_juego        INT           NOT NULL,
    id_modulo       TINYINT       NOT NULL,
    id_organizador  INT           NOT NULL,
    modalidad       ENUM('individual', 'equipos') NOT NULL,
    cupo            SMALLINT      NOT NULL,
    premio          DECIMAL(10,2) NOT NULL DEFAULT 0,
    moneda          ENUM('UYU', 'USD') NOT NULL DEFAULT 'UYU',
    fecha_inicio    DATE          NOT NULL,
    fecha_fin       DATE          NOT NULL,
    descripcion     TEXT          NULL,
    logo            VARCHAR(255)  NULL,
    reglas_pdf      VARCHAR(255)  NULL,
    estado          ENUM('pendiente', 'en_curso', 'finalizado', 'cancelado') NOT NULL DEFAULT 'pendiente',
    publicado       TINYINT(1)    NOT NULL DEFAULT 1,
    fecha_creacion  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_torneos PRIMARY KEY (id_torneo),
    CONSTRAINT fk_torneos_juego FOREIGN KEY (id_juego) REFERENCES juegos (id_juego),
    CONSTRAINT fk_torneos_modulo FOREIGN KEY (id_modulo) REFERENCES modulos_competencia (id_modulo),
    CONSTRAINT fk_torneos_organizador FOREIGN KEY (id_organizador) REFERENCES usuarios (id_usuario),
    CONSTRAINT chk_torneos_nombre CHECK (CHAR_LENGTH(nombre) >= 3),
    CONSTRAINT chk_torneos_cupo CHECK (cupo BETWEEN 2 AND 256),
    CONSTRAINT chk_torneos_premio CHECK (premio >= 0),
    CONSTRAINT chk_torneos_fechas CHECK (fecha_fin >= fecha_inicio),
    INDEX idx_torneos_publicados (publicado, estado),
    INDEX idx_torneos_organizador (id_organizador)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 8. CONFIGURACIÓN DEL TORNEO (1 a 1 con torneos)
-- Reglas que cambian de un torneo a otro: puntos por resultado, si la
-- liga es ida y vuelta, cuántas rondas tiene el suizo (NULL = las que
-- correspondan según la cantidad de participantes), etc.
-- permitir_correcciones es la autorización del administrador para que el
-- organizador corrija resultados (letra, 5.2): empieza sin autorización.
-- =====================================================================
CREATE TABLE configuracion_torneo (
    id_torneo               INT        NOT NULL,
    puntos_victoria         TINYINT    NOT NULL DEFAULT 3,
    puntos_empate           TINYINT    NOT NULL DEFAULT 1,
    puntos_derrota          TINYINT    NOT NULL DEFAULT 0,
    ida_y_vuelta            TINYINT(1) NOT NULL DEFAULT 0,
    rondas_suizo            TINYINT    NULL,
    permitir_empates        TINYINT(1) NOT NULL DEFAULT 1,
    permitir_correcciones   TINYINT(1) NOT NULL DEFAULT 0,
    max_integrantes_equipo  TINYINT    NULL,
    CONSTRAINT pk_configuracion_torneo PRIMARY KEY (id_torneo),
    CONSTRAINT fk_configuracion_torneo FOREIGN KEY (id_torneo) REFERENCES torneos (id_torneo) ON DELETE CASCADE,
    CONSTRAINT chk_configuracion_puntos CHECK (puntos_victoria >= puntos_empate AND puntos_empate >= puntos_derrota AND puntos_derrota >= 0),
    CONSTRAINT chk_configuracion_rondas CHECK (rondas_suizo IS NULL OR rondas_suizo BETWEEN 1 AND 20),
    CONSTRAINT chk_configuracion_integrantes CHECK (max_integrantes_equipo IS NULL OR max_integrantes_equipo BETWEEN 1 AND 50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 9. EQUIPOS (solo en torneos por equipos)
-- Un equipo pertenece a un único torneo. El capitán es quien lo creó y
-- es quien acepta o rechaza las solicitudes para sumarse.
-- =====================================================================
CREATE TABLE equipos (
    id_equipo       INT          NOT NULL AUTO_INCREMENT,
    id_torneo       INT          NOT NULL,
    nombre          VARCHAR(100) NOT NULL,
    logo            VARCHAR(255) NULL,
    id_capitan      INT          NULL,
    fecha_creacion  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_equipos PRIMARY KEY (id_equipo),
    CONSTRAINT uq_equipos_nombre UNIQUE (id_torneo, nombre),
    CONSTRAINT fk_equipos_torneo FOREIGN KEY (id_torneo) REFERENCES torneos (id_torneo) ON DELETE CASCADE,
    CONSTRAINT fk_equipos_capitan FOREIGN KEY (id_capitan) REFERENCES usuarios (id_usuario) ON DELETE SET NULL,
    CONSTRAINT chk_equipos_nombre CHECK (CHAR_LENGTH(nombre) >= 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 10. MIEMBROS DE EQUIPO (relación N a N entre usuarios y equipos)
-- =====================================================================
CREATE TABLE equipo_miembros (
    id_equipo    INT      NOT NULL,
    id_usuario   INT      NOT NULL,
    fecha_union  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_equipo_miembros PRIMARY KEY (id_equipo, id_usuario),
    CONSTRAINT fk_miembros_equipo FOREIGN KEY (id_equipo) REFERENCES equipos (id_equipo) ON DELETE CASCADE,
    CONSTRAINT fk_miembros_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 11. SOLICITUDES PARA UNIRSE A UN EQUIPO
-- =====================================================================
CREATE TABLE equipo_solicitudes (
    id_solicitud     INT      NOT NULL AUTO_INCREMENT,
    id_equipo        INT      NOT NULL,
    id_usuario       INT      NOT NULL,
    estado           ENUM('pendiente', 'aceptada', 'rechazada') NOT NULL DEFAULT 'pendiente',
    fecha_solicitud  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_respuesta  DATETIME NULL,
    CONSTRAINT pk_equipo_solicitudes PRIMARY KEY (id_solicitud),
    CONSTRAINT fk_solicitudes_equipo FOREIGN KEY (id_equipo) REFERENCES equipos (id_equipo) ON DELETE CASCADE,
    CONSTRAINT fk_solicitudes_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE CASCADE,
    INDEX idx_solicitudes_equipo_estado (id_equipo, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 12. PARTICIPANTES (los competidores de cada torneo)
-- Exactamente uno de los dos campos tiene valor: id_usuario en torneos
-- individuales o id_equipo en torneos por equipos (lo controla el CHECK).
-- Una persona no se puede inscribir dos veces al mismo torneo y un
-- equipo participa una sola vez (los UNIQUE).
-- =====================================================================
CREATE TABLE participantes (
    id_participante    INT      NOT NULL AUTO_INCREMENT,
    id_torneo          INT      NOT NULL,
    id_usuario         INT      NULL,
    id_equipo          INT      NULL,
    fecha_inscripcion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_participantes PRIMARY KEY (id_participante),
    CONSTRAINT uq_participantes_usuario UNIQUE (id_torneo, id_usuario),
    CONSTRAINT uq_participantes_equipo UNIQUE (id_equipo),
    CONSTRAINT fk_participantes_torneo FOREIGN KEY (id_torneo) REFERENCES torneos (id_torneo) ON DELETE CASCADE,
    CONSTRAINT fk_participantes_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_participantes_equipo FOREIGN KEY (id_equipo) REFERENCES equipos (id_equipo) ON DELETE CASCADE,
    CONSTRAINT chk_participantes_tipo CHECK ((id_usuario IS NULL) <> (id_equipo IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 13. RONDAS
-- Agrupan los enfrentamientos (fecha 1, fecha 2... o cuartos, semifinal,
-- final). El estado controla qué ve el público:
--   borrador  → solo la ven el organizador y el administrador
--   publicada → la ve todo el mundo y se pueden cargar resultados
--   cerrada   → los resultados quedan firmes
-- El UNIQUE (torneo, número) impide generar dos veces la misma ronda.
-- =====================================================================
CREATE TABLE rondas (
    id_ronda   INT         NOT NULL AUTO_INCREMENT,
    id_torneo  INT         NOT NULL,
    numero     SMALLINT    NOT NULL,
    nombre     VARCHAR(50) NULL,
    estado     ENUM('borrador', 'publicada', 'cerrada') NOT NULL DEFAULT 'borrador',
    fecha      DATE        NULL,
    CONSTRAINT pk_rondas PRIMARY KEY (id_ronda),
    CONSTRAINT uq_rondas_numero UNIQUE (id_torneo, numero),
    CONSTRAINT fk_rondas_torneo FOREIGN KEY (id_torneo) REFERENCES torneos (id_torneo) ON DELETE CASCADE,
    CONSTRAINT chk_rondas_numero CHECK (numero >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 14. ENFRENTAMIENTOS (partidos)
-- "posicion" ordena los partidos dentro de la ronda; en eliminación
-- directa además indica el lugar en la llave (el ganador de la posición
-- p pasa a la posición p/2 de la ronda siguiente).
-- Un "pase libre" es cuando un participante avanza o suma sin rival
-- (cantidad impar en el suizo, o huecos en la llave).
-- Los resultados están en la tabla "resultados".
-- =====================================================================
CREATE TABLE enfrentamientos (
    id_enfrentamiento  INT          NOT NULL AUTO_INCREMENT,
    id_ronda           INT          NOT NULL,
    posicion           SMALLINT     NOT NULL DEFAULT 0,
    id_local           INT          NULL,
    id_visitante       INT          NULL,
    id_ganador         INT          NULL,
    es_pase_libre      TINYINT(1)   NOT NULL DEFAULT 0,
    fecha_hora         DATETIME     NULL,
    lugar              VARCHAR(100) NULL,
    estado             ENUM('pendiente', 'finalizado') NOT NULL DEFAULT 'pendiente',
    CONSTRAINT pk_enfrentamientos PRIMARY KEY (id_enfrentamiento),
    CONSTRAINT uq_enfrentamientos_posicion UNIQUE (id_ronda, posicion),
    CONSTRAINT fk_enfrentamientos_ronda FOREIGN KEY (id_ronda) REFERENCES rondas (id_ronda) ON DELETE CASCADE,
    CONSTRAINT fk_enfrentamientos_local FOREIGN KEY (id_local) REFERENCES participantes (id_participante) ON DELETE SET NULL,
    CONSTRAINT fk_enfrentamientos_visitante FOREIGN KEY (id_visitante) REFERENCES participantes (id_participante) ON DELETE SET NULL,
    CONSTRAINT fk_enfrentamientos_ganador FOREIGN KEY (id_ganador) REFERENCES participantes (id_participante) ON DELETE SET NULL,
    INDEX idx_enfrentamientos_fecha (fecha_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 15. RESULTADOS (con historial de correcciones)
-- Cada carga agrega una fila. La que está "vigente" es la oficial; al
-- corregir, la anterior pasa a vigente = 0 y queda como historial con
-- quién la cargó y cuándo. La aplicación garantiza que haya una sola
-- vigente por enfrentamiento (lo hace dentro de una transacción).
-- =====================================================================
CREATE TABLE resultados (
    id_resultado       INT          NOT NULL AUTO_INCREMENT,
    id_enfrentamiento  INT          NOT NULL,
    puntaje_local      SMALLINT     NOT NULL,
    puntaje_visitante  SMALLINT     NOT NULL,
    id_usuario_carga   INT          NULL,
    fecha_carga        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    vigente            TINYINT(1)   NOT NULL DEFAULT 1,
    observacion        VARCHAR(255) NULL,
    CONSTRAINT pk_resultados PRIMARY KEY (id_resultado),
    CONSTRAINT fk_resultados_enfrentamiento FOREIGN KEY (id_enfrentamiento) REFERENCES enfrentamientos (id_enfrentamiento) ON DELETE CASCADE,
    CONSTRAINT fk_resultados_usuario FOREIGN KEY (id_usuario_carga) REFERENCES usuarios (id_usuario) ON DELETE SET NULL,
    CONSTRAINT chk_resultados_puntajes CHECK (puntaje_local >= 0 AND puntaje_visitante >= 0),
    INDEX idx_resultados_vigente (id_enfrentamiento, vigente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 16. CONFIGURACIÓN GENERAL DEL SISTEMA
-- Valores que el administrador puede cambiar desde su panel sin tocar
-- código (intentos de login permitidos, si el registro está abierto...).
-- =====================================================================
CREATE TABLE configuracion_sistema (
    clave        VARCHAR(60)  NOT NULL,
    valor        VARCHAR(255) NOT NULL,
    tipo         ENUM('texto', 'numero', 'booleano') NOT NULL DEFAULT 'texto',
    descripcion  VARCHAR(255) NOT NULL,
    CONSTRAINT pk_configuracion_sistema PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 17. AUDITORÍA
-- La letra pide que "quede registrado cualquier cambio que se realice en
-- la información de la Base de Datos". Hay dos orígenes:
--   'bd'  → lo escriben los triggers (04_triggers.sql) en cada INSERT,
--           UPDATE o DELETE, con los valores anteriores y nuevos en JSON.
--           Registra el cambio aunque se haga desde phpMyAdmin.
--   'app' → eventos que no son cambios de una fila (inicio de sesión,
--           intentos fallidos, generación de un calendario...).
-- id_usuario es quien estaba logueado en la aplicación; usuario_bd es la
-- cuenta de MySQL que hizo el cambio (sirve para detectar cambios hechos
-- por fuera de la aplicación). No tiene clave foránea a propósito: el
-- historial tiene que conservarse aunque el usuario se elimine.
-- La aplicación solo tiene permiso de INSERT y SELECT sobre esta tabla
-- (ver 05_usuarios_dcl.sql), así que no puede borrar ni alterar el historial.
-- =====================================================================
CREATE TABLE auditoria (
    id_auditoria        BIGINT       NOT NULL AUTO_INCREMENT,
    fecha               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    origen              ENUM('bd', 'app') NOT NULL,
    accion              VARCHAR(30)  NOT NULL,
    tabla               VARCHAR(50)  NULL,
    id_registro         VARCHAR(50)  NULL,
    id_usuario          INT          NULL,
    usuario_bd          VARCHAR(100) NULL,
    ip                  VARCHAR(45)  NULL,
    valores_anteriores  JSON         NULL,
    valores_nuevos      JSON         NULL,
    detalle             VARCHAR(255) NULL,
    CONSTRAINT pk_auditoria PRIMARY KEY (id_auditoria),
    INDEX idx_auditoria_fecha (fecha),
    INDEX idx_auditoria_registro (tabla, id_registro),
    INDEX idx_auditoria_usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
