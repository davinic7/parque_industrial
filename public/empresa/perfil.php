<?php
/**
 * Perfil de Empresa - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Mi Perfil';
$mensaje = '';
$error = '';
$field_errors = [];
$empresa_id = $_SESSION['empresa_id'] ?? null;

if (!$empresa_id) {
    set_flash('error', 'No se encontró la empresa asociada a su cuenta');
    redirect('dashboard.php');
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido. Recargue la página.';
    } else {
        try {
            $stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
            $stmt->execute([$empresa_id]);
            $datos_anteriores = $stmt->fetch();

            if (!$datos_anteriores) {
                set_flash('error', 'Empresa no encontrada');
                redirect('dashboard.php');
            }

            if (array_key_exists('nombre', $_POST)) {
                $nombre        = trim($_POST['nombre'] ?? '');
                $razon_social  = trim($_POST['razon_social'] ?? '');
                $cuit          = trim($_POST['cuit'] ?? '');
                $cuit_digits   = cuit_digits_only($cuit);
                if ($cuit !== '' && $cuit_digits === '') $cuit = '';
                $rubro         = trim($_POST['rubro'] ?? '') ?: null;
                $descripcion   = trim($_POST['descripcion'] ?? '');
                $direccion     = trim($_POST['direccion'] ?? '');
                $latitud       = !empty($_POST['latitud'])  ? (float)$_POST['latitud']  : null;
                $longitud      = !empty($_POST['longitud']) ? (float)$_POST['longitud'] : null;
                $telefono      = trim($_POST['telefono'] ?? '');
                $email_contacto = trim($_POST['email_contacto'] ?? '');
                $contacto_nombre = trim($_POST['contacto_nombre'] ?? '');
                $sitio_web     = trim($_POST['sitio_web'] ?? '');
                $facebook      = trim($_POST['facebook'] ?? '');
                $instagram     = trim($_POST['instagram'] ?? '');

                if ($nombre === '') $field_errors['nombre'] = 'El nombre comercial es obligatorio';
                if ($rubro === null || $rubro === '') $field_errors['rubro'] = 'Seleccioná un rubro';
                if ($cuit_digits !== '' && !is_valid_cuit($cuit_digits)) {
                    $field_errors['cuit'] = 'CUIT no válido: deben ser los 11 dígitos de AFIP (el último es verificador).';
                }
                if ($email_contacto !== '' && !is_valid_email($email_contacto)) {
                    $field_errors['email_contacto'] = 'El email de contacto no es válido';
                }

                if (empty($field_errors)) {
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
                        $db->prepare("
                            UPDATE empresas SET
                                nombre = ?, razon_social = ?, cuit = ?, rubro = ?,
                                descripcion = ?, direccion = ?,
                                latitud = ?, longitud = ?,
                                telefono = ?, email_contacto = ?, contacto_nombre = ?,
                                sitio_web = ?, facebook = ?, instagram = ?, logo = ?
                            WHERE id = ?
                        ")->execute([
                            $nombre, $razon_social, $cuit_guardar, $rubro,
                            $descripcion, $direccion,
                            $latitud, $longitud,
                            $telefono, $email_contacto, $contacto_nombre,
                            $sitio_web, $facebook, $instagram, $logo_filename,
                            $empresa_id
                        ]);

                        $_SESSION['empresa_nombre'] = $nombre;
                        log_activity('perfil_actualizado', 'empresas', $empresa_id, $datos_anteriores);
                        $mensaje = 'Perfil actualizado correctamente';
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error al actualizar perfil empresa_id=$empresa_id: " . $e->getMessage());
            $error = 'Error al guardar los cambios. Intente nuevamente.';
        }
    }
}

$stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->execute([$empresa_id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    set_flash('error', 'Empresa no encontrada');
    redirect('dashboard.php');
}

$csrf_msg = 'Token de seguridad inválido. Recargue la página.';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($field_errors) || ($error !== '' && $error !== $csrf_msg))) {
    foreach (['nombre', 'razon_social', 'cuit', 'rubro', 'descripcion', 'direccion',
              'telefono', 'email_contacto', 'contacto_nombre', 'sitio_web', 'facebook', 'instagram'] as $c) {
        if (array_key_exists($c, $_POST)) {
            $empresa[$c] = is_string($_POST[$c]) ? trim($_POST[$c]) : $_POST[$c];
        }
    }
    if (array_key_exists('latitud', $_POST))  $empresa['latitud']  = $_POST['latitud']  !== '' ? $_POST['latitud']  : null;
    if (array_key_exists('longitud', $_POST)) $empresa['longitud'] = $_POST['longitud'] !== '' ? $_POST['longitud'] : null;
}

$stmt = $db->query("SELECT nombre FROM rubros WHERE activo = 1 ORDER BY orden, nombre");
$rubros = $stmt->fetchAll(PDO::FETCH_COLUMN);

$galeria_imagenes = [];
try {
    $db->query("SELECT 1 FROM empresa_imagenes LIMIT 1");
    $stmt = $db->prepare("SELECT id, imagen FROM empresa_imagenes WHERE empresa_id = ? ORDER BY orden ASC, id ASC");
    $stmt->execute([$empresa_id]);
    $galeria_imagenes = $stmt->fetchAll();
} catch (Exception $e) {
    $galeria_imagenes = [];
}

$empresa_nav = '';
$extra_head = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">';
require_once BASEPATH . '/includes/empresa_layout_header.php';
?>

<style>
.gallery-thumb {
    width: 72px; height: 72px; object-fit: cover;
    border-radius: 6px; border: 2px solid #dee2e6;
    transition: border-color .15s;
}
.gallery-thumb:hover { border-color: #0d6efd; }
.gallery-item { position: relative; display: inline-block; }
.gallery-item .btn-del-img {
    position: absolute; top: -6px; right: -6px;
    width: 20px; height: 20px; padding: 0;
    line-height: 1; font-size: .65rem;
    border-radius: 50%;
}
.map-preview-box { height: 220px; border-radius: 8px; overflow: hidden; }
.upload-drop-zone {
    border: 2px dashed #dee2e6; border-radius: 8px;
    padding: 2rem 1rem; text-align: center; cursor: pointer;
    transition: border-color .2s, background .2s;
}
.upload-drop-zone:hover, .upload-drop-zone.dragover {
    border-color: #0d6efd; background: #f0f4ff;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0">Editar Perfil de Empresa</h1>
    <a href="<?= PUBLIC_URL ?>/empresa.php?id=<?= $empresa['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm">
        <i class="fa-solid fa-eye me-1"></i>Ver perfil público
    </a>
</div>

<?php if ($mensaje): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= e($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= e($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="formPerfil">
    <?= csrf_field() ?>
    <div class="row g-4">
        <!-- Columna principal -->
        <div class="col-lg-8">

            <!-- Datos básicos -->
            <div class="card mb-4">
                <div class="card-header bg-white"><h5 class="mb-0">Datos de la Empresa</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre comercial *</label>
                            <input type="text" name="nombre" class="form-control<?= isset($field_errors['nombre']) ? ' is-invalid' : '' ?>"
                                   value="<?= e($empresa['nombre']) ?>" required>
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
                            <input type="text" name="cuit" id="inputCuit"
                                   class="form-control<?= isset($field_errors['cuit']) ? ' is-invalid' : '' ?>"
                                   value="<?= e($empresa['cuit'] ?? '') ?>"
                                   placeholder="Ej. 20-12345678-6" inputmode="numeric" autocomplete="off" maxlength="13">
                            <?php if (isset($field_errors['cuit'])): ?>
                            <div class="invalid-feedback d-block"><?= e($field_errors['cuit']) ?></div>
                            <?php else: ?>
                            <small class="text-muted">11 dígitos AFIP. Guiones opcionales. Vacío si no aplica.</small>
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
                            <textarea name="descripcion" class="form-control" rows="4"
                                      placeholder="Describe tu empresa..."><?= e($empresa['descripcion'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ubicación -->
            <div class="card mb-4">
                <div class="card-header bg-white"><h5 class="mb-0">Ubicación en el Parque</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Dirección exacta</label>
                        <input type="text" name="direccion" class="form-control"
                               value="<?= e($empresa['direccion'] ?? '') ?>"
                               placeholder="Ej. Calle Industrial 123, Parque Industrial">
                    </div>

                    <!-- Mapa siempre visible -->
                    <label class="form-label">Ubicación en el mapa</label>
                    <p class="text-muted small mb-2">Hacé clic en el mapa para marcar tu posición. Podés arrastrar el marcador para ajustar.</p>
                    <div id="mapPicker" class="map-preview-box mb-2"></div>

                    <!-- Coords display (aparece al seleccionar) -->
                    <div id="coordsDisplay" class="<?= ($empresa['latitud'] && $empresa['longitud']) ? '' : 'd-none' ?> mb-2">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="badge bg-light text-dark border px-3 py-2 fs-6">
                                <i class="fa-solid fa-location-dot text-danger me-1"></i>
                                <span id="coordLat"><?= $empresa['latitud'] ? number_format((float)$empresa['latitud'], 6) : '' ?></span>,
                                <span id="coordLng"><?= $empresa['longitud'] ? number_format((float)$empresa['longitud'], 6) : '' ?></span>
                            </span>
                            <!-- Botones compartir -->
                            <div class="d-flex gap-2 flex-wrap">
                                <a id="btnGoogleMaps" href="#" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-map me-1"></i>Google Maps
                                </a>
                                <a id="btnWhatsApp" href="#" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-success">
                                    <i class="fa-brands fa-whatsapp me-1"></i>WhatsApp
                                </a>
                                <button type="button" id="btnCopiarCoords" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-regular fa-copy me-1"></i>Copiar enlace
                                </button>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="latitud"  id="latitud"  value="<?= e($empresa['latitud'] ?? '') ?>">
                    <input type="hidden" name="longitud" id="longitud" value="<?= e($empresa['longitud'] ?? '') ?>">
                </div>
            </div>

            <!-- Contacto -->
            <div class="card mb-4">
                <div class="card-header bg-white"><h5 class="mb-0">Información de Contacto</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="telefono" class="form-control" value="<?= e($empresa['telefono'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email de contacto</label>
                            <input type="email" name="email_contacto"
                                   class="form-control<?= isset($field_errors['email_contacto']) ? ' is-invalid' : '' ?>"
                                   value="<?= e($empresa['email_contacto'] ?? '') ?>">
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
                            <input type="url" name="sitio_web" class="form-control"
                                   value="<?= e($empresa['sitio_web'] ?? '') ?>" placeholder="https://">
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">

            <!-- Logo -->
            <div class="card mb-3">
                <div class="card-header bg-white"><h5 class="mb-0">Logo</h5></div>
                <div class="card-body text-center">
                    <?php
                    $logo_src = !empty($empresa['logo'])
                        ? uploads_resolve_url($empresa['logo'], 'logos')
                        : 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><rect fill="#e9ecef" width="120" height="120"/><text x="60" y="68" font-size="48" fill="#6c757d" text-anchor="middle">🏢</text></svg>');
                    ?>
                    <img id="logoPreview" src="<?= $logo_src ?>" alt="Logo"
                         class="img-fluid rounded d-block mx-auto mb-3" style="max-height: 130px; background: #f8f9fa;">
                    <input type="file" name="logo" class="form-control<?= isset($field_errors['logo']) ? ' is-invalid' : '' ?>" accept="image/*">
                    <?php if (isset($field_errors['logo'])): ?>
                    <div class="invalid-feedback d-block text-start"><?= e($field_errors['logo']) ?></div>
                    <?php else: ?>
                    <?php $logo_max_mb = max(1, (int) ceil(MAX_FILE_SIZE / 1048576)); ?>
                    <small class="text-muted">JPG, PNG, GIF, WebP. Máx. <?= $logo_max_mb ?> MB.</small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Galería -->
            <div class="card mb-3">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Galería</h5>
                    <span class="badge bg-secondary" id="galleryCount"><?= count($galeria_imagenes) ?></span>
                </div>
                <div class="card-body">
                    <!-- Miniaturas inline -->
                    <div class="d-flex flex-wrap gap-2 mb-3" id="galleryThumbsInline">
                        <?php foreach ($galeria_imagenes as $img): ?>
                        <div class="gallery-item" id="gitem-<?= $img['id'] ?>">
                            <img src="<?= e(uploads_resolve_url($img['imagen'], 'galeria_empresa')) ?>"
                                 class="gallery-thumb" alt="Foto">
                            <button type="button" class="btn btn-danger btn-del-img"
                                    data-id="<?= $img['id'] ?>" title="Eliminar">×</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#modalGaleria">
                        <i class="fa-solid fa-images me-2"></i>Agregar fotos
                    </button>
                </div>
            </div>

            <!-- Redes sociales -->
            <div class="card mb-3">
                <div class="card-header bg-white"><h5 class="mb-0">Redes Sociales</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label"><i class="fa-brands fa-facebook text-primary me-1"></i>Facebook</label>
                        <input type="url" name="facebook" class="form-control" value="<?= e($empresa['facebook'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label"><i class="fa-brands fa-instagram text-danger me-1"></i>Instagram</label>
                        <input type="url" name="instagram" class="form-control" value="<?= e($empresa['instagram'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Guardar -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Guardar cambios
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>

        </div>
    </div>
</form>

<!-- Modal Galería (AJAX) -->
<div class="modal fade" id="modalGaleria" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-images me-2"></i>Galería de imágenes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Estas imágenes se muestran en el carrusel de tu perfil público. Seleccioná un archivo para subirlo automáticamente.</p>

                <!-- Drop zone / file input -->
                <div class="upload-drop-zone mb-4" id="dropZone">
                    <i class="fa-solid fa-cloud-arrow-up fa-2x text-muted mb-2 d-block"></i>
                    <p class="mb-1 fw-semibold">Arrastrá imágenes aquí o hacé clic para seleccionar</p>
                    <small class="text-muted">JPG, PNG, GIF, WebP — se suben al instante</small>
                    <input type="file" id="galeriaFileInput" accept="image/*" multiple class="d-none">
                </div>

                <!-- Progress -->
                <div id="uploadProgress" class="d-none mb-3">
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
                    </div>
                    <small class="text-muted mt-1 d-block">Subiendo...</small>
                </div>

                <!-- Grid de imágenes en modal -->
                <div class="d-flex flex-wrap gap-3" id="galleryGrid">
                    <?php foreach ($galeria_imagenes as $img): ?>
                    <div class="gallery-item" id="mgitem-<?= $img['id'] ?>">
                        <img src="<?= e(uploads_resolve_url($img['imagen'], 'galeria_empresa')) ?>"
                             class="gallery-thumb" style="width:90px;height:90px;" alt="Foto">
                        <button type="button" class="btn btn-danger btn-del-img-modal"
                                data-id="<?= $img['id'] ?>" title="Eliminar">×</button>
                    </div>
                    <?php endforeach; ?>
                    <div id="galleryEmpty" class="<?= empty($galeria_imagenes) ? '' : 'd-none' ?> text-muted small py-2">
                        Sin fotos aún. Subí la primera arriba.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Listo</button>
            </div>
        </div>
    </div>
</div>

<?php
$pu      = htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8');
$js_cfg  = json_encode([
    'csrfName'  => CSRF_TOKEN_NAME,
    'csrfVal'   => $_SESSION[CSRF_TOKEN_NAME] ?? '',
    'defLat'    => (float) MAP_DEFAULT_LAT,
    'defLng'    => (float) MAP_DEFAULT_LNG,
    'hasCoords' => !empty($empresa['latitud']) && !empty($empresa['longitud']),
    'initLat'   => (float)($empresa['latitud'] ?? 0),
    'initLng'   => (float)($empresa['longitud'] ?? 0),
]);
$extra_scripts = '<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>'
    . '<script src="' . $pu . '/js/parque-leaflet.js"></script>'
    . '<script>const __CFG=' . $js_cfg . ';</script>'
    . <<<'JSEOF'
<script>
(function () {
    /* ---- CUIT formatter ---- */
    const cuitEl = document.getElementById('inputCuit');
    if (cuitEl) {
        function fmtCuit(raw) {
            let d = String(raw).replace(/\D/g, '').slice(0, 11);
            if (d.length <= 2)  return d;
            if (d.length <= 10) return d.slice(0, 2) + '-' + d.slice(2);
            return d.slice(0, 2) + '-' + d.slice(2, 10) + '-' + d.slice(10);
        }
        cuitEl.addEventListener('input', function () {
            const cur = this.selectionStart, before = this.value.length;
            this.value = fmtCuit(this.value);
            const after = this.value.length;
            try { this.setSelectionRange(cur + (after - before), cur + (after - before)); } catch(e){}
        });
    }

    /* ---- Logo preview ---- */
    document.querySelector('input[name="logo"]').addEventListener('change', function () {
        if (this.files[0]) {
            const r = new FileReader();
            r.onload = ev => document.getElementById('logoPreview').src = ev.target.result;
            r.readAsDataURL(this.files[0]);
        }
    });

    /* ---- Map ---- */
    const C        = __CFG;
    const initLat  = C.hasCoords ? C.initLat : C.defLat;
    const initLng  = C.hasCoords ? C.initLng : C.defLng;
    const initZoom = C.hasCoords ? 16 : 14;

    const map = L.map('mapPicker').setView([initLat, initLng], initZoom);
    ParqueLeaflet.addSatelliteLayer(map);
    ParqueLeaflet.addParquePolygon(map);

    let marker = null;

    function applyCoords(lat, lng) {
        document.getElementById('latitud').value  = lat.toFixed(8);
        document.getElementById('longitud').value = lng.toFixed(8);
        document.getElementById('coordLat').textContent = lat.toFixed(6);
        document.getElementById('coordLng').textContent = lng.toFixed(6);
        updateShareLinks(lat, lng);
        document.getElementById('coordsDisplay').classList.remove('d-none');
    }

    function updateShareLinks(lat, lng) {
        const mapsUrl = 'https://www.google.com/maps?q=' + lat.toFixed(6) + ',' + lng.toFixed(6);
        const waText  = encodeURIComponent('\u{1F4CD} Ubicaci\u{F3}n Parque Industrial: ' + mapsUrl);
        document.getElementById('btnGoogleMaps').href = mapsUrl;
        document.getElementById('btnWhatsApp').href   = 'https://wa.me/?text=' + waText;
        document.getElementById('btnCopiarCoords').onclick = function() {
            navigator.clipboard.writeText(mapsUrl).then(() => {
                this.innerHTML = '<i class="fa-solid fa-check me-1"></i>Copiado';
                setTimeout(() => { this.innerHTML = '<i class="fa-regular fa-copy me-1"></i>Copiar enlace'; }, 2000);
            });
        };
    }

    if (C.hasCoords) {
        marker = L.marker([initLat, initLng], { draggable: true }).addTo(map);
        marker.on('dragend', function(ev) {
            const p = ev.target.getLatLng();
            applyCoords(p.lat, p.lng);
        });
        updateShareLinks(initLat, initLng);
    }

    map.on('click', function (e) {
        if (marker) {
            marker.setLatLng(e.latlng);
        } else {
            marker = L.marker(e.latlng, { draggable: true }).addTo(map);
            marker.on('dragend', function(ev) {
                const p = ev.target.getLatLng();
                applyCoords(p.lat, p.lng);
            });
        }
        applyCoords(e.latlng.lat, e.latlng.lng);
    });

    /* ---- Gallery AJAX ---- */
    const CSRF_NAME = C.csrfName;
    const CSRF_VAL  = C.csrfVal;
    const API       = 'galeria_api.php';

    function galleryUpdateCount() {
        const n = document.querySelectorAll('[id^="gitem-"]').length;
        document.getElementById('galleryCount').textContent = n;
        document.getElementById('galleryEmpty').classList.toggle('d-none', n > 0);
    }

    function makeThumbEl(id, url, modal) {
        const wrap = document.createElement('div');
        wrap.className = 'gallery-item';
        wrap.id = (modal ? 'mgitem-' : 'gitem-') + id;
        const img = document.createElement('img');
        img.src = url; img.alt = 'Foto'; img.className = 'gallery-thumb';
        if (modal) { img.style.width = '90px'; img.style.height = '90px'; }
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-danger ' + (modal ? 'btn-del-img-modal' : 'btn-del-img');
        btn.dataset.id = id; btn.title = 'Eliminar'; btn.textContent = '\u00D7';
        wrap.appendChild(img); wrap.appendChild(btn);
        return wrap;
    }

    function addThumbToAll(id, url) {
        const inlineEl = makeThumbEl(id, url, false);
        const modalEl  = makeThumbEl(id, url, true);
        document.getElementById('galleryThumbsInline').appendChild(inlineEl);
        document.getElementById('galleryGrid').appendChild(modalEl);
        galleryUpdateCount();
        inlineEl.querySelector('.btn-del-img').addEventListener('click', function() {
            if (confirm('\u00BFEliminar esta imagen?')) deleteImage(this.dataset.id);
        });
        modalEl.querySelector('.btn-del-img-modal').addEventListener('click', function() {
            if (confirm('\u00BFEliminar esta imagen?')) deleteImage(this.dataset.id);
        });
    }

    function removeThumbFromAll(id) {
        ['gitem-' + id, 'mgitem-' + id].forEach(function(sid) {
            const el = document.getElementById(sid);
            if (el) el.remove();
        });
        galleryUpdateCount();
    }

    async function uploadFile(file) {
        const prog = document.getElementById('uploadProgress');
        prog.classList.remove('d-none');
        const fd = new FormData();
        fd.append(CSRF_NAME, CSRF_VAL);
        fd.append('accion', 'subir');
        fd.append('imagen', file);
        try {
            const res  = await fetch(API, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) addThumbToAll(data.id, data.url);
            else alert('Error al subir: ' + data.error);
        } catch(e) {
            alert('Error de red al subir imagen');
        } finally {
            prog.classList.add('d-none');
        }
    }

    async function deleteImage(id) {
        const fd = new FormData();
        fd.append(CSRF_NAME, CSRF_VAL);
        fd.append('accion', 'eliminar');
        fd.append('imagen_id', id);
        try {
            const res  = await fetch(API, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) removeThumbFromAll(id);
            else alert('Error al eliminar: ' + data.error);
        } catch(e) {
            alert('Error de red');
        }
    }

    document.querySelectorAll('.btn-del-img').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (confirm('\u00BFEliminar esta imagen?')) deleteImage(this.dataset.id);
        });
    });
    document.querySelectorAll('.btn-del-img-modal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (confirm('\u00BFEliminar esta imagen?')) deleteImage(this.dataset.id);
        });
    });

    const fileInput = document.getElementById('galeriaFileInput');
    const dropZone  = document.getElementById('dropZone');

    dropZone.addEventListener('click', function() { fileInput.click(); });
    dropZone.addEventListener('dragover', function(e) { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', function() { dropZone.classList.remove('dragover'); });
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault(); dropZone.classList.remove('dragover');
        Array.from(e.dataTransfer.files).forEach(uploadFile);
    });
    fileInput.addEventListener('change', function() {
        Array.from(this.files).forEach(uploadFile);
        this.value = '';
    });
})();
</script>
JSEOF;
require_once BASEPATH . '/includes/empresa_layout_footer.php';
