<?php
/**
 * Exportar Datos - Ministerio
 */
require_once __DIR__ . '/../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$db = getDB();

$tipo = $_GET['tipo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $exportar = $_POST['exportar'] ?? '';

    if ($exportar === 'empresas') {
        $stmt = $db->query("
            SELECT e.nombre, e.razon_social, e.cuit, e.rubro, e.estado, e.ubicacion, e.direccion,
                   e.telefono, e.email_contacto, e.contacto_nombre, e.sitio_web, e.visitas, e.created_at
            FROM empresas e ORDER BY e.nombre
        ");
        $datos = $stmt->fetchAll();
        $filename = 'empresas_' . date('Y-m-d') . '.csv';
        $headers = ['Nombre', 'Razón Social', 'CUIT', 'Rubro', 'Estado', 'Ubicación', 'Dirección', 'Teléfono', 'Email', 'Contacto', 'Sitio Web', 'Visitas', 'Fecha Alta'];
    } elseif ($exportar === 'formularios') {
        $periodo = trim($_POST['periodo_export'] ?? '');
        $where = '';
        $params = [];
        if ($periodo) {
            $where = 'WHERE de.periodo = ?';
            $params = [$periodo];
        }
        $stmt = $db->prepare("
            SELECT e.nombre, e.cuit, de.periodo, de.dotacion_total, de.empleados_masculinos, de.empleados_femeninos,
                   de.capacidad_instalada, de.porcentaje_capacidad_uso, de.consumo_energia, de.consumo_agua, de.consumo_gas,
                   de.conexion_red_agua, de.pozo_agua, de.conexion_gas_natural, de.conexion_cloacas,
                   de.exporta, de.productos_exporta, de.importa, de.productos_importa,
                   de.emisiones_co2, de.estado, de.fecha_declaracion
            FROM datos_empresa de INNER JOIN empresas e ON de.empresa_id = e.id $where
            ORDER BY de.periodo DESC, e.nombre
        ");
        $stmt->execute($params);
        $datos = $stmt->fetchAll();
        $filename = 'formularios_' . ($periodo ?: 'todos') . '_' . date('Y-m-d') . '.csv';
        $headers = ['Empresa', 'CUIT', 'Período', 'Empleados Total', 'Emp. Masc', 'Emp. Fem', 'Capacidad Instalada', '% Uso Capacidad', 'Energía kWh', 'Agua m³', 'Gas m³', 'Red Agua', 'Pozo', 'Gas Natural', 'Cloacas', 'Exporta', 'Prod. Exporta', 'Importa', 'Prod. Importa', 'CO2 ton', 'Estado', 'Fecha Envío'];
    } else {
        set_flash('error', 'Tipo de exportación no válido');
        redirect('exportar.php');
    }

    if (!empty($datos)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        fputcsv($output, $headers, ';');
        foreach ($datos as $row) {
            fputcsv($output, array_values($row), ';');
        }
        fclose($output);
        exit;
    } else {
        set_flash('error', 'No hay datos para exportar');
        redirect('exportar.php');
    }
}

$page_title = 'Exportar Datos';

// Periodos disponibles
$periodos = $db->query("SELECT DISTINCT periodo FROM datos_empresa ORDER BY periodo DESC")->fetchAll(PDO::FETCH_COLUMN);

// Stats
$total_empresas = $db->query("SELECT COUNT(*) FROM empresas")->fetchColumn();
$total_formularios = $db->query("SELECT COUNT(*) FROM datos_empresa WHERE estado = 'enviado' OR estado = 'aprobado'")->fetchColumn();
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
            <a href="graficos.php"><i class="bi bi-graph-up"></i> Gráficos</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <a href="exportar.php" class="active"><i class="bi bi-download"></i> Exportar</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 class="h3 mb-4"><i class="bi bi-download me-2"></i>Exportar Datos</h1>

        <?php show_flash(); ?>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="bi bi-buildings me-2"></i>Directorio de Empresas</h5></div>
                    <div class="card-body">
                        <p>Exporta el listado completo de empresas registradas con todos sus datos de contacto e información general.</p>
                        <p class="text-muted"><strong><?= $total_empresas ?></strong> empresas registradas</p>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="exportar" value="empresas">
                            <button class="btn btn-primary"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Descargar CSV</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Declaraciones Juradas</h5></div>
                    <div class="card-body">
                        <p>Exporta los datos de las declaraciones juradas trimestrales (enviadas y aprobadas).</p>
                        <p class="text-muted"><strong><?= $total_formularios ?></strong> formularios disponibles</p>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="exportar" value="formularios">
                            <div class="mb-3">
                                <select name="periodo_export" class="form-select">
                                    <option value="">Todos los períodos</option>
                                    <?php foreach ($periodos as $p): ?>
                                    <option value="<?= e($p) ?>"><?= e($p) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Descargar CSV</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
