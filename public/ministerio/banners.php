<?php
/**
 * Carrusel del inicio - Banners editables (solo imágenes, Cloudinary)
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Banners del inicio';
$db = getDB();

// Chequeo de tabla
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
        $db->prepare("DELETE FROM banners_home WHERE id = ?")->execute([$id]);
        set_flash('success', 'Banner eliminado.');
        redirect('banners.php');
    }

    if ($accion === 'guardar') {
        $titulo = trim($_POST['titulo'] ?? '');
        $subtitulo = trim($_POST['subtitulo'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;

        $imagen_url = null;
        if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $imagen_url = cloudinary_upload_image($_FILES['imagen']['tmp_name']);
        }

        if ($id > 0) {
            if ($imagen_url === null) {
                $stmt = $db->prepare("SELECT imagen FROM banners_home WHERE id = ?");
                $stmt->execute([$id]);
                $imagen_url = $stmt->fetchColumn();
            }
            $stmt = $db->prepare("UPDATE banners_home SET titulo=?, subtitulo=?, imagen=?, tipo='imagen', url_video=NULL, orden=?, activo=? WHERE id=?");
            $stmt->execute([$titulo, $subtitulo, $imagen_url, $orden, $activo, $id]);
            set_flash('success', 'Banner actualizado.');
        } else {
            if ($imagen_url) {
                $stmt = $db->prepare("INSERT INTO banners_home (titulo, subtitulo, imagen, tipo, url_video, orden, activo) VALUES (?,?,?,'imagen',NULL,?,?)");
                $stmt->execute([$titulo, $subtitulo, $imagen_url, $orden, $activo]);
                set_flash('success', 'Banner agregado.');
            } else {
                set_flash('error', 'Suba una imagen.');
            }
        }
        redirect('banners.php');
    }
}

$banners = [];
if ($table_exists) {
    try {
        $banners = $db->query("SELECT * FROM banners_home ORDER BY orden ASC, id ASC")->fetchAll();
    } catch (Exception $e) { $banners = []; }
}

$editar = null;
if ($table_exists && isset($_GET['editar'])) {
    $id_ed = (int)$_GET['editar'];
    $stmt = $db->prepare("SELECT * FROM banners_home WHERE id = ?");
    $stmt->execute([$id_ed]);
    $editar = $stmt->fetch();
}

$ministerio_nav = 'banners';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <h2 class="h4 mb-4 fw-semibold"><i class="bi bi-images me-2"></i>Banners del inicio</h2>
        <?php show_flash(); ?>

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
                            <input type="text" name="titulo" class="form-control" value="<?= e($editar['titulo'] ?? '') ?>" placeholder="Ej: Bienvenidos al Parque Industrial">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subtítulo</label>
                            <input type="text" name="subtitulo" class="form-control" value="<?= e($editar['subtitulo'] ?? '') ?>" placeholder="Texto secundario">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Imagen</label>
                            <input type="file" name="imagen" class="form-control" accept="image/*">
                            <?php if (!empty($editar['imagen'])): ?>
                                <small class="text-muted">Actual: se mantiene si no elige otra.</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Orden</label>
                            <input type="number" name="orden" class="form-control" value="<?= (int)($editar['orden'] ?? 0) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= ($editar['activo'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="activo">Activo</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><?= $editar ? 'Guardar Cambios' : 'Agregar Banner' ?></button>
                            <?php if ($editar): ?>
                                <a href="banners.php" class="btn btn-outline-secondary">Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Vista</th>
                            <th>Título</th>
                            <th>Orden</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banners as $b): ?>
                        <tr>
                            <td>
                                <?php if (!empty($b['imagen'])): ?>
                                    <img src="<?= e($b['imagen']) ?>" alt="" style="height: 50px; width: 80px; object-fit: cover; border-radius: 5px;">
                                <?php else: ?>
                                    <div class="bg-secondary text-white d-flex align-items-center justify-content-center" style="height: 50px; width: 80px; border-radius: 5px;"><i class="bi bi-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><strong><?= e($b['titulo'] ?: 'Sin título') ?></strong></div>
                            </td>
                            <td><?= $b['orden'] ?></td>
                            <td><?= $b['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' ?></td>
                            <td>
                                <a href="banners.php?editar=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar banner?');">
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
        </div>

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>