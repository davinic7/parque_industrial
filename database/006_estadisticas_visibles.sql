INSERT INTO configuracion_sitio (clave, valor, tipo, grupo, descripcion) VALUES
('estadisticas_visibles', '["header","rubros_pie","rubros_barras","ubicacion","resumen","distribucion","info"]', 'json', 'estadisticas', 'IDs de bloques visibles en la página Estadísticas públicas')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);
