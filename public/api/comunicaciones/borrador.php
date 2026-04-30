<?php
/**
 * POST /api/comunicaciones/borrador.php
 *   conversacion_id: int     (obligatorio: el borrador siempre vive en una conversacion)
 *   contenido: string
 *
 * Idempotente: si ya existe un borrador del actor en esa conversacion, lo
 * actualiza. Si no, lo crea. Devuelve { ok, mensaje_id }.
 *
 * Convencion: cada usuario tiene como mucho UN borrador por conversacion.
 */
require __DIR__ . '/_base.php';
coms_require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    coms_json_error(405, 'Metodo no permitido.');
}

$conv_id = (int)($_POST['conversacion_id'] ?? 0);
$contenido = (string)($_POST['contenido'] ?? '');

if ($conv_id <= 0) {
    coms_json_error(400, 'conversacion_id es obligatorio.');
}

if (!coms_puede_acceder($conv_id, $coms_actor, $coms_empresa_id)) {
    coms_json_error(403, 'No tiene acceso a esta conversacion.');
}

try {
    $db = getDB();

    // Buscar borrador existente del actor en esta conversacion.
    $stmt = $db->prepare("
        SELECT id FROM mensajes_v2
         WHERE conversacion_id = ? AND remitente_id = ? AND es_borrador = 1
         ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$conv_id, $coms_user_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $db->prepare("UPDATE mensajes_v2 SET contenido = ? WHERE id = ?");
        $stmt->execute([$contenido, $existing['id']]);
        $msg_id = (int)$existing['id'];
    } else {
        $msg_id = coms_enviar_mensaje([
            'conversacion_id' => $conv_id,
            'remitente_id'    => $coms_user_id,
            'remitente_tipo'  => $coms_actor,
            'contenido'       => $contenido,
            'es_borrador'     => 1,
        ]);
    }

    echo json_encode(['ok' => true, 'mensaje_id' => $msg_id]);
} catch (Exception $e) {
    error_log('coms borrador: ' . $e->getMessage());
    coms_json_error(500, 'Error al guardar el borrador.');
}
