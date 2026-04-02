<?php
/**
 * Editar contenido de la página Nosotros - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Editar página Nosotros';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $keys = [
        'nosotros_titulo' => trim($_POST['nosotros_titulo'] ?? ''),
        'nosotros_subtitulo' => trim($_POST['nosotros_subtitulo'] ?? ''),
        'nosotros_texto' => trim($_POST['nosotros_texto'] ?? ''),
        'nosotros_contacto_direccion' => trim($_POST['nosotros_contacto_direccion'] ?? ''),
        'nosotros_contacto_email' => trim($_POST['nosotros_contacto_email'] ?? ''),
        'nosotros_contacto_telefono' => trim($_POST['nosotros_contacto_telefono'] ?? ''),
    ];
    try {
        $stmt = $db->prepare("INSERT INTO configuracion_sitio (clave, valor, tipo, grupo) VALUES (?, ?, 'text', 'nosotros') ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        foreach ($keys as $clave => $valor) {
            $tipo = ($clave === 'nosotros_texto' || $clave === 'nosotros_contacto_direccion') ? 'textarea' : 'text';
            $db->prepare("INSERT INTO configuracion_sitio (clave, valor, tipo, grupo) VALUES (?, ?, ?, 'nosotros') ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo)")->execute([$clave, $valor, $tipo]);
        }
        set_flash('success', 'Contenido de la página Nosotros guardado correctamente.');
        redirect('nosotros-editar.php');
    } catch (Exception $e) {
        error_log("nosotros-editar: " . $e->getMessage());
        set_flash('error', 'Error al guardar. Intente nuevamente.');
    }
}

$titulo = get_config('nosotros_titulo', 'Parque Industrial de Catamarca');
$subtitulo = get_config('nosotros_subtitulo', 'Impulsando el desarrollo productivo de la provincia');
$texto_parque = get_config('nosotros_texto', '');
$contacto_direccion = get_config('nosotros_contacto_direccion', '');
$contacto_email = get_config('nosotros_contacto_email', '');
$contacto_telefono = get_config('nosotros_contacto_telefono', '');

$ministerio_nav = 'nosotros';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <h2 class="h4 mb-4 fw-semibold"><i class="bi bi-pencil-square me-2"></i>Editar página Nosotros</h2>
        <?php show_flash(); ?>
        <p class="text-muted">Los cambios se reflejan en la página pública <a href="<?= PUBLIC_URL ?>/nosotros.php" target="_blank">Nosotros</a>.</p>

        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Título principal</label>
                        <input type="text" name="nosotros_titulo" class="form-control" value="<?= e($titulo) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subtítulo</label>
                        <input type="text" name="nosotros_subtitulo" class="form-control" value="<?= e($subtitulo) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Texto sobre el parque</label>
                        <textarea name="nosotros_texto" class="form-control" rows="6"><?= e($texto_parque) ?></textarea>
                    </div>
                    <hr>
                    <h5 class="mb-3">Contacto</h5>
                    <div class="mb-3">
                        <label class="form-label">Dirección (una línea por párrafo)</label>
                        <textarea name="nosotros_contacto_direccion" class="form-control" rows="3"><?= e($contacto_direccion) ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="nosotros_contacto_email" class="form-control" value="<?= e($contacto_email) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="nosotros_contacto_telefono" class="form-control" value="<?= e($contacto_telefono) ?>">
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
                        <a href="<?= PUBLIC_URL ?>/nosotros.php" target="_blank" class="btn btn-outline-secondary">Ver página</a>
                    </div>
                </form>
            </div>
        </div>

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>
