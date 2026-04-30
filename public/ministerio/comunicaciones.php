<?php
/**
 * Centro de Comunicaciones - Panel Ministerio (Fase 2)
 *
 * Reemplaza a mensajes-entrada.php + comunicados.php + notificaciones.php
 * cuando FEATURE_CENTRO_COMS=1.
 */
require_once __DIR__ . '/../../config/config.php';
require_once BASEPATH . '/includes/comunicaciones.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

// Si el feature flag esta apagado o la migracion no se aplico, redirigir al
// sistema viejo para no dejar al usuario sin bandeja.
if (!FEATURE_CENTRO_COMS || !coms_schema_disponible()) {
    redirect('mensajes-entrada.php');
}

$page_title = 'Comunicaciones';
$ministerio_nav = 'mensajes';
$db = getDB();

// Lista de empresas activas para el selector de destinatario en "Nueva conversacion".
$coms_empresas_destino = $db->query("
    SELECT id, nombre FROM empresas
    WHERE estado IN ('activa', 'pendiente')
    ORDER BY nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Variables que consume el partial
$coms_actor                = 'ministerio';
$coms_api_base             = '../api/comunicaciones';
$coms_puede_broadcast      = true;
$coms_puede_elegir_empresa = true;

require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-chat-square-dots me-2"></i>Centro de Comunicaciones</h1>
    <small class="text-muted">Mensajes con empresas + comunicados globales + notificaciones.</small>
</div>

<?php require BASEPATH . '/includes/partials/comunicaciones_panel.php'; ?>

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>
