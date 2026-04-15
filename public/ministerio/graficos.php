<?php
/**
 * Gráficos y Datos - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Gráficos y Datos';
$db = getDB();

// Empresas por rubro
try {
    $stmt = $db->query("
        SELECT rubro, COUNT(*) as total
        FROM empresas
        WHERE rubro IS NOT NULL AND rubro <> ''
        GROUP BY rubro
        ORDER BY total DESC
        LIMIT 10
    ");
    $rubros_data = $stmt->fetchAll();
} catch (Exception $e) {
    $rubros_data = [];
}

$rubros_labels = array_column($rubros_data, 'rubro');
$rubros_values = array_map('intval', array_column($rubros_data, 'total'));

// Empleados por género (último período disponible)
try {
    $stmt = $db->query("SELECT MAX(periodo) FROM datos_empresa");
    $ultimo_periodo = $stmt->fetchColumn();

    if ($ultimo_periodo) {
        $stmt = $db->prepare("
            SELECT e.rubro,
                   SUM(de.empleados_masculinos) AS masc,
                   SUM(de.empleados_femeninos) AS fem
            FROM datos_empresa de
            INNER JOIN empresas e ON de.empresa_id = e.id
            WHERE de.periodo = ?
            GROUP BY e.rubro
            ORDER BY e.rubro
        ");
        $stmt->execute([$ultimo_periodo]);
        $empleo_data = $stmt->fetchAll();
    } else {
        $empleo_data = [];
    }
} catch (Exception $e) {
    $empleo_data = [];
    $ultimo_periodo = null;
}

$empleo_labels = array_column($empleo_data, 'rubro');
$empleo_masc = array_map('intval', array_column($empleo_data, 'masc'));
$empleo_fem = array_map('intval', array_column($empleo_data, 'fem'));

// Evolución de empleo por período
try {
    $stmt = $db->query("
        SELECT periodo,
               SUM(dotacion_total) AS empleados
        FROM datos_empresa
        GROUP BY periodo
        ORDER BY periodo
    ");
    $evolucion_data = $stmt->fetchAll();
} catch (Exception $e) {
    $evolucion_data = [];
}

$evolucion_labels = array_column($evolucion_data, 'periodo');
$evolucion_values = array_map('intval', array_column($evolucion_data, 'empleados'));

// Puntos para mapa de calor (empresas con coordenadas y dotación)
try {
    $stmt = $db->query("
        SELECT e.latitud, e.longitud,
               COALESCE(de.dotacion_total, 0) AS empleados
        FROM v_empresas_completas e
        WHERE e.latitud IS NOT NULL AND e.longitud IS NOT NULL
    ");
    $heat_data = $stmt->fetchAll();
} catch (Exception $e) {
    $heat_data = [];
}

$ministerio_nav = 'graficos';
$extra_head = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Gráficos y Análisis</h1>
            <button class="btn btn-success"><i class="bi bi-download me-1"></i>Exportar PDF</button>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Desde</label>
                        <input type="date" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ubicación</label>
                        <select class="form-select">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Empresas por rubro</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartRubros" height="250"></canvas>
                        <p class="text-muted small mt-2 mb-0">
                            Muestra las empresas activas agrupadas por rubro (top 10).
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Empleo por género <?= $ultimo_periodo ? '(' . e($ultimo_periodo) . ')' : '' ?></h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartEmpleados" height="250"></canvas>
                        <p class="text-muted small mt-2 mb-0">
                            Suma de empleados masculinos y femeninos por rubro para el último período declarado.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Evolución de empleo declarado</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartEvolucion" height="200"></canvas>
                        <p class="text-muted small mt-2 mb-0">
                            Total de empleados declarados por período en los formularios trimestrales.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Mapa de calor de empleo</h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="heatMap" style="height:300px;"></div>
                    </div>
                </div>
            </div>
        </div>

<?php
$pu = htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8');
$mapLat = (float) MAP_DEFAULT_LAT;
$mapLng = (float) MAP_DEFAULT_LNG;
$extra_scripts = '
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
    <script src="' . $pu . '/js/parque-leaflet.js"></script>
    <script>
        const rubrosLabels = ' . json_encode($rubros_labels, JSON_UNESCAPED_UNICODE) . ';
        const rubrosValues = ' . json_encode($rubros_values) . ';
        const empleoLabels = ' . json_encode($empleo_labels, JSON_UNESCAPED_UNICODE) . ';
        const empleoMasc = ' . json_encode($empleo_masc) . ';
        const empleoFem = ' . json_encode($empleo_fem) . ';
        const evolucionLabels = ' . json_encode($evolucion_labels, JSON_UNESCAPED_UNICODE) . ';
        const evolucionValues = ' . json_encode($evolucion_values) . ';
        const heatPoints = ' . json_encode($heat_data) . ';

        const ctxRubros = document.getElementById("chartRubros");
        if (ctxRubros && rubrosLabels.length) {
            new Chart(ctxRubros, {
                type: "doughnut",
                data: {
                    labels: rubrosLabels,
                    datasets: [{
                        data: rubrosValues,
                        backgroundColor: ["#3498db","#e74c3c","#95a5a6","#27ae60","#f39c12","#9b59b6","#1abc9c","#34495e","#2ecc71","#bdc3c7"]
                    }]
                },
                options: { plugins: { legend: { position: "right" } } }
            });
        }

        const ctxEmp = document.getElementById("chartEmpleados");
        if (ctxEmp && empleoLabels.length) {
            new Chart(ctxEmp, {
                type: "bar",
                data: {
                    labels: empleoLabels,
                    datasets: [
                        { label: "Masculino", data: empleoMasc, backgroundColor: "#3498db" },
                        { label: "Femenino", data: empleoFem, backgroundColor: "#e91e63" }
                    ]
                },
                options: { responsive: true, scales: { x: { stacked: true }, y: { stacked: true } } }
            });
        }

        const ctxEvo = document.getElementById("chartEvolucion");
        if (ctxEvo && evolucionLabels.length) {
            new Chart(ctxEvo, {
                type: "line",
                data: {
                    labels: evolucionLabels,
                    datasets: [{
                        label: "Empleados declarados",
                        data: evolucionValues,
                        borderColor: "#1a5276",
                        backgroundColor: "rgba(26,82,118,0.1)",
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: { responsive: true, plugins: { legend: { display: false } } }
            });
        }

        const map = L.map("heatMap").setView([' . $mapLat . ', ' . $mapLng . '], 12);
        ParqueLeaflet.addSatelliteLayer(map);
        ParqueLeaflet.addParquePolygon(map);
        if (heatPoints && heatPoints.length) {
            heatPoints.forEach(function(p) {
                const lat = parseFloat(p.latitud);
                const lng = parseFloat(p.longitud);
                const empleados = parseInt(p.empleados, 10) || 0;
                if (!isNaN(lat) && !isNaN(lng)) {
                    const radius = 50 + (empleados * 2);
                    L.circle([lat, lng], {
                        radius: radius,
                        color: "#e74c3c",
                        fillColor: "#e74c3c",
                        fillOpacity: 0.4,
                        weight: 1
                    }).addTo(map);
                }
            });
        }
    </script>
';
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
