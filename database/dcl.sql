-- DCL educativo: usuario de aplicacion con permisos limitados.
-- Ejecutar como root/admin de MySQL.
CREATE USER IF NOT EXISTS 'springstudent'@'127.0.0.1' IDENTIFIED BY 'springstudent';
GRANT SELECT, INSERT, UPDATE, DELETE ON sgdm.* TO 'springstudent'@'127.0.0.1';
FLUSH PRIVILEGES;
