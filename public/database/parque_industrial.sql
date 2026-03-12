-- =====================================================
-- BASE DE DATOS: PARQUE INDUSTRIAL DE CATAMARCA
-- Versión: 1.0
-- Fecha: Diciembre 2025
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "-03:00";

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS parque_industrial 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE parque_industrial;

-- =====================================================
-- TABLA: usuarios
-- Gestiona todos los usuarios del sistema (empresas, ministerio)
-- =====================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('empresa', 'ministerio', 'admin') NOT NULL DEFAULT 'empresa',
    activo TINYINT(1) DEFAULT 1,
    ultimo_acceso DATETIME NULL,
    token_recuperacion VARCHAR(255) NULL,
    token_expira DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: empresas
-- Información completa de las empresas del parque
-- =====================================================
CREATE TABLE empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    
    -- Datos básicos
    nombre VARCHAR(255) NOT NULL,
    razon_social VARCHAR(255) NULL,
    cuit VARCHAR(20) NULL,
    rubro VARCHAR(100) NULL,
    descripcion TEXT NULL,
    
    -- Ubicación
    ubicacion VARCHAR(100) NULL COMMENT 'PI El Pantanillo, Capital, etc',
    direccion VARCHAR(255) NULL,
    latitud DECIMAL(10, 8) NULL,
    longitud DECIMAL(11, 8) NULL,
    
    -- Contacto
    telefono VARCHAR(50) NULL,
    email_contacto VARCHAR(255) NULL,
    contacto_nombre VARCHAR(255) NULL,
    sitio_web VARCHAR(255) NULL,
    
    -- Redes sociales
    facebook VARCHAR(255) NULL,
    instagram VARCHAR(255) NULL,
    linkedin VARCHAR(255) NULL,
    
    -- Logo e imagen
    logo VARCHAR(255) NULL,
    imagen_portada VARCHAR(255) NULL,
    
    -- Estado
    estado ENUM('pendiente', 'activa', 'suspendida', 'inactiva') DEFAULT 'pendiente',
    perfil_completo TINYINT(1) DEFAULT 0,
    verificada TINYINT(1) DEFAULT 0,
    
    -- Estadísticas de visitas
    visitas INT DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_rubro (rubro),
    INDEX idx_ubicacion (ubicacion),
    INDEX idx_estado (estado),
    INDEX idx_visitas (visitas),
    FULLTEXT idx_busqueda (nombre, razon_social, descripcion, rubro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: datos_empresa (Formulario crítico - Declaración jurada)
-- Datos detallados que solicita el ministerio
-- =====================================================
CREATE TABLE datos_empresa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    periodo VARCHAR(20) NOT NULL COMMENT 'Ej: 2025-Q1, 2025-Q2',
    
    -- Dotación de personal
    dotacion_total INT DEFAULT 0,
    empleados_masculinos INT DEFAULT 0,
    empleados_femeninos INT DEFAULT 0,
    empleados_otros INT DEFAULT 0,
    
    -- Capacidad y producción
    capacidad_instalada VARCHAR(255) NULL COMMENT 'Descripción de capacidad',
    porcentaje_capacidad_uso DECIMAL(5,2) NULL COMMENT 'Porcentaje de uso',
    produccion_mensual VARCHAR(255) NULL,
    unidad_produccion VARCHAR(50) NULL,
    
    -- Consumos
    consumo_energia DECIMAL(12,2) NULL COMMENT 'kWh mensuales',
    consumo_agua DECIMAL(12,2) NULL COMMENT 'm3 mensuales',
    consumo_gas DECIMAL(12,2) NULL COMMENT 'm3 mensuales',
    
    -- Servicios
    conexion_red_agua TINYINT(1) DEFAULT 0,
    pozo_agua TINYINT(1) DEFAULT 0,
    conexion_gas_natural TINYINT(1) DEFAULT 0,
    conexion_cloacas TINYINT(1) DEFAULT 0,
    
    -- Comercio exterior
    exporta TINYINT(1) DEFAULT 0,
    productos_exporta TEXT NULL,
    paises_exporta VARCHAR(255) NULL,
    monto_exportaciones DECIMAL(15,2) NULL,
    
    importa TINYINT(1) DEFAULT 0,
    productos_importa TEXT NULL,
    paises_importa VARCHAR(255) NULL,
    monto_importaciones DECIMAL(15,2) NULL,
    
    -- Huella de carbono
    emisiones_co2 DECIMAL(12,4) NULL COMMENT 'Toneladas CO2 equivalente',
    fuente_emision_principal VARCHAR(100) NULL,
    
    -- Inversiones
    inversion_anual DECIMAL(15,2) NULL,
    inversion_maquinaria DECIMAL(15,2) NULL,
    inversion_infraestructura DECIMAL(15,2) NULL,
    
    -- Facturación (opcional, sensible)
    rango_facturacion ENUM('micro', 'pequeña', 'mediana', 'grande') NULL,
    
    -- Certificaciones
    certificaciones TEXT NULL COMMENT 'ISO, etc. separadas por coma',
    
    -- Estado del formulario
    estado ENUM('borrador', 'enviado', 'aprobado', 'rechazado') DEFAULT 'borrador',
    declaracion_jurada TINYINT(1) DEFAULT 0,
    fecha_declaracion DATETIME NULL,
    ip_declaracion VARCHAR(45) NULL,
    
    -- Observaciones del ministerio
    observaciones_ministerio TEXT NULL,
    revisado_por INT NULL,
    fecha_revision DATETIME NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (revisado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_empresa_periodo (empresa_id, periodo),
    INDEX idx_periodo (periodo),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: rubros
-- Catálogo de rubros industriales
-- =====================================================
CREATE TABLE rubros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    icono VARCHAR(50) NULL,
    color VARCHAR(7) NULL COMMENT 'Color hex para gráficos',
    activo TINYINT(1) DEFAULT 1,
    orden INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: ubicaciones
-- Catálogo de ubicaciones/zonas del parque
-- =====================================================
CREATE TABLE ubicaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    latitud_centro DECIMAL(10, 8) NULL,
    longitud_centro DECIMAL(11, 8) NULL,
    poligono_geojson TEXT NULL COMMENT 'GeoJSON del polígono del área',
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: publicaciones
-- Noticias y contenido publicado
-- =====================================================
CREATE TABLE publicaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NULL COMMENT 'NULL si es del ministerio',
    usuario_id INT NOT NULL,
    
    tipo ENUM('noticia', 'evento', 'promocion', 'comunicado') DEFAULT 'noticia',
    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    extracto TEXT NULL,
    contenido LONGTEXT NULL,
    imagen VARCHAR(255) NULL,
    
    -- Visibilidad
    publicado TINYINT(1) DEFAULT 0,
    destacado TINYINT(1) DEFAULT 0,
    mostrar_en_inicio TINYINT(1) DEFAULT 0,
    
    -- Aprobación
    estado ENUM('borrador', 'pendiente', 'aprobado', 'rechazado') DEFAULT 'borrador',
    aprobado_por INT NULL,
    fecha_aprobacion DATETIME NULL,
    motivo_rechazo TEXT NULL,
    
    -- Fechas
    fecha_publicacion DATETIME NULL,
    fecha_expiracion DATETIME NULL,
    
    -- Estadísticas
    visitas INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_estado (estado),
    INDEX idx_publicado (publicado),
    INDEX idx_fecha (fecha_publicacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: archivos_publicacion
-- Archivos adjuntos a publicaciones
-- =====================================================
CREATE TABLE archivos_publicacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    publicacion_id INT NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(100) NULL,
    tamano INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (publicacion_id) REFERENCES publicaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: mensajes
-- Sistema de mensajería interna
-- =====================================================
CREATE TABLE mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remitente_id INT NOT NULL,
    destinatario_id INT NULL COMMENT 'NULL = mensaje al ministerio',
    empresa_id INT NULL,
    
    asunto VARCHAR(255) NOT NULL,
    contenido TEXT NOT NULL,
    
    -- Archivos adjuntos (JSON array de rutas)
    adjuntos TEXT NULL,
    
    -- Estado
    leido TINYINT(1) DEFAULT 0,
    fecha_lectura DATETIME NULL,
    archivado TINYINT(1) DEFAULT 0,
    
    -- Para hilos de conversación
    mensaje_padre_id INT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (remitente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (destinatario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL,
    FOREIGN KEY (mensaje_padre_id) REFERENCES mensajes(id) ON DELETE SET NULL,
    INDEX idx_destinatario (destinatario_id),
    INDEX idx_leido (leido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: notificaciones
-- Notificaciones del sistema
-- =====================================================
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    
    tipo VARCHAR(50) NOT NULL COMMENT 'perfil_editado, formulario_enviado, etc',
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT NULL,
    url VARCHAR(255) NULL,
    datos JSON NULL COMMENT 'Datos adicionales en JSON',
    
    leida TINYINT(1) DEFAULT 0,
    fecha_lectura DATETIME NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_leida (usuario_id, leida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: configuracion_sitio
-- Configuraciones generales del sitio
-- =====================================================
CREATE TABLE configuracion_sitio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT NULL,
    tipo ENUM('text', 'textarea', 'number', 'boolean', 'json', 'image') DEFAULT 'text',
    grupo VARCHAR(50) NULL,
    descripcion VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: banners
-- Banners/sliders de la página principal
-- =====================================================
CREATE TABLE banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NULL,
    subtitulo VARCHAR(255) NULL,
    imagen VARCHAR(255) NOT NULL,
    url VARCHAR(255) NULL,
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: visitas_empresa
-- Registro de visitas para mapa de calor
-- =====================================================
CREATE TABLE visitas_empresa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    referer VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa_fecha (empresa_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: log_actividad
-- Auditoría de cambios importantes
-- =====================================================
CREATE TABLE log_actividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    empresa_id INT NULL,
    accion VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(50) NULL,
    registro_id INT NULL,
    datos_anteriores JSON NULL,
    datos_nuevos JSON NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_usuario (usuario_id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: formularios_config
-- Configuración de formularios personalizables por el ministerio
-- =====================================================
CREATE TABLE formularios_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    campos JSON NOT NULL COMMENT 'Estructura JSON de campos',
    activo TINYINT(1) DEFAULT 1,
    obligatorio TINYINT(1) DEFAULT 0,
    fecha_limite DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLA: respuestas_formulario
-- Respuestas a formularios personalizados
-- =====================================================
CREATE TABLE respuestas_formulario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT NOT NULL,
    empresa_id INT NOT NULL,
    respuestas JSON NOT NULL,
    estado ENUM('borrador', 'enviado', 'aprobado', 'rechazado') DEFAULT 'borrador',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (formulario_id) REFERENCES formularios_config(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY uk_form_empresa (formulario_id, empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERTAR DATOS INICIALES
-- =====================================================

-- Usuario administrador por defecto
INSERT INTO usuarios (email, password, rol, activo) VALUES 
('admin@parqueindustrial.gob.ar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1),
('ministerio@catamarca.gob.ar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ministerio', 1);
-- Password por defecto: password (cambiar en producción)

-- Rubros iniciales (basados en el Excel)
INSERT INTO rubros (nombre, color, orden) VALUES 
('Textil', '#3498db', 1),
('Construcción', '#e74c3c', 2),
('Metalúrgica', '#95a5a6', 3),
('Alimentos', '#27ae60', 4),
('Transporte', '#f39c12', 5),
('Reciclado', '#2ecc71', 6),
('Hormigón', '#7f8c8d', 7),
('Electrodomésticos', '#9b59b6', 8),
('Medicamentos', '#1abc9c', 9),
('Calzados', '#e67e22', 10),
('Fibra de Vidrio', '#34495e', 11),
('Combustibles', '#c0392b', 12),
('Minería', '#8e44ad', 13),
('Química', '#16a085', 14),
('Maquinaria Industrial', '#2c3e50', 15),
('Autopartes', '#d35400', 16),
('Frigorífico', '#2980b9', 17),
('Lácteos', '#f1c40f', 18),
('Otros', '#bdc3c7', 99);

-- Ubicaciones iniciales
INSERT INTO ubicaciones (nombre, latitud_centro, longitud_centro) VALUES 
('PI El Pantanillo', -28.4696, -65.7795),
('Capital', -28.4696, -65.7852),
('Valle Viejo', -28.3917, -65.7095),
('Recreo', -29.2833, -65.0667);

-- Configuraciones iniciales del sitio
INSERT INTO configuracion_sitio (clave, valor, tipo, grupo, descripcion) VALUES 
('sitio_nombre', 'Parque Industrial de Catamarca', 'text', 'general', 'Nombre del sitio'),
('sitio_descripcion', 'Portal del Parque Industrial de la Provincia de Catamarca', 'textarea', 'general', 'Descripción del sitio'),
('sitio_email', 'contacto@parqueindustrial.gob.ar', 'text', 'contacto', 'Email de contacto'),
('sitio_telefono', '(0383) 4123456', 'text', 'contacto', 'Teléfono de contacto'),
('sitio_direccion', 'San Fernando del Valle de Catamarca, Argentina', 'text', 'contacto', 'Dirección física'),
('mapa_lat_centro', '-28.4696', 'text', 'mapa', 'Latitud centro del mapa'),
('mapa_lng_centro', '-65.7795', 'text', 'mapa', 'Longitud centro del mapa'),
('mapa_zoom_inicial', '12', 'number', 'mapa', 'Zoom inicial del mapa'),
('redes_facebook', 'https://facebook.com/parqueindustrialcatamarca', 'text', 'redes', 'Facebook'),
('redes_instagram', 'https://instagram.com/parqueindustrialcatamarca', 'text', 'redes', 'Instagram'),
('redes_twitter', '', 'text', 'redes', 'Twitter/X'),
('texto_sobre_nosotros', 'El Parque Industrial de Catamarca es un polo de desarrollo...', 'textarea', 'contenido', 'Texto sobre nosotros'),
('mostrar_estadisticas_publicas', '1', 'boolean', 'privacidad', 'Mostrar estadísticas al público');

-- Crear vista para estadísticas rápidas
CREATE OR REPLACE VIEW v_estadisticas_generales AS
SELECT 
    (SELECT COUNT(*) FROM empresas WHERE estado = 'activa') as total_empresas_activas,
    (SELECT COUNT(*) FROM empresas) as total_empresas,
    (SELECT COALESCE(SUM(dotacion_total), 0) FROM datos_empresa de 
     INNER JOIN empresas e ON de.empresa_id = e.id 
     WHERE e.estado = 'activa' 
     AND de.periodo = (SELECT MAX(periodo) FROM datos_empresa WHERE empresa_id = de.empresa_id)) as total_empleados,
    (SELECT COUNT(DISTINCT rubro) FROM empresas WHERE estado = 'activa') as total_rubros,
    (SELECT COUNT(*) FROM publicaciones WHERE publicado = 1 AND estado = 'aprobado') as total_publicaciones;

-- Crear vista para empresas con últimos datos
CREATE OR REPLACE VIEW v_empresas_completas AS
SELECT 
    e.*,
    de.dotacion_total,
    de.empleados_masculinos,
    de.empleados_femeninos,
    de.consumo_energia,
    de.consumo_agua,
    de.exporta,
    de.importa,
    de.emisiones_co2,
    de.periodo as ultimo_periodo
FROM empresas e
LEFT JOIN datos_empresa de ON e.id = de.empresa_id 
    AND de.periodo = (SELECT MAX(periodo) FROM datos_empresa WHERE empresa_id = e.id);

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
