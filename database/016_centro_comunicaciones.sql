-- =====================================================
-- MIGRACION 016 — Centro de Comunicaciones (Fase 2)
-- =====================================================
-- Crea el modelo de mensajeria unificada estilo Gmail/WhatsApp:
--   conversaciones (hilos como entidades primarias)
--   mensajes_v2     (mensajes hijos de conversacion, con borradores)
--   adjuntos_mensajes (multiples archivos por mensaje, hasta 25MB total)
--   comunicado_visto  (pivot para broadcasts ministerio -> todas las empresas)
--
-- Esta migracion es ADITIVA: no toca las tablas `mensajes` ni `notificaciones`
-- existentes. La app actual sigue funcionando. Una vez probado el nuevo
-- centro, una migracion posterior renombrara mensajes -> mensajes_legacy
-- y mensajes_v2 -> mensajes.
--
-- Cumple con los requisitos del checklist (puntos 74-83, 105-106, 123-128,
-- 140-141): bandeja unificada, multiple adjuntos, leido automatico,
-- comunicados masivos sin duplicar mensajes, archivado por usuario.
-- =====================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- =====================================================
-- TABLA: conversaciones
-- Hilo de mensajes entre el ministerio y una empresa (o broadcast a todas).
-- =====================================================
CREATE TABLE IF NOT EXISTS conversaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,

    titulo VARCHAR(200) NOT NULL,

    -- NULL = comunicado global a TODAS las empresas activas.
    -- Cuando es NULL la "lectura" se trackea via tabla comunicado_visto.
    empresa_id INT NULL,

    iniciada_por ENUM('empresa', 'ministerio', 'sistema') NOT NULL,

    categoria ENUM(
        'tramite',
        'consulta',
        'reclamo',
        'comunicado',
        'formulario',
        'sistema'
    ) NOT NULL DEFAULT 'consulta',

    estado ENUM('abierta', 'cerrada', 'archivada') NOT NULL DEFAULT 'abierta',

    -- Enlace opcional a la entidad que origino la conversacion
    -- (formulario_dinamico, solicitud_proyecto, publicacion, etc.)
    referencia_tipo VARCHAR(40) NULL,
    referencia_id   INT NULL,

    -- Denormalizado: timestamp del ultimo mensaje (no borrador) para ordenar
    -- la bandeja sin JOIN ni subquery.
    ultimo_mensaje_at TIMESTAMP NULL DEFAULT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_empresa_estado (empresa_id, estado),
    INDEX idx_estado_ultimo  (estado, ultimo_mensaje_at DESC),
    INDEX idx_categoria      (categoria),
    INDEX idx_referencia     (referencia_tipo, referencia_id),

    CONSTRAINT fk_conv_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: mensajes_v2
-- Cada mensaje pertenece a una conversacion. Soporta borradores.
-- =====================================================
CREATE TABLE IF NOT EXISTS mensajes_v2 (
    id INT AUTO_INCREMENT PRIMARY KEY,

    conversacion_id INT NOT NULL,

    -- Quien envio. Si el usuario fue eliminado, conservamos el mensaje
    -- pero perdemos al autor (NULL).
    remitente_id   INT NULL,
    remitente_tipo ENUM('empresa', 'ministerio', 'sistema') NOT NULL,

    contenido MEDIUMTEXT NOT NULL,

    -- Borradores: el autosave guarda con es_borrador=1 hasta que el usuario
    -- presiona "Enviar". Solo el remitente ve sus propios borradores.
    es_borrador TINYINT(1) NOT NULL DEFAULT 0,

    -- Timestamp en que el receptor abrio el mensaje. NULL = no leido.
    -- Se actualiza automaticamente cuando se carga la conversacion.
    leido_at TIMESTAMP NULL DEFAULT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_conv_created     (conversacion_id, created_at),
    INDEX idx_conv_no_leidos   (conversacion_id, leido_at),
    INDEX idx_remitente        (remitente_id, es_borrador),

    CONSTRAINT fk_msg_conversacion
        FOREIGN KEY (conversacion_id) REFERENCES conversaciones(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_msg_remitente
        FOREIGN KEY (remitente_id) REFERENCES usuarios(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: adjuntos_mensajes
-- Hasta 25MB totales por mensaje (validacion en codigo, no en SQL).
-- =====================================================
CREATE TABLE IF NOT EXISTS adjuntos_mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,

    mensaje_id INT NOT NULL,

    archivo_url    VARCHAR(500) NOT NULL COMMENT 'Path local o URL de Cloudinary',
    archivo_nombre VARCHAR(255) NOT NULL COMMENT 'Nombre original al subir',
    archivo_tipo   VARCHAR(100) NOT NULL COMMENT 'MIME type validado',
    archivo_tamano INT UNSIGNED NOT NULL COMMENT 'Bytes',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_mensaje (mensaje_id),

    CONSTRAINT fk_adj_mensaje
        FOREIGN KEY (mensaje_id) REFERENCES mensajes_v2(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: comunicado_visto
-- Pivot para conversaciones de tipo "comunicado global" (empresa_id IS NULL).
-- Trackea por empresa el estado de lectura/archivado SIN duplicar mensajes.
-- =====================================================
CREATE TABLE IF NOT EXISTS comunicado_visto (
    conversacion_id INT NOT NULL,
    empresa_id      INT NOT NULL,

    leido_at  TIMESTAMP NULL DEFAULT NULL,
    archivado TINYINT(1) NOT NULL DEFAULT 0,

    PRIMARY KEY (conversacion_id, empresa_id),

    INDEX idx_empresa_leido (empresa_id, leido_at),

    CONSTRAINT fk_cv_conversacion
        FOREIGN KEY (conversacion_id) REFERENCES conversaciones(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_cv_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
