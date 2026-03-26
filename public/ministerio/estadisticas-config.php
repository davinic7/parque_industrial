<?php
/**
 * Configurar qué estadísticas se muestran en la página pública - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Configurar estadísticas públicas';
$db = getDB();

$bloques = [
    'header' => 'Encabezado con totales (Empresas, Empleados)',
    'rubros_pie' => 'Gráfico torta por sector',
    'rubros_barras' => 'Barras por rubro',
    'ubicacion' => 'Listado por ubicación',
    'resumen' => 'Resumen numérico (cuadros)',
    'distribucion' => 'Gráfico distribución geográfica',
    'info' => 'Texto "Sobre estos datos"',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $visibles = $_POST['visibles'] ?? [];
    $visibles = array_intersect($visibles, array_keys($bloques));
    $valor = json_encode(array_values($visibles));
    try {
        $db->prepare("INSERT INTO configuracion_sitio (clave, valor, tipo, grupo) VALUES ('estadisticas_visibles', ?, 'json', 'estadisticas') ON DUPLICATE KEY UPDATE valor = VALUES(valor)")->execute([$valor]);
        set_flash('success', 'Configuración guardada. La página de estadísticas públicas mostrará solo los bloques seleccionados.');
        redirect('estadisticas-config.php');
    } catch (Exception $e) {
        set_flash('error', 'Error al guardar.');
    }
}

$valor = get_config('estadisticas_visibles', '["header","rubros_pie","rubros_barras","ubicacion","resumen","distribucion","info"]');
$visibles_actual = json_decode($valor, true);
if (!is_array($visibles_actual)) $visibles_actual = array_keys($bloques);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - Ministerio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= PUBLIC_URL ?>/css/styles.css" rel="stylesheet">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header"><span class="text-white fw-bold"><i class="bi bi-building me-2"></i>Ministerio</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="empresas.php"><i class="bi bi-buildings"></i> Empresas</a>
            <a href="nueva-empresa.php"><i class="bi bi-plus-circle"></i> Nueva Empresa</a>
            <a href="formularios.php"><i class="bi bi-file-earmark-text"></i> Formularios</a>
            <a href="graficos.php"><i class="bi bi-graph-up"></i> Gráficos y Datos</a>
            <a href="estadisticas-config.php" class="active"><i class="bi bi-bar-chart"></i> Estadísticas públicas</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <a href="banners.php"><i class="bi bi-images"></i> Banners inicio</a>
            <a href="comunicados.php"><i class="bi bi-send"></i> Enviar comunicados</a>
            <a href="nosotros-editar.php"><i class="bi bi-pencil-square"></i> Página Nosotros</a>
            <a href="notificaciones.php"><i class="bi bi-bell"></i> Notificaciones</a>
            <a href="exportar.php"><i class="bi bi-download"></i> Exportar</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 class="h3 mb-4"><i class="bi bi-bar-chart me-2"></i>Qué mostrar en Estadísticas públicas</h1>
        <?php show_flash(); ?>
        <p class="text-muted">Seleccione los bloques que desea mostrar en la página <a href="<?= PUBLIC_URL ?>/estadisticas.php" target="_blank">Estadísticas</a> del sitio público.</p>

        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <?php foreach ($bloques as $id => $label): ?>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="visibles[]" value="<?= e($id) ?>" class="form-check-input" id="v_<?= e($id) ?>" <?= in_array($id, $visibles_actual) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="v_<?= e($id) ?>"><?= e($label) ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3">Guardar</button>
                    <a href="<?= PUBLIC_URL ?>/estadisticas.php" target="_blank" class="btn btn-outline-secondary">Ver página</a>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
