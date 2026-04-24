<?php
/**
 * API AJAX para galería de imágenes de empresa.
 * POST accion=subir|eliminar  GET accion=listar
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin acceso']);
    exit;
}

header('Content-Type: application/json');

$empresa_id = (int)($_SESSION['empresa_id'] ?? 0);
if (!$empresa_id) {
    echo json_encode(['ok' => false, 'error' => 'Sin empresa']);
    exit;
}

$db = getDB();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

if ($accion === 'listar') {
    try {
        $stmt = $db->prepare("SELECT id, imagen FROM empresa_imagenes WHERE empresa_id = ? ORDER BY orden ASC, id ASC");
        $stmt->execute([$empresa_id]);
        $imgs = $stmt->fetchAll();
        $result = array_map(fn($img) => [
            'id'  => $img['id'],
            'url' => uploads_resolve_url($img['imagen'], 'galeria_empresa'),
        ], $imgs);
        echo json_encode(['ok' => true, 'imagenes' => $result]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => 'Error al listar']);
    }

} elseif ($accion === 'subir') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        echo json_encode(['ok' => false, 'error' => 'Token inválido']); exit;
    }
    if (empty($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'No se recibió el archivo']); exit;
    }
    try {
        $upload = upload_image_storage($_FILES['imagen'], 'galeria_empresa', ALLOWED_IMAGE_TYPES);
        if (!$upload['success']) {
            echo json_encode(['ok' => false, 'error' => $upload['error']]); exit;
        }
        $stmt = $db->prepare("SELECT COALESCE(MAX(orden), 0) + 1 FROM empresa_imagenes WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);
        $orden = (int)$stmt->fetchColumn();
        $db->prepare("INSERT INTO empresa_imagenes (empresa_id, imagen, orden) VALUES (?, ?, ?)")
           ->execute([$empresa_id, $upload['filename'], $orden]);
        echo json_encode([
            'ok'  => true,
            'id'  => (int)$db->lastInsertId(),
            'url' => uploads_resolve_url($upload['filename'], 'galeria_empresa'),
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => 'Error al subir imagen']);
    }

} elseif ($accion === 'eliminar') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        echo json_encode(['ok' => false, 'error' => 'Token inválido']); exit;
    }
    $img_id = (int)($_POST['imagen_id'] ?? 0);
    if ($img_id > 0) {
        $db->prepare("DELETE FROM empresa_imagenes WHERE id = ? AND empresa_id = ?")->execute([$img_id, $empresa_id]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    }

} else {
    echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
}
