<?php
/**
 * Exportar Datos - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$db = getDB();

$tipo = $_GET['tipo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $exportar = $_POST['exportar'] ?? '';

    if ($exportar === 'empresas') {
        $visitasExpr = '0 AS visitas';
        try {
            $col = $db->query("SHOW COLUMNS FROM empresas WHERE Field = 'visitas'");
            if ($col && $col->fetch(PDO::FETCH_ASSOC)) {
                $visitasExpr = 'COALESCE(e.visitas, 0) AS visitas';
            }
        } catch (Throwable $e) {
            /* exportar sin columna visitas */
        }
        $stmt = $db->query("
            SELECT e.nombre, e.razon_social, e.cuit, e.rubro, e.estado, e.ubicacion, e.direccion,
                   e.telefono, e.email_contacto, e.contacto_nombre, e.sitio_web, $visitasExpr, e.created_at
            FROM empresas e ORDER BY e.nombre
        ");
        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    $formato = $_POST['formato'] ?? 'excel';

    if (!empty($datos)) {
        if ($formato === 'csv') {
            // CSV clásico
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . str_replace('.csv', '.csv', $filename) . '"');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
            fputcsv($output, $headers, ';');
            foreach ($datos as $row) {
                fputcsv($output, array_values($row), ';');
            }
            fclose($output);
            exit;
        }

        // Excel (HTML table — abre correctamente en Excel, LibreOffice y Google Sheets)
        $xls_filename = str_replace('.csv', '.xls', $filename);
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $xls_filename . '"');
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<head><meta charset="utf-8"><style>th{background:#1a5276;color:#fff;font-weight:bold;padding:6px 10px;} td{padding:4px 8px;border:1px solid #ddd;} table{border-collapse:collapse;font-family:Calibri,sans-serif;font-size:11pt;}</style></head>';
        echo '<body><table>';
        echo '<tr>';
        foreach ($headers as $h) { echo '<th>' . htmlspecialchars($h) . '</th>'; }
        echo '</tr>';
        foreach ($datos as $row) {
            echo '<tr>';
            foreach (array_values($row) as $val) {
                echo '<td>' . htmlspecialchars((string) $val) . '</td>';
            }
            echo '</tr>';
        }
        echo '</table></body></html>';
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

$ministerio_nav = 'exportar';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <h2 class="h4 mb-4 fw-semibold"><i class="bi bi-download me-2"></i>Exportar datos</h2>

        <?php show_flash(); ?>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="bi bi-buildings me-2"></i>Directorio de Empresas</h5></div>
                    <div class="card-body">
                        <p>Exporta el listado completo de empresas registradas con todos sus datos de contacto e información general.</p>
                        <p class="text-muted"><strong><?= $total_empresas ?></strong> empresas registradas</p>
                        <form method="POST" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="exportar" value="empresas">
                            <button name="formato" value="excel" class="btn btn-primary"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</button>
                            <button name="formato" value="csv" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
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
                            <div class="d-flex gap-2">
                                <button name="formato" value="excel" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</button>
                                <button name="formato" value="csv" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i>CSV</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>
