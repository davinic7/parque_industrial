<?php
/**
 * Carrusel del inicio - Banners editables por el ministerio con Cloudinary
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Banners del inicio';
$db = getDB();

// Crear tabla si no existe (Chequeo de seguridad)
$table_exists = false;
try {
    $db->query("SELECT 1 FROM banners_home LIMIT 1");
    $table_exists = true;
} catch (Exception $e) {
    $table_exists = false;
}

/**
 * Función para subir imágenes a Cloudinary vía cURL
 */
function uploadToCloudinary($file_path) {
    $cloud_name = CLOUDINARY_CLOUD_NAME;
    $api_key    = CLOUDINARY_API_KEY;
    $api_secret = CLOUDINARY_API_SECRET;

    $timestamp = time();
    $signature = sha1("timestamp=$timestamp$api_secret");
    $url = "https://api.cloudinary.com/v1_1/$cloud_name/image/upload";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'file' => new CURLFile($file_path),
        'api_key' => $api_key,
        'timestamp' => $timestamp,
        'signature' => $signature
    ]);

    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);

    return $result['secure_url'] ?? null;
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
        $tipo = $_POST['tipo'] ?? 'imagen';
        $url_video = trim($_POST['url_video'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;

        $imagen_url = null;
        if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $imagen_url = uploadToCloudinary($_FILES['imagen']['tmp_name']);
        }

        if ($id > 0) {
            if ($imagen_url === null) {
                $stmt = $db->prepare("SELECT imagen FROM banners_home WHERE id = ?");
                $stmt->execute([$id]);
                $imagen_url = $stmt->fetchColumn();
            }
            $stmt = $db->prepare("UPDATE banners_home SET titulo=?, subtitulo=?, imagen=?, tipo=?, url_video=?, orden=?, activo=? WHERE id=?");
            $stmt->execute([$titulo, $subtitulo, $imagen_url, $tipo, $url_video ?: null, $orden, $activo, $id]);
            set_flash('success', 'Banner actualizado.');
        } else {
            if ($tipo === 'video' || $imagen_url) {
                $stmt = $db->prepare("INSERT INTO banners_home (titulo, subtitulo, imagen, tipo, url_video, orden, activo) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$titulo, $subtitulo, $imagen_url, $tipo, $url_video ?: null, $orden, $activo]);
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
    } catch (Exception $e) { $banners = []; }
}

$editar = null;
if ($table_exists && isset($_GET['editar'])) {
    $id_ed = (int)$_GET['editar'];
    $stmt = $db->prepare("SELECT * FROM banners_home WHERE id = ?");
    $stmt->execute([$id_ed]);
    $editar = $stmt->fetch();
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
            <a href="banners.php" class="active"><i class="bi bi-images"></i> Banners inicio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 class="h3 mb-4"><i class="bi bi-images me-2"></i>Banners del inicio</h1>
        <?php show_flash(); ?>

        <div class="card mb-4">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id" value="<?= $editar['id'] ?? 0 ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Título</label>
                            <input type="text" name="titulo" class="form-control" value="<?= e($editar['titulo'] ?? '') ?>">
                        </div>
                        <div class="col-md-4" id="wrapImagen">
                            <label class="form-label">Imagen</label>
                            <input type="file" name="imagen" class="form-control" accept="image/*">
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-primary"><?= $editar ? 'Guardar' : 'Agregar' ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Vista</th>
                            <th>Título</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banners as $b): ?>
                        <tr>
                            <td>
                                <img src="<?= e($b['imagen']) ?>" style="height: 50px; width: 80px; object-fit: cover; border-radius: 5px;">
                            </td>
                            <td><?= e($b['titulo']) ?></td>
                            <td>
                                <a href="banners.php?editar=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline">
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
    </main>
</body>
</html>