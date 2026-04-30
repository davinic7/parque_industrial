<?php
/**
 * Estadísticas Públicas - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../config/config.php';

$page_title = 'Estadísticas';

try {
    $db = getDB();

    // Estadísticas generales
    $stats = get_estadisticas_generales();

    // Empresas por rubro
    $rubros_data = get_rubros_con_conteo();

    // Empresas por ubicación
    $stmt = $db->query("SELECT ubicacion as nombre, COUNT(*) as total FROM empresas WHERE ubicacion IS NOT NULL GROUP BY ubicacion ORDER BY total DESC");
    $ubicaciones_data = $stmt->fetchAll();

    // Total por estado (si aplica)
    $stmt = $db->query("SELECT COUNT(*) as total FROM empresas");
    $total_empresas = $stmt->fetch()['total'];

} catch (Exception $e) {
    $stats = ['total_empresas' => 0, 'total_empresas_activas' => 0, 'total_empleados' => 0, 'total_rubros' => 0];
    $rubros_data = [];
    $ubicaciones_data = [];
    $total_empresas = 0;
}

// --- Datos reales desde datos_empresa ---
$evolucion_labels = [];
$evolucion_values = [];
$consumo_totales = ['energia' => 0, 'agua' => 0, 'gas' => 0];
$co2_real_labels = [];
$co2_real_values = [];
$tiene_datos_reales = false;

try {
    $db = getDB();

    // Evolución de empleo por período
    $stmt = $db->query("
        SELECT periodo, SUM(dotacion_total) AS empleados
        FROM datos_empresa
        WHERE estado IN ('enviado','aprobado')
        GROUP BY periodo ORDER BY periodo
    ");
    $evo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $evolucion_labels = array_column($evo, 'periodo');
    $evolucion_values = array_map('intval', array_column($evo, 'empleados'));

    // Consumos totales (último período)
    $ultimo_periodo = $db->query("SELECT MAX(periodo) FROM datos_empresa WHERE estado IN ('enviado','aprobado')")->fetchColumn();
    if ($ultimo_periodo) {
        $stmt = $db->prepare("
            SELECT SUM(COALESCE(consumo_energia,0)) AS energia,
                   SUM(COALESCE(consumo_agua,0)) AS agua,
                   SUM(COALESCE(consumo_gas,0)) AS gas
            FROM datos_empresa WHERE periodo = ? AND estado IN ('enviado','aprobado')
        ");
        $stmt->execute([$ultimo_periodo]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($c) {
            $consumo_totales = [
                'energia' => round((float)($c['energia'] ?? 0), 1),
                'agua' => round((float)($c['agua'] ?? 0), 1),
                'gas' => round((float)($c['gas'] ?? 0), 1),
            ];
        }
    }

    // CO2 real — sumado por período
    $stmt = $db->query("
        SELECT periodo, SUM(COALESCE(emisiones_co2, 0)) AS co2
        FROM datos_empresa
        WHERE estado IN ('enviado','aprobado') AND emisiones_co2 > 0
        GROUP BY periodo ORDER BY periodo
    ");
    $co2rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $co2_real_labels = array_column($co2rows, 'periodo');
    $co2_real_values = array_map('floatval', array_column($co2rows, 'co2'));

    $tiene_datos_reales = !empty($evolucion_labels) || !empty($co2_real_labels);
} catch (Throwable $e) {
    // sin datos reales, se muestran los de config
}

$visibles_json = get_config('estadisticas_visibles', '["header","rubros_pie","rubros_barras","ubicacion","resumen","distribucion","info"]');
$visibles = json_decode($visibles_json, true);
if (!is_array($visibles)) $visibles = ['header', 'rubros_pie', 'rubros_barras', 'ubicacion', 'resumen', 'distribucion', 'info'];

require_once BASEPATH . '/includes/header.php';
?>

<style>
.stats-header { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; padding: 40px 0; }
.stats-header h1 { font-size: 1.8rem; margin-bottom: 5px; }
.big-stat { font-size: 3.5rem; font-weight: 700; line-height: 1; }
.stat-box { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); height: 100%; }
.stat-box h3 { font-size: 1rem; color: var(--gray-600); margin-bottom: 15px; border-bottom: 2px solid var(--gray-200); padding-bottom: 10px; }
.chart-box { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
.chart-title { font-size: 1rem; color: var(--primary); font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--gray-200); }
.progress-custom { height: 28px; border-radius: 6px; margin-bottom: 10px; background: #eee; overflow: visible; position: relative; }
.progress-custom .bar { height: 100%; border-radius: 6px; display: flex; align-items: center; padding-left: 10px; color: #fff; font-weight: 500; font-size: 0.85rem; }
.progress-custom .count { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-weight: 600; color: #333; }
</style>

<?php if (in_array('header', $visibles)): ?>
<div class="stats-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1><i class="bi bi-graph-up me-2"></i>Estadísticas del Parque Industrial</h1>
                <p class="mb-0 opacity-75">Datos del desarrollo industrial de Catamarca</p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <div class="d-inline-block text-center me-4">
                    <div class="big-stat"><?= $stats['total_empresas_activas'] ?? $total_empresas ?></div>
                    <div class="small">Empresas</div>
                </div>
                <div class="d-inline-block text-center">
                    <div class="big-stat"><?= number_format($stats['total_empleados'] ?? 0) ?></div>
                    <div class="small">Empleados Est.</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="row g-4">
            <?php if (in_array('rubros_pie', $visibles)): ?>
            <div class="col-lg-6">
                <div class="chart-box h-100">
                    <div class="chart-title"><i class="bi bi-pie-chart me-2"></i>INDUSTRIAS POR SECTOR</div>
                    <canvas id="chartRubrosPie" height="280"></canvas>
                </div>
            </div>
            <?php endif; ?>
            <?php if (in_array('rubros_barras', $visibles)): ?>
            <div class="col-lg-6">
                <div class="chart-box h-100">
                    <div class="chart-title"><i class="bi bi-bar-chart me-2"></i>DISTRIBUCIÓN POR RUBRO</div>
                    <?php foreach ($rubros_data as $rubro): ?>
                    <div class="progress-custom">
                        <div class="bar" style="width: <?= $total_empresas > 0 ? min(100, ($rubro['total_empresas'] / $total_empresas * 100)) : 0 ?>%; background: <?= $rubro['color'] ?? '#3498db' ?>;">
                            <?= e($rubro['nombre']) ?>
                        </div>
                        <span class="count"><?= $rubro['total_empresas'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (in_array('ubicacion', $visibles)): ?>
            <div class="col-lg-4">
                <div class="chart-box h-100">
                    <div class="chart-title"><i class="bi bi-geo-alt me-2"></i>POR UBICACIÓN</div>
                    <?php foreach ($ubicaciones_data as $ub): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span><?= e($ub['nombre']) ?></span>
                        <span class="badge bg-primary"><?= $ub['total'] ?></span>
                    </div>
                    <div class="progress mb-3" style="height: 8px;">
                        <div class="progress-bar" style="width: <?= $total_empresas > 0 ? ($ub['total'] / $total_empresas * 100) : 0 ?>%;"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (in_array('resumen', $visibles)): ?>
            <div class="col-lg-4">
                <div class="chart-box h-100">
                    <div class="chart-title"><i class="bi bi-clipboard-data me-2"></i>RESUMEN</div>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="fs-2 fw-bold text-primary"><?= $total_empresas ?></div>
                                <small class="text-muted">Total Empresas</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="fs-2 fw-bold text-success"><?= count($rubros_data) ?></div>
                                <small class="text-muted">Sectores</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="fs-2 fw-bold text-info"><?= count($ubicaciones_data) ?></div>
                                <small class="text-muted">Ubicaciones</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="fs-2 fw-bold text-warning"><?= number_format($stats['total_empleados'] ?? 0) ?></div>
                                <small class="text-muted">Empleados Est.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if (in_array('distribucion', $visibles)): ?>
            <div class="col-lg-4">
                <div class="chart-box h-100">
                    <div class="chart-title"><i class="bi bi-pin-map me-2"></i>DISTRIBUCIÓN GEOGRÁFICA</div>
                    <canvas id="chartUbicaciones" height="200"></canvas>
                </div>
            </div>
            <?php endif; ?>
            <?php if (in_array('info', $visibles)): ?>
            <div class="col-12">
                <div class="chart-box">
                    <div class="chart-title"><i class="bi bi-info-circle me-2"></i>SOBRE ESTOS DATOS</div>
                    <p class="mb-0 text-muted">
                        Los datos presentados corresponden al registro actual de empresas del Parque Industrial de Catamarca.
                        La información de empleados es estimada en base a promedios del sector cuando no está disponible el dato exacto.
                        Para información más detallada, contacte al Ministerio de Producción.
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($evolucion_labels)): ?>
            <!-- Evolución de empleo -->
            <div class="col-lg-8">
                <div class="chart-box h-100">
                    <div class="chart-title"><i class="bi bi-graph-up-arrow me-2"></i>EVOLUCIÓN DE EMPLEO DECLARADO</div>
                    <canvas id="chartEvolucion" height="200"></canvas>
                    <p class="text-muted small mt-2 mb-0">Total de empleados declarados por período en las declaraciones juradas trimestrales.</p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($consumo_totales['energia'] > 0 || $consumo_totales['agua'] > 0 || $consumo_totales['gas'] > 0): ?>
            <!-- Consumos acumulados -->
            <div class="col-lg-4">
                <div class="chart-box h-100">
                    <div class="chart-title"><i class="bi bi-lightning-charge me-2"></i>CONSUMOS <?= $ultimo_periodo ? '(' . e($ultimo_periodo) . ')' : '' ?></div>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="fs-3 fw-bold text-warning"><?= number_format($consumo_totales['energia'], 0, ',', '.') ?></div>
                                <small class="text-muted">kWh Energia</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="fs-4 fw-bold text-primary"><?= number_format($consumo_totales['agua'], 0, ',', '.') ?></div>
                                <small class="text-muted">m3 Agua</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="fs-4 fw-bold text-secondary"><?= number_format($consumo_totales['gas'], 0, ',', '.') ?></div>
                                <small class="text-muted">m3 Gas</small>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Consumos acumulados del ultimo periodo declarado.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Gráfico Huella de Carbono -->
            <div class="col-12" id="huella">
                <div class="chart-box">
                    <div class="chart-title"><i class="bi bi-cloud-arrow-down me-2"></i>HUELLA DE CARBONO (tCO2e)</div>
                    <?php
                    // Intentar datos reales primero, fallback a config
                    if (!empty($co2_real_labels)) {
                        $co2_chart_labels = $co2_real_labels;
                        $co2_chart_values = $co2_real_values;
                        $co2_fuente = 'Datos calculados a partir de las declaraciones juradas de las empresas.';
                    } else {
                        $co2_raw = get_config('huella_carbono_anual', '{"2020":420,"2021":395,"2022":410,"2023":385,"2024":370,"2025":355}');
                        $co2_data = json_decode($co2_raw, true);
                        if (!is_array($co2_data) || empty($co2_data)) {
                            $co2_data = ['2020' => 420, '2021' => 395, '2022' => 410, '2023' => 385, '2024' => 370, '2025' => 355];
                        }
                        ksort($co2_data);
                        $co2_chart_labels = array_keys($co2_data);
                        $co2_chart_values = array_values($co2_data);
                        $co2_fuente = 'Valores estimados actualizados por el Ministerio de Produccion.';
                    }
                    ?>
                    <canvas id="chartCO2" height="100"></canvas>
                    <p class="text-muted small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Toneladas de CO2 equivalente emitidas por las empresas del parque industrial.
                        <?= e($co2_fuente) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rubrosData = <?= json_encode($rubros_data) ?>;
    const ubicacionesData = <?= json_encode($ubicaciones_data) ?>;
    
    const rubrosPieEl = document.getElementById('chartRubrosPie');
    if (rubrosPieEl && rubrosData.length > 0) {
        new Chart(rubrosPieEl, {
            type: 'doughnut',
            data: {
                labels: rubrosData.map(r => r.nombre),
                datasets: [{
                    data: rubrosData.map(r => r.total_empresas),
                    backgroundColor: rubrosData.map(r => r.color || '#3498db'),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'right', labels: { padding: 10, usePointStyle: true } }
                },
                cutout: '50%'
            }
        });
    }
    
    // Evolución de empleo
    const evoLabels = <?= json_encode($evolucion_labels, JSON_UNESCAPED_UNICODE) ?>;
    const evoValues = <?= json_encode($evolucion_values) ?>;
    const evoEl = document.getElementById('chartEvolucion');
    if (evoEl && evoLabels.length) {
        new Chart(evoEl, {
            type: 'line',
            data: {
                labels: evoLabels,
                datasets: [{
                    label: 'Empleados declarados',
                    data: evoValues,
                    borderColor: '#1a5276',
                    backgroundColor: 'rgba(26,82,118,0.1)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#1a5276'
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    const co2Labels = <?= json_encode($co2_chart_labels, JSON_UNESCAPED_UNICODE) ?>;
    const co2Values = <?= json_encode($co2_chart_values) ?>;
    const co2El = document.getElementById('chartCO2');
    if (co2El) {
        new Chart(co2El, {
            type: 'bar',
            data: {
                labels: co2Labels,
                datasets: [{
                    label: 'tCO2e',
                    data: co2Values,
                    backgroundColor: co2Values.map((v, i) => i === co2Values.length - 1 ? '#27ae60' : '#2980b9'),
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.parsed.y + ' tCO2e'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: { callback: v => v + ' t' },
                        grid: { color: '#f0f0f0' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    const ubicacionesEl = document.getElementById('chartUbicaciones');
    if (ubicacionesEl && ubicacionesData.length > 0) {
        new Chart(ubicacionesEl, {
            type: 'pie',
            data: {
                labels: ubicacionesData.map(u => u.nombre),
                datasets: [{
                    data: ubicacionesData.map(u => u.total),
                    backgroundColor: ['#1a5276', '#2980b9', '#3498db', '#5dade2', '#85c1e9']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
});
</script>

<?php require_once BASEPATH . '/includes/footer.php'; ?>
