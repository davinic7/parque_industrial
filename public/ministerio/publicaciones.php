<?php
/**
 * Moderación de Publicaciones - Ministerio
 */
require_once __DIR__ . '/../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Moderación de Publicaciones';
$db = getDB();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';
    $pub_id = (int)($_POST['publicacion_id'] ?? 0);

    if ($pub_id > 0 && in_array($accion, ['aprobar', 'rechazar'])) {
        $nuevo_estado = ($accion === 'aprobar') ? 'aprobado' : 'rechazado';
        $observaciones = trim($_POST['observaciones'] ?? '');

        $stmt = $db->prepare("UPDATE publicaciones SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $pub_id]);

        // Notificar a la empresa
        $stmt = $db->prepare("SELECT p.titulo, p.empresa_id, e.usuario_id FROM publicaciones p INNER JOIN empresas e ON p.empresa_id = e.id WHERE p.id = ?");
        $stmt->execute([$pub_id]);
        $pub_data = $stmt->fetch();

        if ($pub_data) {
            $titulo_notif = ($accion === 'aprobar') ? 'Publicación aprobada' : 'Publicación rechazada';
            $msg = ($accion === 'aprobar')
                ? "Su publicación \"{$pub_data['titulo']}\" fue aprobada y ya es visible."
                : "Su publicación \"{$pub_data['titulo']}\" fue rechazada." . ($observaciones ? " Motivo: $observaciones" : '');
            crear_notificacion($pub_data['usuario_id'], 'publicacion_revisada', $titulo_notif, $msg, EMPRESA_URL . '/publicaciones.php');
            log_activity("publicacion_$accion", 'publicaciones', $pub_data['empresa_id']);
        }

        set_flash('success', "Publicación " . ($accion === 'aprobar' ? 'aprobada' : 'rechazada'));
        redirect('publicaciones.php?' . http_build_query($_GET));
    }
}

// Filtros
$filtro_estado = trim($_GET['estado'] ?? 'pendiente');
$filtro_tipo = trim($_GET['tipo'] ?? '');
$buscar = trim($_GET['buscar'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

$where = [];
$params = [];

if ($filtro_estado !== '' && $filtro_estado !== 'todos') {
    $where[] = "p.estado = ?";
    $params[] = $filtro_estado;
}
if ($filtro_tipo !== '') {
    $where[] = "p.tipo = ?";
    $params[] = $filtro_tipo;
}
if ($buscar !== '') {
    $where[] = "(p.titulo LIKE ? OR e.nombre LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT COUNT(*) FROM publicaciones p INNER JOIN empresas e ON p.empresa_id = e.id $where_sql");
$stmt->execute($params);
$total = $stmt->fetchColumn();

$pagination = paginate($total, ADMIN_ITEMS_PER_PAGE, $pagina, 'publicaciones.php?' . http_build_query(array_merge($_GET, ['pagina' => '{page}'])));
$offset = ($pagination['current_page'] - 1) * ADMIN_ITEMS_PER_PAGE;

$stmt = $db->prepare("
    SELECT p.*, e.nombre as empresa_nombre
    FROM publicaciones p
    INNER JOIN empresas e ON p.empresa_id = e.id
    $where_sql
    ORDER BY p.created_at DESC
    LIMIT " . ADMIN_ITEMS_PER_PAGE . " OFFSET $offset
");
$stmt->execute($params);
$publicaciones = $stmt->fetchAll();
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
            <a href="publicaciones.php" class="active"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 class="h3 mb-4">Moderación de Publicaciones <span class="badge bg-primary"><?= $total ?></span></h1>

        <?php show_flash(); ?>

        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-end" method="GET">
                    <div class="col-md-3">
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar título o empresa..." value="<?= e($buscar) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="estado" class="form-select">
                            <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                            <option value="todos" <?= $filtro_estado === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="aprobado" <?= $filtro_estado === 'aprobado' ? 'selected' : '' ?>>Aprobados</option>
                            <option value="rechazado" <?= $filtro_estado === 'rechazado' ? 'selected' : '' ?>>Rechazados</option>
                            <option value="borrador" <?= $filtro_estado === 'borrador' ? 'selected' : '' ?>>Borradores</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="tipo" class="form-select">
                            <option value="">Todos los tipos</option>
                            <option value="noticia" <?= $filtro_tipo === 'noticia' ? 'selected' : '' ?>>Noticia</option>
                            <option value="evento" <?= $filtro_tipo === 'evento' ? 'selected' : '' ?>>Evento</option>
                            <option value="promocion" <?= $filtro_tipo === 'promocion' ? 'selected' : '' ?>>Promoción</option>
                            <option value="comunicado" <?= $filtro_tipo === 'comunicado' ? 'selected' : '' ?>>Comunicado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Buscar</button>
                        <a href="publicaciones.php" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($publicaciones)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-megaphone display-1"></i>
            <p class="mt-3">No hay publicaciones con los filtros seleccionados</p>
        </div>
        <?php else: ?>

        <div class="row g-4">
            <?php foreach ($publicaciones as $pub): ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <?php if ($pub['imagen']): ?>
                    <img src="<?= UPLOADS_URL ?>/publicaciones/<?= e($pub['imagen']) ?>" class="card-img-top" style="height: 180px; object-fit: cover;" alt="">
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <?php
                            $tipo_badge = ['noticia' => 'bg-info', 'evento' => 'bg-primary', 'promocion' => 'bg-warning text-dark', 'comunicado' => 'bg-secondary'];
                            $estado_badge = ['borrador' => 'bg-secondary', 'pendiente' => 'bg-warning text-dark', 'aprobado' => 'bg-success', 'rechazado' => 'bg-danger'];
                            ?>
                            <span class="badge <?= $tipo_badge[$pub['tipo']] ?? 'bg-secondary' ?>"><?= ucfirst($pub['tipo']) ?></span>
                            <span class="badge <?= $estado_badge[$pub['estado']] ?? 'bg-secondary' ?>"><?= ucfirst($pub['estado']) ?></span>
                        </div>
                        <h5 class="card-title"><?= e($pub['titulo']) ?></h5>
                        <p class="text-muted small mb-2"><i class="bi bi-building me-1"></i><?= e($pub['empresa_nombre']) ?></p>
                        <?php if ($pub['extracto']): ?>
                        <p class="card-text"><?= e(truncate($pub['extracto'], 150)) ?></p>
                        <?php elseif ($pub['contenido']): ?>
                        <p class="card-text"><?= e(truncate($pub['contenido'], 150)) ?></p>
                        <?php endif; ?>
                        <small class="text-muted"><?= format_datetime($pub['created_at']) ?></small>
                    </div>
                    <?php if ($pub['estado'] === 'pendiente'): ?>
                    <div class="card-footer bg-white">
                        <div class="d-flex gap-2">
                            <form method="POST" class="flex-fill">
                                <?= csrf_field() ?>
                                <input type="hidden" name="publicacion_id" value="<?= $pub['id'] ?>">
                                <input type="hidden" name="accion" value="aprobar">
                                <button class="btn btn-success w-100 btn-sm"><i class="bi bi-check-circle me-1"></i>Aprobar</button>
                            </form>
                            <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalRechazar<?= $pub['id'] ?>">
                                <i class="bi bi-x-circle me-1"></i>Rechazar
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($pub['estado'] === 'pendiente'): ?>
            <div class="modal fade" id="modalRechazar<?= $pub['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Rechazar: <?= e($pub['titulo']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="publicacion_id" value="<?= $pub['id'] ?>">
                            <input type="hidden" name="accion" value="rechazar">
                            <div class="modal-body">
                                <label class="form-label">Motivo del rechazo</label>
                                <textarea name="observaciones" class="form-control" rows="3" placeholder="Indique el motivo..."></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-danger">Confirmar rechazo</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?= render_pagination($pagination) ?>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
