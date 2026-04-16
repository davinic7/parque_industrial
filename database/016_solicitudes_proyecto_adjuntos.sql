-- Migración 016: Agregar soporte de link externo, archivo y empresa_id a solicitudes_proyecto
ALTER TABLE solicitudes_proyecto
    ADD COLUMN empresa_id INT NULL AFTER id,
    ADD COLUMN link_externo VARCHAR(500) NULL AFTER resumen_proyecto,
    ADD COLUMN archivo_url VARCHAR(500) NULL AFTER link_externo,
    ADD COLUMN archivo_nombre VARCHAR(255) NULL AFTER archivo_url,
    ADD INDEX idx_empresa_id (empresa_id);
