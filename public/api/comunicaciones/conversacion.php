<?php
/**
 * GET /api/comunicaciones/conversacion.php?id=NN
 *
 * Devuelve metadatos del hilo + lista de mensajes con sus adjuntos.
 * Marca como leidos los mensajes recibidos por el actor en esta operacion
 * (lectura automatica, punto 78/81/125 del checklist).
 */
require __DIR__ . '/_base.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    coms_json_error(405, 'Metodo no permitido.');
}

$conv_id = (int)($_GET['id'] ?? 0);
if ($conv_id <= 0) {
    coms_json_error(400, 'Parametro id requerido.');
}

if (!coms_puede_acceder($conv_id, $coms_actor, $coms_empresa_id)) {
    coms_json_error(403, 'No tiene acceso a esta conversacion.');
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT c.*, e.nombre AS empresa_nombre
        FROM conversaciones c
        LEFT JOIN empresas e ON e.id = c.empresa_id
        WHERE c.id = ?
    ");
    $stmt->execute([$conv_id]);
    $conv = $stmt->fetch();
    if (!$conv) {
        coms_json_error(404, 'Conversacion no encontrada.');
    }

    $mensajes = coms_obtener_hilo($conv_id);

    // Marcar lectura automatica antes de devolver, asi el badge baja al instante.
    coms_marcar_leida($conv_id, $coms_actor, $coms_empresa_id);

    // Formatear adjuntos a estructura limpia
    $mensajes = array_map(function ($m) {
        return [
            'id'             => (int)$m['id'],
            'remitente_tipo' => $m['remitente_tipo'],
            'remitente_id'   => $m['remitente_id'] !== null ? (int)$m['remitente_id'] : null,
            'remitente_email'=> $m['remitente_email'] ?? null,
            'contenido'      => $m['contenido'],
            'leido_at'       => $m['leido_at'],
            'created_at'     => $m['created_at'],
            'adjuntos'       => array_map(function ($a) {
                return [
                    'id'     => (int)$a['id'],
                    'url'    => $a['archivo_url'],
                    'nombre' => $a['archivo_nombre'],
                    'tipo'   => $a['archivo_tipo'],
                    'tamano' => (int)$a['archivo_tamano'],
                ];
            }, $m['adjuntos']),
        ];
    }, $mensajes);

    echo json_encode([
        'ok' => true,
        'conversacion' => [
            'id'                   => (int)$conv['id'],
            'titulo'               => $conv['titulo'],
            'empresa_id'           => $conv['empresa_id'] !== null ? (int)$conv['empresa_id'] : null,
            'empresa_nombre'       => $conv['empresa_nombre'] ?? null,
            'iniciada_por'         => $conv['iniciada_por'],
            'categoria'            => $conv['categoria'],
            'estado'               => $conv['estado'],
            'es_comunicado_global' => $conv['empresa_id'] === null,
            'created_at'           => $conv['created_at'],
        ],
        'mensajes' => $mensajes,
    ]);
} catch (Exception $e) {
    error_log('coms conversacion: ' . $e->getMessage());
    coms_json_error(500, 'Error al cargar la conversacion.');
}
