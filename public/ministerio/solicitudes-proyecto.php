<?php
/**
 * Solicitudes "Presentar proyecto al ministerio" - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Solicitudes de proyecto';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $id = (int)($_POST['id'] ?? 0);
    $estado = $_POST['estado'] ?? '';
    if ($id > 0 && in_array($estado, ['nueva', 'vista', 'contactada', 'cerrada'])) {
        $obs = trim($_POST['observaciones'] ?? '');
        $db->prepare("UPDATE solicitudes_proyecto SET estado = ?, observaciones = ? WHERE id = ?")->execute([$estado, $obs, $id]);
        set_flash('success', 'Estado actualizado.');
        redirect('solicitudes-proyecto.php');
    }
}

try {
    $solicitudes = $db->query("SELECT * FROM solicitudes_proyecto ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {
    $solicitudes = [];
}
$nuevas = count(array_filter($solicitudes, function($s) { return ($s['estado'] ?? '') === 'nueva'; }));
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
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <a href="banners.php"><i class="bi bi-images"></i> Banners inicio</a>
            <a href="comunicados.php"><i class="bi bi-send"></i> Enviar comunicados</a>
            <a href="nosotros-editar.php"><i class="bi bi-pencil-square"></i> Página Nosotros</a>
            <a href="solicitudes-proyecto.php" class="active"><i class="bi bi-inbox"></i> Solicitudes proyecto <?= $nuevas ? '<span class="badge bg-danger">' . $nuevas . '</span>' : '' ?></a>
            <a href="notificaciones.php"><i class="bi bi-bell"></i> Notificaciones</a>
            <a href="exportar.php"><i class="bi bi-download"></i> Exportar</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 class="h3 mb-4"><i class="bi bi-inbox me-2"></i>Solicitudes "Presentar proyecto"</h1>
        <?php show_flash(); ?>

        <?php if (empty($solicitudes)): ?>
        <div class="alert alert-info">No hay solicitudes aún.</div>
        <?php else: ?>
        <div class="list-group">
            <?php foreach ($solicitudes as $s): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h6 class="mb-1"><?= e($s['nombre_empresa']) ?></h6>
                        <p class="mb-1 small text-muted"><?= e($s['contacto']) ?> · <?= e($s['email']) ?><?= $s['telefono'] ? ' · ' . e($s['telefono']) : '' ?></p>
                        <p class="mb-1"><?= nl2br(e(truncate($s['resumen_proyecto'], 200))) ?></p>
                        <small class="text-muted"><?= format_datetime($s['created_at']) ?> · <?= $s['solicita_cita'] ? 'Solicita cita presencial' : '' ?></small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-<?= $s['estado'] === 'nueva' ? 'warning' : ($s['estado'] === 'contactada' ? 'info' : ($s['estado'] === 'cerrada' ? 'secondary' : 'primary')) ?>"><?= ucfirst($s['estado']) ?></span>
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <select name="estado" class="form-select form-select-sm" style="width: auto;">
                                <option value="nueva" <?= $s['estado'] === 'nueva' ? 'selected' : '' ?>>Nueva</option>
                                <option value="vista" <?= $s['estado'] === 'vista' ? 'selected' : '' ?>>Vista</option>
                                <option value="contactada" <?= $s['estado'] === 'contactada' ? 'selected' : '' ?>>Contactada</option>
                                <option value="cerrada" <?= $s['estado'] === 'cerrada' ? 'selected' : '' ?>>Cerrada</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Actualizar</button>
                        </form>
                    </div>
                </div>
                <?php if ($s['observaciones']): ?><p class="mb-0 mt-2 small"><strong>Obs.:</strong> <?= e($s['observaciones']) ?></p><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
