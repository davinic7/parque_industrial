<?php
/**
 * Formulario de Presentación y Pedido de Lote - Empresa
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Presentación y pedido de lote';
$db = getDB();
$empresa_id = $_SESSION['empresa_id'] ?? null;

if (!$empresa_id) {
    set_flash('error', 'No se encontró la empresa asociada a su cuenta');
    redirect('dashboard.php');
}

$mensaje = '';
$error = '';

// Buscar formulario dinámico por título
$stmt = $db->prepare("SELECT * FROM formularios_dinamicos WHERE titulo = ? AND estado = 'publicado' LIMIT 1");
$stmt->execute(['Presentación y pedido de lote']);
$formulario = $stmt->fetch();

if (!$formulario) {
    // Intentar sin tilde por si el registro se creó así
    $stmt = $db->prepare("SELECT * FROM formularios_dinamicos WHERE titulo LIKE ? AND estado = 'publicado' LIMIT 1");
    $stmt->execute(['Presentacion y pedido de lote%']);
    $formulario = $stmt->fetch();
}

if ($formulario) {
    header('Location: ' . EMPRESA_URL . '/formulario_dinamico.php?id=' . (int)$formulario['id']);
    exit;
} else {
    $error = 'El formulario de presentación aún no fue configurado por el Ministerio.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - Parque Industrial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= PUBLIC_URL ?>/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header"><span class="text-white fw-bold">Parque Industrial</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="perfil.php"><i class="bi bi-building"></i> Mi Perfil</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <a href="formularios.php"><i class="bi bi-file-earmark-text"></i> Declaración trimestral</a>
            <a href="formulario_presentacion.php" class="active"><i class="bi bi-file-earmark-plus"></i> Formulario inicial</a>
            <a href="notificaciones.php"><i class="bi bi-bell"></i> Notificaciones</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio público</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 class="h3 mb-4">Formulario de presentación y pedido de lote</h1>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

