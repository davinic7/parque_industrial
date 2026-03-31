-- Migración 007: columnas latitud y longitud en empresas (mapa)
-- MySQL 5.7: sin IF NOT EXISTS. Si "Duplicate column", ya están creadas.

ALTER TABLE empresas ADD COLUMN latitud DECIMAL(10, 8) NULL;
ALTER TABLE empresas ADD COLUMN longitud DECIMAL(11, 8) NULL;
