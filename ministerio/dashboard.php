<?php
/**
 * Dashboard Ministerio - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Dashboard Ministerio';
$db = getDB();

$stats = [];
$stats['empresas_activas'] = $db->query("SELECT COUNT(*) FROM empresas WHERE estado = 'activa'")->fetchColumn();
$stats['empresas_pendientes'] = $db->query("SELECT COUNT(*) FROM empresas WHERE estado = 'pendiente'")->fetchColumn();
$stats['formularios_pendientes'] = $db->query("SELECT COUNT(*) FROM datos_empresa WHERE estado = 'enviado'")->fetchColumn();
$stats['publicaciones_revision'] = $db->query("SELECT COUNT(*) FROM publicaciones WHERE estado = 'pendiente'")->fetchColumn();
$stats['total_empleados'] = $db->query("
    SELECT COALESCE(SUM(de.dotacion_total), 0) FROM datos_empresa de
    INNER JOIN (SELECT empresa_id, MAX(periodo) as max_periodo FROM datos_empresa WHERE estado IN ('enviado','aprobado') GROUP BY empresa_id) latest
    ON de.empresa_id = latest.empresa_id AND de.periodo = latest.max_periodo
")->fetchColumn();
if ($stats['total_empleados'] == 0) {
    $stats['total_empleados'] = $stats['empresas_activas'] * 15;
}
$stats['visitas_mes'] = $db->query("SELECT COUNT(*) FROM visitas_empresa WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

$rubros_data = $db->query("
    SELECT rubro, COUNT(*) as total FROM empresas
    WHERE rubro IS NOT NULL AND rubro != '' AND estado = 'activa'
    GROUP BY rubro ORDER BY total DESC LIMIT 8
")->fetchAll();
$rubros_labels = array_column($rubros_data, 'rubro');
$rubros_values = array_column($rubros_data, 'total');

$actividad = $db->query("
    SELECT la.accion, la.created_at, u.email, e.nombre as empresa_nombre
    FROM log_actividad la
    LEFT JOIN usuarios u ON la.usuario_id = u.id
    LEFT JOIN empresas e ON la.empresa_id = e.id
    ORDER BY la.created_at DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - Parque Industrial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= PUBLIC_URL ?>/css/styles.css" rel="stylesheet">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header"><span class="text-white fw-bold"><i class="bi bi-building me-2"></i>Ministerio</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="empresas.php"><i class="bi bi-buildings"></i> Empresas</a>
            <a href="nueva-empresa.php"><i class="bi bi-plus-circle"></i> Nueva Empresa</a>
            <a href="formularios.php"><i class="bi bi-file-earmark-text"></i> Formularios</a>
            <a href="graficos.php"><i class="bi bi-graph-up"></i> Gráficos y Datos</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <a href="notificaciones.php"><i class="bi bi-bell"></i> Notificaciones</a>
            <a href="exportar.php"><i class="bi bi-download"></i> Exportar</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Dashboard del Ministerio</h1>
                <p class="text-muted mb-0">Panel de control del Parque Industrial</p>
            </div>
            <a href="nueva-empresa.php" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Nueva Empresa</a>
        </div>

        <?php show_flash(); ?>

        <div class="row g-4 mb-4">
            <div class="col-md-4 col-lg-2">
                <div class="dashboard-card text-center"><i class="bi bi-building fs-2 text-primary"></i><div class="card-value"><?= $stats['empresas_activas'] ?></div><div class="card-label">Empresas Activas</div></div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="dashboard-card warning text-center"><i class="bi bi-hourglass-split fs-2 text-warning"></i><div class="card-value"><?= $stats['empresas_pendientes'] ?></div><div class="card-label">Pendientes</div></div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="dashboard-card danger text-center"><i class="bi bi-file-earmark-text fs-2 text-danger"></i><div class="card-value"><?= $stats['formularios_pendientes'] ?></div><div class="card-label">Formularios</div></div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="dashboard-card text-center"><i class="bi bi-people fs-2 text-info"></i><div class="card-value"><?= format_number($stats['total_empleados']) ?></div><div class="card-label">Empleados</div></div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="dashboard-card success text-center"><i class="bi bi-eye fs-2 text-success"></i><div class="card-value"><?= format_number($stats['visitas_mes']) ?></div><div class="card-label">Visitas/mes</div></div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="dashboard-card text-center"><i class="bi bi-newspaper fs-2 text-secondary"></i><div class="card-value"><?= $stats['publicaciones_revision'] ?></div><div class="card-label">Por revisar</div></div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white"><h5 class="mb-0">Acciones Rápidas</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3"><a href="empresas.php" class="btn btn-outline-primary w-100 py-3"><i class="bi bi-buildings d-block fs-3 mb-2"></i>Gestionar Empresas</a></div>
                            <div class="col-md-3"><a href="formularios.php" class="btn btn-outline-warning w-100 py-3"><i class="bi bi-file-earmark-check d-block fs-3 mb-2"></i>Revisar Formularios</a></div>
                            <div class="col-md-3"><a href="graficos.php" class="btn btn-outline-success w-100 py-3"><i class="bi bi-bar-chart d-block fs-3 mb-2"></i>Ver Gráficos</a></div>
                            <div class="col-md-3"><a href="publicaciones.php" class="btn btn-outline-info w-100 py-3"><i class="bi bi-megaphone d-block fs-3 mb-2"></i>Publicaciones</a></div>
                        </div>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <h5 class="mb-0">Empresas por Rubro</h5>
                        <a href="graficos.php" class="btn btn-sm btn-outline-primary">Ver más</a>
                    </div>
                    <div class="card-body"><canvas id="chartRubros" height="200"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-white"><h5 class="mb-0">Actividad Reciente</h5></div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if (empty($actividad)): ?>
                            <div class="list-group-item text-center text-muted py-4">Sin actividad reciente</div>
                            <?php endif; ?>
                            <?php
                            $iconos = ['login' => 'bi-box-arrow-in-right text-primary', 'logout' => 'bi-box-arrow-left text-secondary', 'perfil_actualizado' => 'bi-pencil text-warning', 'formulario_enviado' => 'bi-file-earmark-check text-success', 'empresa_registrada' => 'bi-building text-primary'];
                            foreach ($actividad as $act):
                                $icono = $iconos[$act['accion']] ?? 'bi-circle text-muted';
                            ?>
                            <div class="list-group-item">
                                <div class="d-flex gap-3">
                                    <i class="bi <?= $icono ?>"></i>
                                    <div>
                                        <p class="mb-0 small"><strong><?= e(str_replace('_', ' ', ucfirst($act['accion']))) ?></strong></p>
                                        <small class="text-muted"><?= e($act['empresa_nombre'] ?? $act['email'] ?? '') ?> - <?= format_datetime($act['created_at']) ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <h5 class="mb-0">Mapa del Parque</h5>
                        <a href="<?= PUBLIC_URL ?>/mapa.php" target="_blank" class="btn btn-sm btn-outline-primary">Ampliar</a>
                    </div>
                    <div class="card-body p-0"><div id="miniMap" style="height: 200px;"></div></div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script>
        new Chart(document.getElementById('chartRubros'), {
            type: 'bar',
            data: {
                labels: <?= safe_json_encode($rubros_labels) ?>,
                datasets: [{ label: 'Empresas', data: <?= json_encode($rubros_values) ?>, backgroundColor: ['#3498db','#e74c3c','#95a5a6','#27ae60','#f39c12','#9b59b6','#e67e22','#1abc9c'] }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
        const map = L.map('miniMap').setView([<?= MAP_DEFAULT_LAT ?>, <?= MAP_DEFAULT_LNG ?>], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        L.marker([<?= MAP_DEFAULT_LAT ?>, <?= MAP_DEFAULT_LNG ?>]).addTo(map).bindPopup('Parque Industrial');
    </script>
</body>
</html>
