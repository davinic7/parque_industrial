<?php
/**
 * Nosotros - Parque Industrial de Catamarca
 * Contenido editable por el ministerio (configuracion_sitio).
 */
require_once __DIR__ . '/../config/config.php';

$page_title = 'Nosotros';
$db = getDB();

$stats = get_estadisticas_generales();

$titulo = get_config('nosotros_titulo', 'Parque Industrial de Catamarca');
$subtitulo = get_config('nosotros_subtitulo', 'Impulsando el desarrollo productivo de la provincia');
$texto_parque = get_config('nosotros_texto', 'El Parque Industrial de Catamarca es un polo productivo estratégico que reúne a empresas de diversos rubros, brindando infraestructura, servicios y un entorno favorable para el crecimiento industrial de la provincia.

Gestionado por el Ministerio de Industria, Comercio y Empleo, el parque ofrece a las empresas radicadas acceso a servicios esenciales como red eléctrica, gas natural, agua potable, conectividad y seguridad.

Nuestra misión es promover la inversión productiva, generar empleo genuino y contribuir al desarrollo sustentable de Catamarca.');
$contacto_direccion = get_config('nosotros_contacto_direccion', "Parque Industrial de Catamarca\nSan Fernando del Valle de Catamarca\nCatamarca, Argentina");
$contacto_email = get_config('nosotros_contacto_email', 'parqueindustrial@catamarca.gob.ar');
$contacto_telefono = get_config('nosotros_contacto_telefono', '(0383) 4-XXXXXX');

$servicios = [
    ['icon' => 'bi-lightning-charge', 'titulo' => 'Energía Eléctrica', 'desc' => 'Red de media y baja tensión con capacidad para la demanda industrial.'],
    ['icon' => 'bi-droplet', 'titulo' => 'Agua Potable', 'desc' => 'Red de agua potable y sistema de pozos para abastecimiento continuo.'],
    ['icon' => 'bi-fire', 'titulo' => 'Gas Natural', 'desc' => 'Red de gas natural disponible para procesos industriales.'],
    ['icon' => 'bi-signpost-split', 'titulo' => 'Accesos Viales', 'desc' => 'Rutas de acceso pavimentadas y señalizadas para transporte de cargas.'],
    ['icon' => 'bi-shield-check', 'titulo' => 'Seguridad', 'desc' => 'Sistema de vigilancia y control de acceso las 24 horas.'],
    ['icon' => 'bi-wifi', 'titulo' => 'Conectividad', 'desc' => 'Acceso a servicios de telecomunicaciones y fibra óptica.'],
];
$servicios_json = get_config('nosotros_servicios', '');
if ($servicios_json !== '') {
    $dec = json_decode($servicios_json, true);
    if (is_array($dec) && count($dec) > 0) $servicios = $dec;
}

include __DIR__ . '/../includes/header.php';
?>

<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h1 class="display-5 fw-bold"><?= e($titulo) ?></h1>
        <p class="lead mt-3"><?= e($subtitulo) ?></p>
        <a href="<?= PUBLIC_URL ?>/presentar-proyecto.php" class="btn btn-light btn-lg mt-3">
            <i class="bi bi-send me-2"></i>Presentar proyecto al ministerio
        </a>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <h2 class="h3 mb-4">Sobre el Parque Industrial</h2>
                <?= nl2br(e($texto_parque)) ?>
            </div>
            <div class="col-lg-6">
                <div class="row g-3 text-center">
                    <div class="col-6">
                        <div class="p-4 bg-light rounded-3">
                            <div class="display-6 fw-bold text-primary"><?= $stats['total_empresas'] ?? 0 ?></div>
                            <div class="text-muted">Empresas radicadas</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-4 bg-light rounded-3">
                            <div class="display-6 fw-bold text-success"><?= $stats['total_empresas_activas'] ?? $stats['total_empresas'] ?? 0 ?></div>
                            <div class="text-muted">Empresas activas</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-4 bg-light rounded-3">
                            <div class="display-6 fw-bold text-info"><?= $stats['total_rubros'] ?? 0 ?></div>
                            <div class="text-muted">Rubros industriales</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-4 bg-light rounded-3">
                            <div class="display-6 fw-bold text-warning"><?= $stats['total_empleados'] ?? 0 ?></div>
                            <div class="text-muted">Empleos generados</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <h2 class="h3 text-center mb-5">Servicios e Infraestructura</h2>
        <div class="row g-4">
            <?php foreach ($servicios as $s): ?>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <i class="bi <?= e($s['icon'] ?? 'bi-gear') ?> display-4 text-primary mb-3"></i>
                    <h5><?= e($s['titulo'] ?? '') ?></h5>
                    <p class="text-muted mb-0"><?= e($s['desc'] ?? '') ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <h2 class="h3 text-center mb-5">Contacto Institucional</h2>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h5><i class="bi bi-geo-alt text-primary me-2"></i>Ubicación</h5>
                                <p class="text-muted"><?= nl2br(e($contacto_direccion)) ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="bi bi-envelope text-primary me-2"></i>Contacto</h5>
                                <p class="text-muted">
                                    Email: <a href="mailto:<?= e($contacto_email) ?>"><?= e($contacto_email) ?></a><br>
                                    Teléfono: <?= e($contacto_telefono) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <a href="<?= PUBLIC_URL ?>/presentar-proyecto.php" class="btn btn-primary btn-lg"><i class="bi bi-send me-2"></i>Presentar proyecto al ministerio</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
