<?php
/**
 * Limpia tokens de activación y recuperación expirados.
 */
require_once __DIR__ . '/../../../includes/cron_guard.php';
require_once dirname(__DIR__, 3) . '/config/config.php';

$db = getDB();
try {
    $db->exec("
        UPDATE usuarios SET token_activacion = NULL, token_activacion_expira = NULL
        WHERE activo = 0 AND token_activacion_expira IS NOT NULL AND token_activacion_expira < NOW()
    ");
} catch (Exception $e) {
    // columnas no migradas
}
try {
    $db->exec("
        UPDATE usuarios SET token_recuperacion = NULL, token_expira = NULL
        WHERE token_expira IS NOT NULL AND token_expira < NOW()
    ");
} catch (Exception $e) {
}
try {
    $db->exec("DELETE FROM password_reset_requests WHERE created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)");
} catch (Exception $e) {
}
echo "OK tokens limpiados\n";
