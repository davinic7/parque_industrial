<?php
/**
 * Ver una publicación (noticia) - Vista pública
 */
require_once __DIR__ . '/../config/config.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: ' . PUBLIC_URL . '/noticias.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT p.*, e.id as empresa_id, e.nombre as empresa_nombre, e.logo
    FROM publicaciones p
    LEFT JOIN empresas e ON p.empresa_id = e.id
    WHERE p.slug = ? AND p.estado = 'aprobado'
");
$stmt->execute([$slug]);
$pub = $stmt->fetch();

if (!$pub) {
    set_flash('error', 'Publicación no encontrada');
    header('Location: ' . PUBLIC_URL . '/noticias.php');
    exit;
}

$page_title = $pub['titulo'];

$imagen_url = '';
if (!empty($pub['imagen'])) {
    $imagen_url = (strpos($pub['imagen'], 'http') === 0) ? $pub['imagen'] : (UPLOADS_URL . '/publicaciones/' . $pub['imagen']);
}

$like_count = 0;
$ya_like = false;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM publicacion_likes WHERE publicacion_id = ?");
    $stmt->execute([$pub['id']]);
    $like_count = (int) $stmt->fetchColumn();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $db->prepare("SELECT 1 FROM publicacion_likes WHERE publicacion_id = ? AND ip = ?");
    $stmt->execute([$pub['id'], $ip]);
    $ya_like = (bool) $stmt->fetch();
} catch (Exception $e) { /* tabla puede no existir */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['me_gusta'])) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ya_like) {
            $db->prepare("DELETE FROM publicacion_likes WHERE publicacion_id = ? AND ip = ?")->execute([$pub['id'], $ip]);
        } else {
            $db->prepare("INSERT IGNORE INTO publicacion_likes (publicacion_id, ip) VALUES (?, ?)")->execute([$pub['id'], $ip]);
        }
    } catch (Exception $e) { /* ignore */ }
    header('Location: ' . PUBLIC_URL . '/publicacion.php?slug=' . urlencode($slug));
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= PUBLIC_URL ?>/noticias.php">Noticias</a></li>
                        <li class="breadcrumb-item active"><?= e(truncate($pub['titulo'], 50)) ?></li>
                    </ol>
                </nav>
                <article class="card shadow-sm border-0">
                    <?php if ($imagen_url): ?>
                    <img src="<?= e($imagen_url) ?>" class="card-img-top" style="max-height: 400px; object-fit: cover;" alt="">
                    <?php endif; ?>
                    <div class="card-body p-4">
                        <span class="badge bg-primary mb-2"><?= ucfirst($pub['tipo']) ?></span>
                        <h1 class="h2 mb-3"><?= e($pub['titulo']) ?></h1>
                        <p class="text-muted mb-3">
                            <?php if ($pub['empresa_nombre']): ?>
                            <i class="bi bi-building me-1"></i><?= e($pub['empresa_nombre']) ?>
                            <?php if ($pub['empresa_id']): ?>
                            <a href="<?= PUBLIC_URL ?>/empresa.php?id=<?= (int)$pub['empresa_id'] ?>" class="ms-2 btn btn-sm btn-outline-primary">Ver empresa</a>
                            <?php endif; ?>
                            <?php endif; ?>
                            · <?= format_datetime($pub['created_at']) ?>
                        </p>
                        <div class="contenido-publicacion"><?= nl2br(e($pub['contenido'] ?: $pub['extracto'] ?: '')) ?></div>
                        <hr class="my-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="me_gusta" value="1">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-heart<?= $ya_like ? '-fill' : '' ?> me-1"></i>Me gusta <?= $like_count ? "($like_count)" : '' ?>
                                </button>
                            </form>
                            <?php if ($pub['empresa_id']): ?>
                            <a href="<?= PUBLIC_URL ?>/empresa.php?id=<?= (int)$pub['empresa_id'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-building me-1"></i>Ver empresa</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
