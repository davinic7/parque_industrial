<?php
/**
 * Cabecera HTML + layout panel ministerio (misma base visual que empresa).
 * Antes del require: $page_title (string), $ministerio_nav (clave del ítem activo).
 * Opcional: $extra_head, $ministerio_body_extra, $ministerio_badge_solicitudes (int; si no se pasa, se calcula).
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

$page_title = $page_title ?? 'Ministerio';
$ministerio_nav = $ministerio_nav ?? '';
$extra_head = $extra_head ?? '';
$ministerio_body_extra = $ministerio_body_extra ?? '';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_email = $_SESSION['user_email'] ?? '';
$topbar_name = $user_email !== '' ? $user_email : 'Ministerio';

// Centro de Comunicaciones — cargar helper (idempotente: uses require_once + guards internos)
require_once BASEPATH . '/includes/comunicaciones.php';
$coms_activo = FEATURE_CENTRO_COMS && coms_schema_disponible();

$badge_notif = 0;
$badge_inbox = 0;
$badge_coms  = 0;
$db_layout = null;
try {
    $db_layout = getDB();
    if ($user_id > 0) {
        $st = $db_layout->prepare('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0');
        $st->execute([$user_id]);
        $badge_notif = (int) $st->fetchColumn();
    }
    if ($coms_activo) {
        $badge_coms = coms_contar_no_leidos('ministerio', null);
    } else {
        $st = $db_layout->query('SELECT COUNT(*) FROM mensajes WHERE destinatario_id IS NULL AND leido = 0');
        $badge_inbox = $st ? (int) $st->fetchColumn() : 0;
    }
} catch (Throwable $e) {
    error_log('ministerio_layout_header: ' . $e->getMessage());
}

if (!isset($ministerio_badge_solicitudes) && $db_layout) {
    try {
        $ministerio_badge_solicitudes = (int) $db_layout->query(
            "SELECT COUNT(*) FROM solicitudes_proyecto WHERE estado = 'nueva'"
        )->fetchColumn();
    } catch (Throwable $e) {
        $ministerio_badge_solicitudes = 0;
    }
} else {
    $ministerio_badge_solicitudes = (int) ($ministerio_badge_solicitudes ?? 0);
}

$mn = static function (string $key) use ($ministerio_nav): string {
    return $ministerio_nav === $key ? ' active' : '';
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> · Ministerio · Parque Industrial</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&family=Roboto:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= PUBLIC_URL ?>/css/styles.css" rel="stylesheet">
    <link href="<?= PUBLIC_URL ?>/css/empresa-app.css" rel="stylesheet">
    <?= $extra_head ?>
</head>
<body class="ministerio-app-body<?= $ministerio_body_extra !== '' ? ' ' . e(trim($ministerio_body_extra)) : '' ?>">
<div class="empresa-app-backdrop" id="ministerioSidebarBackdrop" aria-hidden="true"></div>
<div class="empresa-app-layout">
    <aside class="empresa-sidebar" id="ministerioSidebar" aria-label="Menú ministerio">
        <div class="empresa-sidebar-brand">
            <a href="dashboard.php" class="empresa-sidebar-brand-link">
                <i class="fa-solid fa-landmark fa-lg" aria-hidden="true"></i>
                <span class="empresa-sidebar-brand-text">
                    <span class="empresa-sidebar-brand-title">Parque Industrial</span>
                    <small>Panel ministerio</small>
                </span>
            </a>
        </div>
        <nav class="empresa-sidebar-nav">
            <a href="dashboard.php" class="<?= $mn('dashboard') ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>

            <div class="empresa-sidebar-section">Empresas</div>
            <a href="empresas.php" class="<?= $mn('empresas') ?>"><i class="fa-solid fa-buildings"></i> Lista de empresas</a>
            <a href="nueva-empresa.php" class="<?= $mn('nueva_empresa') ?>"><i class="fa-solid fa-user-plus"></i> Nueva empresa</a>
            <a href="solicitudes-proyecto.php" class="<?= $mn('solicitudes') ?>"><i class="fa-solid fa-folder-open"></i> Solicitudes<?php if ($ministerio_badge_solicitudes > 0): ?> <span class="badge bg-warning text-dark rounded-pill"><?= $ministerio_badge_solicitudes > 99 ? '99+' : $ministerio_badge_solicitudes ?></span><?php endif; ?></a>

            <div class="empresa-sidebar-section">Formularios</div>
            <a href="formularios.php" class="<?= $mn('formularios') ?>"><i class="fa-solid fa-file-lines"></i> Declaraciones de datos</a>
            <a href="formularios-dinamicos.php" class="<?= $mn('formularios_dinamicos') ?>"><i class="fa-solid fa-list-check"></i> Plantillas</a>

            <div class="empresa-sidebar-section">Comunicación</div>
            <?php if ($coms_activo): ?>
            <a href="comunicaciones.php" class="<?= $mn('comunicaciones') ?>"><i class="fa-solid fa-comments"></i> Comunicaciones <span class="badge bg-danger rounded-pill<?= $badge_coms === 0 ? ' d-none' : '' ?>" id="coms-badge-sidebar"><?= $badge_coms > 99 ? '99+' : $badge_coms ?></span></a>
            <?php else: ?>
            <a href="mensajes-entrada.php" class="<?= $mn('mensajes_entrada') ?>"><i class="fa-solid fa-inbox"></i> Mensajes<?php if ($badge_inbox > 0): ?> <span class="badge bg-danger rounded-pill"><?= $badge_inbox > 99 ? '99+' : $badge_inbox ?></span><?php endif; ?></a>
            <a href="comunicados.php" class="<?= $mn('comunicados') ?>"><i class="fa-solid fa-paper-plane"></i> Comunicados</a>
            <?php endif; ?>

            <div class="empresa-sidebar-section">Sitio público</div>
            <a href="publicaciones.php" class="<?= $mn('publicaciones') ?>"><i class="fa-solid fa-bullhorn"></i> Publicaciones</a>
            <a href="banners.php" class="<?= $mn('banners') ?>"><i class="fa-solid fa-images"></i> Banners del inicio</a>
            <a href="nosotros-editar.php" class="<?= $mn('nosotros') ?>"><i class="fa-solid fa-pen-to-square"></i> Página El Parque</a>
            <a href="estadisticas-config.php" class="<?= $mn('estadisticas') ?>"><i class="fa-solid fa-chart-column"></i> Estadísticas públicas</a>

            <div class="empresa-sidebar-section">Analítica</div>
            <a href="graficos.php" class="<?= $mn('graficos') ?>"><i class="fa-solid fa-chart-line"></i> Gráficos y datos</a>
            <a href="exportar.php" class="<?= $mn('exportar') ?>"><i class="fa-solid fa-download"></i> Exportar datos</a>

            <div class="empresa-sidebar-section">Tu cuenta</div>
            <a href="notificaciones.php" class="<?= $mn('notificaciones') ?>"><i class="fa-solid fa-bell"></i> Notificaciones<?php if ($badge_notif > 0): ?> <span class="badge bg-primary rounded-pill"><?= $badge_notif > 99 ? '99+' : $badge_notif ?></span><?php endif; ?></a>
        </nav>
        <div class="empresa-sidebar-footer">
            <a href="<?= e(PUBLIC_URL) ?>/" target="_blank" rel="noopener"><i class="fa-solid fa-globe"></i> Ver sitio público</a>
        </div>
    </aside>
    <div class="empresa-app-content">
        <header class="empresa-topbar">
            <div class="empresa-topbar-left">
                <button type="button" class="empresa-menu-toggle" id="ministerioMenuToggle" aria-label="Abrir menú">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="empresa-topbar-title"><?= e($page_title) ?></h1>
            </div>
            <div class="empresa-topbar-actions">
                <div class="dropdown empresa-user-dd">
                    <button class="dropdown-toggle btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-circle-user fa-lg text-secondary"></i>
                        <span class="d-none d-sm-inline text-truncate" style="max-width: 10rem;"><?= e($topbar_name) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-2 small text-muted border-bottom mb-1"><?= e($user_email) ?></li>
                        <?php if ($coms_activo): ?>
                        <li><a class="dropdown-item" href="comunicaciones.php"><i class="fa-solid fa-comments me-2"></i>Comunicaciones <span class="badge bg-danger ms-1<?= $badge_coms === 0 ? ' d-none' : '' ?>" id="coms-badge-topbar"><?= $badge_coms > 99 ? '99+' : (int) $badge_coms ?></span></a></li>
                        <?php else: ?>
                        <li><a class="dropdown-item" href="mensajes-entrada.php"><i class="fa-solid fa-inbox me-2"></i>Mensajes empresas<?php if ($badge_inbox > 0): ?> <span class="badge bg-danger ms-1"><?= (int) $badge_inbox ?></span><?php endif; ?></a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="notificaciones.php"><i class="fa-solid fa-bell me-2"></i>Notificaciones<?php if ($badge_notif > 0): ?> <span class="badge bg-primary ms-1"><?= (int) $badge_notif ?></span><?php endif; ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= e(PUBLIC_URL) ?>/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Cerrar sesión</a></li>
                    </ul>
                </div>
            </div>
        </header>
        <main class="empresa-main">
<?php if ($coms_activo): ?>
<script>
(function () {
    'use strict';
    var badgeEls = [
        document.getElementById('coms-badge-sidebar'),
        document.getElementById('coms-badge-topbar')
    ];
    var apiUrl = '<?= rtrim(PUBLIC_URL, '/') ?>/api/comunicaciones/badge.php';

    function updateBadges(n) {
        var txt = n > 99 ? '99+' : String(n);
        badgeEls.forEach(function (el) {
            if (!el) return;
            el.textContent = txt;
            el.classList.toggle('d-none', n === 0);
        });
    }

    function fetchBadge() {
        fetch(apiUrl, { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && typeof data.no_leidos === 'number') {
                    updateBadges(data.no_leidos);
                }
            })
            .catch(function () { /* silencioso */ });
    }

    setInterval(fetchBadge, 30000);
}());
</script>
<?php endif; ?>
