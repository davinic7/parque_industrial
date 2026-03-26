-- Me gusta en publicaciones (un like por IP por publicación)
CREATE TABLE IF NOT EXISTS publicacion_likes (
    publicacion_id INT NOT NULL,
    ip VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (publicacion_id, ip),
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
