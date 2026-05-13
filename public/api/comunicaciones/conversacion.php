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

    $referencia = null;
    $ref_tipo = $conv['referencia_tipo'] ?? null;
    $ref_id = isset($conv['referencia_id']) ? (int)$conv['referencia_id'] : 0;
    if ($ref_tipo === 'formulario_dinamico' && $ref_id > 0) {
        $emp_for_ref = $conv['empresa_id'] !== null ? (int)$conv['empresa_id'] : (int)($coms_empresa_id ?? 0);
        try {
            $stmtF = $db->prepare("
                SELECT f.id, f.titulo, f.descripcion, f.estado,
                       fr.estado AS estado_respuesta,
                       fr.enviado_at AS respuesta_enviada_at,
                       (
                           SELECT COALESCE(fd.plazo_hasta, fe.fecha_limite)
                           FROM formulario_destinatarios fd
                           INNER JOIN formulario_envios fe ON fe.id = fd.envio_id
                           WHERE fe.formulario_id = f.id AND fd.empresa_id = ?
                           ORDER BY fe.created_at DESC
                           LIMIT 1
                       ) AS fecha_limite
                FROM formularios_dinamicos f
                LEFT JOIN formulario_respuestas fr
                       ON fr.formulario_id = f.id AND fr.empresa_id = ?
                WHERE f.id = ?
                ORDER BY fr.id DESC
                LIMIT 1
            ");
            $stmtF->execute([$emp_for_ref, $emp_for_ref, $ref_id]);
            $detalle = $stmtF->fetch(PDO::FETCH_ASSOC);
            if ($detalle) {
                $referencia = [
                    'tipo' => 'formulario_dinamico',
                    'id'   => (int)$detalle['id'],
                    'detalle' => [
                        'titulo'              => $detalle['titulo'],
                        'descripcion'         => $detalle['descripcion'],
                        'estado_publicacion'  => $detalle['estado'],
                        'fecha_limite'        => $detalle['fecha_limite'],
                        'estado_respuesta'    => $detalle['estado_respuesta'],
                        'respuesta_enviada_at'=> $detalle['respuesta_enviada_at'],
                        'url_completar'       => rtrim(EMPRESA_URL, '/') . '/formulario_dinamico.php?id=' . (int)$detalle['id'],
                    ],
                ];
            }
        } catch (Exception $e) {
            error_log('coms conversacion referencia: ' . $e->getMessage());
        }
    }

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
            'referencia_tipo'      => $ref_tipo,
            'referencia_id'        => $ref_id > 0 ? $ref_id : null,
            'created_at'           => $conv['created_at'],
        ],
        'referencia' => $referencia,
        'mensajes' => $mensajes,
    ]);
} catch (Exception $e) {
    error_log('coms conversacion: ' . $e->getMessage());
    coms_json_error(500, 'Error al cargar la conversacion.');
}
