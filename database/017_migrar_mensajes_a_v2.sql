-- ============================================================
-- 017_migrar_mensajes_a_v2.sql
-- Migración de datos: mensajes (v1) → conversaciones + mensajes_v2
-- ============================================================
-- PREREQUISITO: 016_centro_comunicaciones.sql ya aplicado.
-- IDEMPOTENTE:  seguro ejecutar múltiples veces.
-- REQUISITO DB: MySQL 8.0+ o MariaDB 10.6+ (JSON_TABLE + CTE).
--
-- Qué hace:
--   1. Crea una conversación por cada hilo raíz (mensaje_padre_id IS NULL).
--   2. Copia todos los mensajes (raíz + respuestas de cualquier profundidad)
--      a mensajes_v2 vinculados a su conversación.
--   3. Expande el campo JSON `adjuntos` y crea filas en adjuntos_mensajes.
--   4. Recalcula ultimo_mensaje_at en cada conversación migrada.
--
-- Advertencia sobre adjuntos:
--   El JSON viejo solo almacena URLs (sin nombre, tipo ni tamaño).
--   Se infiere el nombre del último segmento de la URL, se asigna
--   tipo 'application/octet-stream' y tamaño 0. Revisar manualmente
--   después si se necesita precisión.
-- ============================================================

-- ─────────────────────────────────────────────────────────────
-- 0. Índice único para garantizar idempotencia (ANTES de la TX
--    porque DDL hace commit implícito en MySQL).
-- ─────────────────────────────────────────────────────────────
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'conversaciones'
      AND INDEX_NAME = 'ux_conv_referencia'
);
SET @sql = IF(@idx_exists = 0,
    'CREATE UNIQUE INDEX ux_conv_referencia ON conversaciones (referencia_tipo, referencia_id)',
    'SELECT 1'
);
PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────
-- 1. CONVERSACIONES — una por cada hilo raíz
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO conversaciones
    (titulo, empresa_id, iniciada_por, categoria, estado,
     referencia_tipo, referencia_id, ultimo_mensaje_at,
     created_at, updated_at)
SELECT
    m.asunto                                                       AS titulo,
    m.empresa_id                                                   AS empresa_id,
    CASE
        WHEN u.rol IN ('ministerio', 'admin') THEN 'ministerio'
        WHEN u.rol = 'empresa'                THEN 'empresa'
        ELSE                                       'sistema'
    END                                                            AS iniciada_por,
    'consulta'                                                     AS categoria,
    'abierta'                                                      AS estado,
    'migrado_v1'                                                   AS referencia_tipo,
    m.id                                                           AS referencia_id,
    COALESCE(
        (SELECT MAX(h.created_at)
         FROM   mensajes h
         WHERE  h.id = m.id OR h.mensaje_padre_id = m.id),
        m.created_at
    )                                                              AS ultimo_mensaje_at,
    m.created_at                                                   AS created_at,
    m.created_at                                                   AS updated_at
FROM  mensajes m
LEFT  JOIN usuarios u ON u.id = m.remitente_id
WHERE m.mensaje_padre_id IS NULL;

-- ─────────────────────────────────────────────────────────────
-- 2. MENSAJES_V2 — todos los mensajes, con raíz resuelta
--    CTE recursivo para manejar hilos de cualquier profundidad.
--    NOT EXISTS garantiza idempotencia.
-- ─────────────────────────────────────────────────────────────
WITH RECURSIVE hilo AS (
    -- Raíces
    SELECT id AS mensaje_id, id AS raiz_id
    FROM   mensajes
    WHERE  mensaje_padre_id IS NULL
    UNION ALL
    -- Respuestas (baja un nivel por iteración)
    SELECT m.id, h.raiz_id
    FROM   mensajes m
    JOIN   hilo h ON m.mensaje_padre_id = h.mensaje_id
)
INSERT INTO mensajes_v2
    (conversacion_id, remitente_id, remitente_tipo,
     contenido, es_borrador, leido_at, created_at)
SELECT
    c.id                                                           AS conversacion_id,
    m.remitente_id                                                 AS remitente_id,
    CASE
        WHEN u.rol IN ('ministerio', 'admin') THEN 'ministerio'
        WHEN u.rol = 'empresa'                THEN 'empresa'
        ELSE                                       'sistema'
    END                                                            AS remitente_tipo,
    m.contenido                                                    AS contenido,
    0                                                              AS es_borrador,
    CASE WHEN m.leido = 1 THEN m.fecha_lectura ELSE NULL END       AS leido_at,
    m.created_at                                                   AS created_at
FROM  mensajes m
JOIN  hilo           ON hilo.mensaje_id = m.id
LEFT  JOIN usuarios u ON u.id = m.remitente_id
JOIN  conversaciones c
      ON  c.referencia_tipo = 'migrado_v1'
      AND c.referencia_id   = hilo.raiz_id
WHERE NOT EXISTS (
    SELECT 1
    FROM   mensajes_v2 mv2
    WHERE  mv2.conversacion_id = c.id
    AND    mv2.remitente_id    <=> m.remitente_id
    AND    mv2.created_at      = m.created_at
);

-- ─────────────────────────────────────────────────────────────
-- 3. ADJUNTOS_MENSAJES
--    Expande el JSON array de URLs del campo mensajes.adjuntos.
--    Requiere JSON_TABLE (MySQL 8.0+ / MariaDB 10.6+).
--    Si la DB no soporta JSON_TABLE, este bloque falla pero
--    las conversaciones y mensajes ya están migrados (pasos 1-2).
-- ─────────────────────────────────────────────────────────────
WITH RECURSIVE hilo AS (
    SELECT id AS mensaje_id, id AS raiz_id
    FROM   mensajes
    WHERE  mensaje_padre_id IS NULL
    UNION ALL
    SELECT m.id, h.raiz_id
    FROM   mensajes m
    JOIN   hilo h ON m.mensaje_padre_id = h.mensaje_id
)
INSERT INTO adjuntos_mensajes
    (mensaje_id, archivo_url, archivo_nombre, archivo_tipo, archivo_tamano)
SELECT
    mv2.id                                                         AS mensaje_id,
    jt.url                                                         AS archivo_url,
    COALESCE(NULLIF(SUBSTRING_INDEX(jt.url, '/', -1), ''),
             'adjunto')                                            AS archivo_nombre,
    'application/octet-stream'                                     AS archivo_tipo,
    0                                                              AS archivo_tamano
FROM  mensajes m
JOIN  hilo           ON hilo.mensaje_id = m.id
JOIN  conversaciones c
      ON  c.referencia_tipo = 'migrado_v1'
      AND c.referencia_id   = hilo.raiz_id
JOIN  mensajes_v2 mv2
      ON  mv2.conversacion_id = c.id
      AND mv2.remitente_id    <=> m.remitente_id
      AND mv2.created_at      = m.created_at
JOIN  JSON_TABLE(
          m.adjuntos,
          '$[*]' COLUMNS (url VARCHAR(500) PATH '$')
      ) AS jt
WHERE m.adjuntos IS NOT NULL
  AND m.adjuntos NOT IN ('', '[]', 'null')
  AND jt.url IS NOT NULL
  AND jt.url != ''
  AND NOT EXISTS (
      SELECT 1
      FROM   adjuntos_mensajes am
      WHERE  am.mensaje_id = mv2.id
      AND    am.archivo_url = jt.url
  );

-- ─────────────────────────────────────────────────────────────
-- 4. Recalcular ultimo_mensaje_at en conversaciones migradas
-- ─────────────────────────────────────────────────────────────
UPDATE conversaciones c
JOIN (
    SELECT   conversacion_id, MAX(created_at) AS max_at
    FROM     mensajes_v2
    GROUP BY conversacion_id
) latest ON latest.conversacion_id = c.id
SET   c.ultimo_mensaje_at = latest.max_at
WHERE c.referencia_tipo = 'migrado_v1';

COMMIT;

-- ─────────────────────────────────────────────────────────────
-- Verificación post-migración (ejecutar manualmente en HeidiSQL)
-- ─────────────────────────────────────────────────────────────
SELECT
    (SELECT COUNT(*) FROM mensajes WHERE mensaje_padre_id IS NULL)
        AS hilos_origen,
    (SELECT COUNT(*) FROM conversaciones WHERE referencia_tipo = 'migrado_v1')
        AS conversaciones_migradas,
    (SELECT COUNT(*) FROM mensajes)
        AS mensajes_origen_total,
    (SELECT COUNT(*)
     FROM   mensajes_v2 mv2
     JOIN   conversaciones c ON c.id = mv2.conversacion_id
     WHERE  c.referencia_tipo = 'migrado_v1')
        AS mensajes_migrados,
    (SELECT COUNT(*)
     FROM   adjuntos_mensajes am
     JOIN   mensajes_v2 mv2 ON mv2.id = am.mensaje_id
     JOIN   conversaciones c ON c.id = mv2.conversacion_id
     WHERE  c.referencia_tipo = 'migrado_v1')
        AS adjuntos_migrados;
