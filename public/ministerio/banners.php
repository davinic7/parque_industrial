<?php
/**
 * Carrusel del inicio - Banners editables por el ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Banners del inicio';
$db = getDB();

// Crear tabla si no existe (opcional, para desarrollo)
$table_exists = false;
try {
    $db->query("SELECT 1 FROM banners_home LIMIT 1");
    $table_exists = true;
} catch (Exception $e) {
    $table_exists = false;
}

// Procesar acciones
if ($table_exists && $_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($accion === 'eliminar' && $id > 0) {
        $stmt = $db->prepare("SELECT imagen FROM banners_home WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $db->prepare("DELETE FROM banners_home WHERE id = ?")->execute([$id]);
        if ($row && !empty($row['imagen']) && file_exists(UPLOADS_PATH . '/' . $row['imagen'])) {
            @unlink(UPLOADS_PATH . '/' . $row['imagen']);
        }
        set_flash('success', 'Banner eliminado.');
        redirect('banners.php');
    }

    if ($accion === 'guardar') {
        $titulo = trim($_POST['titulo'] ?? '');
        $subtitulo = trim($_POST['subtitulo'] ?? '');
        $tipo = $_POST['tipo'] ?? 'imagen';
        $url_video = trim($_POST['url_video'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($id > 0) {
            $imagen = null;
            if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $up = upload_file($_FILES['imagen'], 'banners', ALLOWED_IMAGE_TYPES);
                if ($up['success']) $imagen = 'banners/' . $up['filename'];
            }
            if ($imagen === null) {
                $stmt = $db->prepare("SELECT imagen FROM banners_home WHERE id = ?");
                $stmt->execute([$id]);
                $imagen = $stmt->fetchColumn();
            }
            $stmt = $db->prepare("UPDATE banners_home SET titulo=?, subtitulo=?, imagen=?, tipo=?, url_video=?, orden=?, activo=? WHERE id=?");
            $stmt->execute([$titulo, $subtitulo, $imagen, $tipo, $url_video ?: null, $orden, $activo, $id]);
            set_flash('success', 'Banner actualizado.');
        } else {
            $imagen = null;
            if ($tipo === 'imagen' && !empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $up = upload_file($_FILES['imagen'], 'banners', ALLOWED_IMAGE_TYPES);
                if ($up['success']) $imagen = 'banners/' . $up['filename'];
            }
            if ($tipo === 'video' || $imagen) {
                $stmt = $db->prepare("INSERT INTO banners_home (titulo, subtitulo, imagen, tipo, url_video, orden, activo) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$titulo, $subtitulo, $imagen, $tipo, $url_video ?: null, $orden, $activo]);
                set_flash('success', 'Banner agregado.');
            } else {
                set_flash('error', 'Suba una imagen o indique URL de video.');
            }
        }
        redirect('banners.php');
    }
}

$banners = [];
if ($table_exists) {
    try {
        $banners = $db->query("SELECT * FROM banners_home ORDER BY orden ASC, id ASC")->fetchAll();
    } catch (Exception $e) {
        $banners = [];
    }
}

$editar = null;
if ($table_exists && isset($_GET['editar'])) {
    $id_ed = (int)$_GET['editar'];
    if ($id_ed > 0) {
        $stmt = $db->prepare("SELECT * FROM banners_home WHERE id = ?");
        $stmt->execute([$id_ed]);
        $editar = $stmt->fetch();
    }
}
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
            <a href="banners.php" class="active"><i class="bi bi-images"></i> Banners inicio</a>
            <a href="notificaciones.php"><i class="bi bi-bell"></i> Notificaciones</a>
            <a href="exportar.php"><i class="bi bi-download"></i> Exportar</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 class="h3 mb-4"><i class="bi bi-images me-2"></i>Banners del inicio (carrusel)</h1>
        <?php show_flash(); ?>

        <?php if (!$table_exists): ?>
        <div class="alert alert-warning">
            <strong>Tabla no creada.</strong> Ejecutá en tu base de datos el archivo <code>database/001_banners_home.sql</code> y recargá esta página.
        </div>
        <?php else: ?>

        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><?= $editar ? 'Editar banner' : 'Nuevo banner' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" value="<?= $editar['id'] ?? 0 ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Título</label>
                            <input type="text" name="titulo" class="form-control" value="<?= e($editar['titulo'] ?? '') ?>" placeholder="Ej: Portal del Parque Industrial">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subtítulo</label>
                            <input type="text" name="subtitulo" class="form-control" value="<?= e($editar['subtitulo'] ?? '') ?>" placeholder="Texto secundario">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" id="bannerTipo">
                                <option value="imagen" <?= ($editar['tipo'] ?? '') === 'video' ? '' : 'selected' ?>>Imagen</option>
                                <option value="video" <?= ($editar['tipo'] ?? '') === 'video' ? 'selected' : '' ?>>Video (URL)</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="wrapImagen">
                            <label class="form-label">Imagen</label>
                            <input type="file" name="imagen" class="form-control" accept="image/*">
                            <?php if (!empty($editar['imagen'])): ?>
                                <small class="text-muted">Actual: <?= e($editar['imagen']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 d-none" id="wrapVideo">
                            <label class="form-label">URL del video</label>
                            <input type="url" name="url_video" class="form-control" value="<?= e($editar['url_video'] ?? '') ?>" placeholder="https://...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Orden</label>
                            <input type="number" name="orden" class="form-control" value="<?= (int)($editar['orden'] ?? 0) ?>" min="0">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= ($editar['activo'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="activo">Activo</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><?= $editar ? 'Guardar cambios' : 'Agregar banner' ?></button>
                            <?php if ($editar): ?>
                                <a href="banners.php" class="btn btn-outline-secondary">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white"><h5 class="mb-0">Banners actuales</h5></div>
            <div class="card-body p-0">
                <?php if (empty($banners)): ?>
                <p class="text-muted p-4 mb-0">No hay banners. Agregá al menos uno para que el carrusel reemplace la imagen fija del inicio.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Vista</th>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Orden</th>
                                <th>Activo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($banners as $b): ?>
                            <tr>
                                <td>
                                    <?php if ($b['tipo'] === 'video' && $b['url_video']): ?>
                                        <span class="badge bg-info">Video</span>
                                    <?php elseif ($b['imagen']): ?>
                                        <img src="<?= UPLOADS_URL ?>/<?= e($b['imagen']) ?>" alt="" style="height: 40px; width: 60px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($b['titulo'] ?: '—') ?></td>
                                <td><?= $b['tipo'] === 'video' ? 'Video' : 'Imagen' ?></td>
                                <td><?= (int)$b['orden'] ?></td>
                                <td><?= $b['activo'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                                <td>
                                    <a href="banners.php?editar=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este banner?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('bannerTipo')?.addEventListener('change', function() {
            var tipo = this.value;
            document.getElementById('wrapImagen').classList.toggle('d-none', tipo === 'video');
            document.getElementById('wrapVideo').classList.toggle('d-none', tipo !== 'video');
        });
        document.querySelector('[name="tipo"]') && document.getElementById('bannerTipo').dispatchEvent(new Event('change'));
    </script>
</body>
</html>
