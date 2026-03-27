<?php
/**
 * Perfil de Empresa - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Mi Perfil';
$mensaje = '';
$error = '';
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
                    $upload = upload_file($_FILES['galeria_imagen'], 'galeria_empresa', ALLOWED_IMAGE_TYPES);
                    if ($upload['success']) {
                        $stmt = $db->prepare("SELECT COALESCE(MAX(orden), 0) + 1 FROM empresa_imagenes WHERE empresa_id = ?");
                        $stmt->execute([$empresa_id]);
                        $orden = (int)$stmt->fetchColumn();
                        $db->prepare("INSERT INTO empresa_imagenes (empresa_id, imagen, orden) VALUES (?, ?, ?)")->execute([$empresa_id, $upload['filename'], $orden]);
                        set_flash('success', 'Imagen agregada a la galería.');
                        redirect('perfil.php');
                    } else {
                        $error = $upload['error'];
                    }
                } catch (Exception $e) {
                    $error = 'No se pudo subir la imagen.';
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

            // Sanitizar inputs
            $nombre = trim($_POST['nombre'] ?? '');
            $razon_social = trim($_POST['razon_social'] ?? '');
            $cuit = trim($_POST['cuit'] ?? '');
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
            if (empty($nombre)) {
                $error = 'El nombre comercial es obligatorio';
            } elseif (!empty($cuit) && !is_valid_cuit($cuit)) {
                $error = 'El CUIT ingresado no es válido';
            } elseif (!empty($email_contacto) && !is_valid_email($email_contacto)) {
                $error = 'El email de contacto no es válido';
            } else {
                // Procesar logo si se subió
                $logo_filename = $datos_anteriores['logo'];
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $upload = upload_file($_FILES['logo'], 'logos', ALLOWED_IMAGE_TYPES);
                    if ($upload['success']) {
                        $logo_filename = $upload['filename'];
                    } else {
                        $error = $upload['error'];
                    }
                }

                if (empty($error)) {
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
                        $nombre, $razon_social, $cuit, $rubro,
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
        } catch (Exception $e) {
            error_log("Error al actualizar perfil empresa_id=$empresa_id: " . $e->getMessage());
            $error = 'Error al guardar los cambios. Intente nuevamente.';
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header"><span class="text-white fw-bold">Parque Industrial</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="perfil.php" class="active"><i class="bi bi-building"></i> Mi Perfil</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <a href="formularios.php"><i class="bi bi-file-earmark-text"></i> Formularios</a>
            <a href="mensajes.php"><i class="bi bi-envelope"></i> Mensajes</a>
            <a href="notificaciones.php"><i class="bi bi-bell"></i> Notificaciones</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio público</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
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
                                    <input type="text" name="nombre" class="form-control" value="<?= e($empresa['nombre']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Razón social</label>
                                    <input type="text" name="razon_social" class="form-control" value="<?= e($empresa['razon_social'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">CUIT</label>
                                    <input type="text" name="cuit" class="form-control" value="<?= e($empresa['cuit'] ?? '') ?>" placeholder="XX-XXXXXXXX-X">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Rubro *</label>
                                    <select name="rubro" class="form-select" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($rubros as $r): ?>
                                        <option value="<?= e($r) ?>" <?= ($empresa['rubro'] ?? '') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                                        <?php endforeach; ?>
                                    </select>
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
                                    <label class="form-label">Ubicación en mapa <small class="text-muted">(clic para marcar)</small></label>
                                    <div id="mapPicker" style="height: 250px; border-radius: 8px;"></div>
                                    <input type="hidden" name="latitud" id="latitud" value="<?= e($empresa['latitud'] ?? '') ?>">
                                    <input type="hidden" name="longitud" id="longitud" value="<?= e($empresa['longitud'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Galería de imágenes (carrusel en perfil público) -->
                    <?php if ($tabla_galeria_existe): ?>
                    <div class="card mt-4">
                        <div class="card-header bg-white"><h5 class="mb-0">Galería de imágenes</h5></div>
                        <div class="card-body">
                            <p class="text-muted small">Estas imágenes se muestran en el carrusel de tu perfil público.</p>
                            <?php if (!empty($galeria_imagenes)): ?>
                            <div class="row g-2 mb-3">
                                <?php foreach ($galeria_imagenes as $img): ?>
                                <div class="col-auto">
                                    <div class="position-relative d-inline-block">
                                        <img src="<?= UPLOADS_URL ?>/galeria_empresa/<?= e($img['imagen']) ?>" alt="" class="rounded" style="width: 80px; height: 80px; object-fit: cover;">
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
                            <form method="POST" enctype="multipart/form-data" class="d-flex align-items-end gap-2">
                                <?= csrf_field() ?>
                                <div class="flex-grow-1">
                                    <input type="file" name="galeria_imagen" class="form-control form-control-sm" accept="image/*" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Agregar imagen</button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

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
                                    <input type="email" name="email_contacto" class="form-control" value="<?= e($empresa['email_contacto'] ?? '') ?>">
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
                                    $logo_src = UPLOADS_URL . '/logos/' . $empresa['logo'];
                                } else {
                                    $logo_src = 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><rect fill="#e9ecef" width="120" height="120"/><text x="60" y="68" font-size="48" fill="#6c757d" text-anchor="middle">🏢</text></svg>');
                                }
                                ?>
                                <img id="logoPreview" src="<?= $logo_src ?>" alt="Logo" class="img-fluid rounded d-block mx-auto" style="max-height: 150px; background: #f8f9fa;">
                            </div>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                            <small class="text-muted">JPG, PNG. Máx 2MB.</small>
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
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
    <script>
        // Preview de imagen
        document.querySelector('input[name="logo"]').addEventListener('change', function() {
            if (this.files[0]) {
                const reader = new FileReader();
                reader.onload = e => document.getElementById('logoPreview').src = e.target.result;
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Mapa para seleccionar ubicación
        const lat = parseFloat(document.getElementById('latitud').value) || <?= MAP_DEFAULT_LAT ?>;
        const lng = parseFloat(document.getElementById('longitud').value) || <?= MAP_DEFAULT_LNG ?>;
        const map = L.map('mapPicker').setView([lat, lng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        let marker;
        if (document.getElementById('latitud').value && document.getElementById('longitud').value) {
            marker = L.marker([lat, lng]).addTo(map);
        }

        map.on('click', function(e) {
            if (marker) map.removeLayer(marker);
            marker = L.marker(e.latlng).addTo(map);
            document.getElementById('latitud').value = e.latlng.lat.toFixed(8);
            document.getElementById('longitud').value = e.latlng.lng.toFixed(8);
        });
    </script>
</body>
</html>
