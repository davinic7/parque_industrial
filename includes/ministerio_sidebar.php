<?php
/**
 * Menú lateral del panel ministerio (única fuente de verdad).
 * Grupos: gestión de empresas vs. contenido del sitio público.
 * Antes del require: $ministerio_nav = 'dashboard'|'empresas'|...
 */
if (!defined('BASEPATH')) {
    exit('No se permite el acceso directo al script');
}
$ministerio_nav = $ministerio_nav ?? '';
$ministerio_badge_solicitudes = isset($ministerio_badge_solicitudes) ? (int)$ministerio_badge_solicitudes : 0;
$ministerio_badge_notificaciones = isset($ministerio_badge_notificaciones) ? (int)$ministerio_badge_notificaciones : 0;
$mnA = static function (string $key) use ($ministerio_nav): string {
    return $ministerio_nav === $key ? ' class="active"' : '';
};
?>
    <aside class="sidebar">
        <div class="sidebar-header"><span class="text-white fw-bold"><i class="bi bi-building me-2"></i>Ministerio</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"<?= $mnA('dashboard') ?>><i class="bi bi-speedometer2"></i> Dashboard</a>

            <div class="sidebar-menu-section-title">Gestión de empresas</div>
            <a href="empresas.php"<?= $mnA('empresas') ?>><i class="bi bi-buildings"></i> Empresas</a>
            <a href="nueva-empresa.php"<?= $mnA('nueva_empresa') ?>><i class="bi bi-plus-circle"></i> Nueva empresa</a>
            <a href="formularios.php"<?= $mnA('formularios') ?>><i class="bi bi-file-earmark-text"></i> Formularios de datos</a>
            <a href="formularios-dinamicos.php"<?= $mnA('formularios_dinamicos') ?>><i class="bi bi-ui-checks"></i> Formularios dinámicos</a>
            <a href="comunicados.php"<?= $mnA('comunicados') ?>><i class="bi bi-send"></i> Comunicados a empresas</a>
            <a href="mensajes-entrada.php"<?= $mnA('mensajes_entrada') ?>><i class="bi bi-inbox-fill"></i> Mensajes de empresas</a>
            <a href="solicitudes-proyecto.php"<?= $mnA('solicitudes') ?>><i class="bi bi-inbox"></i> Solicitudes proyecto<?php if ($ministerio_badge_solicitudes > 0): ?> <span class="badge bg-danger"><?= $ministerio_badge_solicitudes ?></span><?php endif; ?></a>
            <a href="graficos.php"<?= $mnA('graficos') ?>><i class="bi bi-graph-up"></i> Gráficos y datos</a>
            <a href="exportar.php"<?= $mnA('exportar') ?>><i class="bi bi-download"></i> Exportar datos</a>

            <div class="sidebar-menu-section-title">Sitio público</div>
            <a href="publicaciones.php"<?= $mnA('publicaciones') ?>><i class="bi bi-megaphone"></i> Publicaciones</a>
            <a href="banners.php"<?= $mnA('banners') ?>><i class="bi bi-images"></i> Banners del inicio</a>
            <a href="nosotros-editar.php"<?= $mnA('nosotros') ?>><i class="bi bi-pencil-square"></i> Página Nosotros</a>
            <a href="estadisticas-config.php"<?= $mnA('estadisticas') ?>><i class="bi bi-bar-chart"></i> Estadísticas públicas</a>

            <div class="sidebar-menu-section-title">Tu cuenta</div>
            <a href="notificaciones.php"<?= $mnA('notificaciones') ?>><i class="bi bi-bell"></i> Notificaciones<?php if ($ministerio_badge_notificaciones > 0): ?> <span class="badge bg-danger"><?= $ministerio_badge_notificaciones ?></span><?php endif; ?></a>

            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>
