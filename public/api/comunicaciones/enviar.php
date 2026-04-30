<?php
/**
 * POST /api/comunicaciones/enviar.php
 *   conversacion_id: int (opcional si se va a crear una nueva)
 *   contenido: string
 *
 *   Si conversacion_id NO viene, ademas:
 *     titulo: string
 *     categoria: enum
 *     destinatario: 'ministerio' | 'empresa:NN' | 'global' (solo ministerio)
 *
 * Crea una conversacion (si hace falta) e inserta el mensaje.
 * Devuelve { ok, conversacion_id, mensaje_id }.
 */
require __DIR__ . '/_base.php';
coms_require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    coms_json_error(405, 'Metodo no permitido.');
}

$contenido = trim((string)($_POST['contenido'] ?? ''));
if ($contenido === '') {
    coms_json_error(400, 'El contenido del mensaje es obligatorio.');
}

$conv_id = (int)($_POST['conversacion_id'] ?? 0);

try {
    if ($conv_id > 0) {
        // Mensaje en hilo existente: validar acceso.
        if (!coms_puede_acceder($conv_id, $coms_actor, $coms_empresa_id)) {
            coms_json_error(403, 'No tiene acceso a esta conversacion.');
        }
    } else {
        // Crear conversacion nueva.
        $titulo    = trim((string)($_POST['titulo'] ?? ''));
        $categoria = trim((string)($_POST['categoria'] ?? 'consulta'));
        $destino   = trim((string)($_POST['destinatario'] ?? ''));

        if ($titulo === '') coms_json_error(400, 'El titulo es obligatorio para iniciar una conversacion.');

        $empresa_destino = null;
        if ($coms_actor === 'empresa') {
            // Las empresas siempre escriben al ministerio (1-a-1, suya).
            $empresa_destino = $coms_empresa_id;
        } else {
            // Ministerio decide a quien escribir.
            if ($destino === 'global') {
                $empresa_destino = null; // broadcast
                if ($categoria === '' || $categoria === 'consulta') $categoria = 'comunicado';
            } elseif (preg_match('/^empresa:(\d+)$/', $destino, $m)) {
                $empresa_destino = (int)$m[1];
                // verificar que la empresa exista
                $stmt = getDB()->prepare("SELECT 1 FROM empresas WHERE id = ?");
                $stmt->execute([$empresa_destino]);
                if (!$stmt->fetch()) coms_json_error(400, 'Empresa destinataria no encontrada.');
            } else {
                coms_json_error(400, 'Destinatario invalido.');
            }
        }

        $conv_id = coms_crear_conversacion([
            'titulo'       => $titulo,
            'empresa_id'   => $empresa_destino,
            'iniciada_por' => $coms_actor,
            'categoria'    => $categoria ?: 'consulta',
        ]);
    }

    $mensaje_id = coms_enviar_mensaje([
        'conversacion_id' => $conv_id,
        'remitente_id'    => $coms_user_id,
        'remitente_tipo'  => $coms_actor,
        'contenido'       => $contenido,
        'es_borrador'     => 0,
    ]);

    echo json_encode([
        'ok'              => true,
        'conversacion_id' => $conv_id,
        'mensaje_id'      => $mensaje_id,
    ]);
} catch (InvalidArgumentException $e) {
    coms_json_error(400, $e->getMessage());
} catch (Exception $e) {
    error_log('coms enviar: ' . $e->getMessage());
    coms_json_error(500, 'Error al enviar el mensaje.');
}
