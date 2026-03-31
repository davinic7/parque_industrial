-- Alinear ENUM tipo con el panel empresa / noticias públicas (valor 'empleados')
-- Ejecutar en producción si al guardar publicaciones falla con error de tipo ENUM.

ALTER TABLE publicaciones
    MODIFY COLUMN tipo ENUM('noticia', 'evento', 'promocion', 'comunicado', 'empleados')
    NOT NULL DEFAULT 'noticia';
