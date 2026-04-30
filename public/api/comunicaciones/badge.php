<?php
/**
 * GET /api/comunicaciones/badge.php
 *
 * Devuelve { ok, no_leidos } para alimentar el badge global del header.
 * Se consume con polling cada ~30s desde el layout.
 *
 * Si la migracion 016 no esta aplicada, retorna 0 sin error para no
 * romper la UI existente.
 */
require __DIR__ . '/_base.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    coms_json_error(405, 'Metodo no permitido.');
}

try {
    $n = coms_contar_no_leidos($coms_actor, $coms_empresa_id);
    echo json_encode(['ok' => true, 'no_leidos' => $n]);
} catch (Exception $e) {
    error_log('coms badge: ' . $e->getMessage());
    echo json_encode(['ok' => true, 'no_leidos' => 0]);
}
