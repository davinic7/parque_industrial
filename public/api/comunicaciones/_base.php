<?php
/**
 * Base comun a todos los endpoints del Centro de Comunicaciones.
 *
 * Carga config + helper, valida sesion + feature flag + esquema disponible,
 * y resuelve el "actor" (empresa|ministerio) con sus datos asociados:
 *   $coms_actor       'empresa' | 'ministerio'
 *   $coms_user_id     int
 *   $coms_empresa_id  int|null  (solo si actor=empresa)
 *
 * Uso desde un endpoint:
 *   require __DIR__ . '/_base.php';
 *   // a partir de aca, el endpoint ya esta autenticado y autorizado.
 */

require_once __DIR__ . '/../../../config/config.php';
require_once BASEPATH . '/includes/comunicaciones.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function coms_json_error(int $code, string $message, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['error' => $message], $extra));
    exit;
}

if (!$auth->isLoggedIn()) {
    coms_json_error(401, 'No autenticado.');
}

if (!FEATURE_CENTRO_COMS) {
    coms_json_error(503, 'El Centro de Comunicaciones no esta habilitado en este entorno.');
}

if (!coms_schema_disponible()) {
    coms_json_error(503, 'La migracion 016 no fue aplicada en la base de datos.');
}

$rol = $_SESSION['user_rol'] ?? '';
$coms_user_id = (int)($_SESSION['user_id'] ?? 0);
$coms_empresa_id = null;
$coms_actor = null;

if ($rol === 'empresa') {
    $coms_actor = 'empresa';
    $coms_empresa_id = isset($_SESSION['empresa_id']) ? (int)$_SESSION['empresa_id'] : null;
    if (!$coms_empresa_id) {
        coms_json_error(403, 'Su usuario no tiene una empresa asociada.');
    }
} elseif ($rol === 'ministerio' || $rol === 'admin') {
    $coms_actor = 'ministerio';
} else {
    coms_json_error(403, 'Rol no autorizado para usar el centro de comunicaciones.');
}

/**
 * Verifica el token CSRF para metodos que muten datos.
 */
function coms_require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        coms_json_error(403, 'Token CSRF invalido. Recargue la pagina.');
    }
}
