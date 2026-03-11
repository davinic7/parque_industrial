-- =====================================================
-- BASE DE DATOS: PARQUE INDUSTRIAL DE CATAMARCA
-- Versión: 1.0
-- Fecha: 2024
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "-03:00";

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS `parque_industrial` 
DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `parque_industrial`;

-- =====================================================
-- TABLA: USUARIOS (Sistema de autenticación)
-- =====================================================
CREATE TABLE `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `rol` ENUM('visitante', 'empresa', 'ministerio') NOT NULL DEFAULT 'visitante',
    `activo` TINYINT(1) DEFAULT 1,
    `ultimo_acceso` DATETIME NULL,
    `token_recuperacion` VARCHAR(255) NULL,
    `token_expiracion` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_rol` (`rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: RUBROS (Categorías de empresas)
-- =====================================================
CREATE TABLE `rubros` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL UNIQUE,
    `descripcion` TEXT NULL,
    `icono` VARCHAR(50) NULL,
    `color` VARCHAR(7) DEFAULT '#007bff',
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: UBICACIONES (Zonas del parque)
-- =====================================================
CREATE TABLE `ubicaciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `descripcion` TEXT NULL,
    `latitud` DECIMAL(10, 8) NULL,
    `longitud` DECIMAL(11, 8) NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: EMPRESAS
-- =====================================================
CREATE TABLE `empresas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NULL,
    `nombre` VARCHAR(255) NOT NULL,
    `razon_social` VARCHAR(255) NULL,
    `cuit` VARCHAR(15) NULL,
    `rubro_id` INT NULL,
    `ubicacion_id` INT NULL,
    
    -- Datos de contacto
    `telefono` VARCHAR(100) NULL,
    `email` VARCHAR(255) NULL,
    `website` VARCHAR(255) NULL,
    `contacto_nombre` VARCHAR(255) NULL,
    `direccion` VARCHAR(255) NULL,
    
    -- Geolocalización
    `latitud` DECIMAL(10, 8) NULL,
    `longitud` DECIMAL(11, 8) NULL,
    
    -- Datos de la empresa
    `descripcion` TEXT NULL,
    `logo` VARCHAR(255) NULL,
    `imagen_portada` VARCHAR(255) NULL,
    
    -- Datos laborales
    `dotacion_total` INT DEFAULT 0,
    `empleados_masculinos` INT DEFAULT 0,
    `empleados_femeninos` INT DEFAULT 0,
    
    -- Datos operativos
    `capacidad_instalada` VARCHAR(255) NULL,
    `consumo_energia` DECIMAL(12,2) NULL COMMENT 'kWh mensual',
    `consumo_agua` DECIMAL(12,2) NULL COMMENT 'm3 mensual',
    `conexion_red_agua` TINYINT(1) DEFAULT 0,
    `pozo_agua` TINYINT(1) DEFAULT 0,
    
    -- Comercio exterior
    `exporta` TINYINT(1) DEFAULT 0,
    `productos_exporta` TEXT NULL,
    `importa` TINYINT(1) DEFAULT 0,
    `productos_importa` TEXT NULL,
    
    -- Datos ambientales
    `huella_carbono` DECIMAL(12,2) NULL COMMENT 'toneladas CO2/año',
    `certificaciones` TEXT NULL,
    
    -- Estado y visibilidad
    `estado` ENUM('pendiente', 'activa', 'suspendida', 'inactiva') DEFAULT 'pendiente',
    `perfil_completo` TINYINT(1) DEFAULT 0,
    `visible_publico` TINYINT(1) DEFAULT 1,
    `destacada` TINYINT(1) DEFAULT 0,
    
    -- Estadísticas
    `visitas` INT DEFAULT 0,
    `busquedas` INT DEFAULT 0,
    
    -- Auditoría
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`rubro_id`) REFERENCES `rubros`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`ubicacion_id`) REFERENCES `ubicaciones`(`id`) ON DELETE SET NULL,
    
    INDEX `idx_nombre` (`nombre`),
    INDEX `idx_cuit` (`cuit`),
    INDEX `idx_estado` (`estado`),
    INDEX `idx_rubro` (`rubro_id`),
    INDEX `idx_ubicacion` (`ubicacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: DATOS CRITICOS (Formulario declaración jurada)
-- =====================================================
CREATE TABLE `datos_criticos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT NOT NULL,
    `periodo` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    
    -- Producción
    `produccion_mensual` DECIMAL(15,2) NULL,
    `unidad_produccion` VARCHAR(50) NULL,
    `capacidad_utilizada` DECIMAL(5,2) NULL COMMENT 'Porcentaje',
    
    -- Ventas
    `ventas_mensual` DECIMAL(15,2) NULL,
    `ventas_exportacion` DECIMAL(15,2) NULL,
    
    -- Empleo
    `empleados_permanentes` INT NULL,
    `empleados_temporales` INT NULL,
    `nuevas_contrataciones` INT NULL,
    `desvinculaciones` INT NULL,
    
    -- Consumos
    `consumo_energia_kwh` DECIMAL(12,2) NULL,
    `consumo_agua_m3` DECIMAL(12,2) NULL,
    `consumo_gas_m3` DECIMAL(12,2) NULL,
    
    -- Inversiones
    `inversiones_realizadas` DECIMAL(15,2) NULL,
    `tipo_inversion` TEXT NULL,
    
    -- Ambiental
    `residuos_generados_kg` DECIMAL(12,2) NULL,
    `residuos_reciclados_kg` DECIMAL(12,2) NULL,
    `emisiones_co2` DECIMAL(12,2) NULL,
    
    -- Estado del formulario
    `estado` ENUM('borrador', 'enviado', 'aprobado', 'rechazado') DEFAULT 'borrador',
    `declaracion_jurada` TINYINT(1) DEFAULT 0,
    `observaciones_ministerio` TEXT NULL,
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`empresa_id`) REFERENCES `empresas`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_empresa_periodo` (`empresa_id`, `periodo`),
    INDEX `idx_periodo` (`periodo`),
    INDEX `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: PUBLICACIONES/NOTICIAS
-- =====================================================
CREATE TABLE `publicaciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT NULL COMMENT 'NULL si es del ministerio',
    `titulo` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `extracto` TEXT NULL,
    `contenido` TEXT NOT NULL,
    `imagen` VARCHAR(255) NULL,
    `tipo` ENUM('noticia', 'evento', 'promocion', 'comunicado') DEFAULT 'noticia',
    `estado` ENUM('borrador', 'pendiente', 'publicado', 'rechazado') DEFAULT 'borrador',
    `destacado` TINYINT(1) DEFAULT 0,
    `fecha_publicacion` DATETIME NULL,
    `fecha_evento` DATE NULL,
    `visitas` INT DEFAULT 0,
    `motivo_rechazo` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`empresa_id`) REFERENCES `empresas`(`id`) ON DELETE CASCADE,
    INDEX `idx_estado` (`estado`),
    INDEX `idx_tipo` (`tipo`),
    INDEX `idx_fecha` (`fecha_publicacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: MENSAJES INTERNOS
-- =====================================================
CREATE TABLE `mensajes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `remitente_id` INT NOT NULL,
    `destinatario_id` INT NULL COMMENT 'NULL = Ministerio',
    `empresa_id` INT NULL,
    `asunto` VARCHAR(255) NOT NULL,
    `contenido` TEXT NOT NULL,
    `archivo_adjunto` VARCHAR(255) NULL,
    `tipo` ENUM('consulta', 'solicitud', 'documento', 'notificacion') DEFAULT 'consulta',
    `leido` TINYINT(1) DEFAULT 0,
    `respondido` TINYINT(1) DEFAULT 0,
    `mensaje_padre_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`remitente_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`destinatario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`empresa_id`) REFERENCES `empresas`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`mensaje_padre_id`) REFERENCES `mensajes`(`id`) ON DELETE SET NULL,
    INDEX `idx_destinatario` (`destinatario_id`),
    INDEX `idx_leido` (`leido`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: DOCUMENTOS DE EMPRESAS
-- =====================================================
CREATE TABLE `documentos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT NOT NULL,
    `nombre` VARCHAR(255) NOT NULL,
    `archivo` VARCHAR(255) NOT NULL,
    `tipo` ENUM('habilitacion', 'certificado', 'balance', 'contrato', 'otro') DEFAULT 'otro',
    `descripcion` TEXT NULL,
    `publico` TINYINT(1) DEFAULT 0,
    `verificado` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`empresa_id`) REFERENCES `empresas`(`id`) ON DELETE CASCADE,
    INDEX `idx_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: CONFIGURACIÓN DEL SITIO
-- =====================================================
CREATE TABLE `configuracion` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `clave` VARCHAR(100) NOT NULL UNIQUE,
    `valor` TEXT NULL,
    `tipo` ENUM('texto', 'numero', 'booleano', 'json', 'html') DEFAULT 'texto',
    `descripcion` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: BANNERS/SLIDERS
-- =====================================================
CREATE TABLE `banners` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `titulo` VARCHAR(255) NULL,
    `subtitulo` VARCHAR(255) NULL,
    `imagen` VARCHAR(255) NOT NULL,
    `enlace` VARCHAR(255) NULL,
    `orden` INT DEFAULT 0,
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: LOG DE ACTIVIDADES
-- =====================================================
CREATE TABLE `log_actividades` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NULL,
    `empresa_id` INT NULL,
    `accion` VARCHAR(100) NOT NULL,
    `descripcion` TEXT NULL,
    `datos_anteriores` JSON NULL,
    `datos_nuevos` JSON NULL,
    `ip` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`empresa_id`) REFERENCES `empresas`(`id`) ON DELETE SET NULL,
    INDEX `idx_accion` (`accion`),
    INDEX `idx_fecha` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: VISITAS (Para mapa de calor)
-- =====================================================
CREATE TABLE `visitas_empresas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT NOT NULL,
    `ip` VARCHAR(45) NULL,
    `origen` ENUM('mapa', 'listado', 'busqueda', 'directo') DEFAULT 'directo',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`empresa_id`) REFERENCES `empresas`(`id`) ON DELETE CASCADE,
    INDEX `idx_empresa` (`empresa_id`),
    INDEX `idx_fecha` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: NOTIFICACIONES
-- =====================================================
CREATE TABLE `notificaciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL,
    `tipo` VARCHAR(50) NOT NULL,
    `titulo` VARCHAR(255) NOT NULL,
    `mensaje` TEXT NULL,
    `enlace` VARCHAR(255) NULL,
    `leida` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
    INDEX `idx_usuario_leida` (`usuario_id`, `leida`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: CAMPOS PERSONALIZADOS FORMULARIOS
-- =====================================================
CREATE TABLE `campos_formulario` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `formulario` ENUM('nueva_empresa', 'datos_criticos', 'perfil') NOT NULL,
    `nombre_campo` VARCHAR(100) NOT NULL,
    `etiqueta` VARCHAR(255) NOT NULL,
    `tipo` ENUM('texto', 'numero', 'fecha', 'select', 'textarea', 'checkbox', 'archivo') DEFAULT 'texto',
    `opciones` JSON NULL COMMENT 'Para campos select',
    `requerido` TINYINT(1) DEFAULT 0,
    `orden` INT DEFAULT 0,
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DATOS INICIALES
-- =====================================================

-- Insertar rubros desde el Excel
INSERT INTO `rubros` (`nombre`, `color`) VALUES
('TEXTIL', '#9C27B0'),
('CONSTRUCCION', '#FF9800'),
('METALURGICA', '#607D8B'),
('ALIMENTOS', '#4CAF50'),
('TRANSPORTE', '#2196F3'),
('RECICLADO', '#8BC34A'),
('HORMIGON', '#795548'),
('ELECTRODOMESTICOS', '#00BCD4'),
('MEDICAMENTOS', '#E91E63'),
('CALZADOS', '#673AB7'),
('FIBRA DE VIDRIO', '#3F51B5'),
('COMBUSTIBLES', '#F44336'),
('EQUIPOS INDUSTRIALES', '#009688'),
('PINTURA', '#FFEB3B'),
('MINERIA', '#9E9E9E'),
('MOTOCICLETAS', '#FF5722'),
('MEDIAS', '#CE93D8'),
('CONFECCION', '#BA68C8'),
('CLORO', '#00E676'),
('MAQUINAS INDUSTRIALES', '#546E7A'),
('AUTOPARTES', '#D32F2F'),
('FRIGORIFICO', '#1976D2'),
('LACTEOS', '#FFFFFF'),
('DULCES', '#FFB74D'),
('PERFORACIONES', '#5D4037'),
('DESCARTABLES', '#78909C'),
('TUBOS PLASTIFERRO', '#455A64');

-- Insertar ubicaciones
INSERT INTO `ubicaciones` (`nombre`, `latitud`, `longitud`) VALUES
('PI EL PANTANILLO', -28.4696, -65.7852),
('CAPITAL', -28.4696, -65.7795),
('VALLE VIEJO', -28.5167, -65.7167),
('RECREO', -29.2667, -65.0667);

-- Usuario administrador por defecto (password: admin123)
INSERT INTO `usuarios` (`email`, `password`, `rol`, `activo`) VALUES
('admin@parqueindustrial.gob.ar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ministerio', 1);

-- Configuración inicial del sitio
INSERT INTO `configuracion` (`clave`, `valor`, `tipo`, `descripcion`) VALUES
('sitio_nombre', 'Parque Industrial de Catamarca', 'texto', 'Nombre del sitio'),
('sitio_descripcion', 'Portal oficial del Parque Industrial de la Provincia de Catamarca', 'texto', 'Descripción del sitio'),
('sitio_email', 'contacto@parqueindustrial.gob.ar', 'texto', 'Email de contacto'),
('sitio_telefono', '(0383) 4123456', 'texto', 'Teléfono de contacto'),
('sitio_direccion', 'Parque Industrial El Pantanillo, Catamarca', 'texto', 'Dirección física'),
('mapa_lat_centro', '-28.4696', 'numero', 'Latitud centro del mapa'),
('mapa_lng_centro', '-65.7852', 'numero', 'Longitud centro del mapa'),
('mapa_zoom', '14', 'numero', 'Zoom inicial del mapa'),
('mostrar_estadisticas_publicas', '1', 'booleano', 'Mostrar estadísticas en página pública'),
('permitir_registro_empresas', '1', 'booleano', 'Permitir auto-registro de empresas');

-- =====================================================
-- TABLAS: FORMULARIOS DINAMICOS
-- =====================================================
CREATE TABLE formularios_dinamicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NULL,
    estado ENUM('borrador', 'publicado', 'archivado') NOT NULL DEFAULT 'borrador',
    creado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estado (estado),
    INDEX idx_creado_por (creado_por),
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE formulario_preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT NOT NULL,
    tipo ENUM('texto', 'textarea', 'numero', 'fecha', 'select', 'radio', 'checkbox', 'tabla') NOT NULL,
    etiqueta VARCHAR(255) NOT NULL,
    ayuda VARCHAR(255) NULL,
    requerido TINYINT(1) DEFAULT 0,
    opciones LONGTEXT NULL,
    orden INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_formulario (formulario_id),
    FOREIGN KEY (formulario_id) REFERENCES formularios_dinamicos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE formulario_respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT NOT NULL,
    empresa_id INT NOT NULL,
    usuario_id INT NULL,
    estado ENUM('borrador', 'enviado') NOT NULL DEFAULT 'borrador',
    respuestas LONGTEXT NOT NULL,
    ip VARCHAR(45) NULL,
    enviado_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_formulario_empresa (formulario_id, empresa_id),
    INDEX idx_estado (estado),
    FOREIGN KEY (formulario_id) REFERENCES formularios_dinamicos(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
