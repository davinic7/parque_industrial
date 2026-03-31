-- Error 1364: Field 'id' doesn't have a default value (al crear publicación)
-- La columna id debe ser AUTO_INCREMENT.
--
-- Paso 1 — Ver definición:
--   SHOW CREATE TABLE publicaciones;
--
-- Paso 2 — Si ya ves PRIMARY KEY (`id`) pero sin AUTO_INCREMENT:
ALTER TABLE publicaciones MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT;

-- Paso 3 — Si el Paso 2 falla porque no hay clave primaria en `id`, probá primero:
--   ALTER TABLE publicaciones ADD PRIMARY KEY (id);
--   ALTER TABLE publicaciones MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT;
-- (Solo si no hay filas duplicadas en id.)
--
-- Paso 4 — Si `id` no es la primera columna de un índice PRIMARY, revisá con un DBA:
--   a veces hace falta DROP INDEX / recrear la tabla desde parque_industrial.sql en entorno de prueba.
