<?php
/**
 * Header público - Parque Industrial de Catamarca
 */
if (!defined('BASEPATH')) {
    require_once __DIR__ . '/../config/config.php';
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Portal del Parque Industrial de Catamarca - Información, empresas y estadísticas">
    <title><?= isset($page_title) ? e($page_title) . ' - ' : '' ?>Parque Industrial de Catamarca</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= PUBLIC_URL ?>/css/styles.css">
    
    <?php if (isset($extra_css)): ?>
    <?= $extra_css ?>
    <?php endif; ?>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-main">
        <div class="container">
            <a class="navbar-brand" href="<?= PUBLIC_URL ?>/">
                <img src="<?= PUBLIC_URL ?>/img/logo-ministerio.png" alt="Logo" onerror="this.style.display='none'">
                <span>Parque Industrial Catamarca</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'index' ? 'active' : '' ?>" href="<?= PUBLIC_URL ?>/">
                            <i class="bi bi-house-door"></i> Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'empresas' ? 'active' : '' ?>" href="<?= PUBLIC_URL ?>/empresas.php">
                            <i class="bi bi-building"></i> Empresas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'mapa' ? 'active' : '' ?>" href="<?= PUBLIC_URL ?>/mapa.php">
                            <i class="bi bi-geo-alt"></i> Mapa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'estadisticas' ? 'active' : '' ?>" href="<?= PUBLIC_URL ?>/estadisticas.php">
                            <i class="bi bi-graph-up"></i> Estadísticas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'noticias' ? 'active' : '' ?>" href="<?= PUBLIC_URL ?>/noticias.php">
                            <i class="bi bi-newspaper"></i> Noticias
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'nosotros' ? 'active' : '' ?>" href="<?= PUBLIC_URL ?>/nosotros.php">
                            <i class="bi bi-info-circle"></i> Nosotros
                        </a>
                    </li>
                    <li class="nav-item">
                        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                            <?php if ($_SESSION['user_rol'] == 'ministerio' || $_SESSION['user_rol'] == 'admin'): ?>
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
    
    <!-- Flash Messages -->
    <div class="container mt-3">
        <?php show_flash(); ?>
    </div>
