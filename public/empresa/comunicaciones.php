<?php
/**
 * Centro de Comunicaciones - Panel Empresa (Fase 2)
 *
 * Reemplaza a mensajes.php + notificaciones.php cuando FEATURE_CENTRO_COMS=1.
 */
require_once __DIR__ . '/../../config/config.php';
require_once BASEPATH . '/includes/comunicaciones.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

// Si el feature flag esta apagado o la migracion no se aplico, redirigir al
// sistema viejo para no dejar al usuario sin bandeja.
if (!FEATURE_CENTRO_COMS || !coms_schema_disponible()) {
    redirect('mensajes.php');
}

$page_title = 'Comunicaciones';
$empresa_nav = 'comunicaciones';

// Variables que consume el partial
$coms_actor                = 'empresa';
$coms_api_base             = '../api/comunicaciones';
$coms_puede_broadcast      = false;
$coms_puede_elegir_empresa = false;
$coms_empresas_destino     = [];

require_once BASEPATH . '/includes/empresa_layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-chat-square-dots me-2"></i>Centro de Comunicaciones</h1>
    <small class="text-muted">Mensajes, notificaciones y comunicados en un solo lugar.</small>
</div>

<?php require BASEPATH . '/includes/partials/comunicaciones_panel.php'; ?>

<?php require_once BASEPATH . '/includes/empresa_layout_footer.php'; ?>
