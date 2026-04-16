<?php
/**
 * Cabecera HTML + layout panel empresa (sidebar + topbar).
 * Antes del require: $page_title (string), $empresa_nav en 'dashboard'|'formularios'|'publicaciones'|''.
 * Opcional: $extra_head (HTML para <head>).
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

$page_title = $page_title ?? 'Panel';
$empresa_nav = $empresa_nav ?? '';
$extra_head = $extra_head ?? '';
$empresa_body_extra = $empresa_body_extra ?? '';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$empresa_nombre = $_SESSION['empresa_nombre'] ?? 'Mi empresa';
$user_email = $_SESSION['user_email'] ?? '';

$badge_notif = 0;
$badge_msg = 0;
try {
    $db_layout = getDB();
    if ($user_id > 0) {
        $st = $db_layout->prepare('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0');
        $st->execute([$user_id]);
        $badge_notif = (int) $st->fetchColumn();
        $st = $db_layout->prepare('SELECT COUNT(*) FROM mensajes WHERE destinatario_id = ? AND leido = 0');
        $st->execute([$user_id]);
        $badge_msg = (int) $st->fetchColumn();
    }
} catch (Throwable $e) {
    error_log('empresa_layout_header: ' . $e->getMessage());
}

$nav = static function (string $key) use ($empresa_nav): string {
    return $empresa_nav === $key ? ' active' : '';
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> · Parque Industrial</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&family=Roboto:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="<?= PUBLIC_URL ?>/css/styles.css" rel="stylesheet">
    <link href="<?= PUBLIC_URL ?>/css/empresa-app.css" rel="stylesheet">
    <?= $extra_head ?>
</head>
<body class="empresa-app-body<?= $empresa_body_extra !== '' ? ' ' . e(trim($empresa_body_extra)) : '' ?>">
<div class="empresa-app-backdrop" id="empresaSidebarBackdrop" aria-hidden="true"></div>
<div class="empresa-app-layout">
    <aside class="empresa-sidebar" id="empresaSidebar" aria-label="Menú principal">
        <div class="empresa-sidebar-brand">
            <a href="dashboard.php" class="empresa-sidebar-brand-link">
                <i class="fa-solid fa-industry fa-lg" aria-hidden="true"></i>
                <span class="empresa-sidebar-brand-text">
                    <span class="empresa-sidebar-brand-title">Parque Industrial</span>
                    <small>Panel empresa</small>
                </span>
            </a>
        </div>
        <nav class="empresa-sidebar-nav">
            <a href="dashboard.php" class="<?= $nav('dashboard') ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a href="formularios.php" class="<?= $nav('formularios') ?>"><i class="fa-solid fa-file-lines"></i> Formularios</a>
            <a href="publicaciones.php" class="<?= $nav('publicaciones') ?>"><i class="fa-solid fa-bullhorn"></i> Publicaciones</a>
            <a href="solicitar-proyecto.php" class="<?= $nav('solicitar_proyecto') ?>"><i class="fa-solid fa-paper-plane"></i> Presentar proyecto</a>
        </nav>
        <div class="empresa-sidebar-footer">
            <a href="<?= e(PUBLIC_URL) ?>/" target="_blank" rel="noopener"><i class="fa-solid fa-globe"></i> Ver sitio público</a>
        </div>
    </aside>
    <div class="empresa-app-content">
        <header class="empresa-topbar">
            <div class="empresa-topbar-left">
                <button type="button" class="empresa-menu-toggle" id="empresaMenuToggle" aria-label="Abrir menú">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="empresa-topbar-title"><?= e($page_title) ?></h1>
            </div>
            <div class="empresa-topbar-actions">
                <a href="mensajes.php" class="empresa-icon-btn" title="Mensajes">
                    <i class="fa-solid fa-envelope fa-lg"></i>
                    <?php if ($badge_msg > 0): ?><span class="badge rounded-pill bg-danger"><?= $badge_msg > 99 ? '99+' : $badge_msg ?></span><?php endif; ?>
                </a>
                <a href="notificaciones.php" class="empresa-icon-btn" title="Notificaciones">
                    <i class="fa-solid fa-bell fa-lg"></i>
                    <?php if ($badge_notif > 0): ?><span class="badge rounded-pill bg-primary"><?= $badge_notif > 99 ? '99+' : $badge_notif ?></span><?php endif; ?>
                </a>
                <div class="dropdown empresa-user-dd">
                    <button class="dropdown-toggle btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-circle-user fa-lg text-secondary"></i>
                        <span class="d-none d-sm-inline text-truncate" style="max-width: 9rem;"><?= e($empresa_nombre) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-2 small text-muted border-bottom mb-1"><?= e($user_email) ?></li>
                        <li><a class="dropdown-item" href="perfil.php"><i class="fa-solid fa-building me-2"></i>Mi perfil</a></li>
                        <li><a class="dropdown-item" href="mensajes.php"><i class="fa-solid fa-inbox me-2"></i>Mensajes<?php if ($badge_msg > 0): ?> <span class="badge bg-danger ms-1"><?= (int) $badge_msg ?></span><?php endif; ?></a></li>
                        <li><a class="dropdown-item" href="notificaciones.php"><i class="fa-solid fa-bell me-2"></i>Notificaciones<?php if ($badge_notif > 0): ?> <span class="badge bg-primary ms-1"><?= (int) $badge_notif ?></span><?php endif; ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= e(PUBLIC_URL) ?>/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Cerrar sesión</a></li>
                    </ul>
                </div>
            </div>
        </header>
        <main class="empresa-main">
