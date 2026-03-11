<?php
/**
 * Página Principal - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../config/config.php';

$page_title = 'Inicio';

// Obtener estadísticas
$stats = get_estadisticas_generales();

// Obtener empresas destacadas
try {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM empresas WHERE estado = 'activa' ORDER BY visitas DESC LIMIT 6");
    $empresas_destacadas = $stmt->fetchAll();
} catch (Exception $e) {
    $empresas_destacadas = [];
}

// Obtener últimas noticias
try {
    $stmt = $db->query("SELECT p.*, e.nombre as empresa_nombre FROM publicaciones p
                        LEFT JOIN empresas e ON p.empresa_id = e.id
                        WHERE p.estado = 'aprobado'
                        ORDER BY p.created_at DESC LIMIT 3");
    $noticias = $stmt->fetchAll();
} catch (Exception $e) {
    $noticias = [];
}

// Obtener rubros para gráfico
$rubros = get_rubros_con_conteo();

require_once BASEPATH . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section" style="background-image: url('<?= PUBLIC_URL ?>/img/hero-parque.jpg');">
    <div class="hero-content">
        <h1>Portal Estratégico de Parques Industriales</h1>
        <p class="hero-subtitle">Información del desarrollo industrial de la provincia de Catamarca</p>
    </div>
</section>

<!-- Stat Cards -->
<div class="stat-cards">
    <div class="stat-card">
        <div class="icon"><i class="bi bi-building"></i></div>
        <div class="number" data-count="<?= $stats['total_empresas_activas'] ?? 78 ?>"><?= $stats['total_empresas_activas'] ?? 78 ?></div>
        <div class="label">Empresas Activas</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="bi bi-people"></i></div>
        <div class="number" data-count="<?= $stats['total_empleados'] ?? 1250 ?>"><?= format_number($stats['total_empleados'] ?? 1250) ?></div>
        <div class="label">Empleados</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="bi bi-grid"></i></div>
        <div class="number" data-count="<?= $stats['total_rubros'] ?? 18 ?>"><?= $stats['total_rubros'] ?? 18 ?></div>
        <div class="label">Sectores Industriales</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="bi bi-geo-alt"></i></div>
        <div class="number">4</div>
        <div class="label">Zonas Industriales</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="bi bi-cloud-arrow-down"></i></div>
        <div class="number">400</div>
        <div class="label">tCO2e Huella Carbono</div>
    </div>
</div>

<!-- Accesos Rápidos -->
<section class="section bg-white">
    <div class="container">
        <div class="row g-4 justify-content-center">
            <div class="col-md-4 col-lg-2">
                <a href="<?= PUBLIC_URL ?>/mapa.php" class="stat-card d-block text-decoration-none h-100">
                    <div class="icon" style="background: linear-gradient(135deg, #3498db, #2980b9);"><i class="bi bi-map"></i></div>
                    <div class="label fw-bold mt-2">Mapa Interactivo</div>
                </a>
            </div>
            <div class="col-md-4 col-lg-2">
                <a href="<?= PUBLIC_URL ?>/empresas.php" class="stat-card d-block text-decoration-none h-100">
                    <div class="icon" style="background: linear-gradient(135deg, #27ae60, #1e8449);"><i class="bi bi-buildings"></i></div>
                    <div class="label fw-bold mt-2">Industrias</div>
                </a>
            </div>
            <div class="col-md-4 col-lg-2">
                <a href="<?= PUBLIC_URL ?>/estadisticas.php" class="stat-card d-block text-decoration-none h-100">
                    <div class="icon" style="background: linear-gradient(135deg, #f39c12, #d68910);"><i class="bi bi-bar-chart"></i></div>
                    <div class="label fw-bold mt-2">Estadísticas</div>
                </a>
            </div>
            <div class="col-md-4 col-lg-2">
                <a href="<?= PUBLIC_URL ?>/estadisticas.php#huella" class="stat-card d-block text-decoration-none h-100">
                    <div class="icon" style="background: linear-gradient(135deg, #1abc9c, #16a085);"><i class="bi bi-cloud"></i></div>
                    <div class="label fw-bold mt-2">Huella de Carbono</div>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Empresas Destacadas -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>Empresas del Parque Industrial</h2>
            <p>Conocé las empresas que impulsan el desarrollo industrial de Catamarca</p>
            <div class="section-divider"></div>
        </div>
        
        <div class="row g-4">
            <?php if (empty($empresas_destacadas)): ?>
                <!-- Datos de ejemplo cuando no hay empresas -->
                <?php 
                $ejemplos = [
                    ['nombre' => 'Algodonera del Valle S.A.', 'rubro' => 'Textil', 'ubicacion' => 'PI El Pantanillo'],
                    ['nombre' => 'Botas Catamarca S.A.', 'rubro' => 'Calzados', 'ubicacion' => 'PI El Pantanillo'],
                    ['nombre' => 'Block S.R.L.', 'rubro' => 'Hormigón', 'ubicacion' => 'PI El Pantanillo'],
                    ['nombre' => 'INGES S.R.L', 'rubro' => 'Equipos Industriales', 'ubicacion' => 'PI El Pantanillo'],
                    ['nombre' => 'JL Uniformes S.R.L.', 'rubro' => 'Textil', 'ubicacion' => 'PI El Pantanillo'],
                    ['nombre' => 'ATC Antonio Tadeo Cabrera', 'rubro' => 'Metalúrgica', 'ubicacion' => 'PI El Pantanillo'],
                ];
                foreach ($ejemplos as $emp): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="empresa-card">
                        <div class="card-img">
                            <i class="bi bi-building placeholder-icon" style="font-size: 4rem; color: #ccc;"></i>
                        </div>
                        <div class="card-body">
                            <span class="card-rubro"><?= e($emp['rubro']) ?></span>
                            <h5 class="card-title"><?= e($emp['nombre']) ?></h5>
                            <p class="card-text"><i class="bi bi-geo-alt"></i> <?= e($emp['ubicacion']) ?></p>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="#" class="btn btn-sm btn-outline-primary">Ver perfil</a>
                            <small class="text-muted"><i class="bi bi-eye"></i> --</small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($empresas_destacadas as $emp): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="empresa-card">
                        <div class="card-img">
                            <?php if ($emp['logo']): ?>
                                <img src="<?= UPLOADS_URL ?>/logos/<?= e($emp['logo']) ?>" alt="<?= e($emp['nombre']) ?>">
                            <?php else: ?>
                                <i class="bi bi-building placeholder-icon" style="font-size: 4rem; color: #ccc;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <span class="card-rubro"><?= e($emp['rubro']) ?></span>
                            <h5 class="card-title"><?= e($emp['nombre']) ?></h5>
                            <p class="card-text"><i class="bi bi-geo-alt"></i> <?= e($emp['ubicacion']) ?></p>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="<?= PUBLIC_URL ?>/empresa.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-outline-primary">Ver perfil</a>
                            <small class="text-muted"><i class="bi bi-eye"></i> <?= format_number($emp['visitas']) ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="<?= PUBLIC_URL ?>/empresas.php" class="btn btn-primary btn-lg">
                <i class="bi bi-grid me-2"></i>Ver todas las empresas
            </a>
        </div>
    </div>
</section>

<!-- Gráfico de Rubros -->
<section class="section bg-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-5">
                <h2 class="text-primary mb-4">Industrias por Sector</h2>
                <p>El Parque Industrial de Catamarca cuenta con empresas de diversos sectores productivos, destacándose la industria textil, construcción y metalúrgica.</p>
                <a href="<?= PUBLIC_URL ?>/estadisticas.php" class="btn btn-primary mt-3">
                    <i class="bi bi-graph-up me-2"></i>Ver estadísticas completas
                </a>
            </div>
            <div class="col-lg-7">
                <div class="chart-container">
                    <canvas id="chartRubros" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mapa Preview -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>Ubicación del Parque Industrial</h2>
            <p>Explorá la ubicación de las empresas en el mapa interactivo</p>
            <div class="section-divider"></div>
        </div>
        
        <div class="map-container" style="height: 400px;">
            <div id="mapPreview"></div>
        </div>
        
        <div class="text-center mt-4">
            <a href="<?= PUBLIC_URL ?>/mapa.php" class="btn btn-primary btn-lg">
                <i class="bi bi-map me-2"></i>Ver mapa completo
            </a>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de Rubros
    const rubrosData = <?= json_encode(array_slice($rubros, 0, 10)) ?>;
    
    // Si no hay datos, usar datos de ejemplo
    const labels = rubrosData.length > 0 
        ? rubrosData.map(r => r.nombre)
        : ['Textil', 'Construcción', 'Metalúrgica', 'Alimentos', 'Transporte', 'Reciclado', 'Hormigón', 'Otros'];
    
    const data = rubrosData.length > 0 
        ? rubrosData.map(r => r.total_empresas)
        : [14, 11, 5, 5, 5, 4, 3, 31];

    const colors = ['#3498db', '#e74c3c', '#95a5a6', '#27ae60', '#f39c12', '#2ecc71', '#7f8c8d', '#9b59b6', '#1abc9c', '#e67e22'];

    new Chart(document.getElementById('chartRubros'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { padding: 15, usePointStyle: true }
                }
            }
        }
    });

    // Mapa Preview
    const map = L.map('mapPreview').setView([-28.4696, -65.7795], 12);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Marcador del parque industrial
    L.marker([-28.4696, -65.7795])
        .addTo(map)
        .bindPopup('<strong>Parque Industrial El Pantanillo</strong><br>Catamarca, Argentina');
});
</script>

<?php require_once BASEPATH . '/includes/footer.php'; ?>
