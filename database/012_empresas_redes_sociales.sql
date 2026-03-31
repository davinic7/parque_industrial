-- Redes sociales en tabla empresas (perfil empresa / ministerio)
-- Error típico: Unknown column 'facebook' in 'field list'
-- Compatible con MySQL 5.7 (sin IF NOT EXISTS en ADD COLUMN).
-- Si alguna línea da "Duplicate column", esa columna ya está: seguí con la siguiente.

ALTER TABLE empresas ADD COLUMN facebook VARCHAR(255) NULL;
ALTER TABLE empresas ADD COLUMN instagram VARCHAR(255) NULL;
ALTER TABLE empresas ADD COLUMN linkedin VARCHAR(255) NULL;
