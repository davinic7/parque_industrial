-- Error al guardar publicación: SQLSTATE 1364 "Field 'id' doesn't have a default value"
-- Causa: la columna `id` de `publicaciones` no es AUTO_INCREMENT (INSERT no envía id).
--
-- 1) Ver definición actual:
--    SHOW CREATE TABLE publicaciones;
--
-- 2) Si `id` es PRIMARY KEY pero sin AUTO_INCREMENT, ejecutar:

ALTER TABLE publicaciones MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT;

-- 3) Si el paso 2 falla ("multiple primary key" o "Incorrect table definition"):
--    - Pedir a DBA o revisar si `id` es clave primaria.
--    - Si no hay PK:  ALTER TABLE publicaciones ADD PRIMARY KEY (id);
--    - Luego repetir el MODIFY de arriba.
