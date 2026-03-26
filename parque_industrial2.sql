SET SESSION sql_require_primary_key = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- Limpieza absoluta: Borramos si existen como tabla O como vista
DROP VIEW IF EXISTS `v_empresas_completas`;
DROP TABLE IF EXISTS `v_empresas_completas`;
DROP VIEW IF EXISTS `v_estadisticas_generales`;
DROP TABLE IF EXISTS `v_estadisticas_generales`;

DROP TABLE IF EXISTS `archivos_publicacion`;
DROP TABLE IF EXISTS `banners`;
DROP TABLE IF EXISTS `configuracion_sitio`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `empresas`;

-- Creación de tablas
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('empresa','ministerio','admin') NOT NULL DEFAULT 'empresa',
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `usuarios` (`email`, `password`, `rol`) VALUES
('admin@parqueindustrial.gob.ar', '$2y$10$F1X4mmeqkPXr2hKi3L9qZOebdmXnlSU4Xe23AZsWjw8poj8H6lG8C', 'admin'),
('ministerio@catamarca.gob.ar', '$2y$10$F1X4mmeqkPXr2hKi3L9qZOebdmXnlSU4Xe23AZsWjw8poj8H6lG8C', 'ministerio');

CREATE TABLE `empresas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `rubro` varchar(100) DEFAULT NULL,
  `estado` enum('pendiente','activa','suspendida','inactiva') DEFAULT 'pendiente',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Creación de Vistas
CREATE VIEW `v_empresas_completas` AS 
SELECT id, usuario_id, nombre, rubro, estado FROM empresas;

CREATE VIEW `v_estadisticas_generales` AS 
SELECT 
(SELECT count(0) FROM empresas WHERE estado = 'activa') AS total_empresas_activas,
(SELECT count(0) FROM empresas) AS total_empresas;

COMMIT;