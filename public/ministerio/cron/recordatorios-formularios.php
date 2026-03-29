<?php
/**
 * Recordatorios automáticos: empresas con envío pendiente y límite en 7, 3 o 1 día.
 */
require_once __DIR__ . '/../../../includes/cron_guard.php';
require_once dirname(__DIR__, 3) . '/config/config.php';

$db = getDB();
$enviados = 0;
try {
    $rows = $db->query("
        SELECT DISTINCT e.usuario_id, fe.formulario_id, f.titulo
        FROM formulario_destinatarios fd
        INNER JOIN formulario_envios fe ON fe.id = fd.envio_id
        INNER JOIN formularios_dinamicos f ON f.id = fe.formulario_id
        INNER JOIN empresas e ON e.id = fd.empresa_id
        WHERE fd.respondido = 0
          AND COALESCE(fd.plazo_hasta, fe.fecha_limite) IS NOT NULL
          AND DATEDIFF(COALESCE(fd.plazo_hasta, fe.fecha_limite), CURDATE()) IN (7, 3, 1)
    ")->fetchAll();
    $urlBase = rtrim(EMPRESA_URL, '/') . '/formulario_dinamico.php?id=';
    foreach ($rows as $r) {
        if (crear_notificacion(
            (int)$r['usuario_id'],
            'formulario_recordatorio_auto',
            'Recordatorio: ' . $r['titulo'],
            'Su formulario tiene fecha límite próxima. Por favor complételo.',
            $urlBase . (int)$r['formulario_id']
        )) {
            $enviados++;
        }
    }
} catch (Exception $e) {
    echo "Error o tablas no migradas: " . $e->getMessage() . "\n";
    exit(1);
}
echo "OK notificaciones generadas: $enviados\n";
