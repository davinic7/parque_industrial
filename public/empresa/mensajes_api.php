<?php
/**
 * API JSON para la bandeja de mensajes (empresa): cuerpo + marcar leído.
 */
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

$db = getDB();

$stmt = $db->prepare('
    SELECT m.*, u.email AS remitente_email
    FROM mensajes m
    LEFT JOIN usuarios u ON m.remitente_id = u.id
    WHERE m.id = ? AND m.destinatario_id = ?
');
$stmt->execute([$id, $user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Mensaje no encontrado']);
    exit;
}

$db->prepare('UPDATE mensajes SET leido = 1, fecha_lectura = NOW() WHERE id = ? AND destinatario_id = ? AND leido = 0')
    ->execute([$id, $user_id]);

$adjuntos = [];
if (!empty($row['adjuntos'])) {
    $dec = json_decode($row['adjuntos'], true);
    if (is_array($dec)) {
        foreach ($dec as $fn) {
            if (is_string($fn) && $fn !== '') {
                $adjuntos[] = [
                    'nombre' => basename($fn),
                    'url' => uploads_resolve_url($fn, 'mensajes'),
                ];
            }
        }
    }
}

$remitente = 'Ministerio';
if (!empty($row['remitente_email'])) {
    $remitente = $row['remitente_email'];
}

echo json_encode([
    'ok' => true,
    'id' => (int) $row['id'],
    'asunto' => $row['asunto'],
    'remitente' => $remitente,
    'fecha' => format_datetime($row['created_at']),
    'contenido' => $row['contenido'],
    'adjuntos' => $adjuntos,
    'leido' => true,
], JSON_UNESCAPED_UNICODE);
