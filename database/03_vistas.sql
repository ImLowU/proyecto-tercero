-- =====================================================================
-- NEXTLEAGUE v2 - VISTAS
-- Consultas guardadas que la aplicación usa como si fueran tablas.
-- Concentran los JOIN más usados en un solo lugar y permiten darle a un
-- usuario de solo lectura acceso a datos ya armados.
-- Se ejecuta después de 02_datos_base.sql.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- PARTICIPANTES CON NOMBRE
-- Un participante puede ser una persona o un equipo; esta vista devuelve
-- siempre un "nombre" y una "imagen" sin importar cuál de los dos es.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW vista_participantes AS
SELECT p.id_participante,
       p.id_torneo,
       p.id_usuario,
       p.id_equipo,
       p.fecha_inscripcion,
       IF(p.id_equipo IS NULL, 'individual', 'equipo') AS tipo,
       COALESCE(e.nombre, u.nombre_usuario)            AS nombre,
       IF(p.id_equipo IS NULL, u.foto_perfil, e.logo)  AS imagen,
       e.id_capitan,
       IF(p.id_equipo IS NULL, 1,
          (SELECT COUNT(*) FROM equipo_miembros m WHERE m.id_equipo = p.id_equipo)) AS cantidad_miembros
FROM participantes p
LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario
LEFT JOIN equipos  e ON e.id_equipo  = p.id_equipo;

-- ---------------------------------------------------------------------
-- TORNEOS CON SUS DATOS RELACIONADOS
-- Juego, módulo, organizador y cantidad de inscriptos en una sola fila.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW vista_torneos AS
SELECT t.id_torneo,
       t.nombre,
       t.id_juego,
       j.nombre        AS juego,
       j.categoria,
       t.id_modulo,
       m.codigo        AS modulo,
       m.nombre        AS nombre_modulo,
       t.id_organizador,
       u.nombre_usuario AS organizador,
       t.modalidad,
       t.cupo,
       (SELECT COUNT(*) FROM participantes p WHERE p.id_torneo = t.id_torneo) AS inscriptos,
       t.premio,
       t.moneda,
       t.fecha_inicio,
       t.fecha_fin,
       t.descripcion,
       t.logo,
       t.reglas_pdf,
       t.estado,
       t.publicado,
       t.fecha_creacion,
       -- Llegó la fecha de inicio y pasó, y el calendario no se generó: las
       -- inscripciones se cierran solas (se puede anotar hasta el día de inicio).
       (t.estado = 'pendiente' AND t.fecha_inicio < CURDATE()) AS inscripcion_vencida,
       -- Pasó la fecha de fin y todavía faltan partidos (aviso para el organizador)
       (t.estado = 'en_curso' AND t.fecha_fin < CURDATE()) AS fin_vencido
FROM torneos t
JOIN juegos              j ON j.id_juego       = t.id_juego
JOIN modulos_competencia m ON m.id_modulo      = t.id_modulo
JOIN usuarios            u ON u.id_usuario     = t.id_organizador;

-- Los que puede ver cualquier visitante: publicados y no cancelados.
CREATE OR REPLACE VIEW vista_torneos_publicos AS
SELECT *
FROM vista_torneos
WHERE publicado = 1
  AND estado <> 'cancelado';

-- ---------------------------------------------------------------------
-- RESULTADOS VIGENTES
-- El resultado oficial de cada enfrentamiento (las correcciones viejas
-- quedan en la tabla resultados con vigente = 0).
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW vista_resultados_vigentes AS
SELECT r.id_resultado,
       r.id_enfrentamiento,
       r.puntaje_local,
       r.puntaje_visitante,
       r.id_usuario_carga,
       r.fecha_carga,
       r.observacion
FROM resultados r
WHERE r.vigente = 1;

-- ---------------------------------------------------------------------
-- CALENDARIO
-- Cada enfrentamiento con su ronda, torneo, nombres de los rivales y el
-- resultado vigente. "es_publico" indica si un visitante lo puede ver:
-- el torneo tiene que estar publicado y la ronda publicada o cerrada.
-- Un torneo cancelado sigue mostrando sus partidos como historial (antes
-- se ocultaban); las listas de próximos partidos lo dejan afuera con
-- "estado_torneo", porque esos partidos ya no se van a jugar.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW vista_calendario AS
SELECT e.id_enfrentamiento,
       ro.id_torneo,
       t.nombre          AS torneo,
       t.id_juego,
       j.nombre          AS juego,
       ro.id_ronda,
       ro.numero         AS ronda,
       ro.nombre         AS nombre_ronda,
       ro.estado         AS estado_ronda,
       e.posicion,
       e.id_local,
       pl.nombre         AS nombre_local,
       e.id_visitante,
       pv.nombre         AS nombre_visitante,
       e.id_ganador,
       e.es_pase_libre,
       e.fecha_hora,
       e.lugar,
       e.estado,
       rv.puntaje_local,
       rv.puntaje_visitante,
       rv.fecha_carga    AS fecha_resultado,
       t.estado          AS estado_torneo,
       IF(t.publicado = 1 AND ro.estado <> 'borrador', 1, 0) AS es_publico
FROM enfrentamientos e
JOIN rondas  ro ON ro.id_ronda  = e.id_ronda
JOIN torneos t  ON t.id_torneo  = ro.id_torneo
JOIN juegos  j  ON j.id_juego   = t.id_juego
LEFT JOIN vista_participantes      pl ON pl.id_participante   = e.id_local
LEFT JOIN vista_participantes      pv ON pv.id_participante   = e.id_visitante
LEFT JOIN vista_resultados_vigentes rv ON rv.id_enfrentamiento = e.id_enfrentamiento;

-- ---------------------------------------------------------------------
-- PARTIDOS JUGADOS, VISTOS DESDE CADA PARTICIPANTE
-- Cada partido finalizado aparece dos veces: una desde el local y otra
-- desde el visitante, con "a favor" y "en contra" ya invertidos. Es la
-- base para calcular la tabla de posiciones.
-- Un pase libre aparece solo para quien lo recibió y cuenta como ganado.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW vista_partidos_por_participante AS
SELECT ro.id_torneo,
       e.id_enfrentamiento,
       e.id_local                      AS id_participante,
       e.id_visitante                  AS id_rival,
       COALESCE(rv.puntaje_local, 0)     AS a_favor,
       COALESCE(rv.puntaje_visitante, 0) AS en_contra,
       e.es_pase_libre
FROM enfrentamientos e
JOIN rondas ro ON ro.id_ronda = e.id_ronda
LEFT JOIN vista_resultados_vigentes rv ON rv.id_enfrentamiento = e.id_enfrentamiento
WHERE e.estado = 'finalizado'
  AND e.id_local IS NOT NULL
UNION ALL
SELECT ro.id_torneo,
       e.id_enfrentamiento,
       e.id_visitante                  AS id_participante,
       e.id_local                      AS id_rival,
       COALESCE(rv.puntaje_visitante, 0) AS a_favor,
       COALESCE(rv.puntaje_local, 0)     AS en_contra,
       e.es_pase_libre
FROM enfrentamientos e
JOIN rondas ro ON ro.id_ronda = e.id_ronda
LEFT JOIN vista_resultados_vigentes rv ON rv.id_enfrentamiento = e.id_enfrentamiento
WHERE e.estado = 'finalizado'
  AND e.id_visitante IS NOT NULL;

-- ---------------------------------------------------------------------
-- TABLA DE POSICIONES
-- Se calcula en el momento a partir de los resultados vigentes y de los
-- puntos que configuró cada torneo. Como no se guarda, corregir un
-- resultado actualiza la tabla sola y nunca quedan puntos duplicados.
-- El orden (puntos, diferencia, a favor) lo aplica la aplicación.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW vista_tabla_posiciones AS
SELECT p.id_torneo,
       p.id_participante,
       p.nombre,
       p.tipo,
       p.imagen,
       COUNT(jp.id_enfrentamiento) AS pj,
       COALESCE(SUM(jp.es_pase_libre = 1 OR jp.a_favor > jp.en_contra), 0)  AS pg,
       COALESCE(SUM(jp.es_pase_libre = 0 AND jp.a_favor = jp.en_contra), 0) AS pe,
       COALESCE(SUM(jp.es_pase_libre = 0 AND jp.a_favor < jp.en_contra), 0) AS pp,
       COALESCE(SUM(jp.a_favor), 0)   AS gf,
       COALESCE(SUM(jp.en_contra), 0) AS gc,
       COALESCE(SUM(jp.a_favor), 0) - COALESCE(SUM(jp.en_contra), 0) AS diferencia,
       COALESCE(SUM(jp.es_pase_libre = 1 OR jp.a_favor > jp.en_contra), 0)  * c.puntos_victoria
     + COALESCE(SUM(jp.es_pase_libre = 0 AND jp.a_favor = jp.en_contra), 0) * c.puntos_empate
     + COALESCE(SUM(jp.es_pase_libre = 0 AND jp.a_favor < jp.en_contra), 0) * c.puntos_derrota AS puntos
FROM vista_participantes p
JOIN configuracion_torneo c ON c.id_torneo = p.id_torneo
LEFT JOIN vista_partidos_por_participante jp ON jp.id_participante = p.id_participante
GROUP BY p.id_torneo, p.id_participante, p.nombre, p.tipo, p.imagen,
         c.puntos_victoria, c.puntos_empate, c.puntos_derrota;

-- ---------------------------------------------------------------------
-- ESTADÍSTICAS GENERALES
-- Los números reales que muestran el inicio y el panel de administración
-- (antes eran un "120+" escrito a mano en el HTML).
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW vista_estadisticas AS
SELECT
    (SELECT COUNT(*) FROM usuarios WHERE activo = 1) AS usuarios,
    (SELECT COUNT(*) FROM torneos WHERE publicado = 1 AND estado <> 'cancelado') AS torneos,
    (SELECT COUNT(*) FROM torneos WHERE publicado = 1 AND estado IN ('pendiente', 'en_curso')) AS torneos_activos,
    (SELECT COUNT(*) FROM equipos) AS equipos,
    (SELECT COUNT(*) FROM participantes) AS participantes,
    (SELECT COUNT(*) FROM enfrentamientos WHERE estado = 'finalizado' AND es_pase_libre = 0) AS partidos_jugados,
    (SELECT COUNT(*)
       FROM torneos t
       JOIN juegos j ON j.id_juego = t.id_juego
      WHERE j.categoria = 'electronico' AND t.publicado = 1 AND t.estado <> 'cancelado') AS torneos_electronicos;
