<?php
/**
 * Noticias y Publicaciones - Vista Pública
 */
require_once __DIR__ . '/../config/config.php';

$page_title = 'Noticias';
$db = getDB();

$filtro_tipo = trim($_GET['tipo'] ?? '');
$buscar = trim($_GET['buscar'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

$where = ["p.estado = 'aprobado'"];
$params = [];

if ($filtro_tipo !== '') {
    $where[] = "p.tipo = ?";
    $params[] = $filtro_tipo;
}
if ($buscar !== '') {
    $where[] = "(p.titulo LIKE ? OR p.contenido LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$stmt = $db->prepare("SELECT COUNT(*) FROM publicaciones p $where_sql");
$stmt->execute($params);
$total = $stmt->fetchColumn();

$pagination = paginate($total, ITEMS_PER_PAGE, $pagina, 'noticias.php?' . http_build_query(array_merge($_GET, ['pagina' => '{page}'])));
$offset = ($pagination['current_page'] - 1) * ITEMS_PER_PAGE;

$stmt = $db->prepare("
    SELECT p.*, e.id as empresa_id, e.nombre as empresa_nombre, e.logo
    FROM publicaciones p
    LEFT JOIN empresas e ON p.empresa_id = e.id
    $where_sql
    ORDER BY p.created_at DESC
    LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset
");
$stmt->execute($params);
$publicaciones = $stmt->fetchAll();

// Toggle me gusta (GET para no romper navegación)
if (isset($_GET['like'])) {
    $like_id = (int)$_GET['like'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    try {
        $stmt = $db->prepare("SELECT 1 FROM publicacion_likes WHERE publicacion_id = ? AND ip = ?");
        $stmt->execute([$like_id, $ip]);
        if ($stmt->fetch()) {
            $db->prepare("DELETE FROM publicacion_likes WHERE publicacion_id = ? AND ip = ?")->execute([$like_id, $ip]);
        } else {
            $db->prepare("INSERT IGNORE INTO publicacion_likes (publicacion_id, ip) VALUES (?, ?)")->execute([$like_id, $ip]);
        }
    } catch (Exception $e) { /* tabla puede no existir */ }
    header('Location: ' . PUBLIC_URL . '/noticias.php?' . http_build_query(array_diff_key($_GET, ['like' => 1])));
    exit;
}

$likes_count = [];
$likes_ya = [];
try {
    foreach ($publicaciones as $p) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM publicacion_likes WHERE publicacion_id = ?");
        $stmt->execute([$p['id']]);
        $likes_count[$p['id']] = (int) $stmt->fetchColumn();
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ids = array_column($publicaciones, 'id');
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT publicacion_id FROM publicacion_likes WHERE publicacion_id IN ($placeholders) AND ip = ?");
        $stmt->execute(array_merge($ids, [$ip]));
        $likes_ya = array_column($stmt->fetchAll(), 'publicacion_id');
    }
} catch (Exception $e) { }

include __DIR__ . '/../includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <h1 class="text-center mb-4">Noticias y Publicaciones</h1>

        <div class="row justify-content-center mb-4">
            <div class="col-lg-8">
                <form class="row g-2" method="GET">
                    <div class="col-md-5">
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar publicaciones..." value="<?= e($buscar) ?>">
                    </div>
                    <div class="col-md-4">
                        <select name="tipo" class="form-select">
                            <option value="">Todas las categorías</option>
                            <option value="noticia" <?= $filtro_tipo === 'noticia' ? 'selected' : '' ?>>Noticias</option>
                            <option value="evento" <?= $filtro_tipo === 'evento' ? 'selected' : '' ?>>Eventos</option>
                            <option value="promocion" <?= $filtro_tipo === 'promocion' ? 'selected' : '' ?>>Promociones</option>
                            <option value="comunicado" <?= $filtro_tipo === 'comunicado' ? 'selected' : '' ?>>Comunicados</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Buscar</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($publicaciones)): ?>
        <div class="text-center py-5">
            <i class="bi bi-newspaper display-1 text-muted"></i>
            <p class="mt-3 text-muted">No se encontraron publicaciones</p>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($publicaciones as $pub): 
                $img_src = '';
                if (!empty($pub['imagen'])) {
                    $img_src = (strpos($pub['imagen'], 'http') === 0) ? $pub['imagen'] : (UPLOADS_URL . '/publicaciones/' . $pub['imagen']);
                }
                $num_likes = $likes_count[$pub['id']] ?? 0;
                $ya_like = in_array($pub['id'], $likes_ya);
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <?php if ($img_src): ?>
                    <img src="<?= e($img_src) ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="">
                    <?php else: ?>
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                        <i class="bi bi-newspaper display-4 text-muted"></i>
                    </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <?php
                        $tipo_badge = ['noticia' => 'bg-info', 'evento' => 'bg-primary', 'promocion' => 'bg-warning text-dark', 'comunicado' => 'bg-secondary'];
                        ?>
                        <span class="badge <?= $tipo_badge[$pub['tipo']] ?? 'bg-secondary' ?> mb-2"><?= ucfirst($pub['tipo']) ?></span>
                        <h5 class="card-title"><?= e($pub['titulo']) ?></h5>
                        <p class="card-text"><?= e(truncate($pub['extracto'] ?: $pub['contenido'], 120)) ?></p>
                    </div>
                    <div class="card-footer bg-white">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <small class="text-muted">
                                <?php if (!empty($pub['empresa_nombre'])): ?>
                                <i class="bi bi-building me-1"></i><?= e($pub['empresa_nombre']) ?>
                                <?php endif; ?>
                                · <?= format_date($pub['created_at']) ?>
                            </small>
                            <div class="d-flex gap-2">
                                <a href="<?= PUBLIC_URL ?>/publicacion.php?slug=<?= e(urlencode($pub['slug'])) ?>" class="btn btn-sm btn-outline-primary">Ver más</a>
                                <?php if (!empty($pub['empresa_id'])): ?>
                                <a href="<?= PUBLIC_URL ?>/empresa.php?id=<?= (int)$pub['empresa_id'] ?>" class="btn btn-sm btn-outline-secondary">Ver empresa</a>
                                <?php endif; ?>
                                <a href="noticias.php?<?= http_build_query(array_merge($_GET, ['like' => $pub['id']])) ?>" class="btn btn-sm btn-outline-danger" title="Me gusta"><i class="bi bi-heart<?= $ya_like ? '-fill' : '' ?>"></i> <?= $num_likes ?: '' ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4">
            <?= render_pagination($pagination) ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
