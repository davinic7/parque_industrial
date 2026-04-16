<?php
/**
 * Dashboard Ministerio - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Dashboard Ministerio';
$db = getDB();

$stats = [
    'empresas_activas'      => 0,
    'empresas_pendientes'   => 0,
    'formularios_pendientes'=> 0,
    'publicaciones_revision'=> 0,
    'total_empleados'       => 0,
    'visitas_mes'           => 0,
];
$rubros_labels = [];
$rubros_values = [];
$actividad = [];

try {
    $stats['empresas_activas']       = (int) $db->query("SELECT COUNT(*) FROM empresas WHERE estado = 'activa'")->fetchColumn();
    $stats['empresas_pendientes']    = (int) $db->query("SELECT COUNT(*) FROM empresas WHERE estado = 'pendiente'")->fetchColumn();
    $stats['formularios_pendientes'] = (int) $db->query("SELECT COUNT(*) FROM datos_empresa WHERE estado = 'enviado'")->fetchColumn();
    $stats['publicaciones_revision'] = (int) $db->query("SELECT COUNT(*) FROM publicaciones WHERE estado = 'pendiente'")->fetchColumn();

    $emp_sum = $db->query("
        SELECT COALESCE(SUM(de.dotacion_total), 0) FROM datos_empresa de
        INNER JOIN (SELECT empresa_id, MAX(periodo) as max_periodo FROM datos_empresa WHERE estado IN ('enviado','aprobado') GROUP BY empresa_id) latest
        ON de.empresa_id = latest.empresa_id AND de.periodo = latest.max_periodo
    ")->fetchColumn();
    $stats['total_empleados'] = $emp_sum > 0 ? (int) $emp_sum : $stats['empresas_activas'] * 15;

    $rubros_data   = $db->query("
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
} catch (Exception $e) {
    error_log("Error en dashboard ministerio: " . $e->getMessage());
}

try {
    $stats['visitas_mes'] = (int) $db->query("SELECT COUNT(*) FROM visitas_empresa WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
} catch (Exception $e) {
    $stats['visitas_mes'] = 0;
}

$ministerio_nav = 'dashboard';
$extra_head = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <p class="text-muted small mb-1">Panel de control</p>
                <h2 class="h4 mb-0 fw-semibold">Dashboard del Ministerio</h2>
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
                        <?php if (empty($actividad)): ?>
                        <p class="text-muted text-center py-4 mb-0">Sin actividad reciente</p>
                        <?php else: ?>
                        <div class="empresa-timeline-scroll px-3 py-2">
                        <ul class="empresa-timeline mb-0">
                            <?php foreach ($actividad as $act):
                                $accion = (string) $act['accion'];
                                if (str_contains($accion, 'login') && !str_contains($accion, 'logout')) {
                                    $ic_class = 'ic-login'; $ic_bi = 'bi-box-arrow-in-right';
                                } elseif (str_contains($accion, 'logout')) {
                                    $ic_class = 'ic-logout'; $ic_bi = 'bi-box-arrow-right';
                                } elseif (str_contains($accion, 'perfil') || str_contains($accion, 'empresa')) {
                                    $ic_class = 'ic-perfil'; $ic_bi = 'bi-person-check';
                                } elseif (str_contains($accion, 'formulario') || str_contains($accion, 'datos')) {
                                    $ic_class = 'ic-form'; $ic_bi = 'bi-file-earmark-check';
                                } elseif (str_contains($accion, 'publicacion') || str_contains($accion, 'noticia')) {
                                    $ic_class = 'ic-pub'; $ic_bi = 'bi-megaphone';
                                } else {
                                    $ic_class = 'ic-default'; $ic_bi = 'bi-activity';
                                }
                                $subtitulo = trim(($act['empresa_nombre'] ?? '') ?: ($act['email'] ?? ''));
                            ?>
                            <li>
                                <span class="tl-icon <?= $ic_class ?>"><i class="bi <?= $ic_bi ?>"></i></span>
                                <div class="tl-body">
                                    <div class="tl-what"><?= e(str_replace('_', ' ', ucfirst($accion))) ?></div>
                                    <div class="tl-when"><?= $subtitulo ? e($subtitulo) . ' · ' : '' ?><?= e(format_datetime($act['created_at'])) ?></div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        </div>
                        <?php endif; ?>
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

<?php
$pu = htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8');
$labelsJson = safe_json_encode($rubros_labels);
$dataJson = json_encode($rubros_values);
$lat = (float) MAP_DEFAULT_LAT;
$lng = (float) MAP_DEFAULT_LNG;
$extra_scripts = <<<HTML
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
    <script src="{$pu}/js/parque-leaflet.js"></script>
    <script>
        new Chart(document.getElementById('chartRubros'), {
            type: 'bar',
            data: {
                labels: {$labelsJson},
                datasets: [{ label: 'Empresas', data: {$dataJson}, backgroundColor: ['#3498db','#e74c3c','#95a5a6','#27ae60','#f39c12','#9b59b6','#e67e22','#1abc9c'] }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
        const map = L.map('miniMap', { zoomControl: false, attributionControl: false });
        ParqueLeaflet.addSatelliteLayer(map);
        const poly = ParqueLeaflet.addParquePolygon(map);
        map.fitBounds(poly.getBounds(), { padding: [8, 8] });
        ParqueLeaflet.freezeMap(map);
    </script>
HTML;
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
