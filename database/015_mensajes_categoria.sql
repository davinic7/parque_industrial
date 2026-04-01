-- Categoría opcional en mensajes empresa → ministerio (ej. consulta, trámite)
ALTER TABLE mensajes
    ADD COLUMN categoria VARCHAR(80) NULL DEFAULT NULL COMMENT 'Solo mensajes salientes empresa' AFTER asunto;
