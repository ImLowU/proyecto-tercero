-- NOTA (proyecto destino "SportTime"): esta migración NO se ejecutó desde este
-- archivo — el cambio (columna failed_attempts + valor 'bloqueada' en el enum
-- estado de usuarios) ya estaba aplicado en la base de datos viva de destino
-- antes de esta sincronización (verificado por SHOW COLUMNS el 2026-09-03).
-- Se copia acá únicamente para dejar el historial de migraciones completo e
-- idéntico al del proyecto de referencia. No hay tabla de control de
-- migraciones en este proyecto (no existe `migrations`/`schema_version` en
-- ninguna de las dos bases), así que esta nota es el registro de trazabilidad.
-- Aplicada externamente / origen desconocido, confirmada ya presente el 2026-09-03.

-- Migration: Add failed_attempts column and 'bloqueada' estado to usuarios
ALTER TABLE usuarios
  ADD COLUMN failed_attempts INT NOT NULL DEFAULT 0 AFTER password_hash;

-- Extend enum to include 'bloqueada'
ALTER TABLE usuarios
  MODIFY COLUMN estado ENUM('pendiente','activo','inactivo','suspendido','rechazado','bloqueada') NOT NULL DEFAULT 'activo';

-- Nota: index opcional para consultas por estado puede agregarse manualmente si se desea.
