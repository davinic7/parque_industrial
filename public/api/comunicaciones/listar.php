<?php
/**
 * GET /api/comunicaciones/listar.php
 *   ?categoria=&estado=&buscar=&limit=&offset=
 *
 * Devuelve la bandeja de conversaciones del actor actual con su contador
 * de no-leidos, ordenadas por ultimo mensaje (mas reciente primero).
 */
require __DIR__ . '/_base.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    coms_json_error(405, 'Metodo no permitido.');
}

try {
    $rows = coms_listar_conversaciones([
        'actor'      => $coms_actor,
        'empresa_id' => $coms_empresa_id,
        'categoria'  => trim((string)($_GET['categoria'] ?? '')),
        'estado'     => trim((string)($_GET['estado']    ?? 'abierta')),
        'buscar'     => trim((string)($_GET['buscar']    ?? '')),
        'limit'      => (int)($_GET['limit']  ?? 50),
        'offset'     => (int)($_GET['offset'] ?? 0),
    ]);

    // Sanitizar/formatear para el cliente
    $payload = array_map(function ($r) {
        return [
            'id'                    => (int)$r['id'],
            'titulo'                => $r['titulo'],
            'empresa_id'            => $r['empresa_id'] !== null ? (int)$r['empresa_id'] : null,
            'empresa_nombre'        => $r['empresa_nombre'] ?? null,
            'iniciada_por'          => $r['iniciada_por'],
            'categoria'             => $r['categoria'],
            'estado'                => $r['estado'],
            'ultimo_mensaje_at'     => $r['ultimo_mensaje_at'],
            'created_at'            => $r['created_at'],
            'no_leidos'             => (int)$r['no_leidos'],
            'ultimo_remitente_tipo' => $r['ultimo_remitente_tipo'],
            'es_comunicado_global'  => $r['empresa_id'] === null,
        ];
    }, $rows);

    echo json_encode(['ok' => true, 'conversaciones' => $payload]);
} catch (Exception $e) {
    error_log('coms listar: ' . $e->getMessage());
    coms_json_error(500, 'Error al listar conversaciones.');
}
