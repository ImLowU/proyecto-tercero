-- Actualizacion no destructiva para una base FlexArena existente.
-- Ejecutar solo si ya habias importado una version anterior del proyecto.
USE sgdm;

CREATE TABLE IF NOT EXISTS equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    participante_id INT DEFAULT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipo_miembros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    rol VARCHAR(80) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    descripcion TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Estas sentencias pueden fallar si las columnas ya existen; en ese caso se pueden ignorar.
ALTER TABLE participantes ADD COLUMN IF NOT EXISTS contacto VARCHAR(150) DEFAULT NULL;
ALTER TABLE participantes ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE tabla_posiciones ADD COLUMN IF NOT EXISTS puntos_favor INT DEFAULT 0;
ALTER TABLE tabla_posiciones ADD COLUMN IF NOT EXISTS puntos_contra INT DEFAULT 0;
ALTER TABLE auditoria ADD COLUMN IF NOT EXISTS tabla_afectada VARCHAR(50) DEFAULT NULL;
