<?php
/**
 * Gestión de Empresas - Ministerio
 */
require_once __DIR__ . '/../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Gestión de Empresas';
$db = getDB();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';
    $emp_id = (int)($_POST['empresa_id'] ?? 0);

    if ($emp_id > 0 && in_array($accion, ['activar', 'suspender', 'inactivar'])) {
        $estados = ['activar' => 'activa', 'suspender' => 'suspendida', 'inactivar' => 'inactiva'];
        $nuevo_estado = $estados[$accion];
        $stmt = $db->prepare("UPDATE empresas SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $emp_id]);
        log_activity("empresa_$accion", 'empresas', $emp_id);
        set_flash('success', "Estado de la empresa actualizado a: $nuevo_estado");
        redirect('empresas.php?' . http_build_query($_GET));
    }
}

// Filtros
$buscar = trim($_GET['buscar'] ?? '');
$filtro_rubro = trim($_GET['rubro'] ?? '');
$filtro_estado = trim($_GET['estado'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

$where = [];
$params = [];

if ($buscar !== '') {
    $where[] = "(e.nombre LIKE ? OR e.cuit LIKE ? OR e.contacto_nombre LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}
if ($filtro_rubro !== '') {
    $where[] = "e.rubro = ?";
    $params[] = $filtro_rubro;
}
if ($filtro_estado !== '') {
    $where[] = "e.estado = ?";
    $params[] = $filtro_estado;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT COUNT(*) FROM empresas e $where_sql");
$stmt->execute($params);
$total = $stmt->fetchColumn();

$pagination = paginate($total, ADMIN_ITEMS_PER_PAGE, $pagina, 'empresas.php?' . http_build_query(array_merge($_GET, ['pagina' => '{page}'])));
$offset = ($pagination['current_page'] - 1) * ADMIN_ITEMS_PER_PAGE;

$stmt = $db->prepare("
    SELECT e.*,
        (SELECT de.estado FROM datos_empresa de WHERE de.empresa_id = e.id ORDER BY de.periodo DESC LIMIT 1) as form_estado
    FROM empresas e
    $where_sql
    ORDER BY e.nombre ASC
    LIMIT " . ADMIN_ITEMS_PER_PAGE . " OFFSET $offset
");
$stmt->execute($params);
$empresas = $stmt->fetchAll();

$rubros = $db->query("SELECT DISTINCT rubro FROM empresas WHERE rubro IS NOT NULL AND rubro != '' ORDER BY rubro")->fetchAll(PDO::FETCH_COLUMN);
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
            <a href="empresas.php" class="active"><i class="bi bi-buildings"></i> Empresas</a>
            <a href="nueva-empresa.php"><i class="bi bi-plus-circle"></i> Nueva Empresa</a>
            <a href="formularios.php"><i class="bi bi-file-earmark-text"></i> Formularios</a>
            <a href="graficos.php"><i class="bi bi-graph-up"></i> Gráficos</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Gestión de Empresas <span class="badge bg-primary"><?= $total ?></span></h1>
            <a href="nueva-empresa.php" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Nueva Empresa</a>
        </div>

        <?php show_flash(); ?>

        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-end" method="GET">
                    <div class="col-md-3">
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar empresa..." value="<?= e($buscar) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="rubro" class="form-select">
                            <option value="">Todos los rubros</option>
                            <?php foreach ($rubros as $r): ?>
                            <option value="<?= e($r) ?>" <?= $filtro_rubro === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="estado" class="form-select">
                            <option value="">Todos los estados</option>
                            <option value="activa" <?= $filtro_estado === 'activa' ? 'selected' : '' ?>>Activa</option>
                            <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="suspendida" <?= $filtro_estado === 'suspendida' ? 'selected' : '' ?>>Suspendida</option>
                            <option value="inactiva" <?= $filtro_estado === 'inactiva' ? 'selected' : '' ?>>Inactiva</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Buscar</button>
                        <a href="empresas.php" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-container">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Empresa</th><th>Rubro</th><th>Ubicación</th><th>Estado</th><th>Formulario</th><th>Visitas</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($empresas)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No se encontraron empresas</td></tr>
                    <?php endif; ?>
                    <?php foreach ($empresas as $emp): ?>
                    <tr>
                        <td>
                            <strong><?= e($emp['nombre']) ?></strong>
                            <?php if ($emp['cuit']): ?><br><small class="text-muted"><?= e($emp['cuit']) ?></small><?php endif; ?>
                        </td>
                        <td><?= e($emp['rubro'] ?? '-') ?></td>
                        <td><?= e($emp['ubicacion'] ?? '-') ?></td>
                        <td>
                            <?php $badge_estado = ['activa' => 'bg-success', 'pendiente' => 'bg-warning text-dark', 'suspendida' => 'bg-danger', 'inactiva' => 'bg-secondary']; ?>
                            <span class="badge <?= $badge_estado[$emp['estado']] ?? 'bg-secondary' ?>"><?= ucfirst($emp['estado']) ?></span>
                        </td>
                        <td>
                            <?php if ($emp['form_estado']): ?>
                                <?php $badge_form = ['borrador' => 'bg-secondary', 'enviado' => 'bg-warning text-dark', 'aprobado' => 'bg-success', 'rechazado' => 'bg-danger']; ?>
                                <span class="badge <?= $badge_form[$emp['form_estado']] ?? 'bg-secondary' ?>"><?= ucfirst($emp['form_estado']) ?></span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark">Sin datos</span>
                            <?php endif; ?>
                        </td>
                        <td><?= format_number($emp['visitas'] ?? 0) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="empresa-detalle.php?id=<?= $emp['id'] ?>" class="btn btn-outline-primary" title="Ver"><i class="bi bi-eye"></i></a>
                                <a href="empresa-editar.php?id=<?= $emp['id'] ?>" class="btn btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"></button>
                                    <ul class="dropdown-menu">
                                        <li><form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="empresa_id" value="<?= $emp['id'] ?>"><button name="accion" value="activar" class="dropdown-item"><i class="bi bi-check-circle me-2"></i>Activar</button></form></li>
                                        <li><form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="empresa_id" value="<?= $emp['id'] ?>"><button name="accion" value="suspender" class="dropdown-item"><i class="bi bi-pause-circle me-2"></i>Suspender</button></form></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="empresa_id" value="<?= $emp['id'] ?>"><button name="accion" value="inactivar" class="dropdown-item text-danger" onclick="return confirm('¿Desactivar esta empresa?')"><i class="bi bi-x-circle me-2"></i>Desactivar</button></form></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= render_pagination($pagination) ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
