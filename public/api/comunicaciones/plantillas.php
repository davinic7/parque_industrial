<?php
/**
 * GET /api/comunicaciones/plantillas.php[?categoria=X]
 *
 * Devuelve las plantillas activas para usar en el compositor.
 * Solo disponible para ministerio/admin.
 */
require __DIR__ . '/_base.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    coms_json_error(405, 'Metodo no permitido.');
}

if ($coms_actor !== 'ministerio') {
    coms_json_error(403, 'Solo disponible para el ministerio.');
}

$categoria = trim($_GET['categoria'] ?? '');

try {
    $db = getDB();

    // Verificar que la tabla exista
    try {
        $db->query('SELECT 1 FROM plantillas_respuesta LIMIT 1');
    } catch (Throwable $e) {
        echo json_encode(['ok' => true, 'plantillas' => []]);
        exit;
    }

    $sql = "SELECT id, titulo, contenido, categoria FROM plantillas_respuesta WHERE activa = 1";
    $params = [];

    if ($categoria !== '') {
        $sql .= " AND categoria = ?";
        $params[] = $categoria;
    }

    $sql .= " ORDER BY orden ASC, titulo ASC";

    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'plantillas' => $rows]);
} catch (Throwable $e) {
    error_log('coms plantillas: ' . $e->getMessage());
    coms_json_error(500, 'Error al cargar plantillas.');
}
