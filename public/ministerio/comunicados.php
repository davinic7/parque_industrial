<?php
/**
 * Enviar comunicados - Ministerio
 * Permite enviar mensajes a una empresa o a todas.
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

// Si el Centro de Comunicaciones esta activo, redirigir al nuevo panel.
require_once BASEPATH . '/includes/comunicaciones.php';
if (FEATURE_CENTRO_COMS && coms_schema_disponible()) {
    redirect('comunicaciones.php');
}

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

$ministerio_nav = 'comunicados';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <h2 class="h4 mb-4 fw-semibold"><i class="bi bi-send me-2"></i>Enviar comunicado</h2>
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

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>
