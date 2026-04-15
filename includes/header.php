<?php
/**
 * Header público - Parque Industrial de Catamarca
 * Meta SEO, Open Graph, breadcrumbs y navegación.
 */
if (!defined('BASEPATH')) {
    require_once __DIR__ . '/../config/config.php';
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');

$request_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    ? 'https' : 'http';
$request_host = $_SERVER['HTTP_HOST'] ?? '';
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$current_url = $request_scheme . '://' . $request_host . $request_uri;

$meta_descriptions = [
    'index' => 'Portal oficial del Parque Industrial de Catamarca. Empresas activas, empleo y desarrollo industrial en la provincia.',
    'empresas' => 'Directorio de empresas del Parque Industrial de Catamarca. Conocé las empresas que impulsan el desarrollo industrial.',
    'mapa' => 'Mapa interactivo del Parque Industrial de Catamarca. Ubicación de empresas y sectores del parque.',
    'estadisticas' => 'Estadísticas del Parque Industrial de Catamarca: empresas, empleados, sectores y huella de carbono.',
    'noticias' => 'Noticias, eventos y publicaciones del Parque Industrial de Catamarca.',
    'nosotros'  => 'Sobre el Parque Industrial de Catamarca. Ministerio de Producción e Industria. Información institucional.',
    'el-parque' => 'Parque Industrial El Pantanillo (Catamarca): ubicación, sectores, infraestructura y contacto institucional.',
    'empresa' => 'Perfil de empresa del Parque Industrial de Catamarca: datos de contacto y actividad.',
    'publicacion' => 'Publicación del Parque Industrial de Catamarca.',
    'parque'    => 'Parque Industrial El Pantanillo: ubicación, sectores y empresas radicadas en Catamarca.',
    'presentar-proyecto' => 'Presentá tu proyecto industrial ante el Parque Industrial de Catamarca.',
    'recuperar' => 'Recuperación de contraseña del portal Parque Industrial de Catamarca.',
    'activar-cuenta' => 'Activación de cuenta de empresa en el portal Parque Industrial de Catamarca.',
];

$meta_keywords = [
    'index' => 'parque industrial, catamarca, empresas, industria, desarrollo, empleo, argentina',
    'empresas' => 'empresas catamarca, parque industrial, directorio empresas, industrias catamarca',
    'mapa' => 'mapa parque industrial, ubicación empresas catamarca, zona industrial',
    'estadisticas' => 'estadísticas industriales, datos empresas catamarca, empleo industrial',
    'noticias' => 'noticias parque industrial, eventos catamarca, comunicados',
    'nosotros' => 'ministerio producción catamarca, parque industrial, información institucional',
    'parque' => 'el pantanillo, parque industrial catamarca, sectores industriales',
];

$page_meta_description = $meta_descriptions[$current_page] ?? 'Portal del Parque Industrial de Catamarca: información, empresas y estadísticas del desarrollo industrial provincial.';
$page_meta_keywords = $meta_keywords[$current_page] ?? 'parque industrial, catamarca, empresas, industria, argentina';

if (!empty($custom_meta_description)) {
    $page_meta_description = $custom_meta_description;
}
if (!empty($custom_meta_keywords)) {
    $page_meta_keywords = $custom_meta_keywords;
}

$og_rel = '/img/og-parque-industrial.jpg';
if (!is_readable(BASEPATH . '/public' . $og_rel)) {
    $og_rel = '/img/logo-ministerio.png';
}
$og_image = rtrim(PUBLIC_URL, '/') . $og_rel;
if (!empty($custom_og_image)) {
    $og_image = $custom_og_image;
}

$og_title_text = (isset($page_title) && $page_title !== '')
    ? $page_title . ' - Parque Industrial de Catamarca'
    : 'Parque Industrial de Catamarca';

$schema_org = [
    '@context' => 'https://schema.org',
    '@type' => 'GovernmentOrganization',
    'name' => 'Parque Industrial de Catamarca',
    'description' => 'Portal del Parque Industrial de la Provincia de Catamarca',
    'url' => rtrim(PUBLIC_URL, '/'),
    'logo' => PUBLIC_URL . '/img/logo-ministerio.png',
    'image' => $og_image,
    'address' => [
        '@type' => 'PostalAddress',
        'addressLocality' => 'San Fernando del Valle de Catamarca',
        'addressRegion' => 'Catamarca',
        'addressCountry' => 'AR',
    ],
    'geo' => [
        '@type' => 'GeoCoordinates',
        'latitude' => -28.4696,
        'longitude' => -65.7795,
    ],
];

$breadcrumb_flat = [];
$breadcrumb_schema_items = [];
if ($current_page !== 'index') {
    $breadcrumb_flat[] = ['label' => 'Inicio', 'url' => rtrim(PUBLIC_URL, '/') . '/'];
    $pub_row = $publicacion ?? $pub ?? null;
    $emp_row = $empresa ?? null;

    switch ($current_page) {
        case 'empresas':
            $breadcrumb_flat[] = ['label' => 'Empresas', 'url' => ''];
            break;
        case 'empresa':
            $breadcrumb_flat[] = ['label' => 'Empresas', 'url' => PUBLIC_URL . '/empresas.php'];
            $breadcrumb_flat[] = ['label' => truncate($emp_row['nombre'] ?? 'Empresa', 72), 'url' => ''];
            break;
        case 'mapa':
            $breadcrumb_flat[] = ['label' => 'Mapa del Parque', 'url' => ''];
            break;
        case 'estadisticas':
            $breadcrumb_flat[] = ['label' => 'Estadísticas', 'url' => ''];
            break;
        case 'noticias':
            $breadcrumb_flat[] = ['label' => 'Noticias', 'url' => ''];
            break;
        case 'publicacion':
            $breadcrumb_flat[] = ['label' => 'Noticias', 'url' => PUBLIC_URL . '/noticias.php'];
            $breadcrumb_flat[] = ['label' => truncate($pub_row['titulo'] ?? 'Publicación', 72), 'url' => ''];
            break;
        case 'nosotros':
        case 'parque':
        case 'el-parque':
            $breadcrumb_flat[] = ['label' => 'El Parque Industrial', 'url' => ''];
            break;
        case 'presentar-proyecto':
            $breadcrumb_flat[] = ['label' => 'Presentar proyecto', 'url' => ''];
            break;
        case 'recuperar':
            $breadcrumb_flat[] = ['label' => 'Recuperar contraseña', 'url' => ''];
            break;
        default:
            $breadcrumb_flat[] = [
                'label' => ucfirst(str_replace(['-', '_'], ' ', $current_page)),
                'url' => '',
            ];
    }

    $pos = 1;
    foreach ($breadcrumb_flat as $bc) {
        $item_url = $bc['url'] !== '' ? $bc['url'] : $current_url;
        $breadcrumb_schema_items[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'name' => $bc['label'],
            'item' => $item_url,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="<?= e($page_meta_description) ?>">
    <meta name="keywords" content="<?= e($page_meta_keywords) ?>">
    <meta name="author" content="Ministerio de Producción e Industria - Catamarca">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= e($current_url) ?>">

    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($current_url) ?>">
    <meta property="og:title" content="<?= e($og_title_text) ?>">
    <meta property="og:description" content="<?= e($page_meta_description) ?>">
    <meta property="og:image" content="<?= e($og_image) ?>">
    <meta property="og:site_name" content="Parque Industrial de Catamarca">
    <meta property="og:locale" content="es_AR">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= e($current_url) ?>">
    <meta name="twitter:title" content="<?= e($og_title_text) ?>">
    <meta name="twitter:description" content="<?= e($page_meta_description) ?>">
    <meta name="twitter:image" content="<?= e($og_image) ?>">

    <meta name="geo.region" content="AR-K">
    <meta name="geo.placename" content="San Fernando del Valle de Catamarca">
    <meta name="geo.position" content="-28.4696;-65.7795">
    <meta name="ICBM" content="-28.4696, -65.7795">

    <title><?= isset($page_title) ? e($page_title) . ' - ' : '' ?>Parque Industrial de Catamarca</title>

    <link rel="icon" type="image/x-icon" href="<?= PUBLIC_URL ?>/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <link rel="stylesheet" href="<?= PUBLIC_URL ?>/css/styles.css">

    <?php if (isset($extra_css)): ?>
    <?= $extra_css ?>
    <?php endif; ?>

    <script type="application/ld+json"><?= json_encode($schema_org, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <?php if (!empty($breadcrumb_schema_items)): ?>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $breadcrumb_schema_items,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <?php endif; ?>
</head>
<body<?= isset($body_class) && $body_class ? ' class="' . e($body_class) . '"' : '' ?>>
    <nav class="navbar navbar-expand-lg navbar-main">
        <div class="container">
            <a class="navbar-brand" href="<?= PUBLIC_URL ?>/">
                <img src="<?= PUBLIC_URL ?>/img/logo-ministerio.png" alt="Logo Parque Industrial Catamarca" onerror="this.style.display='none'">
                <span>Parque Industrial Catamarca</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'index' ? 'active' : '' ?>" href="<?= PUBLIC_URL ?>/">
                            <i class="bi bi-house-door"></i> Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'empresas' || $current_page === 'empresa' ? 'active' : '' ?>" href="<?= PUBLIC_URL ?>/empresas.php">
                            <i class="bi bi-building"></i> Empresas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'mapa' ? 'active' : '' ?>" href="<?= PUBLIC_URL ?>/mapa.php">
                            <i class="bi bi-geo-alt"></i> Mapa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= in_array($current_page, ['el-parque','parque','nosotros']) ? 'active' : '' ?>" href="<?= PUBLIC_URL ?>/el-parque.php">
                            <i class="bi bi-geo"></i> El Parque
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'estadisticas' ? 'active' : '' ?>" href="<?= PUBLIC_URL ?>/estadisticas.php">
                            <i class="bi bi-graph-up"></i> Estadísticas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'noticias' || $current_page === 'publicacion' ? 'active' : '' ?>" href="<?= PUBLIC_URL ?>/noticias.php">
                            <i class="bi bi-newspaper"></i> Noticias
                        </a>
                    </li>
                    <li class="nav-item">
                        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                            <?php if ($_SESSION['user_rol'] === 'ministerio' || $_SESSION['user_rol'] === 'admin'): ?>
                                <a class="nav-link btn-login" href="<?= MINISTERIO_URL ?>/dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Panel
                                </a>
                            <?php else: ?>
                                <a class="nav-link btn-login" href="<?= EMPRESA_URL ?>/dashboard.php">
                                    <i class="bi bi-person"></i> Mi Empresa
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a class="nav-link btn-login" href="<?= PUBLIC_URL ?>/login.php">
                                <i class="bi bi-box-arrow-in-right"></i> Ingresar
                            </a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <?php if (!empty($breadcrumb_flat)): ?>
    <nav aria-label="breadcrumb" class="breadcrumb-nav py-2">
        <div class="container">
            <ol class="breadcrumb mb-0 pi-breadcrumb">
                <?php foreach ($breadcrumb_flat as $item): ?>
                    <?php if ($item['url'] !== ''): ?>
                <li class="breadcrumb-item">
                    <a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
                </li>
                    <?php else: ?>
                <li class="breadcrumb-item active" aria-current="page"><?= e($item['label']) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </div>
    </nav>
    <?php endif; ?>

    <div class="container mt-3">
        <?php show_flash(); ?>
    </div>
