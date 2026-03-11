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
    SELECT p.*, e.nombre as empresa_nombre, e.logo
    FROM publicaciones p
    LEFT JOIN empresas e ON p.empresa_id = e.id
    $where_sql
    ORDER BY p.created_at DESC
    LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset
");
$stmt->execute($params);
$publicaciones = $stmt->fetchAll();

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
            <?php foreach ($publicaciones as $pub): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <?php if ($pub['imagen']): ?>
                    <img src="<?= UPLOADS_URL ?>/publicaciones/<?= e($pub['imagen']) ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="">
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
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <?php if ($pub['empresa_nombre']): ?>
                            <i class="bi bi-building me-1"></i><?= e($pub['empresa_nombre']) ?>
                            <?php endif; ?>
                        </small>
                        <small class="text-muted"><?= format_date($pub['created_at']) ?></small>
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
