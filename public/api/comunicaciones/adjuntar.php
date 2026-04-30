<?php
/**
 * POST /api/comunicaciones/adjuntar.php  (multipart/form-data)
 *   mensaje_id: int     (mensaje al que se anexa, NORMALMENTE un borrador)
 *   archivos[]: file    (uno o varios)
 *
 * Sube uno o mas archivos, valida MIME y tope total acumulado de 25MB,
 * los persiste (Cloudinary o disco local) y los enlaza al mensaje.
 *
 * Devuelve { ok, adjuntos: [{id,url,nombre,tipo,tamano}], total_bytes }.
 */
require __DIR__ . '/_base.php';
coms_require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    coms_json_error(405, 'Metodo no permitido.');
}

$mensaje_id = (int)($_POST['mensaje_id'] ?? 0);
if ($mensaje_id <= 0) {
    coms_json_error(400, 'mensaje_id es obligatorio.');
}

// Verificar que el mensaje exista, sea del actor y la conversacion sea accesible.
$db = getDB();
$stmt = $db->prepare("
    SELECT m.id, m.conversacion_id, m.remitente_id
    FROM mensajes_v2 m
    WHERE m.id = ?
");
$stmt->execute([$mensaje_id]);
$msg = $stmt->fetch();
if (!$msg) {
    coms_json_error(404, 'Mensaje no encontrado.');
}
if ((int)$msg['remitente_id'] !== $coms_user_id) {
    coms_json_error(403, 'No puede adjuntar archivos a un mensaje ajeno.');
}
if (!coms_puede_acceder((int)$msg['conversacion_id'], $coms_actor, $coms_empresa_id)) {
    coms_json_error(403, 'No tiene acceso a esta conversacion.');
}

if (empty($_FILES['archivos']) || !is_array($_FILES['archivos']['name'])) {
    coms_json_error(400, 'No se recibieron archivos.');
}

$archivos = $_FILES['archivos'];
$cantidad = count($archivos['name']);
if ($cantidad === 0) {
    coms_json_error(400, 'No se recibieron archivos.');
}

// Calcular tamano ya consumido en este mensaje (para soportar uploads en tandas).
$ya_subido = coms_suma_adjuntos_bytes($mensaje_id);

$resultados = [];

try {
    for ($i = 0; $i < $cantidad; $i++) {
        $file = [
            'name'     => $archivos['name'][$i],
            'type'     => $archivos['type'][$i],
            'tmp_name' => $archivos['tmp_name'][$i],
            'error'    => $archivos['error'][$i],
            'size'     => $archivos['size'][$i],
        ];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            coms_json_error(400, "Error al subir '{$file['name']}'.");
        }

        // Validar MIME real con finfo (no confiar en el cliente).
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_real = $finfo->file($file['tmp_name']);
        if (!in_array($mime_real, COMS_ALLOWED_MIMES, true)) {
            coms_json_error(415, "Tipo de archivo no permitido: '{$file['name']}' ($mime_real).");
        }

        // Validar suma acumulada (25MB total)
        $ya_subido += (int)$file['size'];
        if ($ya_subido > COMS_MAX_TOTAL_BYTES) {
            $mb = round(COMS_MAX_TOTAL_BYTES / 1048576, 0);
            coms_json_error(413, "Los adjuntos exceden el limite total de {$mb} MB por mensaje.");
        }

        // Persistir: usa Cloudinary si esta configurado, sino /uploads/mensajes/
        $upload_result = upload_image_storage($file, 'mensajes', COMS_ALLOWED_MIMES);
        if (!$upload_result['success']) {
            // Fallback: si no es imagen, usar upload_file generico
            $upload_result = upload_file($file, 'mensajes', COMS_ALLOWED_MIMES, $mime_real);
        }
        if (!$upload_result['success']) {
            coms_json_error(500, "Error al guardar '{$file['name']}': " . $upload_result['error']);
        }

        $url = $upload_result['filename'] ?? ($upload_result['url'] ?? '');

        $adj_id = coms_agregar_adjunto($mensaje_id, [
            'url'    => $url,
            'nombre' => basename((string)$file['name']),
            'tipo'   => $mime_real,
            'tamano' => (int)$file['size'],
        ]);

        $resultados[] = [
            'id'     => $adj_id,
            'url'    => $url,
            'nombre' => basename((string)$file['name']),
            'tipo'   => $mime_real,
            'tamano' => (int)$file['size'],
        ];
    }

    echo json_encode([
        'ok'          => true,
        'adjuntos'    => $resultados,
        'total_bytes' => $ya_subido,
    ]);
} catch (Exception $e) {
    error_log('coms adjuntar: ' . $e->getMessage());
    coms_json_error(500, 'Error al procesar los adjuntos.');
}
