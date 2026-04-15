<?php
/**
 * Perfil de Empresa - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Mi Perfil';
$mensaje = '';
$error = '';
/** Errores de validación por nombre de campo (evita banner genérico y mantiene el foco en el input). */
$field_errors = [];
$empresa_id = $_SESSION['empresa_id'] ?? null;

if (!$empresa_id) {
    set_flash('error', 'No se encontró la empresa asociada a su cuenta');
    redirect('dashboard.php');
}

$db = getDB();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido. Recargue la página.';
    } else {
        try {
            // Acciones de galería (antes del formulario principal)
            if (isset($_POST['galeria_eliminar']) && isset($_POST['imagen_id'])) {
                $id_img = (int)$_POST['imagen_id'];
                $db->prepare("DELETE FROM empresa_imagenes WHERE id = ? AND empresa_id = ?")->execute([$id_img, $empresa_id]);
                set_flash('success', 'Imagen eliminada de la galería.');
                redirect('perfil.php');
            }
            if (!empty($_FILES['galeria_imagen']['name']) && $_FILES['galeria_imagen']['error'] === UPLOAD_ERR_OK) {
                try {
                    $db->query("SELECT 1 FROM empresa_imagenes LIMIT 1");
                    $upload = upload_image_storage($_FILES['galeria_imagen'], 'galeria_empresa', ALLOWED_IMAGE_TYPES);
                    if ($upload['success']) {
                        $stmt = $db->prepare("SELECT COALESCE(MAX(orden), 0) + 1 FROM empresa_imagenes WHERE empresa_id = ?");
                        $stmt->execute([$empresa_id]);
                        $orden = (int)$stmt->fetchColumn();
                        $db->prepare("INSERT INTO empresa_imagenes (empresa_id, imagen, orden) VALUES (?, ?, ?)")->execute([$empresa_id, $upload['filename'], $orden]);
                        set_flash('success', 'Imagen agregada a la galería.');
                        redirect('perfil.php');
                    } else {
                        $field_errors['galeria_imagen'] = $upload['error'];
                    }
                } catch (Exception $e) {
                    $field_errors['galeria_imagen'] = 'No se pudo subir la imagen.';
                }
            }

            // Obtener datos anteriores para el log
            $stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
            $stmt->execute([$empresa_id]);
            $datos_anteriores = $stmt->fetch();

            if (!$datos_anteriores) {
                set_flash('error', 'Empresa no encontrada');
                redirect('dashboard.php');
            }

            // El formulario de galería no envía "nombre"; no validar ni actualizar el perfil en ese caso
            if (array_key_exists('nombre', $_POST)) {

            // Sanitizar inputs
            $nombre = trim($_POST['nombre'] ?? '');
            $razon_social = trim($_POST['razon_social'] ?? '');
            $cuit = trim($_POST['cuit'] ?? '');
            $cuit_digits = cuit_digits_only($cuit);
            if ($cuit !== '' && $cuit_digits === '') {
                $cuit = '';
            }
            $rubro = trim($_POST['rubro'] ?? '') ?: null;
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

            // Validaciones
            if ($nombre === '') {
                $field_errors['nombre'] = 'El nombre comercial es obligatorio';
            }
            if ($rubro === null || $rubro === '') {
                $field_errors['rubro'] = 'Seleccioná un rubro';
            }
            if ($cuit_digits !== '' && !is_valid_cuit($cuit_digits)) {
                $field_errors['cuit'] = 'CUIT no válido: deben ser los 11 dígitos de AFIP (el último es verificador). Revisá que no falte ni sobre un dígito; con o sin guiones está bien.';
            }
            if ($email_contacto !== '' && !is_valid_email($email_contacto)) {
                $field_errors['email_contacto'] = 'El email de contacto no es válido';
            }

            if (empty($field_errors)) {
                // Procesar logo si se subió
                $logo_filename = $datos_anteriores['logo'];
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $upload = upload_image_storage($_FILES['logo'], 'logos', ALLOWED_IMAGE_TYPES);
                    if ($upload['success']) {
                        $logo_filename = $upload['filename'];
                    } else {
                        $field_errors['logo'] = $upload['error'];
                    }
                }

                if (empty($field_errors)) {
                    $cuit_guardar = ($cuit_digits !== '') ? format_cuit_argentina($cuit_digits) : '';
                    $stmt = $db->prepare("
                        UPDATE empresas SET
                            nombre = ?, razon_social = ?, cuit = ?, rubro = ?,
                            descripcion = ?, ubicacion = ?, direccion = ?,
                            latitud = ?, longitud = ?,
                            telefono = ?, email_contacto = ?, contacto_nombre = ?,
                            sitio_web = ?, facebook = ?, instagram = ?, logo = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $nombre, $razon_social, $cuit_guardar, $rubro,
                        $descripcion, $ubicacion, $direccion,
                        $latitud, $longitud,
                        $telefono, $email_contacto, $contacto_nombre,
                        $sitio_web, $facebook, $instagram, $logo_filename,
                        $empresa_id
                    ]);

                    // Actualizar nombre en sesión
                    $_SESSION['empresa_nombre'] = $nombre;

                    log_activity('perfil_actualizado', 'empresas', $empresa_id, $datos_anteriores);
                    $mensaje = 'Perfil actualizado correctamente';
                }
            }
            }
        } catch (Exception $e) {
            error_log("Error al actualizar perfil empresa_id=$empresa_id: " . $e->getMessage());
            $error = 'Error al guardar los cambios. Intente nuevamente.';
            if (function_exists('env_bool') && env_bool('APP_DEBUG', false)) {
                $error .= ' (' . $e->getMessage() . ')';
            }
        }
    }
}

// Cargar datos de la empresa desde BD
$stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->execute([$empresa_id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    set_flash('error', 'Empresa no encontrada');
    redirect('dashboard.php');
}

// Tras un error de validación, mantener lo que el usuario cargó (no volver todo a la BD)
$csrf_msg = 'Token de seguridad inválido. Recargue la página.';
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (!empty($field_errors) || ($error !== '' && $error !== $csrf_msg))
) {
    $campos_texto = [
        'nombre', 'razon_social', 'cuit', 'rubro', 'descripcion', 'ubicacion', 'direccion',
        'telefono', 'email_contacto', 'contacto_nombre', 'sitio_web', 'facebook', 'instagram',
    ];
    foreach ($campos_texto as $c) {
        if (array_key_exists($c, $_POST)) {
            $empresa[$c] = is_string($_POST[$c]) ? trim($_POST[$c]) : $_POST[$c];
        }
    }
    if (array_key_exists('latitud', $_POST)) {
        $empresa['latitud'] = $_POST['latitud'] !== '' && $_POST['latitud'] !== null
            ? $_POST['latitud'] : null;
    }
    if (array_key_exists('longitud', $_POST)) {
        $empresa['longitud'] = $_POST['longitud'] !== '' && $_POST['longitud'] !== null
            ? $_POST['longitud'] : null;
    }
}

// Obtener rubros desde la tabla rubros
// En MySQL 8 (modo estricto) DISTINCT + ORDER BY en columna no seleccionada da error,
// por eso se quita DISTINCT y se ordena por orden, nombre
$stmt = $db->query("SELECT nombre FROM rubros WHERE activo = 1 ORDER BY orden, nombre");
$rubros = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Obtener ubicaciones
$stmt = $db->query("SELECT nombre FROM ubicaciones WHERE activo = 1 ORDER BY nombre");
$ubicaciones = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Galería de imágenes (tabla empresa_imagenes)
$galeria_imagenes = [];
$tabla_galeria_existe = false;
try {
    $db->query("SELECT 1 FROM empresa_imagenes LIMIT 1");
    $tabla_galeria_existe = true;
    $stmt = $db->prepare("SELECT * FROM empresa_imagenes WHERE empresa_id = ? ORDER BY orden ASC, id ASC");
    $stmt->execute([$empresa_id]);
    $galeria_imagenes = $stmt->fetchAll();
} catch (Exception $e) {
    $galeria_imagenes = [];
}

$empresa_nav = '';
$extra_head = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">';
require_once BASEPATH . '/includes/empresa_layout_header.php';
?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Editar Perfil de Empresa</h1>
            <a href="<?= PUBLIC_URL ?>/empresa.php?id=<?= $empresa['id'] ?>" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-eye me-1"></i>Ver perfil público
            </a>
        </div>

        <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= e($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <div class="row g-4">
                <!-- Datos básicos -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white"><h5 class="mb-0">Datos de la Empresa</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre comercial *</label>
                                    <input type="text" name="nombre" class="form-control<?= isset($field_errors['nombre']) ? ' is-invalid' : '' ?>" value="<?= e($empresa['nombre']) ?>" required>
                                    <?php if (isset($field_errors['nombre'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($field_errors['nombre']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Razón social</label>
                                    <input type="text" name="razon_social" class="form-control" value="<?= e($empresa['razon_social'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">CUIT</label>
                                    <input type="text" name="cuit" id="inputCuit" class="form-control<?= isset($field_errors['cuit']) ? ' is-invalid' : '' ?>" value="<?= e($empresa['cuit'] ?? '') ?>" placeholder="Ej. 20-12345678-6" inputmode="numeric" autocomplete="off" maxlength="13">
                                    <?php if (isset($field_errors['cuit'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($field_errors['cuit']) ?></div>
                                    <?php else: ?>
                                    <small class="text-muted">Usá el CUIT tal como figura en AFIP: 11 dígitos y el último es verificador (un cambio de un solo dígito suele invalidarlo). Guiones opcionales. Vacío si no aplica.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Rubro *</label>
                                    <select name="rubro" class="form-select<?= isset($field_errors['rubro']) ? ' is-invalid' : '' ?>" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($rubros as $r): ?>
                                        <option value="<?= e($r) ?>" <?= ($empresa['rubro'] ?? '') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($field_errors['rubro'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($field_errors['rubro']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Descripción</label>
                                    <textarea name="descripcion" class="form-control" rows="4" placeholder="Describe tu empresa..."><?= e($empresa['descripcion'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ubicación -->
                    <div class="card mt-4">
                        <div class="card-header bg-white"><h5 class="mb-0">Ubicación</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Zona/Parque Industrial</label>
                                    <select name="ubicacion" class="form-select">
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($ubicaciones as $ub): ?>
                                        <option value="<?= e($ub) ?>" <?= ($empresa['ubicacion'] ?? '') === $ub ? 'selected' : '' ?>><?= e($ub) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Dirección exacta</label>
                                    <input type="text" name="direccion" class="form-control" value="<?= e($empresa['direccion'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Ubicación en el mapa</label>
                                    <p class="text-muted small mb-2 mb-md-3">Vista previa <strong>fija</strong> (satélite). Para marcar o cambiar el punto, abrí el mapa con el botón, hacé clic (o arrastrá el marcador) y cerrá con «Listo».</p>
                                    <div id="mapPreviewWrap" class="border rounded overflow-hidden shadow-sm mb-2">
                                        <div id="mapPreview" class="map-preview-ficha" style="display: none; height: 200px;"></div>
                                        <div id="mapPreviewEmpty" class="d-flex align-items-center justify-content-center text-muted small px-3 py-5">
                                            <span class="text-center"><i class="bi bi-geo-alt d-block fs-3 mb-2 opacity-50"></i>Aún no hay ubicación. Tocá «Seleccionar ubicación en el mapa».</span>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <button type="button" class="btn btn-outline-primary" id="btnAbrirMapaUbicacion">
                                            <i class="bi bi-map me-1"></i>Seleccionar ubicación en el mapa
                                        </button>
                                        <button type="button" class="btn btn-success d-none" id="btnCerrarMapaUbicacion">
                                            <i class="bi bi-check2 me-1"></i>Listo
                                        </button>
                                    </div>
                                    <div id="mapEditorPanel" class="d-none border rounded overflow-hidden p-2 p-md-3 bg-light">
                                        <p class="small text-muted mb-2">Mapa interactivo · clic para ubicar · marcador arrastrable · zoom con controles o rueda</p>
                                        <div id="mapPicker" style="height: 300px; border-radius: 8px;"></div>
                                    </div>
                                    <input type="hidden" name="latitud" id="latitud" value="<?= e($empresa['latitud'] ?? '') ?>">
                                    <input type="hidden" name="longitud" id="longitud" value="<?= e($empresa['longitud'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contacto -->
                    <div class="card mt-4">
                        <div class="card-header bg-white"><h5 class="mb-0">Información de Contacto</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono</label>
                                    <input type="tel" name="telefono" class="form-control" value="<?= e($empresa['telefono'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email de contacto</label>
                                    <input type="email" name="email_contacto" class="form-control<?= isset($field_errors['email_contacto']) ? ' is-invalid' : '' ?>" value="<?= e($empresa['email_contacto'] ?? '') ?>">
                                    <?php if (isset($field_errors['email_contacto'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($field_errors['email_contacto']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Persona de contacto</label>
                                    <input type="text" name="contacto_nombre" class="form-control" value="<?= e($empresa['contacto_nombre'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sitio web</label>
                                    <input type="url" name="sitio_web" class="form-control" value="<?= e($empresa['sitio_web'] ?? '') ?>" placeholder="https://">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logo y redes -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-white"><h5 class="mb-0">Logo</h5></div>
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <?php
                                $logo_src = '';
                                if (!empty($empresa['logo'])) {
                                    $logo_src = uploads_resolve_url($empresa['logo'], 'logos');
                                } else {
                                    $logo_src = 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><rect fill="#e9ecef" width="120" height="120"/><text x="60" y="68" font-size="48" fill="#6c757d" text-anchor="middle">🏢</text></svg>');
                                }
                                ?>
                                <img id="logoPreview" src="<?= $logo_src ?>" alt="Logo" class="img-fluid rounded d-block mx-auto" style="max-height: 150px; background: #f8f9fa;">
                            </div>
                            <input type="file" name="logo" class="form-control<?= isset($field_errors['logo']) ? ' is-invalid' : '' ?>" accept="image/*">
                            <?php if (isset($field_errors['logo'])): ?>
                            <div class="invalid-feedback d-block text-start"><?= e($field_errors['logo']) ?></div>
                            <small class="text-muted d-block">Volvé a elegir el archivo si querés cambiar el logo.</small>
                            <?php else: ?>
                            <?php $logo_max_mb = max(1, (int) ceil(MAX_FILE_SIZE / 1048576)); ?>
                            <small class="text-muted">JPG, PNG, GIF, WebP. Máx. <?= (int) $logo_max_mb ?> MB.</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header bg-white"><h5 class="mb-0">Redes Sociales</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-facebook text-primary"></i> Facebook</label>
                                <input type="url" name="facebook" class="form-control" value="<?= e($empresa['facebook'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-instagram text-danger"></i> Instagram</label>
                                <input type="url" name="instagram" class="form-control" value="<?= e($empresa['instagram'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-lg me-2"></i>Guardar cambios
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($tabla_galeria_existe): ?>
        <div class="row g-4 mt-0">
            <div class="col-lg-8">
                <div class="card mt-4">
                    <div class="card-header bg-white"><h5 class="mb-0">Galería de imágenes</h5></div>
                    <div class="card-body">
                        <p class="text-muted small">Estas imágenes se muestran en el carrusel de tu perfil público.</p>
                        <?php if (!empty($galeria_imagenes)): ?>
                        <div class="row g-2 mb-3">
                            <?php foreach ($galeria_imagenes as $img): ?>
                            <div class="col-auto">
                                <div class="position-relative d-inline-block">
                                    <img src="<?= e(uploads_resolve_url($img['imagen'], 'galeria_empresa')) ?>" alt="" class="rounded" style="width: 80px; height: 80px; object-fit: cover;">
                                    <form method="POST" class="position-absolute top-0 end-0" style="transform: translate(50%, -50%);" onsubmit="return confirm('¿Eliminar esta imagen?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="galeria_eliminar" value="1">
                                        <input type="hidden" name="imagen_id" value="<?= $img['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm rounded-circle p-0" style="width: 24px; height: 24px;" title="Eliminar"><i class="bi bi-x small"></i></button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <form method="POST" enctype="multipart/form-data" class="d-flex align-items-end gap-2 flex-wrap">
                            <?= csrf_field() ?>
                            <div class="flex-grow-1" style="min-width: 200px;">
                                <input type="file" name="galeria_imagen" class="form-control form-control-sm<?= isset($field_errors['galeria_imagen']) ? ' is-invalid' : '' ?>" accept="image/*" required>
                                <?php if (isset($field_errors['galeria_imagen'])): ?>
                                <div class="invalid-feedback d-block"><?= e($field_errors['galeria_imagen']) ?></div>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Agregar imagen</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

<?php
$pu = htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8');
$mapLat = (float) MAP_DEFAULT_LAT;
$mapLng = (float) MAP_DEFAULT_LNG;
$extra_scripts = <<<HTML
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
    <script src="{$pu}/js/parque-leaflet.js"></script>
    <script>
        (function() {
            const cuitEl = document.getElementById('inputCuit');
            if (!cuitEl) return;
            function formatCuitDisplay(raw) {
                let d = String(raw).replace(/\D/g, '').slice(0, 11);
                if (d.length <= 2) return d;
                if (d.length <= 10) return d.slice(0, 2) + '-' + d.slice(2);
                return d.slice(0, 2) + '-' + d.slice(2, 10) + '-' + d.slice(10);
            }
            cuitEl.addEventListener('input', function() {
                const cur = this.selectionStart;
                const before = this.value.length;
                this.value = formatCuitDisplay(this.value);
                const after = this.value.length;
                try {
                    this.setSelectionRange(cur + (after - before), cur + (after - before));
                } catch (e) {}
            });
        })();

        document.querySelector('input[name="logo"]').addEventListener('change', function() {
            if (this.files[0]) {
                const reader = new FileReader();
                reader.onload = e => document.getElementById('logoPreview').src = e.target.result;
                reader.readAsDataURL(this.files[0]);
            }
        });

        (function() {
            const defLat = {$mapLat};
            const defLng = {$mapLng};
            const latEl = document.getElementById('latitud');
            const lngEl = document.getElementById('longitud');
            const previewEl = document.getElementById('mapPreview');
            const emptyEl = document.getElementById('mapPreviewEmpty');
            const editorPanel = document.getElementById('mapEditorPanel');
            const btnAbrir = document.getElementById('btnAbrirMapaUbicacion');
            const btnCerrar = document.getElementById('btnCerrarMapaUbicacion');
            let previewMap = null;
            let editorMap = null;
            let editorMarker = null;

            function hasCoords() {
                const la = parseFloat(latEl.value);
                const ln = parseFloat(lngEl.value);
                return latEl.value.trim() !== '' && lngEl.value.trim() !== '' && !isNaN(la) && !isNaN(ln);
            }

            function getPair() {
                if (hasCoords()) {
                    return [parseFloat(latEl.value), parseFloat(lngEl.value)];
                }
                return [defLat, defLng];
            }

            function destroyPreview() {
                if (previewMap) {
                    previewMap.remove();
                    previewMap = null;
                }
                previewEl.style.display = 'none';
            }

            function buildPreview() {
                destroyPreview();
                if (!hasCoords()) {
                    emptyEl.classList.remove('d-none');
                    return;
                }
                emptyEl.classList.add('d-none');
                previewEl.style.display = 'block';
                const ll = getPair();
                previewMap = L.map('mapPreview', { zoomControl: false }).setView(ll, 16);
                ParqueLeaflet.addSatelliteLayer(previewMap);
                ParqueLeaflet.addParquePolygon(previewMap);
                ParqueLeaflet.freezeMap(previewMap);
                L.marker(ll).addTo(previewMap);
                requestAnimationFrame(function() { previewMap.invalidateSize(); });
            }

            function bindMarkerDrag(m) {
                m.on('dragend', function(ev) {
                    const p = ev.target.getLatLng();
                    latEl.value = p.lat.toFixed(8);
                    lngEl.value = p.lng.toFixed(8);
                });
            }

            function destroyEditor() {
                if (editorMap) {
                    editorMap.remove();
                    editorMap = null;
                    editorMarker = null;
                }
            }

            function openEditor() {
                editorPanel.classList.remove('d-none');
                btnAbrir.classList.add('d-none');
                btnCerrar.classList.remove('d-none');
                destroyEditor();
                const ll = getPair();
                const z = hasCoords() ? 16 : 13;
                editorMap = L.map('mapPicker').setView(ll, z);
                ParqueLeaflet.addSatelliteLayer(editorMap);
                ParqueLeaflet.addParquePolygon(editorMap);

                function applyPos(latlng) {
                    latEl.value = latlng.lat.toFixed(8);
                    lngEl.value = latlng.lng.toFixed(8);
                }

                if (hasCoords()) {
                    editorMarker = L.marker(ll, { draggable: true }).addTo(editorMap);
                    bindMarkerDrag(editorMarker);
                }

                editorMap.on('click', function(e) {
                    if (editorMarker) {
                        editorMarker.setLatLng(e.latlng);
                    } else {
                        editorMarker = L.marker(e.latlng, { draggable: true }).addTo(editorMap);
                        bindMarkerDrag(editorMarker);
                    }
                    applyPos(e.latlng);
                });

                setTimeout(function() { editorMap.invalidateSize(); }, 250);
            }

            function closeEditor() {
                editorPanel.classList.add('d-none');
                btnCerrar.classList.add('d-none');
                btnAbrir.classList.remove('d-none');
                destroyEditor();
                buildPreview();
            }

            btnAbrir.addEventListener('click', openEditor);
            btnCerrar.addEventListener('click', closeEditor);
            buildPreview();
        })();
    </script>
HTML;
require_once BASEPATH . '/includes/empresa_layout_footer.php';
