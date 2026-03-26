-- Galería de imágenes de cada empresa (carrusel en perfil público)
CREATE TABLE IF NOT EXISTS empresa_imagenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    imagen VARCHAR(255) NOT NULL COMMENT 'Ruta en uploads/galeria_empresa/',
    orden INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_empresa (empresa_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
