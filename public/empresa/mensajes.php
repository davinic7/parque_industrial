<?php
/**
 * Mensajes - Panel Empresa
 * Comunicados recibidos del Ministerio.
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Mensajes';
$db = getDB();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'leer') {
        $mid = (int)($_POST['mensaje_id'] ?? 0);
        if ($mid > 0) {
            $stmt = $db->prepare("UPDATE mensajes SET leido = 1, fecha_lectura = NOW() WHERE id = ? AND destinatario_id = ?");
            $stmt->execute([$mid, $user_id]);
        }
    } elseif ($accion === 'leer_todas') {
        $stmt = $db->prepare("UPDATE mensajes SET leido = 1, fecha_lectura = NOW() WHERE destinatario_id = ? AND leido = 0");
        $stmt->execute([$user_id]);
        set_flash('success', 'Todos los mensajes marcados como leídos.');
        redirect('mensajes.php');
    }
}

$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$stmt = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE destinatario_id = ?");
$stmt->execute([$user_id]);
$total = $stmt->fetchColumn();

$pagination = paginate($total, 20, $pagina, 'mensajes.php?pagina={page}');
$offset = ($pagination['current_page'] - 1) * 20;

$stmt = $db->prepare("
    SELECT m.*, u.email as remitente_email
    FROM mensajes m
    LEFT JOIN usuarios u ON m.remitente_id = u.id
    WHERE m.destinatario_id = ?
    ORDER BY m.created_at DESC
    LIMIT 20 OFFSET $offset
");
$stmt->execute([$user_id]);
$mensajes = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE destinatario_id = ? AND leido = 0");
$stmt->execute([$user_id]);
$no_leidos = $stmt->fetchColumn();
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
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header"><span class="text-white fw-bold">Parque Industrial</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="perfil.php"><i class="bi bi-building"></i> Mi Perfil</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <a href="formularios.php"><i class="bi bi-file-earmark-text"></i> Formularios</a>
            <a href="mensajes.php" class="active"><i class="bi bi-envelope"></i> Mensajes <?php if ($no_leidos): ?><span class="badge bg-danger"><?= $no_leidos ?></span><?php endif; ?></a>
            <a href="notificaciones.php"><i class="bi bi-bell"></i> Notificaciones</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio público</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Mensajes <?php if ($no_leidos): ?><span class="badge bg-danger"><?= $no_leidos ?> sin leer</span><?php endif; ?></h1>
            <?php if ($no_leidos > 0): ?>
            <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="leer_todas">
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-check-all me-1"></i>Marcar todos como leídos</button>
            </form>
            <?php endif; ?>
        </div>

        <?php show_flash(); ?>

        <?php if (empty($mensajes)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox display-1"></i>
            <p class="mt-3">No tiene mensajes. Los comunicados del Ministerio aparecerán aquí.</p>
        </div>
        <?php else: ?>
        <div class="list-group">
            <?php foreach ($mensajes as $m): ?>
            <div class="list-group-item <?= $m['leido'] ? '' : 'list-group-item-light border-start border-primary border-3' ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">
                            <?php if (!$m['leido']): ?>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="leer">
                                <input type="hidden" name="mensaje_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-link p-0 text-decoration-none text-dark fw-bold"><?= e($m['asunto']) ?></button>
                            </form>
                            <?php else: ?>
                                <?= e($m['asunto']) ?>
                            <?php endif; ?>
                        </h6>
                        <p class="mb-1 small text-muted">Ministerio · <?= format_datetime($m['created_at']) ?></p>
                        <p class="mb-0"><?= nl2br(e(truncate($m['contenido'], 200))) ?></p>
                        <button class="btn btn-sm btn-outline-primary mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#msg<?= $m['id'] ?>">Ver completo</button>
                        <div class="collapse mt-2" id="msg<?= $m['id'] ?>">
                            <div class="card card-body bg-light small"><?= nl2br(e($m['contenido'])) ?></div>
                        </div>
                    </div>
                    <?php if (!$m['leido']): ?><span class="badge bg-primary">Nuevo</span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?= render_pagination($pagination) ?>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
