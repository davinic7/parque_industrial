-- Contador de visitas al perfil público (empresa.php)
-- Sin esta columna falla ministerio/exportar.php y otras consultas que usan e.visitas

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS visitas INT NOT NULL DEFAULT 0;
