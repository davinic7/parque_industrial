-- Migración 010: activación cuentas, login_attempts, recuperación rate-limit,
-- envíos selectivos de formularios dinámicos y destinatarios.
-- Ejecutar en HeidiSQL / producción (backup antes). Si una columna ya existe, omitir ese ALTER.

-- --- Usuarios: activación por enlace (no reutilizar token_expira: es para recuperar contraseña) ---
ALTER TABLE usuarios
    ADD COLUMN token_activacion VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN token_activacion_expira DATETIME NULL DEFAULT NULL,
    ADD COLUMN email_verificado TINYINT(1) NOT NULL DEFAULT 0;

-- --- Intentos de login por IP ---
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    email VARCHAR(255) NULL,
    intentos INT NOT NULL DEFAULT 1,
    ultimo_intento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bloqueado_hasta DATETIME NULL,
    UNIQUE KEY uq_login_attempts_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Límite solicitudes recuperar contraseña (por IP, ventana 1 h) ---
CREATE TABLE IF NOT EXISTS password_reset_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_created (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Campañas de envío de formulario dinámico ---
CREATE TABLE IF NOT EXISTS formulario_envios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT NOT NULL,
    tipo_filtro ENUM('todos','rubro','ubicacion','estado','empresas_especificas') NOT NULL DEFAULT 'todos',
    filtros_json TEXT NULL,
    total_destinatarios INT NOT NULL DEFAULT 0,
    fecha_limite DATE NULL,
    enviado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_formulario_id (formulario_id),
    CONSTRAINT fk_fe_formulario FOREIGN KEY (formulario_id) REFERENCES formularios_dinamicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_fe_usuario FOREIGN KEY (enviado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formulario_destinatarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    envio_id INT NOT NULL,
    empresa_id INT NOT NULL,
    notificado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_notificacion DATETIME NULL,
    respondido TINYINT(1) NOT NULL DEFAULT 0,
    fecha_respuesta DATETIME NULL,
    plazo_hasta DATE NULL,
    UNIQUE KEY uq_envio_empresa (envio_id, empresa_id),
    CONSTRAINT fk_fd_envio FOREIGN KEY (envio_id) REFERENCES formulario_envios(id) ON DELETE CASCADE,
    CONSTRAINT fk_fd_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
