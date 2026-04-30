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

// Consumos por rubro (último período)
$consumo_labels = [];
$consumo_energia = [];
$consumo_agua = [];
$consumo_gas = [];
try {
    if ($ultimo_periodo) {
        $stmt = $db->prepare("
            SELECT e.rubro,
                   SUM(COALESCE(de.consumo_energia,0)) AS energia,
                   SUM(COALESCE(de.consumo_agua,0)) AS agua,
                   SUM(COALESCE(de.consumo_gas,0)) AS gas
            FROM datos_empresa de
            INNER JOIN empresas e ON de.empresa_id = e.id
            WHERE de.periodo = ? AND e.rubro IS NOT NULL AND e.rubro <> ''
            GROUP BY e.rubro ORDER BY energia DESC
        ");
        $stmt->execute([$ultimo_periodo]);
        $consumo_data = $stmt->fetchAll();
        $consumo_labels  = array_column($consumo_data, 'rubro');
        $consumo_energia = array_map('floatval', array_column($consumo_data, 'energia'));
        $consumo_agua    = array_map('floatval', array_column($consumo_data, 'agua'));
        $consumo_gas     = array_map('floatval', array_column($consumo_data, 'gas'));
    }
} catch (Throwable $e) {}

// Emisiones CO2 por empresa (último período)
$co2_labels = [];
$co2_values = [];
try {
    if ($ultimo_periodo) {
        $stmt = $db->prepare("
            SELECT e.nombre, COALESCE(de.emisiones_co2, 0) AS co2
            FROM datos_empresa de
            INNER JOIN empresas e ON de.empresa_id = e.id
            WHERE de.periodo = ? AND de.emisiones_co2 > 0
            ORDER BY co2 DESC LIMIT 10
        ");
        $stmt->execute([$ultimo_periodo]);
        $co2_data   = $stmt->fetchAll();
        $co2_labels = array_column($co2_data, 'nombre');
        $co2_values = array_map('floatval', array_column($co2_data, 'co2'));
    }
} catch (Throwable $e) {}

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
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 class="h3 mb-0"><i class="bi bi-bar-chart-line me-2"></i>Gráficos y Análisis</h1>
            <div class="d-flex gap-2">
                <a href="exportar.php" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar Excel</a>
            </div>
        </div>
        <?php if ($ultimo_periodo): ?>
        <div class="alert alert-info small py-2 mb-4">
            <i class="bi bi-info-circle me-1"></i>Los gráficos de empleo, consumos y CO₂ corresponden al último período declarado: <strong><?= e($ultimo_periodo) ?></strong>
        </div>
        <?php endif; ?>
        
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

            <!-- Consumos por rubro -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-lightning-charge text-warning me-2"></i>Consumos por rubro <?= $ultimo_periodo ? '(' . e($ultimo_periodo) . ')' : '' ?></h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartConsumos" height="220"></canvas>
                        <p class="text-muted small mt-2 mb-0">Energía (kWh), agua (m³) y gas (m³) acumulados por rubro.</p>
                    </div>
                </div>
            </div>

            <!-- Emisiones CO2 -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-cloud-haze2 text-danger me-2"></i>Huella de carbono (tCO₂e)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartCO2" height="300"></canvas>
                        <p class="text-muted small mt-2 mb-0">Top 10 empresas con mayor emisión declarada.</p>
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
        const consumoLabels  = ' . json_encode($consumo_labels, JSON_UNESCAPED_UNICODE) . ';
        const consumoEnergia = ' . json_encode($consumo_energia) . ';
        const consumoAgua    = ' . json_encode($consumo_agua) . ';
        const consumoGas     = ' . json_encode($consumo_gas) . ';
        const co2Labels = ' . json_encode($co2_labels, JSON_UNESCAPED_UNICODE) . ';
        const co2Values = ' . json_encode($co2_values) . ';

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

        // Consumos por rubro
        const ctxConsumos = document.getElementById("chartConsumos");
        if (ctxConsumos && consumoLabels.length) {
            new Chart(ctxConsumos, {
                type: "bar",
                data: {
                    labels: consumoLabels,
                    datasets: [
                        { label: "Energía (kWh)", data: consumoEnergia, backgroundColor: "#f39c12" },
                        { label: "Agua (m³)", data: consumoAgua, backgroundColor: "#3498db" },
                        { label: "Gas (m³)", data: consumoGas, backgroundColor: "#95a5a6" }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: "top" } } }
            });
        }

        // CO2 por empresa
        const ctxCO2 = document.getElementById("chartCO2");
        if (ctxCO2 && co2Labels.length) {
            new Chart(ctxCO2, {
                type: "bar",
                data: {
                    labels: co2Labels,
                    datasets: [{
                        label: "tCO₂e",
                        data: co2Values,
                        backgroundColor: "#e74c3c",
                        borderRadius: 4
                    }]
                },
                options: {
                    indexAxis: "y",
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true } }
                }
            });
        } else if (ctxCO2) {
            ctxCO2.parentElement.innerHTML += "<p class=\\"text-muted text-center small\\">No hay datos de emisiones declarados en el último período.</p>";
        }
    </script>
';
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
