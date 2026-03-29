<?php
/**
 * Permite ejecutar scripts de cron solo por CLI o con ?key=CRON_SECRET
 */
if (!function_exists('env')) {
    function env($k, $d = null) {
        $v = getenv($k);
        return $v === false ? $d : $v;
    }
}

$secret = (string)env('CRON_SECRET', '');
$ok = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if (!$ok && $secret !== '' && isset($_GET['key']) && hash_equals($secret, (string)$_GET['key'])) {
    $ok = true;
}
if (!$ok) {
    if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    exit("Forbidden\n");
}
