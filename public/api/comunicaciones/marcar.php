<?php
/**
 * POST /api/comunicaciones/marcar.php
 *   conversacion_id: int
 *   accion: 'leida' | 'no_leida' | 'archivar' | 'desarchivar'
 *
 * Operaciones de estado sobre una conversacion. La marca de "leida" tambien
 * ocurre automaticamente cuando se carga la conversacion via /conversacion.php;
 * este endpoint es util para botones explicitos del UI ("recordar para luego",
 * "archivar", etc.).
 */
require __DIR__ . '/_base.php';
coms_require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    coms_json_error(405, 'Metodo no permitido.');
}

$conv_id = (int)($_POST['conversacion_id'] ?? 0);
$accion  = trim((string)($_POST['accion'] ?? ''));

if ($conv_id <= 0) {
    coms_json_error(400, 'conversacion_id es obligatorio.');
}
if (!in_array($accion, ['leida', 'no_leida', 'archivar', 'desarchivar'], true)) {
    coms_json_error(400, 'accion invalida.');
}
if (!coms_puede_acceder($conv_id, $coms_actor, $coms_empresa_id)) {
    coms_json_error(403, 'No tiene acceso a esta conversacion.');
}

try {
    switch ($accion) {
        case 'leida':
            $n = coms_marcar_leida($conv_id, $coms_actor, $coms_empresa_id);
            echo json_encode(['ok' => true, 'mensajes_marcados' => $n]);
            break;
        case 'no_leida':
            $ok = coms_marcar_no_leida($conv_id, $coms_actor, $coms_empresa_id);
            echo json_encode(['ok' => $ok]);
            break;
        case 'archivar':
            coms_archivar($conv_id, true, $coms_actor, $coms_empresa_id);
            echo json_encode(['ok' => true]);
            break;
        case 'desarchivar':
            coms_archivar($conv_id, false, $coms_actor, $coms_empresa_id);
            echo json_encode(['ok' => true]);
            break;
    }
} catch (Exception $e) {
    error_log('coms marcar: ' . $e->getMessage());
    coms_json_error(500, 'Error al actualizar la conversacion.');
}
