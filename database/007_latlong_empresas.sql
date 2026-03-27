-- Migración 007: Agregar columnas latitud y longitud a tabla empresas si no existen
-- Ejecutar en Aiven si la tabla empresas no tiene estas columnas

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS latitud DECIMAL(10, 8) NULL AFTER direccion,
    ADD COLUMN IF NOT EXISTS longitud DECIMAL(11, 8) NULL AFTER latitud;
