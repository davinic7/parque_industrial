<?php
/**
 * El Parque Industrial El Pantanillo – página unificada
 * Fusiona: parque.php (ubicación, sectores) + nosotros.php (institucional, servicios, contacto)
 */
require_once __DIR__ . '/../config/config.php';

$page_title = 'El Parque Industrial';
$db = getDB();

// ── Datos del parque ────────────────────────────────────────────────────────
try {
    $total_activas = (int) $db->query("SELECT COUNT(*) FROM empresas WHERE estado = 'activa'")->fetchColumn();
} catch (Exception $e) {
    $total_activas = 0;
}

try {
    $stmt = $db->query("SELECT rubro, COUNT(*) as total FROM empresas WHERE rubro IS NOT NULL AND rubro != '' AND estado = 'activa' GROUP BY rubro ORDER BY total DESC");
    $sectores = $stmt->fetchAll();
} catch (Exception $e) {
    $sectores = [];
}

$stats = get_estadisticas_generales();

// ── Contenido editable (configuracion_sitio) ────────────────────────────────
$titulo            = get_config('nosotros_titulo',    'Parque Industrial de Catamarca');
$subtitulo         = get_config('nosotros_subtitulo', 'Impulsando el desarrollo productivo de la provincia');
$texto_parque      = get_config('nosotros_texto',
    "El Parque Industrial de Catamarca es un polo productivo estratégico que reúne a empresas de diversos rubros, brindando infraestructura, servicios y un entorno favorable para el crecimiento industrial de la provincia.\n\n" .
    "Gestionado por el Ministerio de Industria, Comercio y Empleo, el parque ofrece a las empresas radicadas acceso a servicios esenciales como red eléctrica, gas natural, agua potable, conectividad y seguridad.\n\n" .
    "Nuestra misión es promover la inversión productiva, generar empleo genuino y contribuir al desarrollo sustentable de Catamarca.");
$contacto_dir      = get_config('nosotros_contacto_direccion', "Parque Industrial de Catamarca\nSan Fernando del Valle de Catamarca\nCatamarca, Argentina");
$contacto_email    = get_config('nosotros_contacto_email',    'parqueindustrial@catamarca.gob.ar');
$contacto_tel      = get_config('nosotros_contacto_telefono', '(0383) 4-XXXXXX');

$servicios_default = [
    ['icon' => 'bi-lightning-charge', 'titulo' => 'Energía Eléctrica',  'desc' => 'Red de media y baja tensión con capacidad para la demanda industrial.'],
    ['icon' => 'bi-droplet',          'titulo' => 'Agua Potable',        'desc' => 'Red de agua potable y sistema de pozos para abastecimiento continuo.'],
    ['icon' => 'bi-fire',             'titulo' => 'Gas Natural',         'desc' => 'Red de gas natural disponible para procesos industriales.'],
    ['icon' => 'bi-signpost-split',   'titulo' => 'Accesos Viales',      'desc' => 'Rutas de acceso pavimentadas y señalizadas para transporte de cargas.'],
    ['icon' => 'bi-shield-check',     'titulo' => 'Seguridad',           'desc' => 'Sistema de vigilancia y control de acceso las 24 horas.'],
    ['icon' => 'bi-wifi',             'titulo' => 'Conectividad',        'desc' => 'Acceso a servicios de telecomunicaciones y fibra óptica.'],
];
$servicios_json = get_config('nosotros_servicios', '');
$servicios = ($servicios_json !== '' && is_array(json_decode($servicios_json, true)) && count(json_decode($servicios_json, true)) > 0)
    ? json_decode($servicios_json, true)
    : $servicios_default;

$custom_meta_description = 'Parque Industrial El Pantanillo (Catamarca): ubicación, sectores, infraestructura y contacto institucional. '
    . ($total_activas > 0 ? $total_activas . ' empresas activas.' : '');

require_once BASEPATH . '/includes/header.php';
?>

<style>
/* ── Hero ── */
.ep-hero {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: #fff;
    padding: 56px 0 48px;
}
.ep-hero h1 { font-size: 2.1rem; font-weight: 800; margin-bottom: 8px; }
.ep-hero .lead { opacity: .85; font-size: 1.05rem; }
.ep-stat-box { background: rgba(255,255,255,.13); border-radius: 12px; padding: 16px 24px; text-align: center; }
.ep-stat-box .num { font-size: 2.2rem; font-weight: 800; line-height: 1; }
.ep-stat-box .lbl { font-size: .78rem; opacity: .85; margin-top: 4px; }

/* ── Mapa ── */
.map-embed-wrapper { border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.14); }
.map-embed-wrapper iframe { width: 100%; height: 460px; border: 0; display: block; }

/* ── Info cards sidebar ── */
.info-card { background: #fff; border-radius: 12px; padding: 22px; box-shadow: 0 2px 10px rgba(0,0,0,.07); height: 100%; }
.info-card h5 { font-size: 1rem; color: var(--primary); font-weight: 700; border-bottom: 2px solid var(--gray-200); padding-bottom: 10px; margin-bottom: 14px; }
.sector-badge { display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 8px; border: 1px solid #e5e7eb; }
.sector-dot { width: 13px; height: 13px; border-radius: 50%; flex-shrink: 0; }
.sector-name { flex: 1; font-size: .9rem; font-weight: 500; }
.sector-count { font-size: .85rem; color: #666; font-weight: 600; }

/* ── Sobre el parque ── */
.sobre-stats .stat-box { background: #fff; border-radius: 12px; padding: 22px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,.07); }
.sobre-stats .stat-num { font-size: 2.4rem; font-weight: 800; line-height: 1; }

/* ── Servicios ── */
.servicio-card { background: #fff; border-radius: 12px; padding: 28px 20px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,.07); height: 100%; transition: transform .18s, box-shadow .18s; }
.servicio-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(26,82,118,.13); }
.servicio-card i { font-size: 2.4rem; }
.servicio-card h5 { font-size: .95rem; font-weight: 700; margin: 12px 0 6px; }
.servicio-card p { font-size: .85rem; color: #6b7280; margin: 0; }
</style>

<!-- ═══════════════════ HERO ═══════════════════ -->
<section class="ep-hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <p class="text-white-50 small mb-1 text-uppercase fw-semibold" style="letter-spacing:.08em;">
                    <i class="bi bi-geo-alt-fill me-1"></i>RN 38 · San Fernando del Valle de Catamarca
                </p>
                <h1><?= e($titulo) ?></h1>
                <p class="lead mb-4"><?= e($subtitulo) ?></p>
                <a href="<?= PUBLIC_URL ?>/presentar-proyecto.php" class="btn btn-light btn-lg fw-semibold me-2">
                    <i class="bi bi-send me-2"></i>Presentar proyecto
                </a>
                <a href="<?= PUBLIC_URL ?>/empresas.php" class="btn btn-outline-light btn-lg">
                    <i class="bi bi-buildings me-2"></i>Ver empresas
                </a>
            </div>
            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="ep-stat-box">
                            <div class="num"><?= $total_activas ?></div>
                            <div class="lbl">Empresas activas</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="ep-stat-box">
                            <div class="num"><?= count($sectores) ?></div>
                            <div class="lbl">Sectores</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="ep-stat-box">
                            <div class="num"><?= (int)($stats['total_empleados'] ?? 0) ?></div>
                            <div class="lbl">Empleos generados</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="ep-stat-box">
                            <div class="num"><?= (int)($stats['total_rubros'] ?? 0) ?></div>
                            <div class="lbl">Rubros industriales</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════ MAPA + SECTORES ═══════════════════ -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>Ubicación y Sectores</h2>
            <p>Parque Industrial El Pantanillo · RN 38, Catamarca</p>
            <div class="section-divider"></div>
        </div>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="map-embed-wrapper">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d8581.102999803312!2d-65.80320054234065!3d-28.53373098685408!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9424297ed9062de9%3A0xab676b250c7a9379!2sParque%20Industrial%20El%20Pantanillo!5e0!3m2!1ses-419!2sar!4v1774649365290!5m2!1ses-419!2sar"
                        allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>
            <div class="col-lg-4 d-flex flex-column gap-4">
                <div class="info-card">
                    <h5><i class="bi bi-geo-alt me-2"></i>Ubicación</h5>
                    <p class="mb-1"><strong>Dirección:</strong> RN 38, El Pantanillo</p>
                    <p class="mb-1"><strong>Localidad:</strong> San Fernando del Valle de Catamarca</p>
                    <p class="mb-0"><strong>Provincia:</strong> Catamarca, Argentina</p>
                </div>
                <div class="info-card">
                    <h5><i class="bi bi-grid me-2"></i>Sectores industriales</h5>
                    <?php
                    $colores_rubro = ['#3498db','#e74c3c','#27ae60','#f39c12','#9b59b6','#1abc9c','#e67e22','#95a5a6'];
                    ?>
                    <?php if (!empty($sectores)): ?>
                        <?php foreach ($sectores as $i => $s): ?>
                        <div class="sector-badge">
                            <div class="sector-dot" style="background:<?= $colores_rubro[$i % count($colores_rubro)] ?>;"></div>
                            <span class="sector-name"><?= e($s['rubro']) ?></span>
                            <span class="sector-count"><?= (int)$s['total'] ?> emp.</span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">Sin datos disponibles.</p>
                    <?php endif; ?>
                    <div class="mt-3">
                        <a href="<?= PUBLIC_URL ?>/empresas.php" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-building me-1"></i>Ver todas las empresas
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════ SOBRE EL PARQUE ═══════════════════ -->
<section class="section bg-light">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <h2 class="h3 mb-4">Sobre el Parque Industrial</h2>
                <div class="text-muted" style="line-height:1.8;"><?= nl2br(e($texto_parque)) ?></div>
            </div>
            <div class="col-lg-6">
                <div class="row g-3 sobre-stats">
                    <div class="col-6">
                        <div class="stat-box">
                            <div class="stat-num text-primary"><?= $stats['total_empresas'] ?? 0 ?></div>
                            <div class="text-muted small mt-1">Empresas radicadas</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box">
                            <div class="stat-num text-success"><?= $stats['total_empresas_activas'] ?? $stats['total_empresas'] ?? 0 ?></div>
                            <div class="text-muted small mt-1">Empresas activas</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box">
                            <div class="stat-num text-info"><?= $stats['total_rubros'] ?? 0 ?></div>
                            <div class="text-muted small mt-1">Rubros industriales</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box">
                            <div class="stat-num text-warning"><?= $stats['total_empleados'] ?? 0 ?></div>
                            <div class="text-muted small mt-1">Empleos generados</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════ SERVICIOS E INFRAESTRUCTURA ═══════════════════ -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2>Servicios e Infraestructura</h2>
            <p>Todo lo que el parque ofrece a las empresas radicadas</p>
            <div class="section-divider"></div>
        </div>
        <div class="row g-4">
            <?php foreach ($servicios as $s): ?>
            <div class="col-md-4 col-sm-6">
                <div class="servicio-card">
                    <i class="bi <?= e($s['icon'] ?? 'bi-gear') ?> text-primary"></i>
                    <h5><?= e($s['titulo'] ?? '') ?></h5>
                    <p><?= e($s['desc'] ?? '') ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════ CONTACTO INSTITUCIONAL ═══════════════════ -->
<section class="section bg-light">
    <div class="container">
        <div class="section-header">
            <h2>Contacto Institucional</h2>
            <div class="section-divider"></div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h5 class="fw-semibold"><i class="bi bi-geo-alt text-primary me-2"></i>Ubicación</h5>
                                <p class="text-muted mb-0"><?= nl2br(e($contacto_dir)) ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5 class="fw-semibold"><i class="bi bi-envelope text-primary me-2"></i>Contacto</h5>
                                <p class="text-muted mb-0">
                                    Email: <a href="mailto:<?= e($contacto_email) ?>"><?= e($contacto_email) ?></a><br>
                                    Teléfono: <?= e($contacto_tel) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <a href="<?= PUBLIC_URL ?>/presentar-proyecto.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-send me-2"></i>Presentar proyecto al ministerio
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once BASEPATH . '/includes/footer.php'; ?>
