-- ============================================================
-- 018_plantillas_respuesta.sql
-- Plantillas de respuesta rápida para el ministerio.
-- ============================================================

CREATE TABLE IF NOT EXISTS plantillas_respuesta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(120) NOT NULL,
    contenido TEXT NOT NULL,
    categoria ENUM('tramite','consulta','reclamo','comunicado','formulario','sistema','general')
        NOT NULL DEFAULT 'general',
    orden INT NOT NULL DEFAULT 0,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_activa_orden (activa, orden, titulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plantillas de ejemplo (se pueden borrar)
INSERT INTO plantillas_respuesta (titulo, contenido, categoria, orden) VALUES
('Recibimos su consulta', 'Estimado/a,\n\nHemos recibido su consulta y será derivada al área correspondiente.\n\nLe responderemos a la brevedad.\n\nSaludos cordiales,\nMinisterio de Industria', 'consulta', 1),
('Documentación requerida', 'Estimado/a,\n\nPara continuar con su trámite necesitamos que adjunte la siguiente documentación:\n\n- \n- \n\nQuedamos a disposición.\n\nSaludos cordiales,\nMinisterio de Industria', 'tramite', 2),
('Trámite aprobado', 'Estimado/a,\n\nNos complace informarle que su trámite ha sido aprobado.\n\nSaludos cordiales,\nMinisterio de Industria', 'tramite', 3),
('Formulario con observaciones', 'Estimado/a,\n\nHemos revisado su declaración de datos y encontramos las siguientes observaciones:\n\n- \n\nPor favor corrija y reenvíe.\n\nSaludos cordiales,\nMinisterio de Industria', 'formulario', 4);
