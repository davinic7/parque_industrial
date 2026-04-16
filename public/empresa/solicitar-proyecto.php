<?php
/**
 * Solicitar presentación de proyecto al Ministerio - Panel Empresa
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Presentar proyecto';
$db = getDB();
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
$user_id    = (int) ($_SESSION['user_id'] ?? 0);
$empresa_nombre = $_SESSION['empresa_nombre'] ?? '';
$error   = '';
$success = '';

/* ── Cargar historial de esta empresa ── */
try {
    $historial = $db->prepare(
        "SELECT id, resumen_proyecto, link_externo, archivo_nombre, solicita_cita, estado, observaciones, created_at
         FROM solicitudes_proyecto
         WHERE empresa_id = ?
         ORDER BY created_at DESC"
    );
    $historial->execute([$empresa_id]);
    $historial = $historial->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $historial = [];
}

/* ── Procesamiento POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido. Recargue la página.';
    } else {
        $modo           = $_POST['modo'] ?? 'formulario'; // formulario | link | archivo
        $contacto       = trim($_POST['contacto'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $telefono       = trim($_POST['telefono'] ?? '');
        $resumen        = trim($_POST['resumen_proyecto'] ?? '');
        $solicita_cita  = isset($_POST['solicita_cita']) ? 1 : 0;
        $link_externo   = null;
        $archivo_url    = null;
        $archivo_nombre = null;

        if (empty($contacto) || empty($email)) {
            $error = 'Los campos Contacto y Email son obligatorios.';
        } elseif (!is_valid_email($email)) {
            $error = 'El email ingresado no es válido.';
        } elseif ($modo === 'formulario' && $resumen === '') {
            $error = 'Debe completar el resumen del proyecto.';
        } elseif ($modo === 'link') {
            $link_raw = trim($_POST['link_externo'] ?? '');
            if ($link_raw === '') {
                $error = 'Debe ingresar un enlace.';
            } elseif (!filter_var($link_raw, FILTER_VALIDATE_URL)) {
                $error = 'El enlace ingresado no es una URL válida.';
            } else {
                $link_externo = $link_raw;
                if ($resumen === '') $resumen = 'Proyecto presentado mediante enlace externo.';
            }
        } elseif ($modo === 'archivo') {
            if (empty($_FILES['archivo']['name'])) {
                $error = 'Debe seleccionar un archivo.';
            } else {
                $file = $_FILES['archivo'];
                $allowed_mime = ['application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
                $detected = resolve_upload_mime_to_allowed($file, $allowed_mime);
                if (!$detected) {
                    $error = 'Formato no permitido. Use PDF, Word (.docx) o PowerPoint (.pptx).';
                } elseif ($file['size'] > 20 * 1024 * 1024) {
                    $error = 'El archivo no puede superar los 20 MB.';
                } else {
                    // Subir via Cloudinary (raw) o disco
                    if (cloudinary_configured()) {
                        $url = cloudinary_upload_raw($file['tmp_name']);
                        if (!$url) {
                            $error = 'Error al subir el archivo. Intente nuevamente.';
                        } else {
                            $archivo_url    = $url;
                            $archivo_nombre = basename($file['name']);
                        }
                    } else {
                        $result = upload_file($file, 'proyectos', $allowed_mime, $detected);
                        if (!$result['success']) {
                            $error = $result['error'] ?? 'Error al subir el archivo.';
                        } else {
                            $archivo_url    = $result['filename'];
                            $archivo_nombre = basename($file['name']);
                        }
                    }
                    if (!$error && $resumen === '') {
                        $resumen = 'Proyecto presentado mediante archivo adjunto.';
                    }
                }
            }
        }

        if (!$error) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO solicitudes_proyecto
                        (empresa_id, nombre_empresa, contacto, email, telefono,
                         resumen_proyecto, link_externo, archivo_url, archivo_nombre, solicita_cita)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $empresa_id, $empresa_nombre, $contacto, $email, $telefono,
                    $resumen, $link_externo, $archivo_url, $archivo_nombre, $solicita_cita
                ]);
                log_activity('solicitud_proyecto_enviada', 'solicitudes_proyecto', (int)$db->lastInsertId());
                $success = 'Su solicitud fue enviada al Ministerio. Le responderemos a la brevedad.';
                // Recargar historial
                $h = $db->prepare("SELECT id, resumen_proyecto, link_externo, archivo_nombre, solicita_cita, estado, observaciones, created_at FROM solicitudes_proyecto WHERE empresa_id = ? ORDER BY created_at DESC");
                $h->execute([$empresa_id]);
                $historial = $h->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                error_log('solicitar-proyecto: ' . $e->getMessage());
                $error = 'No se pudo enviar la solicitud. Intente nuevamente.';
            }
        }
    }
}

$empresa_nav = 'solicitar_proyecto';
require_once BASEPATH . '/includes/empresa_layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="h4 mb-0 fw-semibold"><i class="bi bi-send me-2"></i>Presentar proyecto al Ministerio</h2>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i><?= e($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card empresa-card-soft">
            <div class="card-header fw-semibold">Nueva solicitud</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="formSolicitud">
                    <?= csrf_field() ?>

                    <!-- Datos de contacto -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Persona de contacto <span class="text-danger">*</span></label>
                            <input type="text" name="contacto" class="form-control"
                                   value="<?= e($_POST['contacto'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= e($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="telefono" class="form-control"
                                   value="<?= e($_POST['telefono'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Selector de modo -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">¿Cómo desea presentar el proyecto?</label>
                        <div class="d-flex gap-2 flex-wrap" id="modoSelector">
                            <input type="hidden" name="modo" id="modoInput" value="formulario">
                            <button type="button" class="btn btn-sm modo-btn btn-primary" data-modo="formulario">
                                <i class="bi bi-file-text me-1"></i>Descripción
                            </button>
                            <button type="button" class="btn btn-sm modo-btn btn-outline-secondary" data-modo="link">
                                <i class="bi bi-link-45deg me-1"></i>Enlace externo
                            </button>
                            <button type="button" class="btn btn-sm modo-btn btn-outline-secondary" data-modo="archivo">
                                <i class="bi bi-paperclip me-1"></i>Adjuntar archivo
                            </button>
                        </div>
                    </div>

                    <!-- Modo: formulario -->
                    <div id="modo-formulario" class="modo-panel">
                        <label class="form-label">Resumen del proyecto <span class="text-danger">*</span></label>
                        <textarea name="resumen_proyecto" class="form-control" rows="5"
                                  placeholder="Describa su proyecto: objetivos, rubros, necesidades, etapa actual..."><?= e($_POST['resumen_proyecto'] ?? '') ?></textarea>
                    </div>

                    <!-- Modo: link -->
                    <div id="modo-link" class="modo-panel d-none">
                        <label class="form-label">Enlace al proyecto <span class="text-danger">*</span></label>
                        <input type="url" name="link_externo" class="form-control"
                               placeholder="https://drive.google.com/... o cualquier URL"
                               value="<?= e($_POST['link_externo'] ?? '') ?>">
                        <div class="form-text">Puede ser Google Drive, Dropbox, OneDrive, sitio web, etc.</div>
                        <div class="mt-3">
                            <label class="form-label">Descripción breve (opcional)</label>
                            <textarea name="resumen_proyecto" class="form-control" rows="3"
                                      placeholder="Breve introducción al proyecto..."><?= e($_POST['resumen_proyecto'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Modo: archivo -->
                    <div id="modo-archivo" class="modo-panel d-none">
                        <label class="form-label">Archivo del proyecto <span class="text-danger">*</span></label>
                        <input type="file" name="archivo" class="form-control"
                               accept=".pdf,.doc,.docx,.ppt,.pptx">
                        <div class="form-text">Formatos aceptados: PDF, Word, PowerPoint. Máximo 20 MB.</div>
                        <div class="mt-3">
                            <label class="form-label">Descripción breve (opcional)</label>
                            <textarea name="resumen_proyecto" class="form-control" rows="3"
                                      placeholder="Breve introducción al proyecto..."><?= e($_POST['resumen_proyecto'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="form-check">
                            <input type="checkbox" name="solicita_cita" class="form-check-input" id="solicita_cita"
                                   value="1" <?= isset($_POST['solicita_cita']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="solicita_cita">
                                Solicitar reunión presencial con el Ministerio
                            </label>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-2"></i>Enviar al Ministerio
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Historial -->
    <div class="col-lg-5">
        <div class="card empresa-card-soft">
            <div class="card-header fw-semibold">Mis solicitudes enviadas</div>
            <div class="card-body p-0">
                <?php if (empty($historial)): ?>
                <p class="text-muted text-center py-4 mb-0">Aún no enviaste ninguna solicitud.</p>
                <?php else: ?>
                <div class="empresa-timeline-scroll">
                <?php
                $badge_map = [
                    'nueva'      => 'warning',
                    'vista'      => 'primary',
                    'contactada' => 'info',
                    'cerrada'    => 'secondary',
                ];
                $label_map = [
                    'nueva'      => 'Nueva',
                    'vista'      => 'Vista',
                    'contactada' => 'Contactada',
                    'cerrada'    => 'Cerrada',
                ];
                foreach ($historial as $h):
                    $color = $badge_map[$h['estado']] ?? 'secondary';
                    $label = $label_map[$h['estado']] ?? ucfirst($h['estado']);
                ?>
                <div class="p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="small text-muted"><?= format_datetime($h['created_at']) ?></div>
                        <span class="badge bg-<?= $color ?>"><?= $label ?></span>
                    </div>
                    <p class="mb-1 mt-1 small"><?= e(truncate($h['resumen_proyecto'], 120)) ?></p>
                    <?php if ($h['link_externo']): ?>
                    <a href="<?= e($h['link_externo']) ?>" target="_blank" rel="noopener"
                       class="small text-primary d-block text-truncate">
                        <i class="bi bi-link-45deg me-1"></i><?= e($h['link_externo']) ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($h['archivo_nombre']): ?>
                    <span class="small text-muted"><i class="bi bi-paperclip me-1"></i><?= e($h['archivo_nombre']) ?></span>
                    <?php endif; ?>
                    <?php if ($h['solicita_cita']): ?>
                    <div class="small text-info mt-1"><i class="bi bi-calendar-check me-1"></i>Solicitó reunión presencial</div>
                    <?php endif; ?>
                    <?php if ($h['observaciones']): ?>
                    <div class="small mt-1 p-2 bg-light rounded">
                        <strong>Respuesta del Ministerio:</strong> <?= e($h['observaciones']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$extra_scripts = <<<'JS'
<script>
(function () {
    const modoInput = document.getElementById('modoInput');
    const paneles   = document.querySelectorAll('.modo-panel');
    const botones   = document.querySelectorAll('.modo-btn');

    function activarModo(modo) {
        modoInput.value = modo;
        paneles.forEach(p => p.classList.add('d-none'));
        document.getElementById('modo-' + modo).classList.remove('d-none');
        botones.forEach(b => {
            const activo = b.dataset.modo === modo;
            b.classList.toggle('btn-primary', activo);
            b.classList.toggle('btn-outline-secondary', !activo);
        });
    }

    botones.forEach(b => b.addEventListener('click', () => activarModo(b.dataset.modo)));
    activarModo('formulario');
})();
</script>
JS;
require_once BASEPATH . '/includes/empresa_layout_footer.php';
