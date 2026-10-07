-- =====================================================================
-- MIGRACIÓN A LA VERSIÓN 2 — PASO 1 DE 2: PREPARAR
-- Para una base que ya existe con el formato anterior (el del servidor).
-- ANTES: hacer un respaldo (phpMyAdmin → Exportar).
--
-- Orden completo (lo hace solo: php database/instalar.php --bd=NOMBRE --migrar):
--   migracion_v7_preparar.sql → 01_esquema.sql → migracion_v7_datos.sql
--   → 03_vistas.sql → 04_triggers.sql → 05_usuarios_dcl.sql
--
-- Este paso renombra las tablas viejas con el prefijo v6_ para dejar los
-- nombres libres. 01_esquema.sql crea las tablas nuevas vacías y
-- migracion_v7_datos.sql copia los datos y borra las tablas v6_.
-- =====================================================================

RENAME TABLE
    usuarios           TO v6_usuarios,
    juegos             TO v6_juegos,
    torneos            TO v6_torneos,
    equipos            TO v6_equipos,
    equipo_miembros    TO v6_equipo_miembros,
    equipo_solicitudes TO v6_equipo_solicitudes,
    participantes      TO v6_participantes,
    enfrentamientos    TO v6_enfrentamientos,
    tabla_posiciones   TO v6_tabla_posiciones,
    llaves             TO v6_llaves,
    auditoria          TO v6_auditoria;
