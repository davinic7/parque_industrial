<?php
/**
 * Limpia intentos de login antiguos (ej. diario vía cron).
 * CLI: php limpiar-login-attempts.php
 * Web: .../limpiar-login-attempts.php?key=TU_CRON_SECRET
 */
require_once __DIR__ . '/../../../includes/cron_guard.php';
require_once dirname(__DIR__, 3) . '/config/config.php';

$db = getDB();
try {
    $n = $db->exec("DELETE FROM login_attempts WHERE ultimo_intento < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    echo "OK login_attempts eliminados (aprox): " . (int)$n . "\n";
} catch (Exception $e) {
    echo "Skip o error: " . $e->getMessage() . "\n";
}
