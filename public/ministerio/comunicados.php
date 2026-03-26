<?php
/**
 * Enviar comunicados - Ministerio
 * Permite enviar mensajes a una empresa o a todas.
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Enviar comunicados';
$db = getDB();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $asunto = trim($_POST['asunto'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $destino = $_POST['destino'] ?? ''; // id de empresa o 'todas'

    if (empty($asunto)) {
        set_flash('error', 'El asunto es obligatorio.');
        redirect('comunicados.php');
    }
    if (empty($contenido)) {
        set_flash('error', 'El contenido del comunicado es obligatorio.');
        redirect('comunicados.php');
    }

    try {
        $empresas_a_enviar = [];
        if ($destino === 'todas') {
            $stmt = $db->query("SELECT id, usuario_id, nombre FROM empresas WHERE estado = 'activa' AND usuario_id IS NOT NULL");
            $empresas_a_enviar = $stmt->fetchAll();
        } else {
            $emp_id = (int) $destino;
            if ($emp_id > 0) {
                $stmt = $db->prepare("SELECT id, usuario_id, nombre FROM empresas WHERE id = ? AND usuario_id IS NOT NULL");
                $stmt->execute([$emp_id]);
                $row = $stmt->fetch();
                if ($row) $empresas_a_enviar[] = $row;
            }
        }

        if (empty($empresas_a_enviar)) {
            set_flash('error', 'No hay empresas con usuario asociado para enviar el comunicado.');
            redirect('comunicados.php');
        }

        $stmt = $db->prepare("
            INSERT INTO mensajes (remitente_id, destinatario_id, empresa_id, asunto, contenido)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($empresas_a_enviar as $emp) {
            $stmt->execute([$user_id, $emp['usuario_id'], $emp['id'], $asunto, $contenido]);
        }

        log_activity('comunicado_enviado', 'mensajes', null);
        set_flash('success', 'Comunicado enviado a ' . count($empresas_a_enviar) . (count($empresas_a_enviar) === 1 ? ' empresa.' : ' empresas.'));
        redirect('comunicados.php');
    } catch (Exception $e) {
        error_log("Error enviar comunicado: " . $e->getMessage());
        set_flash('error', 'Error al enviar el comunicado. Intente nuevamente.');
        redirect('comunicados.php');
    }
}

$empresas = $db->query("SELECT id, nombre FROM empresas WHERE estado = 'activa' ORDER BY nombre")->fetchAll();
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
            <a href="comunicados.php" class="active"><i class="bi bi-send"></i> Enviar comunicados</a>
            <a href="notificaciones.php"><i class="bi bi-bell"></i> Notificaciones</a>
            <a href="exportar.php"><i class="bi bi-download"></i> Exportar</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 class="h3 mb-4"><i class="bi bi-send me-2"></i>Enviar comunicado</h1>
        <?php show_flash(); ?>

        <div class="card">
            <div class="card-body">
                <p class="text-muted">Envíe un mensaje a una empresa en particular o a todas las empresas activas. Lo verán en su bandeja de <strong>Mensajes</strong>.</p>
                <form method="POST" class="mt-3">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Destinatario *</label>
                        <select name="destino" class="form-select" required>
                            <option value="todas">Todas las empresas activas</option>
                            <?php foreach ($empresas as $e): ?>
                                <option value="<?= (int)$e['id'] ?>"><?= e($e['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Asunto *</label>
                        <input type="text" name="asunto" class="form-control" required maxlength="255" placeholder="Ej: Recordatorio de declaración jurada">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mensaje *</label>
                        <textarea name="contenido" class="form-control" rows="6" required placeholder="Escriba el contenido del comunicado..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Enviar comunicado</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
