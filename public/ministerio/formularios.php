<?php
/**
 * Revisión de Formularios - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Revisión de Formularios';
$db = getDB();

// Procesar aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';
    $form_id = (int)($_POST['formulario_id'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');

    if ($form_id > 0 && in_array($accion, ['aprobar', 'rechazar'])) {
        $nuevo_estado = ($accion === 'aprobar') ? 'aprobado' : 'rechazado';

        $stmt = $db->prepare("
            UPDATE datos_empresa SET estado = ?, observaciones_ministerio = ?, revisado_por = ?
            WHERE id = ?
        ");
        $stmt->execute([$nuevo_estado, $observaciones, $_SESSION['user_id'], $form_id]);

        // Obtener empresa_id para notificar
        $stmt = $db->prepare("SELECT empresa_id, periodo FROM datos_empresa WHERE id = ?");
        $stmt->execute([$form_id]);
        $form_data = $stmt->fetch();

        if ($form_data) {
            // Notificar a la empresa
            $stmt = $db->prepare("SELECT usuario_id FROM empresas WHERE id = ?");
            $stmt->execute([$form_data['empresa_id']]);
            $emp_user = $stmt->fetch();

            if ($emp_user) {
                $titulo = ($accion === 'aprobar') ? 'Formulario aprobado' : 'Formulario rechazado';
                $msg = ($accion === 'aprobar')
                    ? "Su declaración del período {$form_data['periodo']} fue aprobada."
                    : "Su declaración del período {$form_data['periodo']} fue rechazada. Revise las observaciones.";
                crear_notificacion($emp_user['usuario_id'], 'formulario_revisado', $titulo, $msg, EMPRESA_URL . '/formularios.php');
            }

            log_activity("formulario_$accion", 'datos_empresa', $form_data['empresa_id']);
        }

        set_flash('success', "Formulario " . ($accion === 'aprobar' ? 'aprobado' : 'rechazado') . " correctamente.");
        redirect('formularios.php?' . http_build_query($_GET));
    }
}

// Filtros
$filtro_estado = trim($_GET['estado'] ?? 'enviado');
$filtro_periodo = trim($_GET['periodo'] ?? '');
$buscar = trim($_GET['buscar'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

$where = [];
$params = [];

if ($filtro_estado !== '' && $filtro_estado !== 'todos') {
    $where[] = "de.estado = ?";
    $params[] = $filtro_estado;
}
if ($filtro_periodo !== '') {
    $where[] = "de.periodo = ?";
    $params[] = $filtro_periodo;
}
if ($buscar !== '') {
    $where[] = "(e.nombre LIKE ? OR e.cuit LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT COUNT(*) FROM datos_empresa de INNER JOIN empresas e ON de.empresa_id = e.id $where_sql");
$stmt->execute($params);
$total = $stmt->fetchColumn();

$pagination = paginate($total, ADMIN_ITEMS_PER_PAGE, $pagina, 'formularios.php?' . http_build_query(array_merge($_GET, ['pagina' => '{page}'])));
$offset = ($pagination['current_page'] - 1) * ADMIN_ITEMS_PER_PAGE;

$stmt = $db->prepare("
    SELECT de.*, e.nombre as empresa_nombre, e.cuit, e.rubro
    FROM datos_empresa de
    INNER JOIN empresas e ON de.empresa_id = e.id
    $where_sql
    ORDER BY de.created_at DESC
    LIMIT " . ADMIN_ITEMS_PER_PAGE . " OFFSET $offset
");
$stmt->execute($params);
$formularios = $stmt->fetchAll();

// Periodos disponibles
$periodos = $db->query("SELECT DISTINCT periodo FROM datos_empresa ORDER BY periodo DESC")->fetchAll(PDO::FETCH_COLUMN);
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
            <a href="formularios.php" class="active"><i class="bi bi-file-earmark-text"></i> Formularios</a>
            <a href="graficos.php"><i class="bi bi-graph-up"></i> Gráficos y Datos</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <a href="banners.php"><i class="bi bi-images"></i> Banners inicio</a>
            <a href="notificaciones.php"><i class="bi bi-bell"></i> Notificaciones</a>
            <a href="exportar.php"><i class="bi bi-download"></i> Exportar</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 class="h3 mb-4">Revisión de Formularios <span class="badge bg-primary"><?= $total ?></span></h1>

        <?php show_flash(); ?>

        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-end" method="GET">
                    <div class="col-md-3">
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar empresa..." value="<?= e($buscar) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="estado" class="form-select">
                            <option value="enviado" <?= $filtro_estado === 'enviado' ? 'selected' : '' ?>>Enviados (pendientes)</option>
                            <option value="todos" <?= $filtro_estado === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="aprobado" <?= $filtro_estado === 'aprobado' ? 'selected' : '' ?>>Aprobados</option>
                            <option value="rechazado" <?= $filtro_estado === 'rechazado' ? 'selected' : '' ?>>Rechazados</option>
                            <option value="borrador" <?= $filtro_estado === 'borrador' ? 'selected' : '' ?>>Borradores</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="periodo" class="form-select">
                            <option value="">Todos los períodos</option>
                            <?php foreach ($periodos as $p): ?>
                            <option value="<?= e($p) ?>" <?= $filtro_periodo === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Buscar</button>
                        <a href="formularios.php" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-container">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Período</th>
                        <th>Empleados</th>
                        <th>Capacidad</th>
                        <th>Estado</th>
                        <th>Fecha envío</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($formularios)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No se encontraron formularios</td></tr>
                    <?php endif; ?>
                    <?php foreach ($formularios as $f): ?>
                    <tr>
                        <td>
                            <strong><?= e($f['empresa_nombre']) ?></strong>
                            <?php if ($f['cuit']): ?><br><small class="text-muted"><?= e($f['cuit']) ?></small><?php endif; ?>
                        </td>
                        <td><strong><?= e($f['periodo']) ?></strong></td>
                        <td><?= $f['dotacion_total'] ?></td>
                        <td><?= $f['porcentaje_capacidad_uso'] ? $f['porcentaje_capacidad_uso'] . '%' : '-' ?></td>
                        <td>
                            <?php
                            $badge_class = ['borrador' => 'bg-secondary', 'enviado' => 'bg-warning text-dark', 'aprobado' => 'bg-success', 'rechazado' => 'bg-danger'];
                            ?>
                            <span class="badge <?= $badge_class[$f['estado']] ?? 'bg-secondary' ?>"><?= ucfirst($f['estado']) ?></span>
                        </td>
                        <td><?= $f['fecha_declaracion'] ? format_datetime($f['fecha_declaracion']) : '-' ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalDetalle<?= $f['id'] ?>" title="Ver detalle">
                                <i class="bi bi-eye"></i>
                            </button>
                            <?php if ($f['estado'] === 'enviado'): ?>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalAccion<?= $f['id'] ?>" title="Revisar">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= render_pagination($pagination) ?>
    </main>

    <!-- Modales de detalle -->
    <?php foreach ($formularios as $f): ?>
    <div class="modal fade" id="modalDetalle<?= $f['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= e($f['empresa_nombre']) ?> - <?= e($f['periodo']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="text-primary">Personal</h6>
                            <p>Total: <strong><?= $f['dotacion_total'] ?></strong> | Masc: <?= $f['empleados_masculinos'] ?? '-' ?> | Fem: <?= $f['empleados_femeninos'] ?? '-' ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">Producción</h6>
                            <p>Capacidad: <?= e($f['capacidad_instalada'] ?: '-') ?><br>Uso: <?= $f['porcentaje_capacidad_uso'] ? $f['porcentaje_capacidad_uso'] . '%' : '-' ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">Consumos mensuales</h6>
                            <p>Energía: <?= $f['consumo_energia'] ? number_format($f['consumo_energia'], 2) . ' kWh' : '-' ?><br>
                               Agua: <?= $f['consumo_agua'] ? number_format($f['consumo_agua'], 2) . ' m³' : '-' ?><br>
                               Gas: <?= $f['consumo_gas'] ? number_format($f['consumo_gas'], 2) . ' m³' : '-' ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">Servicios</h6>
                            <p>
                                <?= $f['conexion_red_agua'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?> Red de agua
                                <?= $f['pozo_agua'] ? '<i class="bi bi-check-circle text-success ms-2"></i>' : '<i class="bi bi-x-circle text-muted ms-2"></i>' ?> Pozo propio<br>
                                <?= $f['conexion_gas_natural'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?> Gas natural
                                <?= $f['conexion_cloacas'] ? '<i class="bi bi-check-circle text-success ms-2"></i>' : '<i class="bi bi-x-circle text-muted ms-2"></i>' ?> Cloacas
                            </p>
                        </div>
                        <?php if ($f['exporta']): ?>
                        <div class="col-md-6">
                            <h6 class="text-primary">Exportaciones</h6>
                            <p>Productos: <?= e($f['productos_exporta'] ?: '-') ?><br>Destino: <?= e($f['paises_exporta'] ?: '-') ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($f['importa']): ?>
                        <div class="col-md-6">
                            <h6 class="text-primary">Importaciones</h6>
                            <p>Productos: <?= e($f['productos_importa'] ?: '-') ?><br>Origen: <?= e($f['paises_importa'] ?: '-') ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <h6 class="text-success">Huella de Carbono</h6>
                            <p>CO2: <?= $f['emisiones_co2'] ? number_format($f['emisiones_co2'], 4) . ' ton' : '-' ?><br>
                               Fuente: <?= e($f['fuente_emision_principal'] ?: '-') ?></p>
                        </div>
                        <?php if ($f['observaciones_ministerio']): ?>
                        <div class="col-12">
                            <h6 class="text-warning">Observaciones del Ministerio</h6>
                            <p><?= e($f['observaciones_ministerio']) ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <small class="text-muted">
                                DJ: <?= $f['declaracion_jurada'] ? 'Sí' : 'No' ?>
                                | Enviado: <?= $f['fecha_declaracion'] ? format_datetime($f['fecha_declaracion']) : '-' ?>
                                | IP: <?= e($f['ip_declaracion'] ?? '-') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($f['estado'] === 'enviado'): ?>
    <div class="modal fade" id="modalAccion<?= $f['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Revisar: <?= e($f['empresa_nombre']) ?> - <?= e($f['periodo']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Empleados: <strong><?= $f['dotacion_total'] ?></strong> | Capacidad: <strong><?= $f['porcentaje_capacidad_uso'] ? $f['porcentaje_capacidad_uso'] . '%' : '-' ?></strong></p>
                    <form method="POST" id="formAccion<?= $f['id'] ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="formulario_id" value="<?= $f['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Observaciones (opcional)</label>
                            <textarea name="observaciones" class="form-control" rows="3" placeholder="Ingrese observaciones o motivo de rechazo..."></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="accion" value="aprobar" class="btn btn-success flex-fill">
                                <i class="bi bi-check-circle me-1"></i>Aprobar
                            </button>
                            <button type="submit" name="accion" value="rechazar" class="btn btn-danger flex-fill" onclick="return confirm('¿Rechazar este formulario?')">
                                <i class="bi bi-x-circle me-1"></i>Rechazar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
