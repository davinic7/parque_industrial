-- Carrusel / Banners del inicio - editables por el ministerio
-- Ejecutar una vez en la base de datos del proyecto.

CREATE TABLE IF NOT EXISTS banners_home (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NULL,
    subtitulo VARCHAR(500) NULL,
    imagen VARCHAR(255) NULL COMMENT 'Ruta relativa en uploads/banners/',
    url_video VARCHAR(500) NULL COMMENT 'Opcional: URL de video para slide',
    tipo ENUM('imagen', 'video') NOT NULL DEFAULT 'imagen',
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_activo_orden (activo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
