-- Contador de visitas al perfil público (empresa.php)
-- MySQL 5.7 y versiones sin "ADD COLUMN IF NOT EXISTS": solo ADD COLUMN.
-- Si aparece "Duplicate column name 'visitas'", la columna ya existe: no hace falta repetir.

ALTER TABLE empresas ADD COLUMN visitas INT NOT NULL DEFAULT 0;
