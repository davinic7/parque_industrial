<?php
/**
 * Editar Empresa - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$db = getDB();
$emp_id = (int)($_GET['id'] ?? 0);

if ($emp_id <= 0) {
    set_flash('error', 'Empresa no encontrada');
    redirect('empresas.php');
}

$stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->execute([$emp_id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    set_flash('error', 'Empresa no encontrada');
    redirect('empresas.php');
}

$page_title = 'Editar: ' . $empresa['nombre'];

// Cargar rubros y ubicaciones
$rubros = $db->query("SELECT * FROM rubros WHERE activo = 1 ORDER BY nombre")->fetchAll();
$ubicaciones = $db->query("SELECT * FROM ubicaciones WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        set_flash('error', 'Token de seguridad inválido');
        redirect("empresa-editar.php?id=$emp_id");
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $razon_social = trim($_POST['razon_social'] ?? '');
    $cuit = trim($_POST['cuit'] ?? '');
    $cuit_digits = cuit_digits_only($cuit);
    $rubro = trim($_POST['rubro'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $latitud = !empty($_POST['latitud']) ? (float)$_POST['latitud'] : null;
    $longitud = !empty($_POST['longitud']) ? (float)$_POST['longitud'] : null;
    $telefono = trim($_POST['telefono'] ?? '');
    $email_contacto = trim($_POST['email_contacto'] ?? '');
    $contacto_nombre = trim($_POST['contacto_nombre'] ?? '');
    $sitio_web = trim($_POST['sitio_web'] ?? '');
    $facebook = trim($_POST['facebook'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $estado = trim($_POST['estado'] ?? $empresa['estado']);

    if (empty($nombre)) {
        set_flash('error', 'El nombre es obligatorio');
        redirect("empresa-editar.php?id=$emp_id");
    }

    if ($cuit_digits !== '' && !is_valid_cuit($cuit_digits)) {
        set_flash('error', 'CUIT inválido: deben ser 11 dígitos con verificador correcto (como en el panel empresa).');
        redirect("empresa-editar.php?id=$emp_id");
    }
    $cuit_guardar = ($cuit_digits !== '') ? format_cuit_argentina($cuit_digits) : '';

    if (!in_array($estado, ['pendiente', 'activa', 'suspendida', 'inactiva'])) {
        $estado = $empresa['estado'];
    }

    // Logo
    $logo = $empresa['logo'];
    if (!empty($_FILES['logo']['name'])) {
        $allowed_logo = array_values(array_unique(array_merge(ALLOWED_IMAGE_TYPES, ['image/svg+xml'])));
        $resultado = upload_image_storage($_FILES['logo'], 'logos', $allowed_logo);
        if ($resultado['success']) {
            $logo = $resultado['filename'];
        } else {
            set_flash('error', $resultado['error']);
            redirect("empresa-editar.php?id=$emp_id");
        }
    }

    try {
        $stmt = $db->prepare("
            UPDATE empresas SET nombre = ?, razon_social = ?, cuit = ?, rubro = ?, descripcion = ?,
            ubicacion = ?, direccion = ?, latitud = ?, longitud = ?, telefono = ?, email_contacto = ?,
            contacto_nombre = ?, sitio_web = ?, facebook = ?, instagram = ?, logo = ?, estado = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $nombre, $razon_social, $cuit_guardar, $rubro, $descripcion,
            $ubicacion, $direccion, $latitud, $longitud, $telefono, $email_contacto,
            $contacto_nombre, $sitio_web, $facebook, $instagram, $logo, $estado,
            $emp_id
        ]);

        log_activity('empresa_editada_ministerio', 'empresas', $emp_id);
        set_flash('success', 'Empresa actualizada correctamente');
        redirect("empresa-detalle.php?id=$emp_id");
    } catch (Exception $e) {
        error_log("Error al editar empresa: " . $e->getMessage());
        set_flash('error', 'Error al guardar los cambios');
        redirect("empresa-editar.php?id=$emp_id");
    }
}

$ministerio_nav = 'empresas';
$extra_head = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="empresa-detalle.php?id=<?= $emp_id ?>" class="text-decoration-none text-muted"><i class="bi bi-arrow-left me-1"></i>Volver al detalle</a>
                <h1 class="h3 mb-0 mt-2">Editar: <?= e($empresa['nombre']) ?></h1>
            </div>
        </div>

        <?php show_flash(); ?>

        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><h5 class="mb-0">Datos Principales</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required value="<?= e($empresa['nombre']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Razón Social</label>
                            <input type="text" name="razon_social" class="form-control" value="<?= e($empresa['razon_social'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CUIT</label>
                            <input type="text" name="cuit" class="form-control" placeholder="XX-XXXXXXXX-X" value="<?= e($empresa['cuit'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rubro</label>
                            <select name="rubro" class="form-select">
                                <option value="">Seleccione...</option>
                                <?php foreach ($rubros as $r): ?>
                                <option value="<?= e($r['nombre']) ?>" <?= ($empresa['rubro'] ?? '') === $r['nombre'] || strtoupper($empresa['rubro'] ?? '') === strtoupper($r['nombre']) ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="pendiente" <?= $empresa['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                <option value="activa" <?= $empresa['estado'] === 'activa' ? 'selected' : '' ?>>Activa</option>
                                <option value="suspendida" <?= $empresa['estado'] === 'suspendida' ? 'selected' : '' ?>>Suspendida</option>
                                <option value="inactiva" <?= $empresa['estado'] === 'inactiva' ? 'selected' : '' ?>>Inactiva</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="3"><?= e($empresa['descripcion'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <?php $logo_max_mb = max(1, (int) ceil(MAX_FILE_SIZE / 1048576)); ?>
                            <label class="form-label">Logo (JPG, PNG, GIF, WebP, SVG — máx. <?= (int) $logo_max_mb ?> MB)</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                            <?php if ($empresa['logo']): ?>
                            <small class="text-muted">Actual: <?= e($empresa['logo']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><h5 class="mb-0">Ubicación y Contacto</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Zona/Ubicación</label>
                            <select name="ubicacion" class="form-select">
                                <option value="">Seleccione...</option>
                                <?php foreach ($ubicaciones as $u): ?>
                                <option value="<?= e($u['nombre']) ?>" <?= ($empresa['ubicacion'] ?? '') === $u['nombre'] ? 'selected' : '' ?>><?= e($u['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="direccion" class="form-control" value="<?= e($empresa['direccion'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Persona de contacto</label>
                            <input type="text" name="contacto_nombre" class="form-control" value="<?= e($empresa['contacto_nombre'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control" value="<?= e($empresa['telefono'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Email contacto</label>
                            <input type="email" name="email_contacto" class="form-control" value="<?= e($empresa['email_contacto'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sitio web</label>
                            <input type="url" name="sitio_web" class="form-control" value="<?= e($empresa['sitio_web'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Facebook</label>
                            <input type="url" name="facebook" class="form-control" value="<?= e($empresa['facebook'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Instagram</label>
                            <input type="url" name="instagram" class="form-control" value="<?= e($empresa['instagram'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Latitud</label>
                            <input type="number" name="latitud" id="latitud" class="form-control" step="any" value="<?= e($empresa['latitud'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Longitud</label>
                            <input type="number" name="longitud" id="longitud" class="form-control" step="any" value="<?= e($empresa['longitud'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <div id="mapEditar" style="height: 300px; border-radius: 8px;"></div>
                            <small class="text-muted">Haga clic en el mapa para actualizar la ubicación</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-3 mb-4">
                <a href="empresa-detalle.php?id=<?= $emp_id ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save me-2"></i>Guardar cambios</button>
            </div>
        </form>

<?php
$pu = htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8');
$lat = (float) ($empresa['latitud'] ?: MAP_DEFAULT_LAT);
$lng = (float) ($empresa['longitud'] ?: MAP_DEFAULT_LNG);
$zoom = ($empresa['latitud'] && $empresa['longitud']) ? 15 : 13;
$extra_scripts = <<<HTML
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
    <script src="{$pu}/js/parque-leaflet.js"></script>
    <script>
        const lat = {$lat};
        const lng = {$lng};
        const zoom = {$zoom};
        const map = L.map('mapEditar').setView([lat, lng], zoom);
        ParqueLeaflet.addSatelliteLayer(map);
        let marker = L.marker([lat, lng], {draggable: true}).addTo(map);
        marker.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            document.getElementById('latitud').value = pos.lat.toFixed(6);
            document.getElementById('longitud').value = pos.lng.toFixed(6);
        });
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            document.getElementById('latitud').value = e.latlng.lat.toFixed(6);
            document.getElementById('longitud').value = e.latlng.lng.toFixed(6);
        });
    </script>
HTML;
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
