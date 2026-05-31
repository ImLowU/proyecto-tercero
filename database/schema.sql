-- ============================================================
-- FlexArena - Sistema de Gestion Deportiva Modular
-- Base de datos MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS sgdm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sgdm;

-- Roles del sistema: administrador, organizador y participante.
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT
) ENGINE=InnoDB;

-- Usuarios autenticados. Las contrasenas se guardan con password_hash().
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol_id INT NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- Participantes competitivos. Pueden ser personas o equipos.
CREATE TABLE IF NOT EXISTS participantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    tipo ENUM('individual','equipo') DEFAULT 'individual',
    usuario_id INT DEFAULT NULL,
    contacto VARCHAR(150) DEFAULT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Equipos registrados. Cada equipo tambien tiene un participante asociado.
CREATE TABLE IF NOT EXISTS equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    participante_id INT DEFAULT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (participante_id) REFERENCES participantes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Integrantes de equipos. Sirve para registrar planteles sin crear usuario a cada persona.
CREATE TABLE IF NOT EXISTS equipo_miembros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    rol VARCHAR(80) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tipos modulares de torneo.
CREATE TABLE IF NOT EXISTS tipos_torneo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT
) ENGINE=InnoDB;

-- Torneos creados dentro del sistema.
CREATE TABLE IF NOT EXISTS torneos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    tipo_torneo_id INT NOT NULL,
    organizador_id INT NOT NULL,
    estado ENUM('borrador','inscripcion','en_curso','finalizado') DEFAULT 'borrador',
    fecha_inicio DATE,
    fecha_fin DATE,
    publico TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_torneo_id) REFERENCES tipos_torneo(id),
    FOREIGN KEY (organizador_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Inscripciones de participantes en torneos.
CREATE TABLE IF NOT EXISTS inscripciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    torneo_id INT NOT NULL,
    participante_id INT NOT NULL,
    fecha_inscripcion DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente','confirmada','baja') DEFAULT 'confirmada',
    UNIQUE KEY uq_torneo_participante (torneo_id, participante_id),
    FOREIGN KEY (torneo_id) REFERENCES torneos(id) ON DELETE CASCADE,
    FOREIGN KEY (participante_id) REFERENCES participantes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Rondas, fechas o etapas de un torneo.
CREATE TABLE IF NOT EXISTS rondas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    torneo_id INT NOT NULL,
    numero INT NOT NULL,
    nombre VARCHAR(100),
    estado ENUM('pendiente','en_curso','cerrada') DEFAULT 'pendiente',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_torneo_ronda (torneo_id, numero),
    FOREIGN KEY (torneo_id) REFERENCES torneos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Enfrentamientos dentro de una ronda. participante_b NULL representa descanso/bye.
CREATE TABLE IF NOT EXISTS enfrentamientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ronda_id INT NOT NULL,
    participante_a INT NOT NULL,
    participante_b INT DEFAULT NULL,
    estado ENUM('pendiente','en_curso','finalizado') DEFAULT 'pendiente',
    fecha_hora DATETIME,
    lugar VARCHAR(200),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ronda_id) REFERENCES rondas(id) ON DELETE CASCADE,
    FOREIGN KEY (participante_a) REFERENCES participantes(id),
    FOREIGN KEY (participante_b) REFERENCES participantes(id)
) ENGINE=InnoDB;

-- Resultados oficiales de enfrentamientos.
CREATE TABLE IF NOT EXISTS resultados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enfrentamiento_id INT NOT NULL UNIQUE,
    puntos_a INT DEFAULT 0,
    puntos_b INT DEFAULT 0,
    ganador_id INT DEFAULT NULL,
    observaciones TEXT,
    cargado_por INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (enfrentamiento_id) REFERENCES enfrentamientos(id) ON DELETE CASCADE,
    FOREIGN KEY (ganador_id) REFERENCES participantes(id) ON DELETE SET NULL,
    FOREIGN KEY (cargado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Tabla acumulada de posiciones por torneo.
CREATE TABLE IF NOT EXISTS tabla_posiciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    torneo_id INT NOT NULL,
    participante_id INT NOT NULL,
    puntos INT DEFAULT 0,
    partidos_jugados INT DEFAULT 0,
    victorias INT DEFAULT 0,
    empates INT DEFAULT 0,
    derrotas INT DEFAULT 0,
    puntos_favor INT DEFAULT 0,
    puntos_contra INT DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_posicion (torneo_id, participante_id),
    FOREIGN KEY (torneo_id) REFERENCES torneos(id) ON DELETE CASCADE,
    FOREIGN KEY (participante_id) REFERENCES participantes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Registro de actividad. Mantiene trazabilidad de cambios importantes.
CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT DEFAULT NULL,
    accion VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(50),
    tabla VARCHAR(50),
    registro_id INT,
    detalle TEXT,
    ip VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Configuracion general simple del sistema.
CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    descripcion TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (id, nombre, descripcion) VALUES
    (1, 'admin', 'Administrador general con acceso completo'),
    (2, 'organizador', 'Gestiona torneos asignados'),
    (3, 'participante', 'Consulta sus torneos, calendario y resultados');

INSERT IGNORE INTO tipos_torneo (id, nombre, descripcion) VALUES
    (1, 'liga', 'Todos contra todos, con tabla de posiciones'),
    (2, 'eliminacion_directa', 'Llave de eliminacion por rondas'),
    (3, 'suizo', 'Emparejamientos por rendimiento acumulado');

-- Usuario inicial: admin@sgdm.local / Admin1234!
INSERT IGNORE INTO usuarios (id, nombre, email, password, rol_id) VALUES
    (1, 'Administrador', 'admin@sgdm.local', '$2y$12$f3imax6MOzDSIvYG26WjH.MsP6cPWR2WcPFicNh4FvWETQHiytV1i', 1);

INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
    ('nombre_sistema', 'FlexArena', 'Nombre visible de la aplicacion'),
    ('inscripciones_publicas', '0', 'Permite o bloquea inscripciones publicas futuras'),
    ('puntos_victoria', '3', 'Puntos otorgados por victoria'),
    ('puntos_empate', '1', 'Puntos otorgados por empate');
