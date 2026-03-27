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

// Banners del carrusel (editables por ministerio)
$banners_home = [];
try {
    $db->query("SELECT 1 FROM banners_home LIMIT 1");
    $stmt = $db->query("SELECT * FROM banners_home WHERE activo = 1 ORDER BY orden ASC, id ASC");
    $banners_home = $stmt->fetchAll();
} catch (Exception $e) {
    $banners_home = [];
}

require_once BASEPATH . '/includes/header.php';
?>

<?php if (!empty($banners_home)): ?>
<?php
$hero_titulo_default = 'Portal Estratégico de Parques Industriales';
$hero_subtitulo_default = 'Información del desarrollo industrial de la provincia de Catamarca';
$hero_imagen_fallback = (defined('PUBLIC_URL') ? PUBLIC_URL : '') . '/img/hero-parque.jpg';
?>
<!-- Carrusel de banners (hero) -->
<section class="hero-section hero-carousel-wrap">
    <div id="heroCarousel" class="carousel slide carousel-fade h-100" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-inner h-100">
            <?php foreach ($banners_home as $i => $b): ?>
            <?php
            $slide_imagen = $hero_imagen_fallback;
            if ($b['tipo'] !== 'video' && !empty($b['imagen'])) {
                // Si es URL absoluta (Cloudinary, etc.) usarla tal cual; si no, ruta local
                $img = trim($b['imagen']);
                if (preg_match('#^https?://#i', $img)) {
                    $slide_imagen = $img;
                } elseif (defined('UPLOADS_URL')) {
                    $slide_imagen = UPLOADS_URL . '/' . $img;
                }
            }
            $slide_titulo = trim($b['titulo'] ?? '') ?: $hero_titulo_default;
            $slide_subtitulo = trim($b['subtitulo'] ?? '') ?: $hero_subtitulo_default;
            ?>
            <div class="carousel-item h-100 <?= $i === 0 ? 'active' : '' ?>">
                <?php if ($b['tipo'] === 'video' && !empty($b['url_video'])): ?>
                <div class="hero-slide hero-slide-video">
                    <iframe src="<?= e($b['url_video']) ?>" title="Video" allowfullscreen class="hero-video-iframe"></iframe>
                </div>
                <?php else: ?>
                <div class="hero-slide" style="background-image: url('<?= e($slide_imagen) ?>');"></div>
                <?php endif; ?>
                <div class="hero-content">
                    <h1><?= e($slide_titulo) ?></h1>
                    <p class="hero-subtitle"><?= e($slide_subtitulo) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($banners_home) > 1): ?>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Anterior</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Siguiente</span>
        </button>
        <div class="carousel-indicators">
            <?php foreach ($banners_home as $i => $b): ?>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>" aria-label="Slide <?= $i + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php else: ?>
<?php $hero_fallback_img = (defined('PUBLIC_URL') && PUBLIC_URL !== '') ? (PUBLIC_URL . '/img/hero-parque.jpg') : 'img/hero-parque.jpg'; ?>
<!-- Hero Section (fallback si no hay banners) -->
<section class="hero-section" style="background-image: url('<?= e($hero_fallback_img) ?>');">
    <div class="hero-content">
        <h1>Portal Estratégico de Parques Industriales</h1>
        <p class="hero-subtitle">Información del desarrollo industrial de la provincia de Catamarca</p>
    </div>
</section>
<?php endif; ?>

<!-- Cuadros con números: todos son botones (enlaces) -->
<div class="stat-cards">
    <a href="<?= PUBLIC_URL ?>/empresas.php" class="stat-card stat-card-link" title="Ver empresas">
        <div class="icon"><i class="bi bi-building"></i></div>
        <div class="number" data-count="<?= $stats['total_empresas_activas'] ?? 0 ?>"><?= $stats['total_empresas_activas'] ?? 0 ?></div>
        <div class="label">Empresas Activas</div>
    </a>
    <a href="<?= PUBLIC_URL ?>/noticias.php?tipo=empleados" class="stat-card stat-card-link" title="Noticias sobre empleados">
        <div class="icon"><i class="bi bi-people"></i></div>
        <div class="number" data-count="<?= $stats['total_empleados'] ?? 0 ?>"><?= format_number($stats['total_empleados'] ?? 0) ?></div>
        <div class="label">Empleados</div>
    </a>
    <a href="<?= PUBLIC_URL ?>/parque.php" class="stat-card stat-card-link" title="Mapa del parque industrial">
        <div class="icon"><i class="bi bi-grid"></i></div>
        <div class="number" data-count="<?= $stats['total_rubros'] ?? 0 ?>"><?= $stats['total_rubros'] ?? 0 ?></div>
        <div class="label">Sectores Industriales</div>
    </a>
    <a href="<?= PUBLIC_URL ?>/mapa.php" class="stat-card stat-card-link" title="Mapa interactivo">
        <div class="icon"><i class="bi bi-geo-alt"></i></div>
        <div class="number">4</div>
        <div class="label">Zonas Industriales</div>
    </a>
    <a href="<?= PUBLIC_URL ?>/estadisticas.php#huella" class="stat-card stat-card-link" title="Huella de carbono">
        <div class="icon"><i class="bi bi-cloud-arrow-down"></i></div>
        <div class="number"><?= $stats['huella_carbono'] ?? '400' ?></div>
        <div class="label">tCO2e Huella Carbono</div>
    </a>
</div>

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
                <?php
                $ejemplos = [
                    ['nombre' => 'Algodonera del Valle S.A.', 'rubro' => 'Textil', 'ubicacion' => 'PI El Pantanillo'],
                    ['nombre' => 'Botas Catamarca S.A.', 'rubro' => 'Calzados', 'ubicacion' => 'PI El Pantanillo'],
                    ['nombre' => 'Block S.R.L.', 'rubro' => 'Hormigón', 'ubicacion' => 'PI El Pantanillo'],
                    ['nombre' => 'INGES S.R.L', 'rubro' => 'Equipos Industriales', 'ubicacion' => 'PI El Pantanillo'],
                    ['nombre' => 'JL Uniformes S.R.L.', 'rubro' => 'Textil', 'ubicacion' => 'PI El Pantanillo'],
                    ['nombre' => 'ATC Antonio Tadeo Cabrera', 'rubro' => 'Metalúrgica', 'ubicacion' => 'PI El Pantanillo'],
                ];
                foreach ($ejemplos as $emp):
                    $card_options = ['show_visitas' => false, 'show_contact' => false, 'show_tel_button' => false];
                    require BASEPATH . '/includes/partials/card_empresa.php';
                endforeach;
                ?>
            <?php else: ?>
                <?php foreach ($empresas_destacadas as $emp):
                    $card_options = ['show_visitas' => true, 'show_contact' => false, 'show_tel_button' => false];
                    require BASEPATH . '/includes/partials/card_empresa.php';
                endforeach; ?>
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

<!-- Zona del parque: mapa desde internet (OSM) con polígono y contorno resaltado -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>Zona del Parque Industrial</h2>
            <p>Polígono del Parque Industrial El Pantanillo. Para ver las empresas en el mapa, ingresá al mapa interactivo.</p>
            <div class="section-divider"></div>
        </div>
        
        <div class="mapa-poligono-wrap map-container" style="height: 400px;">
            <div id="mapaParquePolygono"></div>
        </div>
        
        <div class="text-center mt-4">
            <a href="<?= PUBLIC_URL ?>/mapa.php" class="btn btn-primary btn-lg">
                <i class="bi bi-map me-2"></i>Ver mapa con empresas
            </a>
        </div>
    </div>
</section>

<?php
$rubros_json = json_encode(array_slice($rubros, 0, 10));
$extra_js = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    var rubrosData = $rubros_json;
    var labels = rubrosData.length > 0 ? rubrosData.map(function(r) { return r.nombre; }) : ['Textil', 'Construcción', 'Metalúrgica', 'Alimentos', 'Transporte', 'Reciclado', 'Hormigón', 'Otros'];
    var data = rubrosData.length > 0 ? rubrosData.map(function(r) { return r.total_empresas; }) : [14, 11, 5, 5, 5, 4, 3, 31];
    var colors = ['#3498db', '#e74c3c', '#95a5a6', '#27ae60', '#f39c12', '#2ecc71', '#7f8c8d', '#9b59b6', '#1abc9c', '#e67e22'];
    if (document.getElementById('chartRubros') && typeof Chart !== 'undefined') {
        new Chart(document.getElementById('chartRubros'), {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { padding: 15, usePointStyle: true } } } }
        });
    }
    if (typeof L !== 'undefined' && document.getElementById('mapaParquePolygono')) {
        var centroParque = [-28.4696, -65.7795];
        var mapParque = L.map('mapaParquePolygono').setView(centroParque, 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(mapParque);
        L.marker(centroParque).addTo(mapParque).bindPopup('<strong>Parque Industrial El Pantanillo</strong><br>Catamarca, Argentina');
        setTimeout(function() { mapParque.invalidateSize(); }, 300);
    }
});
</script>
JS;
require_once BASEPATH . '/includes/footer.php';
?>
