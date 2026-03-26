-- Claves de configuración para la página Nosotros (editable por ministerio)
INSERT INTO configuracion_sitio (clave, valor, tipo, grupo, descripcion) VALUES
('nosotros_titulo', 'Parque Industrial de Catamarca', 'text', 'nosotros', 'Título principal'),
('nosotros_subtitulo', 'Impulsando el desarrollo productivo de la provincia', 'text', 'nosotros', 'Subtítulo'),
('nosotros_texto', 'El Parque Industrial de Catamarca es un polo productivo estratégico que reúne a empresas de diversos rubros, brindando infraestructura, servicios y un entorno favorable para el crecimiento industrial de la provincia.\n\nGestionado por el Ministerio de Industria, Comercio y Empleo, el parque ofrece a las empresas radicadas acceso a servicios esenciales como red eléctrica, gas natural, agua potable, conectividad y seguridad.\n\nNuestra misión es promover la inversión productiva, generar empleo genuino y contribuir al desarrollo sustentable de Catamarca.', 'textarea', 'nosotros', 'Texto sobre el parque'),
('nosotros_contacto_direccion', 'Parque Industrial de Catamarca\nSan Fernando del Valle de Catamarca\nCatamarca, Argentina', 'textarea', 'nosotros', 'Dirección'),
('nosotros_contacto_email', 'parqueindustrial@catamarca.gob.ar', 'text', 'nosotros', 'Email de contacto'),
('nosotros_contacto_telefono', '(0383) 4-XXXXXX', 'text', 'nosotros', 'Teléfono')
ON DUPLICATE KEY UPDATE grupo = VALUES(grupo), descripcion = VALUES(descripcion);
