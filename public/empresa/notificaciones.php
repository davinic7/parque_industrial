<?php
/**
 * Notificaciones - Panel Empresa
 */
require_once __DIR__ . '/../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Notificaciones';
$db = getDB();
$user_id = $_SESSION['user_id'];

// Marcar como leída
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'leer') {
        $nid = (int)($_POST['notificacion_id'] ?? 0);
        if ($nid > 0) {
            $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$nid, $user_id]);
        }
    } elseif ($accion === 'leer_todas') {
        $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0");
        $stmt->execute([$user_id]);
        set_flash('success', 'Todas las notificaciones marcadas como leídas');
        redirect('notificaciones.php');
    }
}

$pagina = max(1, (int)($_GET['pagina'] ?? 1));

$stmt = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$total = $stmt->fetchColumn();

$pagination = paginate($total, 20, $pagina, 'notificaciones.php?pagina={page}');
$offset = ($pagination['current_page'] - 1) * 20;

$stmt = $db->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 20 OFFSET $offset");
$stmt->execute([$user_id]);
$notificaciones = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0");
$stmt->execute([$user_id]);
$no_leidas = $stmt->fetchColumn();
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
            <a href="notificaciones.php" class="active"><i class="bi bi-bell"></i> Notificaciones <?php if ($no_leidas): ?><span class="badge bg-danger"><?= $no_leidas ?></span><?php endif; ?></a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio público</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Notificaciones <?php if ($no_leidas): ?><span class="badge bg-danger"><?= $no_leidas ?> sin leer</span><?php endif; ?></h1>
            <?php if ($no_leidas > 0): ?>
            <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="leer_todas">
                <button class="btn btn-outline-primary btn-sm"><i class="bi bi-check-all me-1"></i>Marcar todas como leídas</button>
            </form>
            <?php endif; ?>
        </div>

        <?php show_flash(); ?>

        <?php if (empty($notificaciones)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-bell-slash display-1"></i>
            <p class="mt-3">No tiene notificaciones</p>
        </div>
        <?php else: ?>
        <div class="list-group">
            <?php foreach ($notificaciones as $n): ?>
            <div class="list-group-item <?= $n['leida'] ? '' : 'list-group-item-light border-start border-primary border-3' ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1"><?= $n['leida'] ? '' : '<i class="bi bi-circle-fill text-primary me-1" style="font-size: 0.5rem;"></i>' ?><?= e($n['titulo']) ?></h6>
                        <p class="mb-1"><?= e($n['mensaje']) ?></p>
                        <small class="text-muted"><?= format_datetime($n['created_at']) ?></small>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($n['url']): ?>
                        <a href="<?= e($n['url']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-right"></i></a>
                        <?php endif; ?>
                        <?php if (!$n['leida']): ?>
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="leer">
                            <input type="hidden" name="notificacion_id" value="<?= $n['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary" title="Marcar como leída"><i class="bi bi-check"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
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
