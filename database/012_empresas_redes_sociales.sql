-- Redes sociales en tabla empresas (perfil empresa / ministerio)
-- Error típico sin esta migración: Unknown column 'facebook' in 'field list'
-- MySQL 8.0.12+ / MariaDB 10.3.2+: IF NOT EXISTS en ADD COLUMN.
-- Si tu servidor no lo admite, ejecutá cada ADD por separado y omití el que diga "Duplicate column".

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS facebook VARCHAR(255) NULL;

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS instagram VARCHAR(255) NULL;

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS linkedin VARCHAR(255) NULL;
