-- ============================================================
-- SportTime — create_db_user.sql
-- Crear usuario MySQL con permisos mínimos necesarios.
-- Ejecutar como root ANTES de levantar el proyecto.
-- ============================================================

-- Ajustar los valores según el .env del proyecto
SET @db_name = 'sporttime';
SET @db_user = 'sporttime_user';
SET @db_pass = 'SportTime-2026_Db';

CREATE DATABASE IF NOT EXISTS sporttime
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Crear usuario (si no existe)
CREATE USER IF NOT EXISTS 'sporttime_user'@'%' IDENTIFIED BY 'SportTime-2026_Db';

-- Otorgar solo los permisos necesarios (principio de mínimo privilegio)
GRANT SELECT, INSERT, UPDATE, DELETE ON sporttime.* TO 'sporttime_user'@'%';

-- Revocar permisos administrativos innecesarios
REVOKE CREATE, DROP, ALTER, INDEX, REFERENCES ON sporttime.* FROM 'sporttime_user'@'%';

FLUSH PRIVILEGES;

SELECT 'Usuario sporttime_user creado con permisos mínimos.' AS resultado;
