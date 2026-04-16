<?php
/**
 * Exportar Datos - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$db = getDB();

/* ══════════════════════════════════════════════
   BLOQUE DE EXPORTACIÓN (antes de cualquier HTML)
   ══════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $exportar = $_POST['exportar'] ?? '';

    /* ── Empresas ── */
    if ($exportar === 'empresas') {
        $visitasExpr = '0 AS visitas';
        try {
            $col = $db->query("SHOW COLUMNS FROM empresas WHERE Field = 'visitas'");
            if ($col && $col->fetch()) $visitasExpr = 'COALESCE(e.visitas,0) AS visitas';
        } catch (Throwable $e) {}

        $stmt = $db->query("
            SELECT e.nombre, e.razon_social, e.cuit, e.rubro, e.estado,
                   e.ubicacion, e.direccion, e.telefono, e.email_contacto,
                   e.contacto_nombre, e.sitio_web, $visitasExpr, e.created_at
            FROM empresas e ORDER BY e.nombre
        ");
        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers  = ['Nombre','Razón Social','CUIT','Rubro','Estado','Ubicación','Dirección','Teléfono','Email','Contacto','Sitio Web','Visitas','Fecha Alta'];
        $filename = 'empresas_' . date('Y-m-d') . '.csv';

    /* ── Declaraciones juradas ── */
    } elseif ($exportar === 'formularios') {
        $periodo = trim($_POST['periodo_export'] ?? '');
        $where   = $periodo ? 'WHERE de.periodo = ?' : '';
        $params  = $periodo ? [$periodo] : [];
        $stmt    = $db->prepare("
            SELECT e.nombre, e.cuit, de.periodo, de.dotacion_total,
                   de.empleados_masculinos, de.empleados_femeninos,
                   de.capacidad_instalada, de.porcentaje_capacidad_uso,
                   de.consumo_energia, de.consumo_agua, de.consumo_gas,
                   de.conexion_red_agua, de.pozo_agua, de.conexion_gas_natural,
                   de.conexion_cloacas, de.exporta, de.productos_exporta,
                   de.importa, de.productos_importa, de.emisiones_co2,
                   de.estado, de.fecha_declaracion
            FROM datos_empresa de
            INNER JOIN empresas e ON de.empresa_id = e.id $where
            ORDER BY de.periodo DESC, e.nombre
        ");
        $stmt->execute($params);
        $datos    = $stmt->fetchAll();
        $headers  = ['Empresa','CUIT','Período','Empleados Total','Emp. Masc','Emp. Fem',
                     'Cap. Instalada','% Uso Cap.','Energía kWh','Agua m³','Gas m³',
                     'Red Agua','Pozo','Gas Natural','Cloacas',
                     'Exporta','Prod. Exporta','Importa','Prod. Importa',
                     'CO2 ton','Estado','Fecha Envío'];
        $filename = 'declaraciones_' . ($periodo ?: 'todos') . '_' . date('Y-m-d') . '.csv';

    /* ── Lista de formularios dinámicos ── */
    } elseif ($exportar === 'formularios_dinamicos') {
        $stmt = $db->query("
            SELECT fd.id, fd.titulo, fd.descripcion, fd.estado, fd.created_at,
                   COUNT(DISTINCT fp.id)  AS total_preguntas,
                   COUNT(DISTINCT fr.id)  AS total_respuestas
            FROM formularios_dinamicos fd
            LEFT JOIN formulario_preguntas fp ON fp.formulario_id = fd.id
            LEFT JOIN formulario_respuestas fr ON fr.formulario_id = fd.id AND fr.estado = 'enviado'
            GROUP BY fd.id
            ORDER BY fd.created_at DESC
        ");
        $datos    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $headers  = ['ID','Título','Descripción','Estado','Preguntas','Respuestas Enviadas','Fecha Creación'];
        $filename = 'formularios_dinamicos_' . date('Y-m-d') . '.csv';
        // Reordenar columnas para coincidir con headers
        $datos = array_map(fn($r) => [
            $r['id'], $r['titulo'], $r['descripcion'] ?? '', $r['estado'],
            $r['total_preguntas'], $r['total_respuestas'], $r['created_at']
        ], $datos);

    /* ── Respuestas de un formulario dinámico ── */
    } elseif ($exportar === 'respuestas_formulario') {
        $fid = (int)($_POST['formulario_id'] ?? 0);
        if ($fid <= 0) { set_flash('error', 'Seleccione un formulario.'); redirect('exportar.php'); }

        // Obtener preguntas del formulario
        $stmtP = $db->prepare("
            SELECT id, etiqueta FROM formulario_preguntas
            WHERE formulario_id = ? ORDER BY orden, id
        ");
        $stmtP->execute([$fid]);
        $preguntas = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        // Obtener respuestas enviadas
        $stmtR = $db->prepare("
            SELECT e.nombre AS empresa, e.cuit,
                   fr.estado, fr.enviado_at, fr.respuestas
            FROM formulario_respuestas fr
            INNER JOIN empresas e ON fr.empresa_id = e.id
            WHERE fr.formulario_id = ?
            ORDER BY fr.enviado_at DESC
        ");
        $stmtR->execute([$fid]);
        $respuestas_raw = $stmtR->fetchAll(PDO::FETCH_ASSOC);

        // Nombre del formulario para el archivo
        $titulo_form = $db->prepare("SELECT titulo FROM formularios_dinamicos WHERE id = ?");
        $titulo_form->execute([$fid]);
        $titulo_form = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $titulo_form->fetchColumn() ?: 'formulario');

        // Construir CSV con una columna por pregunta
        $preg_ids     = array_column($preguntas, 'id');
        $preg_labels  = array_column($preguntas, 'etiqueta');
        $headers      = array_merge(['Empresa', 'CUIT', 'Estado', 'Fecha Envío'], $preg_labels);
        $filename     = 'respuestas_' . $titulo_form . '_' . date('Y-m-d') . '.csv';

        $datos = [];
        foreach ($respuestas_raw as $r) {
            $json = json_decode($r['respuestas'] ?? '{}', true);
            $row  = [$r['empresa'], $r['cuit'], $r['estado'], $r['enviado_at'] ?? ''];
            foreach ($preg_ids as $pid) {
                $val = $json[$pid] ?? '';
                if (is_array($val)) $val = implode(', ', $val);
                $row[] = $val;
            }
            $datos[] = $row;
        }

    } else {
        set_flash('error', 'Tipo de exportación no válido.');
        redirect('exportar.php');
    }

    if (!empty($datos)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        fputcsv($output, $headers, ';');
        foreach ($datos as $row) {
            fputcsv($output, array_values((array)$row), ';');
        }
        fclose($output);
        exit;
    } else {
        set_flash('error', 'No hay datos para exportar.');
        redirect('exportar.php');
    }
}

/* ══════════════════════════════════════════════
   CARGA DE DATOS PARA LA VISTA
   ══════════════════════════════════════════════ */
$page_title = 'Exportar Datos';

$periodos          = $db->query("SELECT DISTINCT periodo FROM datos_empresa ORDER BY periodo DESC")->fetchAll(PDO::FETCH_COLUMN);
$total_empresas    = (int) $db->query("SELECT COUNT(*) FROM empresas")->fetchColumn();
$total_formularios = (int) $db->query("SELECT COUNT(*) FROM datos_empresa WHERE estado IN ('enviado','aprobado')")->fetchColumn();

// Formularios dinámicos con conteos
try {
    $stmt_fd = $db->query("
        SELECT fd.id, fd.titulo, fd.estado, fd.created_at,
               COUNT(DISTINCT fp.id)  AS total_preguntas,
               COUNT(DISTINCT fr.id)  AS total_respuestas
        FROM formularios_dinamicos fd
        LEFT JOIN formulario_preguntas fp ON fp.formulario_id = fd.id
        LEFT JOIN formulario_respuestas fr ON fr.formulario_id = fd.id AND fr.estado = 'enviado'
        GROUP BY fd.id
        ORDER BY fd.created_at DESC
    ");
    $formularios_din = $stmt_fd->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $formularios_din = [];
}

// Formulario seleccionado para preview de respuestas
$fid_sel = (int)($_GET['fid'] ?? 0);
$respuestas_preview = [];
$preguntas_sel      = [];
if ($fid_sel > 0) {
    try {
        $stmtP = $db->prepare("SELECT id, etiqueta FROM formulario_preguntas WHERE formulario_id = ? ORDER BY orden, id");
        $stmtP->execute([$fid_sel]);
        $preguntas_sel = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        $stmtR = $db->prepare("
            SELECT e.nombre AS empresa, e.cuit,
                   fr.estado, fr.enviado_at, fr.respuestas
            FROM formulario_respuestas fr
            INNER JOIN empresas e ON fr.empresa_id = e.id
            WHERE fr.formulario_id = ?
            ORDER BY fr.enviado_at DESC
            LIMIT 50
        ");
        $stmtR->execute([$fid_sel]);
        $respuestas_preview = $stmtR->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $respuestas_preview = [];
    }
}

$ministerio_nav = 'exportar';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0 fw-semibold"><i class="bi bi-download me-2"></i>Exportar datos</h2>
</div>

<?php show_flash(); ?>

<!-- ── Exportaciones existentes ── -->
<div class="row g-4 mb-5">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-buildings me-2"></i>Directorio de Empresas</h5>
            </div>
            <div class="card-body">
                <p>Listado completo de empresas con datos de contacto e información general.</p>
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
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Declaraciones Juradas</h5>
            </div>
            <div class="card-body">
                <p>Datos trimestrales declarados por las empresas (enviados y aprobados).</p>
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

<!-- ── Formularios Dinámicos ── -->
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-ui-checks-grid me-2 text-primary"></i>Formularios dinámicos</h5>
        <?php if (!empty($formularios_din)): ?>
        <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="exportar" value="formularios_dinamicos">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar lista</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($formularios_din)): ?>
        <p class="text-muted text-center py-4 mb-0">No hay formularios dinámicos creados.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Título</th>
                        <th>Estado</th>
                        <th class="text-center">Preguntas</th>
                        <th class="text-center">Respuestas</th>
                        <th>Creado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $estado_badge = ['borrador' => 'secondary', 'publicado' => 'success', 'archivado' => 'dark'];
                foreach ($formularios_din as $fd):
                    $badge = $estado_badge[$fd['estado']] ?? 'secondary';
                ?>
                <tr>
                    <td class="text-muted small"><?= $fd['id'] ?></td>
                    <td><strong><?= e($fd['titulo']) ?></strong></td>
                    <td><span class="badge bg-<?= $badge ?>"><?= ucfirst($fd['estado']) ?></span></td>
                    <td class="text-center"><?= (int)$fd['total_preguntas'] ?></td>
                    <td class="text-center">
                        <?php if ($fd['total_respuestas'] > 0): ?>
                        <span class="badge bg-info text-dark"><?= (int)$fd['total_respuestas'] ?></span>
                        <?php else: ?>
                        <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= format_datetime($fd['created_at']) ?></td>
                    <td>
                        <?php if ($fd['total_respuestas'] > 0): ?>
                        <a href="?fid=<?= $fd['id'] ?>#respuestas"
                           class="btn btn-sm btn-outline-secondary <?= $fid_sel === (int)$fd['id'] ? 'active' : '' ?>">
                            <i class="bi bi-eye me-1"></i>Ver respuestas
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Respuestas de empresas ── -->
<div class="card mb-4" id="respuestas">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-chat-square-text me-2 text-success"></i>Respuestas de empresas</h5>
        <?php if ($fid_sel > 0 && !empty($respuestas_preview)): ?>
        <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="exportar" value="respuestas_formulario">
            <input type="hidden" name="formulario_id" value="<?= $fid_sel ?>">
            <button class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar respuestas CSV
            </button>
        </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <!-- Selector de formulario -->
        <form method="GET" action="exportar.php" class="row g-2 align-items-end mb-3">
            <div class="col-md-6">
                <label class="form-label">Seleccionar formulario</label>
                <select name="fid" class="form-select" onchange="this.form.submit()">
                    <option value="">— Elegir formulario —</option>
                    <?php foreach ($formularios_din as $fd): ?>
                    <?php if ((int)$fd['total_respuestas'] > 0): ?>
                    <option value="<?= $fd['id'] ?>" <?= $fid_sel === (int)$fd['id'] ? 'selected' : '' ?>>
                        <?= e($fd['titulo']) ?> (<?= (int)$fd['total_respuestas'] ?> resp.)
                    </option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <a href="exportar.php#respuestas" class="btn btn-outline-secondary w-100">Limpiar</a>
            </div>
        </form>

        <?php if ($fid_sel <= 0): ?>
        <p class="text-muted mb-0">Seleccione un formulario para ver y exportar sus respuestas.</p>
        <?php elseif (empty($respuestas_preview)): ?>
        <p class="text-muted mb-0">Este formulario no tiene respuestas enviadas aún.</p>
        <?php else: ?>
        <!-- Tabla de respuestas -->
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0 align-middle" style="font-size:.85rem;">
                <thead class="table-light">
                    <tr>
                        <th>Empresa</th>
                        <th>CUIT</th>
                        <th>Estado</th>
                        <th>Fecha envío</th>
                        <?php foreach ($preguntas_sel as $pq): ?>
                        <th class="text-nowrap" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;"
                            title="<?= e($pq['etiqueta']) ?>">
                            <?= e(truncate($pq['etiqueta'], 30)) ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($respuestas_preview as $r):
                    $json = json_decode($r['respuestas'] ?? '{}', true) ?: [];
                ?>
                <tr>
                    <td><strong><?= e($r['empresa']) ?></strong></td>
                    <td class="text-muted small"><?= e($r['cuit']) ?></td>
                    <td>
                        <span class="badge bg-<?= $r['estado'] === 'enviado' ? 'success' : 'secondary' ?>">
                            <?= ucfirst($r['estado']) ?>
                        </span>
                    </td>
                    <td class="small text-muted"><?= $r['enviado_at'] ? format_datetime($r['enviado_at']) : '—' ?></td>
                    <?php foreach ($preguntas_sel as $pq):
                        $val = $json[$pq['id']] ?? '—';
                        if (is_array($val)) $val = implode(', ', $val);
                    ?>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                        title="<?= e((string)$val) ?>">
                        <?= e(truncate((string)$val, 60)) ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($respuestas_preview) >= 50): ?>
        <p class="text-muted small mt-2 mb-0">Mostrando las últimas 50 respuestas. Usa "Exportar CSV" para obtener el listado completo.</p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>
