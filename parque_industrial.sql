-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 11-02-2026 a las 23:32:59
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `parque_industrial`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `archivos_publicacion`
--

CREATE TABLE `archivos_publicacion` (
  `id` int(11) NOT NULL,
  `publicacion_id` int(11) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `tipo_mime` varchar(100) DEFAULT NULL,
  `tamano` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `subtitulo` varchar(255) DEFAULT NULL,
  `imagen` varchar(255) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `orden` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_sitio`
--

CREATE TABLE `configuracion_sitio` (
  `id` int(11) NOT NULL,
  `clave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `tipo` enum('text','textarea','number','boolean','json','image') DEFAULT 'text',
  `grupo` varchar(50) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion_sitio`
--

INSERT INTO `configuracion_sitio` (`id`, `clave`, `valor`, `tipo`, `grupo`, `descripcion`, `updated_at`) VALUES
(1, 'sitio_nombre', 'Parque Industrial de Catamarca', 'text', 'general', 'Nombre del sitio', '2025-12-17 04:42:12'),
(2, 'sitio_descripcion', 'Portal del Parque Industrial de la Provincia de Catamarca', 'textarea', 'general', 'Descripción del sitio', '2025-12-17 04:42:12'),
(3, 'sitio_email', 'contacto@parqueindustrial.gob.ar', 'text', 'contacto', 'Email de contacto', '2025-12-17 04:42:12'),
(4, 'sitio_telefono', '(0383) 4123456', 'text', 'contacto', 'Teléfono de contacto', '2025-12-17 04:42:12'),
(5, 'sitio_direccion', 'San Fernando del Valle de Catamarca, Argentina', 'text', 'contacto', 'Dirección física', '2025-12-17 04:42:12'),
(6, 'mapa_lat_centro', '-28.4696', 'text', 'mapa', 'Latitud centro del mapa', '2025-12-17 04:42:12'),
(7, 'mapa_lng_centro', '-65.7795', 'text', 'mapa', 'Longitud centro del mapa', '2025-12-17 04:42:12'),
(8, 'mapa_zoom_inicial', '12', 'number', 'mapa', 'Zoom inicial del mapa', '2025-12-17 04:42:12'),
(9, 'redes_facebook', 'https://facebook.com/parqueindustrialcatamarca', 'text', 'redes', 'Facebook', '2025-12-17 04:42:12'),
(10, 'redes_instagram', 'https://instagram.com/parqueindustrialcatamarca', 'text', 'redes', 'Instagram', '2025-12-17 04:42:12'),
(11, 'redes_twitter', '', 'text', 'redes', 'Twitter/X', '2025-12-17 04:42:12'),
(12, 'texto_sobre_nosotros', 'El Parque Industrial de Catamarca es un polo de desarrollo...', 'textarea', 'contenido', 'Texto sobre nosotros', '2025-12-17 04:42:12'),
(13, 'mostrar_estadisticas_publicas', '1', 'boolean', 'privacidad', 'Mostrar estadísticas al público', '2025-12-17 04:42:12');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `datos_empresa`
--

CREATE TABLE `datos_empresa` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `periodo` varchar(20) NOT NULL COMMENT 'Ej: 2025-Q1, 2025-Q2',
  `dotacion_total` int(11) DEFAULT 0,
  `empleados_masculinos` int(11) DEFAULT 0,
  `empleados_femeninos` int(11) DEFAULT 0,
  `empleados_otros` int(11) DEFAULT 0,
  `capacidad_instalada` varchar(255) DEFAULT NULL COMMENT 'Descripción de capacidad',
  `porcentaje_capacidad_uso` decimal(5,2) DEFAULT NULL COMMENT 'Porcentaje de uso',
  `produccion_mensual` varchar(255) DEFAULT NULL,
  `unidad_produccion` varchar(50) DEFAULT NULL,
  `consumo_energia` decimal(12,2) DEFAULT NULL COMMENT 'kWh mensuales',
  `consumo_agua` decimal(12,2) DEFAULT NULL COMMENT 'm3 mensuales',
  `consumo_gas` decimal(12,2) DEFAULT NULL COMMENT 'm3 mensuales',
  `conexion_red_agua` tinyint(1) DEFAULT 0,
  `pozo_agua` tinyint(1) DEFAULT 0,
  `conexion_gas_natural` tinyint(1) DEFAULT 0,
  `conexion_cloacas` tinyint(1) DEFAULT 0,
  `exporta` tinyint(1) DEFAULT 0,
  `productos_exporta` text DEFAULT NULL,
  `paises_exporta` varchar(255) DEFAULT NULL,
  `monto_exportaciones` decimal(15,2) DEFAULT NULL,
  `importa` tinyint(1) DEFAULT 0,
  `productos_importa` text DEFAULT NULL,
  `paises_importa` varchar(255) DEFAULT NULL,
  `monto_importaciones` decimal(15,2) DEFAULT NULL,
  `emisiones_co2` decimal(12,4) DEFAULT NULL COMMENT 'Toneladas CO2 equivalente',
  `fuente_emision_principal` varchar(100) DEFAULT NULL,
  `inversion_anual` decimal(15,2) DEFAULT NULL,
  `inversion_maquinaria` decimal(15,2) DEFAULT NULL,
  `inversion_infraestructura` decimal(15,2) DEFAULT NULL,
  `rango_facturacion` enum('micro','pequeña','mediana','grande') DEFAULT NULL,
  `certificaciones` text DEFAULT NULL COMMENT 'ISO, etc. separadas por coma',
  `estado` enum('borrador','enviado','aprobado','rechazado') DEFAULT 'borrador',
  `declaracion_jurada` tinyint(1) DEFAULT 0,
  `fecha_declaracion` datetime DEFAULT NULL,
  `ip_declaracion` varchar(45) DEFAULT NULL,
  `observaciones_ministerio` text DEFAULT NULL,
  `revisado_por` int(11) DEFAULT NULL,
  `fecha_revision` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas`
--

CREATE TABLE `empresas` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `razon_social` varchar(255) DEFAULT NULL,
  `cuit` varchar(20) DEFAULT NULL,
  `rubro` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `ubicacion` varchar(100) DEFAULT NULL COMMENT 'PI El Pantanillo, Capital, etc',
  `direccion` varchar(255) DEFAULT NULL,
  `latitud` decimal(10,8) DEFAULT NULL,
  `longitud` decimal(11,8) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email_contacto` varchar(255) DEFAULT NULL,
  `contacto_nombre` varchar(255) DEFAULT NULL,
  `sitio_web` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `imagen_portada` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','activa','suspendida','inactiva') DEFAULT 'pendiente',
  `perfil_completo` tinyint(1) DEFAULT 0,
  `verificada` tinyint(1) DEFAULT 0,
  `visitas` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `empresas`
--

INSERT INTO `empresas` (`id`, `usuario_id`, `nombre`, `razon_social`, `cuit`, `rubro`, `descripcion`, `ubicacion`, `direccion`, `latitud`, `longitud`, `telefono`, `email_contacto`, `contacto_nombre`, `sitio_web`, `facebook`, `instagram`, `linkedin`, `logo`, `imagen_portada`, `estado`, `perfil_completo`, `verificada`, `visitas`, `created_at`, `updated_at`) VALUES
(1, 3, 'Empresa Demo S.R.L.', NULL, NULL, 'Textil', NULL, 'PI El Pantanillo', NULL, NULL, NULL, '3834123456', NULL, 'Juan Pérez', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 10:57:02', '2025-12-17 10:57:02'),
(2, 102, 'ALGODONERA DEL VALLE S.A.', NULL, '30-61172472-8', 'TEXTIL', NULL, 'PI EL PANTANILLO', NULL, -28.46760000, -65.77750000, '3834205107', NULL, 'Ing. Carlos Pinetta', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(3, 103, 'ARIDOS DEL VALLE S.R.L.', NULL, '30-71422466-9', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.46560000, -65.77550000, '3834582815', NULL, 'Eduardo Seleme', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(4, 104, 'ATC ANTONIO TADEO CABRERA', NULL, '20-17373148-6', 'METALÚRGICA', NULL, 'PI EL PANTANILLO', NULL, -28.46360000, -65.77350000, '3834637350', NULL, 'Antonio Cabrera', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(5, 105, 'ASHA COSNTRUCCIONES S.R.L.', NULL, '30-70883415-3', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.46160000, -65.77150000, '3834637350', NULL, 'Antonio Cabrera', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(6, 106, 'BG CONS SRL', NULL, '30-69513929-9', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45960000, -65.76950000, '3834995711', NULL, 'Ricardo Vega', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(7, 107, 'INGES S.R.L', NULL, '30-66812296-1', 'MAQUINARIA INDUSTRIAL', NULL, 'PI EL PANTANILLO', NULL, -28.45760000, -65.76750000, '3834265913', NULL, 'Arturo Castellon', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(8, 108, 'BLOCK S.R.L.', NULL, '30-70747402-1', 'HORMIGÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45560000, -65.76550000, '3834431684 / 3834543355', NULL, 'Felipe Loss', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(9, 109, 'BOTAS CATAMARCA S.A.', NULL, '30-71426884-4', 'CALZADOS', NULL, 'PI EL PANTANILLO', NULL, -28.45360000, -65.76350000, '91140861355 / 3834405390', NULL, 'Gustavo Muia / Miguel Muzla', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(10, 110, 'JL UNIFORMES S.R.L.', NULL, '30-71583594-7', 'TEXTIL', NULL, 'PI EL PANTANILLO', NULL, -28.45160000, -65.76150000, '91140861355 / 3834405390', NULL, 'Gustavo Muia / Miguel Muzla', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(11, 111, 'CONSTRUCCIONES METALICAS S.A.S.', NULL, '30-71594837-7', 'METALÚRGICA', NULL, 'PI EL PANTANILLO', NULL, -28.46860000, -65.77950000, '3834058944', NULL, 'Adrián Morano', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(12, 112, 'CORRALON LAVALLE S.R.L.', NULL, '30-71540258-7', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.46660000, -65.77750000, '3834406384 / 3834516885', NULL, 'Martin Fikinger / Luis Santucho', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(13, 113, 'GRUPO TN PLATEX - COTECA S.A.', NULL, '30-57024569-0', 'TEXTIL', NULL, 'PI EL PANTANILLO', NULL, -28.46460000, -65.77550000, '3804497001', NULL, 'Ing Jorge TN', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(14, 114, 'GRUPO TN PLATEX - TEXTILES INDUSTRIALES BIG BAGS', NULL, '30-57024569-0', 'TEXTIL', NULL, 'PI EL PANTANILLO', NULL, -28.46260000, -65.77350000, '91123406535', NULL, 'Federico Flores', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(15, 115, 'COLORES ANDINOS S.R.L.', NULL, '33-71682289-9', 'QUÍMICA', NULL, 'PI EL PANTANILLO', NULL, -28.46060000, -65.77150000, '3804364747', NULL, 'Mustafa Yoma', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(16, 116, 'DANTE RICARDO PORCEL DE PERALTA', NULL, '20-18785480-7', 'METALÚRGICA', NULL, 'PI EL PANTANILLO', NULL, -28.45860000, -65.76950000, '3834319265', NULL, 'Ricardo Peralta', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(17, 117, 'CANBIR S.R.L.', NULL, '30-71514234-8', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45660000, -65.76750000, '3834379770', NULL, 'Daniel Birgi', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(18, 118, 'CVA S.H.', NULL, '30-70893329-1', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45460000, -65.76550000, '3834612244', NULL, 'Carlos Vergara', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(19, 119, 'EDIFICAT S.A.', NULL, '30-70956589-9', 'METALÚRGICA', NULL, 'PI EL PANTANILLO', NULL, -28.45260000, -65.76350000, '3834240527', NULL, 'Arq. Luis Cantarell', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(20, 120, 'EXPRESO DIEMAR DEL NOROESTE S.R.L.', NULL, '30-71362630-5', 'TRANSPORTE', NULL, 'PI EL PANTANILLO', NULL, -28.45060000, -65.76150000, '91133697910', NULL, 'Diego Colona', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(21, 121, 'FRIPP S.A.', NULL, '20-95357110-3', 'PLÁSTICOS', NULL, 'PI EL PANTANILLO', NULL, -28.46760000, -65.77950000, '3834240527', NULL, 'Arq. Luis Cantarell', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(22, 101, 'ABC CONSTRUCCIONES S.R.L.', NULL, NULL, 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.46960000, -65.77950000, '3834781396', NULL, 'César Bursi', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(23, 102, 'ALGODONERA DEL VALLE S.A.', NULL, '30-61172472-8', 'TEXTIL', NULL, 'PI EL PANTANILLO', NULL, -28.46760000, -65.77750000, '3834205107', NULL, 'Ing. Carlos Pinetta', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(24, 103, 'ARIDOS DEL VALLE S.R.L.', NULL, '30-71422466-9', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.46560000, -65.77550000, '3834582815', NULL, 'Eduardo Seleme', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(25, 104, 'ATC ANTONIO TADEO CABRERA', NULL, '20-17373148-6', 'METALÚRGICA', NULL, 'PI EL PANTANILLO', NULL, -28.46360000, -65.77350000, '3834637350', NULL, 'Antonio Cabrera', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(26, 105, 'ASHA COSNTRUCCIONES S.R.L.', NULL, '30-70883415-3', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.46160000, -65.77150000, '3834637350', NULL, 'Antonio Cabrera', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(27, 106, 'BG CONS SRL', NULL, '30-69513929-9', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45960000, -65.76950000, '3834995711', NULL, 'Ricardo Vega', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(28, 107, 'INGES S.R.L', NULL, '30-66812296-1', 'MAQUINARIA INDUSTRIAL', NULL, 'PI EL PANTANILLO', NULL, -28.45760000, -65.76750000, '3834265913', NULL, 'Arturo Castellon', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(29, 108, 'BLOCK S.R.L.', NULL, '30-70747402-1', 'HORMIGÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45560000, -65.76550000, '3834431684 / 3834543355', NULL, 'Felipe Loss', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(30, 109, 'BOTAS CATAMARCA S.A.', NULL, '30-71426884-4', 'CALZADOS', NULL, 'PI EL PANTANILLO', NULL, -28.45360000, -65.76350000, '91140861355 / 3834405390', NULL, 'Gustavo Muia / Miguel Muzla', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(31, 110, 'JL UNIFORMES S.R.L.', NULL, '30-71583594-7', 'TEXTIL', NULL, 'PI EL PANTANILLO', NULL, -28.45160000, -65.76150000, '91140861355 / 3834405390', NULL, 'Gustavo Muia / Miguel Muzla', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(32, 111, 'CONSTRUCCIONES METALICAS S.A.S.', NULL, '30-71594837-7', 'METALÚRGICA', NULL, 'PI EL PANTANILLO', NULL, -28.46860000, -65.77950000, '3834058944', NULL, 'Adrián Morano', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(33, 112, 'CORRALON LAVALLE S.R.L.', NULL, '30-71540258-7', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.46660000, -65.77750000, '3834406384 / 3834516885', NULL, 'Martin Fikinger / Luis Santucho', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(34, 113, 'GRUPO TN PLATEX - COTECA S.A.', NULL, '30-57024569-0', 'TEXTIL', NULL, 'PI EL PANTANILLO', NULL, -28.46460000, -65.77550000, '3804497001', NULL, 'Ing Jorge TN', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(35, 113, 'GRUPO TN PLATEX - TEXTILES INDUSTRIALES BIG BAGS', NULL, '30-57024569-0', 'TEXTIL', NULL, 'PI EL PANTANILLO', NULL, -28.46260000, -65.77350000, '91123406535', NULL, 'Federico Flores', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(36, 115, 'COLORES ANDINOS S.R.L.', NULL, '33-71682289-9', 'QUÍMICA', NULL, 'PI EL PANTANILLO', NULL, -28.46060000, -65.77150000, '3804364747', NULL, 'Mustafa Yoma', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(37, 116, 'DANTE RICARDO PORCEL DE PERALTA', NULL, '20-18785480-7', 'METALÚRGICA', NULL, 'PI EL PANTANILLO', NULL, -28.45860000, -65.76950000, '3834319265', NULL, 'Ricardo Peralta', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(38, 117, 'CANBIR S.R.L.', NULL, '30-71514234-8', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45660000, -65.76750000, '3834379770', NULL, 'Daniel Birgi', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(39, 118, 'CVA S.H.', NULL, '30-70893329-1', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45460000, -65.76550000, '3834612244', NULL, 'Carlos Vergara', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(40, 119, 'EDIFICAT S.A.', NULL, '30-70956589-9', 'METALÚRGICA', NULL, 'PI EL PANTANILLO', NULL, -28.45260000, -65.76350000, '3834240527', NULL, 'Arq. Luis Cantarell', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(41, 120, 'EXPRESO DIEMAR DEL NOROESTE S.R.L.', NULL, '30-71362630-5', 'TRANSPORTE', NULL, 'PI EL PANTANILLO', NULL, -28.45060000, -65.76150000, '91133697910', NULL, 'Diego Colona', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(42, 121, 'FRIPP S.A.', NULL, '20-95357110-3', 'PLÁSTICOS', NULL, 'PI EL PANTANILLO', NULL, -28.46760000, -65.77950000, '3834240527', NULL, 'Arq. Luis Cantarell', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(43, 122, 'FMF MINING & SERVICES S.R.L.', NULL, '30-71567341-6', 'FIBRA DE VIDRIO', NULL, 'PI EL PANTANILLO', NULL, -28.46560000, -65.77750000, '3813360920', NULL, 'Denis Brizuela', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(44, 145, 'CARLOS ALBERTO LARCHER - FRIGORIFICO TRASMONTAÑA', NULL, '23-17150346-9', 'ALIMENTOS', NULL, 'PI EL PANTANILLO', NULL, -28.46360000, -65.77550000, '3834210176 / 3834422199', NULL, 'Carlos Larcher', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(45, 146, 'GADOR S.A.', NULL, '30-50098718-5', 'MEDICAMENTOS', NULL, 'PI EL PANTANILLO', NULL, -28.46160000, -65.77350000, '3834355888', NULL, 'Ernesto Martínez ', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(46, 147, 'ARPA S.A.', NULL, '30-71254436-4', 'HORMIGÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45960000, -65.77150000, '3834538524', NULL, 'José Gaso', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(47, 148, 'RODRIGUEZ FRANCO LUIS - GASFRAN', NULL, '20-31126390-1', 'TRANSPORTE', NULL, 'PI EL PANTANILLO', NULL, -28.45760000, -65.76950000, '3834600413', NULL, 'Franco Rodriguez', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(48, 149, 'GREENCAT S.R.L.', NULL, '30-71683438-3', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45560000, -65.76750000, '3834371006', NULL, 'Felipe Loss', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(49, 150, 'GRUPO CINCO ENERGY S.R.L.', NULL, '30-71652780-4', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45360000, -65.76550000, '38344692007', NULL, 'Laura BRANDAN', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(50, 151, 'GUSTAVO EMILIO CORDOBA - RESINDUS', NULL, '20-28540449-6', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45160000, -65.76350000, '3834309464', NULL, 'Gustavo Cordoba', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(51, 152, 'HIDROPER S.R.L.', NULL, '30-69516034-4', 'MINERÍA', NULL, 'PI EL PANTANILLO', NULL, -28.44960000, -65.76150000, '3834188258', NULL, 'Administración', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(52, 153, 'INSTALACIONES CATAMARCA S.R.L.', NULL, '30-71049233-2', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.46660000, -65.77950000, '3834558751 / 3834594209', NULL, 'Carlos Moreno / Martin Moreno', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(53, 154, 'LA CIUDADELA S.R.L.', NULL, '30-71127890-3', 'ALIMENTOS', NULL, 'PI EL PANTANILLO', NULL, -28.46460000, -65.77750000, '3814683184', NULL, 'Roberto Farias Menéndez', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(54, 155, 'DISTRIBUIDORA LA MENDOCINA SRL', NULL, '30-64775586-7', 'PLÁSTICOS', NULL, 'PI EL PANTANILLO', NULL, -28.46260000, -65.77550000, '3834227530', NULL, 'Jonathan de Arco', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(55, 156, 'TRANSPORTE LA SEVILLANITA S.R.L.', NULL, '30-57993900-8', 'TRANSPORTE', NULL, 'PI EL PANTANILLO', NULL, -28.46060000, -65.77350000, '3834255179', NULL, 'Marcelo Mansilla', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(56, 157, 'LONGVIE S.A.', NULL, '30-50083378-1', 'ELECTRODOMÉSTICOS', NULL, 'PI EL PANTANILLO', NULL, -28.45860000, -65.77150000, '3834315506', NULL, 'Ing. Oscar Schonhals', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(57, 158, 'MARIMARI MODULAR S.A.', NULL, '30-71140924-2', 'METALÚRGICA', NULL, 'PI EL PANTANILLO', NULL, -28.45660000, -65.76950000, '3834464277', NULL, 'Luis Nour (gerente adm)', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(58, 159, 'MATIAS AMENGUAL S.R.L.', NULL, '30-66813329-7', 'COMBUSTIBLES', NULL, 'PI EL PANTANILLO', NULL, -28.45460000, -65.76750000, '3834626994', NULL, 'Luis Nieva', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(59, 160, 'MBA INGENIERIA S.R.L.', NULL, '30-71101286-5', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45260000, -65.76550000, '3834273518', NULL, 'Antonio Mazuco', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(60, 161, 'AGROLACTEA DEL NOA S.R.L. - LACTEOS MICKY', NULL, '30-71597786-5', 'ALIMENTOS', NULL, 'PI EL PANTANILLO', NULL, -28.45060000, -65.76350000, '3834261678', NULL, 'Ing. Daiana Martin Lazo', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(61, 162, 'MINERA PETREA S.R.L.', NULL, '30-70804894-8', 'MINERÍA', NULL, 'PI EL PANTANILLO', NULL, -28.44860000, -65.76150000, '3834464200', NULL, 'Liliana D´agostini', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(62, 163, 'NATILLA S.A.', NULL, '30-69516041-7', 'ALIMENTOS', NULL, 'PI EL PANTANILLO', NULL, -28.46560000, -65.77950000, '3834680649', NULL, 'Hugo Natilla', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(63, 164, 'HL CATAMARCA S.A.', NULL, '30-71678659-1', 'ELECTRODOMÉSTICOS', NULL, 'PI EL PANTANILLO', NULL, -28.46360000, -65.77750000, '1160283877', NULL, 'Miguel Ferrari', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(64, 165, 'NORTEXTIL S.A.', NULL, '30-60181938-0', 'TEXTIL', NULL, 'PI EL PANTANILLO', NULL, -28.46160000, -65.77550000, '3834683285 / 3834641783', NULL, 'Mario Ahumada / Marcelo Avellaneda', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(65, 166, 'ONE CONSTRUCCIONES S.R.L.', NULL, '30-71500232-5', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45960000, -65.77350000, '383-4599627', NULL, 'Verona Stefanoff', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(66, 167, 'PUMA CATAMARCA S.A.', NULL, '30-62400257-8', 'MOTOCICLETAS', NULL, 'PI EL PANTANILLO', NULL, -28.45760000, -65.77150000, '3834509565 / 3834509564', NULL, 'Felipe Franco / Daniel Franco', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(67, 168, 'ROCCIA S.A.', NULL, '30-66808682-5', 'CALZADOS', NULL, 'PI EL PANTANILLO', NULL, -28.45560000, -65.76950000, '3834407407 / 3834791530', NULL, 'Gonzalo Sevillano / Veronica', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(68, 169, 'ROCOTEX S.R.L.', NULL, '30-70891986-8', 'TEXTIL', NULL, 'PI EL PANTANILLO', NULL, -28.45360000, -65.76750000, '91144448215 / 91130418189', NULL, 'Marcelo Derwill / Osvaldo Olguin', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(69, 170, 'ROUTER S.A. MERCOMAT', NULL, '30-71048216-7', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45160000, -65.76550000, '3834519017', NULL, 'Silvio Rodriguez', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(70, 171, 'ROQUE ASTUDILLO - RECICLADO', NULL, '20-22828939-7', 'RECICLADO', NULL, 'PI EL PANTANILLO', NULL, -28.44960000, -65.76350000, '3834320067', NULL, 'Roque Astudillo', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(71, 172, 'DEPOSITO MARTINEZ S.R.L.', NULL, '30-71819308-3', 'RECICLADO', NULL, 'PI EL PANTANILLO', NULL, -28.44760000, -65.76150000, '3468644474', NULL, 'Dorian Campos', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(72, 173, 'LUIS LAZA - RECUPERADORA', NULL, '20-16118117-0', 'RECICLADO', NULL, 'PI EL PANTANILLO', NULL, -28.46460000, -65.77950000, '3834620951', NULL, 'Luis Laza', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(73, 174, 'SMBCONS S.R.L.', NULL, '30-71757261-7', 'CONSTRUCCIÓN', NULL, 'PI EL PANTANILLO', NULL, -28.46260000, -65.77750000, '3834291311', NULL, 'Marcelo Billincanta', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(74, 175, 'TEVINOR S.A.', NULL, '30-60717023-8', 'TEXTIL', NULL, 'PI EL PANTANILLO', NULL, -28.46060000, -65.77550000, '3834950201', NULL, 'Walter Nieva', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(75, 176, 'TEXTIL DE LOS ANDES S.A.', NULL, '30-71077122-3', 'TEXTIL', NULL, 'PI EL PANTANILLO', NULL, -28.45860000, -65.77350000, '3834972678', NULL, 'Mariano Botella Gte', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(76, 177, ' SAN JOSE OBRERO S.R.L.', NULL, '30-70752484-3', 'TRANSPORTE', NULL, 'PI EL PANTANILLO', NULL, -28.45660000, -65.77150000, '3834435940 / 3834432238', NULL, 'Jose Guillermo Arce ', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(77, 178, 'TRANSPORTE URINE SRL', NULL, '30-71587718-6', 'MEDICAMENTOS', NULL, 'PI EL PANTANILLO', NULL, -28.45460000, -65.76950000, '3834290072', NULL, 'Matias Soria', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(78, 179, 'VIALNORT S.R.L.', NULL, '30-71056495-3', 'HORMIGÓN', NULL, 'PI EL PANTANILLO', NULL, -28.45260000, -65.76750000, '3834305526', NULL, 'Jorge Aparicio', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(79, 180, 'IMEFF ELECTRODOMESTICOS S.R.L.', NULL, '30-71624612-0', 'ELECTRODOMÉSTICOS', NULL, 'PI EL PANTANILLO', NULL, -28.45060000, -65.76550000, '91160283878', NULL, 'David Santillan', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(80, 181, 'PARADIGMA DEL NORTE S.R.L.', NULL, '30-71174844-6', 'RECICLADO', NULL, 'PI EL PANTANILLO', NULL, -28.44860000, -65.76350000, '3834603995', NULL, 'Carlos P.', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(81, 182, 'VCC S.R.L.', NULL, '30-71550337-5', 'TEXTIL', NULL, 'PI EL PANTANILLO', NULL, -28.44660000, -65.76150000, '91122389967', NULL, 'Rubén Calderone', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(82, 183, 'LIZCLOR S.R.L.', NULL, '30-71882023-1', 'QUÍMICA', NULL, 'PI EL PANTANILLO', NULL, -28.46360000, -65.77950000, '3834594209', NULL, 'Ing. Martin Moreno', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(83, 184, 'TECMIC S.R.L.', NULL, '30-71069166-1', 'MAQUINARIA INDUSTRIAL', NULL, 'PI EL PANTANILLO', NULL, -28.46160000, -65.77750000, '3512303939', NULL, 'Carlos Lurgo', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(84, 185, 'WAYKU S.R.L.', NULL, '30-71564029-1', 'AGROINDUSTRIA', NULL, 'PI EL PANTANILLO', NULL, -28.45960000, -65.77550000, '3834047161', NULL, 'Julio Aibar', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(85, 186, 'RECUPERO CATAMARCA S.R.L.', NULL, '30-71888436-1', 'AUTOPARTES', NULL, 'PI EL PANTANILLO', NULL, -28.45760000, -65.77350000, '3834685192', NULL, 'Fernando P.', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(86, 187, 'MAPE S.R.L.', NULL, '30-71477464-2', 'FRIGORÍFICO', NULL, 'PI EL PANTANILLO', NULL, -28.45560000, -65.77150000, '3482539455', NULL, 'Matias Sartor', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(87, 188, 'TECNOFIBRA  S.A.S.', NULL, '30-71869894-0', 'FIBRA DE VIDRIO', NULL, 'PI EL PANTANILLO', NULL, -28.45360000, -65.76950000, '3815109238', NULL, 'Marco Peñaloza', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(88, 189, 'CATAMARCA TRANSPORTE S.A.U.', NULL, '33-71858290-0', 'TRANSPORTE', NULL, 'PI EL PANTANILLO', NULL, -28.45160000, -65.76750000, '3834026819', NULL, 'Eduardo Andrada', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(89, 190, 'SERVICENTRO LAVALLE S.R.L.', NULL, '30-70751138-5', 'COMBUSTIBLES', NULL, 'PI EL PANTANILLO', NULL, -28.44960000, -65.76550000, '3854880012', NULL, 'Emilio alderete', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(90, 191, 'CONFECAT S.A.', NULL, '30-59791915-4', 'TEXTIL', NULL, 'CAPITAL', NULL, -28.46960000, -65.78520000, '91144064606 / 3834507380', NULL, 'Carlos Muia / Rosana Costilla - RR. HH.', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(91, 192, 'RA INTERTRADING S.A.', NULL, '30-65824065-6', 'TEXTIL', NULL, 'CAPITAL', NULL, -28.46760000, -65.78320000, '3816013286 / 1134279620', NULL, 'Daniel Gonzalez RR.HH. / Adolfo Atienza - Gerente', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(92, 193, 'INDUMENTARIA CATAMARCA S.A.', NULL, '30-71720041-8', 'TEXTIL', NULL, 'CAPITAL', NULL, -28.46560000, -65.78120000, '3834394240 / 3834625861', NULL, 'Julio Serrano - Gerente / Silvana Mercado', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:07', '2025-12-17 11:53:07'),
(93, 194, 'MACATA S.A.', NULL, '30-60715256-6', 'TEXTIL', NULL, 'VALLE VIEJO', NULL, -28.39170000, -65.70950000, '91144007774', NULL, 'Alejandra - Administracion', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:07', '2025-12-17 11:53:07'),
(94, 195, 'COOPERATIVA DE TAMBEROS CATAMARCA LTDA - COTALI', NULL, '30-52760787-2', 'LÁCTEOS', NULL, 'VALLE VIEJO', NULL, -28.38970000, -65.70750000, '3834926123', NULL, 'Joaquin Reyes', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:07', '2025-12-17 11:53:07'),
(95, 196, 'REGIONALES DEL NORTE S.R.L. - CUESTA DEL PORTEZUELO', NULL, '30-66035642-4', 'DULCES', NULL, 'VALLE VIEJO', NULL, -28.38770000, -65.70550000, '3834658120', NULL, 'Juan Pablo CP', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:07', '2025-12-17 11:53:07'),
(96, 197, 'GRUPO FIBRAN SUR S.A.', NULL, '30-71472681-8', 'TEXTIL', NULL, 'VALLE VIEJO', NULL, -28.38570000, -65.70350000, '3834427700 / 3834537922', NULL, 'Jorge Rodriguez/Julio Herrera', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:07', '2025-12-17 11:53:07'),
(97, 198, 'TEJICA S.A.', NULL, '30-60037675-2', 'TEXTIL', NULL, 'RECREO', NULL, -29.28330000, -65.06670000, '91164044596', NULL, 'Leonardo Peretta', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:07', '2025-12-17 11:53:07'),
(98, 199, 'ARCOR S.A.', NULL, '30-50279317-5', 'ALIMENTOS', NULL, 'RECREO', NULL, -29.28130000, -65.06470000, '3834298159', NULL, 'Pamela Espinosa', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 3, '2025-12-17 11:53:07', '2025-12-17 12:16:22'),
(99, 200, 'SABRI S.A.', NULL, '30-61234013-3', 'TEXTIL', NULL, 'RECREO', NULL, -29.27930000, -65.06270000, '9115603111', NULL, 'Carlos Brieva', NULL, NULL, NULL, NULL, NULL, NULL, 'activa', 0, 0, 0, '2025-12-17 11:53:07', '2025-12-17 11:53:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `formularios_config`
--

CREATE TABLE `formularios_config` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `campos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Estructura JSON de campos' CHECK (json_valid(`campos`)),
  `activo` tinyint(1) DEFAULT 1,
  `obligatorio` tinyint(1) DEFAULT 0,
  `fecha_limite` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_actividad`
--

CREATE TABLE `log_actividad` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `empresa_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `datos_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_anteriores`)),
  `datos_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_nuevos`)),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `log_actividad`
--

INSERT INTO `log_actividad` (`id`, `usuario_id`, `empresa_id`, `accion`, `tabla_afectada`, `registro_id`, `datos_anteriores`, `datos_nuevos`, `ip`, `user_agent`, `created_at`) VALUES
(1, 3, 1, 'login', 'usuarios', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 11:04:48'),
(2, 3, 1, 'logout', 'usuarios', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 11:07:27'),
(3, 2, NULL, 'login', 'usuarios', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-17 11:07:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes`
--

CREATE TABLE `mensajes` (
  `id` int(11) NOT NULL,
  `remitente_id` int(11) NOT NULL,
  `destinatario_id` int(11) DEFAULT NULL COMMENT 'NULL = mensaje al ministerio',
  `empresa_id` int(11) DEFAULT NULL,
  `asunto` varchar(255) NOT NULL,
  `contenido` text NOT NULL,
  `adjuntos` text DEFAULT NULL,
  `leido` tinyint(1) DEFAULT 0,
  `fecha_lectura` datetime DEFAULT NULL,
  `archivado` tinyint(1) DEFAULT 0,
  `mensaje_padre_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL COMMENT 'perfil_editado, formulario_enviado, etc',
  `titulo` varchar(255) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `datos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Datos adicionales en JSON' CHECK (json_valid(`datos`)),
  `leida` tinyint(1) DEFAULT 0,
  `fecha_lectura` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `publicaciones`
--

CREATE TABLE `publicaciones` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) DEFAULT NULL COMMENT 'NULL si es del ministerio',
  `usuario_id` int(11) NOT NULL,
  `tipo` enum('noticia','evento','promocion','comunicado') DEFAULT 'noticia',
  `titulo` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `extracto` text DEFAULT NULL,
  `contenido` longtext DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `publicado` tinyint(1) DEFAULT 0,
  `destacado` tinyint(1) DEFAULT 0,
  `mostrar_en_inicio` tinyint(1) DEFAULT 0,
  `estado` enum('borrador','pendiente','aprobado','rechazado') DEFAULT 'borrador',
  `aprobado_por` int(11) DEFAULT NULL,
  `fecha_aprobacion` datetime DEFAULT NULL,
  `motivo_rechazo` text DEFAULT NULL,
  `fecha_publicacion` datetime DEFAULT NULL,
  `fecha_expiracion` datetime DEFAULT NULL,
  `visitas` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas_formulario`
--

CREATE TABLE `respuestas_formulario` (
  `id` int(11) NOT NULL,
  `formulario_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `respuestas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`respuestas`)),
  `estado` enum('borrador','enviado','aprobado','rechazado') DEFAULT 'borrador',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rubros`
--

CREATE TABLE `rubros` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(50) DEFAULT NULL,
  `color` varchar(7) DEFAULT NULL COMMENT 'Color hex para gráficos',
  `activo` tinyint(1) DEFAULT 1,
  `orden` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `rubros`
--

INSERT INTO `rubros` (`id`, `nombre`, `descripcion`, `icono`, `color`, `activo`, `orden`, `created_at`) VALUES
(1, 'Textil', NULL, NULL, '#3498db', 1, 1, '2025-12-17 04:42:11'),
(2, 'Construcción', NULL, NULL, '#e74c3c', 1, 2, '2025-12-17 04:42:11'),
(3, 'Metalúrgica', NULL, NULL, '#95a5a6', 1, 3, '2025-12-17 04:42:11'),
(4, 'Alimentos', NULL, NULL, '#27ae60', 1, 4, '2025-12-17 04:42:11'),
(5, 'Transporte', NULL, NULL, '#f39c12', 1, 5, '2025-12-17 04:42:11'),
(6, 'Reciclado', NULL, NULL, '#2ecc71', 1, 6, '2025-12-17 04:42:11'),
(7, 'Hormigón', NULL, NULL, '#7f8c8d', 1, 7, '2025-12-17 04:42:11'),
(8, 'Electrodomésticos', NULL, NULL, '#9b59b6', 1, 8, '2025-12-17 04:42:11'),
(9, 'Medicamentos', NULL, NULL, '#1abc9c', 1, 9, '2025-12-17 04:42:11'),
(10, 'Calzados', NULL, NULL, '#e67e22', 1, 10, '2025-12-17 04:42:11'),
(11, 'Fibra de Vidrio', NULL, NULL, '#34495e', 1, 11, '2025-12-17 04:42:11'),
(12, 'Combustibles', NULL, NULL, '#c0392b', 1, 12, '2025-12-17 04:42:11'),
(13, 'Minería', NULL, NULL, '#8e44ad', 1, 13, '2025-12-17 04:42:11'),
(14, 'Química', NULL, NULL, '#16a085', 1, 14, '2025-12-17 04:42:11'),
(15, 'Maquinaria Industrial', NULL, NULL, '#2c3e50', 1, 15, '2025-12-17 04:42:11'),
(16, 'Autopartes', NULL, NULL, '#d35400', 1, 16, '2025-12-17 04:42:11'),
(17, 'Frigorífico', NULL, NULL, '#2980b9', 1, 17, '2025-12-17 04:42:11'),
(18, 'Lácteos', NULL, NULL, '#f1c40f', 1, 18, '2025-12-17 04:42:11'),
(19, 'Otros', NULL, NULL, '#bdc3c7', 1, 99, '2025-12-17 04:42:11'),
(20, 'PLÁSTICOS', NULL, NULL, '#666666', 1, 20, '2025-12-17 11:50:08'),
(21, 'QUÍMICA', NULL, NULL, '#666666', 1, 21, '2025-12-17 11:50:08'),
(22, 'AGROINDUSTRIA', NULL, NULL, '#666666', 1, 22, '2025-12-17 11:50:08'),
(23, 'MOTOCICLETAS', NULL, NULL, '#666666', 1, 23, '2025-12-17 11:50:08'),
(24, 'FIBRA DE VIDRIO', NULL, NULL, '#666666', 1, 24, '2025-12-17 11:50:08'),
(25, 'DULCES', NULL, NULL, '#666666', 1, 25, '2025-12-17 11:50:08'),
(26, 'PLÁSTICOS', NULL, NULL, '#666666', 1, 20, '2025-12-17 11:51:14'),
(27, 'QUÍMICA', NULL, NULL, '#666666', 1, 21, '2025-12-17 11:51:14'),
(28, 'AGROINDUSTRIA', NULL, NULL, '#666666', 1, 22, '2025-12-17 11:51:14'),
(29, 'MOTOCICLETAS', NULL, NULL, '#666666', 1, 23, '2025-12-17 11:51:14'),
(30, 'FIBRA DE VIDRIO', NULL, NULL, '#666666', 1, 24, '2025-12-17 11:51:14'),
(31, 'DULCES', NULL, NULL, '#666666', 1, 25, '2025-12-17 11:51:14'),
(32, 'PLÁSTICOS', NULL, NULL, '#666666', 1, 20, '2025-12-17 11:51:29'),
(33, 'QUÍMICA', NULL, NULL, '#666666', 1, 21, '2025-12-17 11:51:29'),
(34, 'AGROINDUSTRIA', NULL, NULL, '#666666', 1, 22, '2025-12-17 11:51:29'),
(35, 'MOTOCICLETAS', NULL, NULL, '#666666', 1, 23, '2025-12-17 11:51:29'),
(36, 'FIBRA DE VIDRIO', NULL, NULL, '#666666', 1, 24, '2025-12-17 11:51:29'),
(37, 'DULCES', NULL, NULL, '#666666', 1, 25, '2025-12-17 11:51:29'),
(38, 'PLÁSTICOS', NULL, NULL, '#666666', 1, 20, '2025-12-17 11:53:06'),
(39, 'QUÍMICA', NULL, NULL, '#666666', 1, 21, '2025-12-17 11:53:06'),
(40, 'AGROINDUSTRIA', NULL, NULL, '#666666', 1, 22, '2025-12-17 11:53:06'),
(41, 'MOTOCICLETAS', NULL, NULL, '#666666', 1, 23, '2025-12-17 11:53:06'),
(42, 'FIBRA DE VIDRIO', NULL, NULL, '#666666', 1, 24, '2025-12-17 11:53:06'),
(43, 'DULCES', NULL, NULL, '#666666', 1, 25, '2025-12-17 11:53:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ubicaciones`
--

CREATE TABLE `ubicaciones` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `latitud_centro` decimal(10,8) DEFAULT NULL,
  `longitud_centro` decimal(11,8) DEFAULT NULL,
  `poligono_geojson` text DEFAULT NULL COMMENT 'GeoJSON del polígono del área',
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ubicaciones`
--

INSERT INTO `ubicaciones` (`id`, `nombre`, `descripcion`, `latitud_centro`, `longitud_centro`, `poligono_geojson`, `activo`, `created_at`) VALUES
(1, 'PI El Pantanillo', NULL, -28.46960000, -65.77950000, NULL, 1, '2025-12-17 04:42:11'),
(2, 'Capital', NULL, -28.46960000, -65.78520000, NULL, 1, '2025-12-17 04:42:11'),
(3, 'Valle Viejo', NULL, -28.39170000, -65.70950000, NULL, 1, '2025-12-17 04:42:11'),
(4, 'Recreo', NULL, -29.28330000, -65.06670000, NULL, 1, '2025-12-17 04:42:11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('empresa','ministerio','admin') NOT NULL DEFAULT 'empresa',
  `activo` tinyint(1) DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `token_recuperacion` varchar(255) DEFAULT NULL,
  `token_expira` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `email`, `password`, `rol`, `activo`, `ultimo_acceso`, `token_recuperacion`, `token_expira`, `created_at`, `updated_at`) VALUES
(1, 'admin@parqueindustrial.gob.ar', '$2y$10$F1X4mmeqkPXr2hKi3L9qZOebdmXnlSU4Xe23AZsWjw8poj8H6lG8C', 'admin', 1, NULL, NULL, NULL, '2025-12-17 10:57:02', '2025-12-17 11:04:44'),
(2, 'ministerio@catamarca.gob.ar', '$2y$10$F1X4mmeqkPXr2hKi3L9qZOebdmXnlSU4Xe23AZsWjw8poj8H6lG8C', 'ministerio', 1, '2025-12-17 08:07:52', NULL, NULL, '2025-12-17 10:57:02', '2025-12-17 11:07:52'),
(3, 'empresa@demo.com', '$2y$10$F1X4mmeqkPXr2hKi3L9qZOebdmXnlSU4Xe23AZsWjw8poj8H6lG8C', 'empresa', 1, '2025-12-17 08:04:48', NULL, NULL, '2025-12-17 10:57:02', '2025-12-17 11:04:48'),
(101, 'empresa1@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(102, '30611724728@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(103, '30714224669@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(104, '20173731486@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(105, '30708834153@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(106, '30695139299@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(107, '30668122961@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(108, '30707474021@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(109, '30714268844@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(110, '30715835947@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(111, '30715948377@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(112, '30715402587@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(113, '30570245690@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(115, '33716822899@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(116, '20187854807@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(117, '30715142348@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(118, '30708933291@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(119, '30709565899@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(120, '30713626305@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(121, '20953571103@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(122, '30715673416@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:50:08', '2025-12-17 11:50:08'),
(145, '23171503469@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(146, '30500987185@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(147, '30712544364@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(148, '20311263901@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(149, '30716834383@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(150, '30716527804@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(151, '20285404496@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(152, '30695160344@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(153, '30710492332@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(154, '30711278903@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(155, '30647755867@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(156, '30579939008@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(157, '30500833781@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(158, '30711409242@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(159, '30668133297@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(160, '30711012865@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(161, '30715977865@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(162, '30708048948@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(163, '30695160417@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(164, '30716786591@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(165, '30601819380@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(166, '30715002325@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(167, '30624002578@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(168, '30668086825@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(169, '30708919868@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(170, '30710482167@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(171, '20228289397@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(172, '30718193083@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(173, '20161181170@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(174, '30717572617@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(175, '30607170238@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(176, '30710771223@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(177, '30707524843@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(178, '30715877186@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(179, '30710564953@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(180, '30716246120@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(181, '30711748446@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(182, '30715503375@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(183, '30718820231@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(184, '30710691661@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(185, '30715640291@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(186, '30718884361@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(187, '30714774642@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(188, '30718698940@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(189, '33718582900@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(190, '30707511385@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(191, '30597919154@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(192, '30658240656@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:06', '2025-12-17 11:53:06'),
(193, '30717200418@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:07', '2025-12-17 11:53:07'),
(194, '30607152566@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:07', '2025-12-17 11:53:07'),
(195, '30527607872@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:07', '2025-12-17 11:53:07'),
(196, '30660356424@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:07', '2025-12-17 11:53:07'),
(197, '30714726818@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:07', '2025-12-17 11:53:07'),
(198, '30600376752@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:07', '2025-12-17 11:53:07'),
(199, '30502793175@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:07', '2025-12-17 11:53:07'),
(200, '30612340133@parqueindustrial.com', '$2y$10$8K1p/a0FPmODZj4Xq2qJXeGZp3T1O5nY7rLmJ6kWvHcN4sD9e1uXi', 'empresa', 1, NULL, NULL, NULL, '2025-12-17 11:53:07', '2025-12-17 11:53:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `visitas_empresa`
--

CREATE TABLE `visitas_empresa` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `referer` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `visitas_empresa`
--

INSERT INTO `visitas_empresa` (`id`, `empresa_id`, `ip`, `user_agent`, `referer`, `created_at`) VALUES
(1, 98, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', NULL, '2025-12-17 12:15:48'),
(2, 98, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', NULL, '2025-12-17 12:16:10'),
(3, 98, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', NULL, '2025-12-17 12:16:22');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_empresas_completas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_empresas_completas` (
`id` int(11)
,`usuario_id` int(11)
,`nombre` varchar(255)
,`razon_social` varchar(255)
,`cuit` varchar(20)
,`rubro` varchar(100)
,`descripcion` text
,`ubicacion` varchar(100)
,`direccion` varchar(255)
,`latitud` decimal(10,8)
,`longitud` decimal(11,8)
,`telefono` varchar(50)
,`email_contacto` varchar(255)
,`contacto_nombre` varchar(255)
,`sitio_web` varchar(255)
,`facebook` varchar(255)
,`instagram` varchar(255)
,`linkedin` varchar(255)
,`logo` varchar(255)
,`imagen_portada` varchar(255)
,`estado` enum('pendiente','activa','suspendida','inactiva')
,`perfil_completo` tinyint(1)
,`verificada` tinyint(1)
,`visitas` int(11)
,`created_at` timestamp
,`updated_at` timestamp
,`dotacion_total` int(11)
,`empleados_masculinos` int(11)
,`empleados_femeninos` int(11)
,`consumo_energia` decimal(12,2)
,`consumo_agua` decimal(12,2)
,`exporta` tinyint(1)
,`importa` tinyint(1)
,`emisiones_co2` decimal(12,4)
,`ultimo_periodo` varchar(20)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_estadisticas_generales`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_estadisticas_generales` (
`total_empresas_activas` bigint(21)
,`total_empresas` bigint(21)
,`total_empleados` decimal(32,0)
,`total_rubros` bigint(21)
,`total_publicaciones` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_empresas_completas`
--
DROP TABLE IF EXISTS `v_empresas_completas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_empresas_completas`  AS SELECT `e`.`id` AS `id`, `e`.`usuario_id` AS `usuario_id`, `e`.`nombre` AS `nombre`, `e`.`razon_social` AS `razon_social`, `e`.`cuit` AS `cuit`, `e`.`rubro` AS `rubro`, `e`.`descripcion` AS `descripcion`, `e`.`ubicacion` AS `ubicacion`, `e`.`direccion` AS `direccion`, `e`.`latitud` AS `latitud`, `e`.`longitud` AS `longitud`, `e`.`telefono` AS `telefono`, `e`.`email_contacto` AS `email_contacto`, `e`.`contacto_nombre` AS `contacto_nombre`, `e`.`sitio_web` AS `sitio_web`, `e`.`facebook` AS `facebook`, `e`.`instagram` AS `instagram`, `e`.`linkedin` AS `linkedin`, `e`.`logo` AS `logo`, `e`.`imagen_portada` AS `imagen_portada`, `e`.`estado` AS `estado`, `e`.`perfil_completo` AS `perfil_completo`, `e`.`verificada` AS `verificada`, `e`.`visitas` AS `visitas`, `e`.`created_at` AS `created_at`, `e`.`updated_at` AS `updated_at`, `de`.`dotacion_total` AS `dotacion_total`, `de`.`empleados_masculinos` AS `empleados_masculinos`, `de`.`empleados_femeninos` AS `empleados_femeninos`, `de`.`consumo_energia` AS `consumo_energia`, `de`.`consumo_agua` AS `consumo_agua`, `de`.`exporta` AS `exporta`, `de`.`importa` AS `importa`, `de`.`emisiones_co2` AS `emisiones_co2`, `de`.`periodo` AS `ultimo_periodo` FROM (`empresas` `e` left join `datos_empresa` `de` on(`e`.`id` = `de`.`empresa_id` and `de`.`periodo` = (select max(`datos_empresa`.`periodo`) from `datos_empresa` where `datos_empresa`.`empresa_id` = `e`.`id`))) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_estadisticas_generales`
--
DROP TABLE IF EXISTS `v_estadisticas_generales`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_estadisticas_generales`  AS SELECT (select count(0) from `empresas` where `empresas`.`estado` = 'activa') AS `total_empresas_activas`, (select count(0) from `empresas`) AS `total_empresas`, (select coalesce(sum(`de`.`dotacion_total`),0) from (`datos_empresa` `de` join `empresas` `e` on(`de`.`empresa_id` = `e`.`id`)) where `e`.`estado` = 'activa' and `de`.`periodo` = (select max(`datos_empresa`.`periodo`) from `datos_empresa` where `datos_empresa`.`empresa_id` = `de`.`empresa_id`)) AS `total_empleados`, (select count(distinct `empresas`.`rubro`) from `empresas` where `empresas`.`estado` = 'activa') AS `total_rubros`, (select count(0) from `publicaciones` where `publicaciones`.`publicado` = 1 and `publicaciones`.`estado` = 'aprobado') AS `total_publicaciones` ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `archivos_publicacion`
--
ALTER TABLE `archivos_publicacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `publicacion_id` (`publicacion_id`);

--
-- Indices de la tabla `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `configuracion_sitio`
--
ALTER TABLE `configuracion_sitio`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave` (`clave`);

--
-- Indices de la tabla `datos_empresa`
--
ALTER TABLE `datos_empresa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_empresa_periodo` (`empresa_id`,`periodo`),
  ADD KEY `revisado_por` (`revisado_por`),
  ADD KEY `idx_periodo` (`periodo`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indices de la tabla `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_rubro` (`rubro`),
  ADD KEY `idx_ubicacion` (`ubicacion`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_visitas` (`visitas`);
ALTER TABLE `empresas` ADD FULLTEXT KEY `idx_busqueda` (`nombre`,`razon_social`,`descripcion`,`rubro`);

--
-- Indices de la tabla `formularios_config`
--
ALTER TABLE `formularios_config`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `log_actividad`
--
ALTER TABLE `log_actividad`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_empresa` (`empresa_id`),
  ADD KEY `idx_fecha` (`created_at`);

--
-- Indices de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `remitente_id` (`remitente_id`),
  ADD KEY `empresa_id` (`empresa_id`),
  ADD KEY `mensaje_padre_id` (`mensaje_padre_id`),
  ADD KEY `idx_destinatario` (`destinatario_id`),
  ADD KEY `idx_leido` (`leido`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario_leida` (`usuario_id`,`leida`);

--
-- Indices de la tabla `publicaciones`
--
ALTER TABLE `publicaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `empresa_id` (`empresa_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `aprobado_por` (`aprobado_por`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_publicado` (`publicado`),
  ADD KEY `idx_fecha` (`fecha_publicacion`);

--
-- Indices de la tabla `respuestas_formulario`
--
ALTER TABLE `respuestas_formulario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_form_empresa` (`formulario_id`,`empresa_id`),
  ADD KEY `empresa_id` (`empresa_id`);

--
-- Indices de la tabla `rubros`
--
ALTER TABLE `rubros`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `ubicaciones`
--
ALTER TABLE `ubicaciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_rol` (`rol`);

--
-- Indices de la tabla `visitas_empresa`
--
ALTER TABLE `visitas_empresa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_empresa_fecha` (`empresa_id`,`created_at`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `archivos_publicacion`
--
ALTER TABLE `archivos_publicacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `configuracion_sitio`
--
ALTER TABLE `configuracion_sitio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `datos_empresa`
--
ALTER TABLE `datos_empresa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT de la tabla `formularios_config`
--
ALTER TABLE `formularios_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `log_actividad`
--
ALTER TABLE `log_actividad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `publicaciones`
--
ALTER TABLE `publicaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `respuestas_formulario`
--
ALTER TABLE `respuestas_formulario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rubros`
--
ALTER TABLE `rubros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT de la tabla `ubicaciones`
--
ALTER TABLE `ubicaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT de la tabla `visitas_empresa`
--
ALTER TABLE `visitas_empresa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `archivos_publicacion`
--
ALTER TABLE `archivos_publicacion`
  ADD CONSTRAINT `archivos_publicacion_ibfk_1` FOREIGN KEY (`publicacion_id`) REFERENCES `publicaciones` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `datos_empresa`
--
ALTER TABLE `datos_empresa`
  ADD CONSTRAINT `datos_empresa_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `datos_empresa_ibfk_2` FOREIGN KEY (`revisado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `empresas`
--
ALTER TABLE `empresas`
  ADD CONSTRAINT `empresas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD CONSTRAINT `mensajes_ibfk_1` FOREIGN KEY (`remitente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mensajes_ibfk_2` FOREIGN KEY (`destinatario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `mensajes_ibfk_3` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `mensajes_ibfk_4` FOREIGN KEY (`mensaje_padre_id`) REFERENCES `mensajes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `publicaciones`
--
ALTER TABLE `publicaciones`
  ADD CONSTRAINT `publicaciones_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `publicaciones_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `publicaciones_ibfk_3` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `respuestas_formulario`
--
ALTER TABLE `respuestas_formulario`
  ADD CONSTRAINT `respuestas_formulario_ibfk_1` FOREIGN KEY (`formulario_id`) REFERENCES `formularios_config` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `respuestas_formulario_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `visitas_empresa`
--
ALTER TABLE `visitas_empresa`
  ADD CONSTRAINT `visitas_empresa_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Tablas para formularios dinamicos
--
CREATE TABLE `formularios_dinamicos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('borrador','publicado','archivado') NOT NULL DEFAULT 'borrador',
  `creado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_creado_por` (`creado_por`),
  CONSTRAINT `formularios_dinamicos_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `formulario_preguntas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `formulario_id` int(11) NOT NULL,
  `tipo` enum('texto','textarea','numero','fecha','select','radio','checkbox','tabla') NOT NULL,
  `etiqueta` varchar(255) NOT NULL,
  `ayuda` varchar(255) DEFAULT NULL,
  `requerido` tinyint(1) DEFAULT 0,
  `opciones` longtext DEFAULT NULL,
  `orden` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_formulario` (`formulario_id`),
  CONSTRAINT `formulario_preguntas_ibfk_1` FOREIGN KEY (`formulario_id`) REFERENCES `formularios_dinamicos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `formulario_respuestas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `formulario_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `estado` enum('borrador','enviado') NOT NULL DEFAULT 'borrador',
  `respuestas` longtext NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `enviado_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_formulario_empresa` (`formulario_id`,`empresa_id`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `formulario_respuestas_ibfk_1` FOREIGN KEY (`formulario_id`) REFERENCES `formularios_dinamicos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `formulario_respuestas_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `formulario_respuestas_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
