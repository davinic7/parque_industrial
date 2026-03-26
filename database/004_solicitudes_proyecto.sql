-- Solicitudes "Presentar proyecto al ministerio"
CREATE TABLE IF NOT EXISTS solicitudes_proyecto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_empresa VARCHAR(255) NOT NULL,
    contacto VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telefono VARCHAR(50) NULL,
    resumen_proyecto TEXT NOT NULL,
    solicita_cita TINYINT(1) DEFAULT 1,
    estado ENUM('nueva', 'vista', 'contactada', 'cerrada') DEFAULT 'nueva',
    observaciones TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
