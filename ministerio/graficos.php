<?php
/**
 * Gráficos y Datos - Ministerio
 */
require_once __DIR__ . '/../config/config.php';
if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;
$page_title = 'Gráficos y Datos';
$db = getDB();

// Empresas por rubro
$rubros_data = $db->query("
    SELECT rubro, COUNT(*) as total FROM empresas
    WHERE rubro IS NOT NULL AND rubro != '' AND estado = 'activa'
    GROUP BY rubro ORDER BY total DESC LIMIT 10
")->fetchAll();
$rubros_labels = safe_json_encode(array_column($rubros_data, 'rubro'));
$rubros_values = safe_json_encode(array_column($rubros_data, 'total'));

// Empleados por género (último período por empresa)
$empleo_data = $db->query("
    SELECT
        COALESCE(SUM(de.empleados_masculinos), 0) as masculinos,
        COALESCE(SUM(de.empleados_femeninos), 0) as femeninos,
        COALESCE(SUM(de.dotacion_total), 0) as total
    FROM datos_empresa de
    INNER JOIN (
        SELECT empresa_id, MAX(periodo) as max_periodo
        FROM datos_empresa WHERE estado IN ('enviado','aprobado')
        GROUP BY empresa_id
    ) latest ON de.empresa_id = latest.empresa_id AND de.periodo = latest.max_periodo
")->fetch();
$emp_masc = (int)$empleo_data['masculinos'];
$emp_fem = (int)$empleo_data['femeninos'];
$emp_otros = max(0, (int)$empleo_data['total'] - $emp_masc - $emp_fem);

// Empresas por estado
$estados_data = $db->query("
    SELECT estado, COUNT(*) as total FROM empresas GROUP BY estado ORDER BY total DESC
")->fetchAll();
$estados_labels = safe_json_encode(array_column($estados_data, 'estado'));
$estados_values = safe_json_encode(array_column($estados_data, 'total'));

// Consumo promedio de servicios
$consumo_data = $db->query("
    SELECT
        ROUND(AVG(consumo_energia), 2) as energia,
        ROUND(AVG(consumo_agua), 2) as agua,
        ROUND(AVG(consumo_gas), 2) as gas
    FROM datos_empresa
    WHERE estado IN ('enviado','aprobado')
    AND (consumo_energia > 0 OR consumo_agua > 0 OR consumo_gas > 0)
")->fetch();

// Ubicaciones de empresas para mapa
$ubicaciones_mapa = $db->query("
    SELECT nombre, latitud, longitud, rubro FROM empresas
    WHERE latitud IS NOT NULL AND longitud IS NOT NULL AND estado = 'activa'
")->fetchAll();

// Ubicaciones para select
$ubicaciones = $db->query("SELECT * FROM ubicaciones WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Empleados por rubro (top rubros)
$empleo_rubro = $db->query("
    SELECT e.rubro,
        COALESCE(SUM(de.empleados_masculinos), 0) as masc,
        COALESCE(SUM(de.empleados_femeninos), 0) as fem
    FROM datos_empresa de
    INNER JOIN empresas e ON de.empresa_id = e.id
    INNER JOIN (
        SELECT empresa_id, MAX(periodo) as max_periodo
        FROM datos_empresa WHERE estado IN ('enviado','aprobado')
        GROUP BY empresa_id
    ) latest ON de.empresa_id = latest.empresa_id AND de.periodo = latest.max_periodo
    WHERE e.rubro IS NOT NULL AND e.rubro != ''
    GROUP BY e.rubro ORDER BY (masc + fem) DESC LIMIT 6
")->fetchAll();
$empleo_rubro_labels = safe_json_encode(array_column($empleo_rubro, 'rubro'));
$empleo_rubro_masc = safe_json_encode(array_column($empleo_rubro, 'masc'));
$empleo_rubro_fem = safe_json_encode(array_column($empleo_rubro, 'fem'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - Ministerio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= PUBLIC_URL ?>/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header"><span class="text-white fw-bold"><i class="bi bi-building me-2"></i>Ministerio</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="empresas.php"><i class="bi bi-buildings"></i> Empresas</a>
            <a href="nueva-empresa.php"><i class="bi bi-plus-circle"></i> Nueva Empresa</a>
            <a href="formularios.php"><i class="bi bi-file-earmark-text"></i> Formularios</a>
            <a href="graficos.php" class="active"><i class="bi bi-graph-up"></i> Gráficos</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <a href="notificaciones.php"><i class="bi bi-bell"></i> Notificaciones</a>
            <a href="exportar.php"><i class="bi bi-download"></i> Exportar</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 class="h3 mb-4">Gráficos y Análisis de Datos</h1>

        <!-- KPIs rápidos -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <div class="card-value"><?= $empleo_data['total'] ?></div>
                    <div class="card-label">Empleados totales</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <div class="card-value"><?= number_format($consumo_data['energia'] ?? 0, 0) ?></div>
                    <div class="card-label">Energía prom. (kWh)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <div class="card-value"><?= number_format($consumo_data['agua'] ?? 0, 0) ?></div>
                    <div class="card-label">Agua prom. (m3)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <div class="card-value"><?= number_format($consumo_data['gas'] ?? 0, 0) ?></div>
                    <div class="card-label">Gas prom. (m3)</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white"><h5 class="mb-0">Empresas por Rubro</h5></div>
                    <div class="card-body"><canvas id="chartRubros" height="280"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white"><h5 class="mb-0">Empleados por Género y Rubro</h5></div>
                    <div class="card-body"><canvas id="chartEmpleados" height="280"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white"><h5 class="mb-0">Distribución por Estado</h5></div>
                    <div class="card-body"><canvas id="chartEstados" height="280"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white"><h5 class="mb-0">Composición de Empleo</h5></div>
                    <div class="card-body"><canvas id="chartGenero" height="280"></canvas></div>
                </div>
            </div>
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white"><h5 class="mb-0">Mapa de Empresas</h5></div>
                    <div class="card-body p-0"><div id="mapaEmpresas" style="height:400px;"></div></div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const colores = ['#3498db','#e74c3c','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#34495e','#16a085','#c0392b'];

        // Rubros
        new Chart(document.getElementById('chartRubros'), {
            type: 'doughnut',
            data: { labels: <?= $rubros_labels ?>, datasets: [{ data: <?= $rubros_values ?>, backgroundColor: colores }] },
            options: { plugins: { legend: { position: 'right' } } }
        });

        // Empleados por rubro
        new Chart(document.getElementById('chartEmpleados'), {
            type: 'bar',
            data: {
                labels: <?= $empleo_rubro_labels ?>,
                datasets: [
                    { label: 'Masculino', data: <?= $empleo_rubro_masc ?>, backgroundColor: '#3498db' },
                    { label: 'Femenino', data: <?= $empleo_rubro_fem ?>, backgroundColor: '#e91e63' }
                ]
            },
            options: { scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }, plugins: { legend: { position: 'top' } } }
        });

        // Estados
        new Chart(document.getElementById('chartEstados'), {
            type: 'pie',
            data: {
                labels: <?= $estados_labels ?>,
                datasets: [{ data: <?= $estados_values ?>, backgroundColor: ['#2ecc71','#f39c12','#e74c3c','#95a5a6'] }]
            },
            options: { plugins: { legend: { position: 'right' } } }
        });

        // Género general
        new Chart(document.getElementById('chartGenero'), {
            type: 'doughnut',
            data: {
                labels: ['Masculino', 'Femenino', 'Sin especificar'],
                datasets: [{ data: [<?= $emp_masc ?>, <?= $emp_fem ?>, <?= $emp_otros ?>], backgroundColor: ['#3498db','#e91e63','#bdc3c7'] }]
            },
            options: { plugins: { legend: { position: 'right' } } }
        });

        // Mapa
        const map = L.map('mapaEmpresas').setView([<?= MAP_DEFAULT_LAT ?>, <?= MAP_DEFAULT_LNG ?>], <?= MAP_DEFAULT_ZOOM ?>);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        const empresas = <?= safe_json_encode($ubicaciones_mapa) ?>;
        empresas.forEach(e => {
            if (e.latitud && e.longitud) {
                L.marker([parseFloat(e.latitud), parseFloat(e.longitud)])
                    .addTo(map)
                    .bindPopup('<strong>' + e.nombre + '</strong><br>' + (e.rubro || ''));
            }
        });
    </script>
</body>
</html>
