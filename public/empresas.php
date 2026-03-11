<?php
/**
 * Listado de Empresas - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../config/config.php';

$page_title = 'Empresas';
$filtro_rubro = $_GET['rubro'] ?? '';
$filtro_ubicacion = $_GET['ubicacion'] ?? '';
$busqueda = $_GET['q'] ?? '';
$pagina = max(1, intval($_GET['page'] ?? 1));
$por_pagina = 12;

try {
    $db = getDB();
    $where = ["1=1"];
    $params = [];
    
    if ($busqueda) {
        $where[] = "(nombre LIKE ? OR rubro LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    if ($filtro_rubro) {
        $where[] = "rubro = ?";
        $params[] = $filtro_rubro;
    }
    if ($filtro_ubicacion) {
        $where[] = "ubicacion = ?";
        $params[] = $filtro_ubicacion;
    }
    
    $where_sql = implode(' AND ', $where);
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM empresas WHERE $where_sql");
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    $offset = ($pagina - 1) * $por_pagina;
    $stmt = $db->prepare("SELECT * FROM empresas WHERE $where_sql ORDER BY nombre ASC LIMIT $por_pagina OFFSET $offset");
    $stmt->execute($params);
    $empresas = $stmt->fetchAll();
    
    $rubros_lista = $db->query("SELECT DISTINCT rubro FROM empresas WHERE rubro IS NOT NULL ORDER BY rubro")->fetchAll(PDO::FETCH_COLUMN);
    $ubicaciones_lista = $db->query("SELECT DISTINCT ubicacion FROM empresas WHERE ubicacion IS NOT NULL ORDER BY ubicacion")->fetchAll(PDO::FETCH_COLUMN);
    $total_paginas = ceil($total / $por_pagina);
} catch (Exception $e) {
    $empresas = []; $rubros_lista = []; $ubicaciones_lista = []; $total = 0; $total_paginas = 0;
}

require_once BASEPATH . '/includes/header.php';
?>

<div class="bg-primary text-white py-4">
    <div class="container">
        <h1 class="h3 mb-0"><i class="bi bi-buildings me-2"></i>Directorio de Empresas</h1>
        <p class="mb-0 opacity-75">Encontrá las empresas del Parque Industrial de Catamarca</p>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Buscar empresa</label>
                        <input type="text" name="q" class="form-control" placeholder="Nombre..." value="<?= e($busqueda) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Rubro</label>
                        <select name="rubro" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($rubros_lista as $r): ?>
                            <option value="<?= e($r) ?>" <?= $filtro_rubro === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ubicación</label>
                        <select name="ubicacion" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach ($ubicaciones_lista as $ub): ?>
                            <option value="<?= e($ub) ?>" <?= $filtro_ubicacion === $ub ? 'selected' : '' ?>><?= e($ub) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Buscar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted"><?= $total ?> empresas encontradas</span>
        </div>
        
        <div class="row g-4" id="empresasGrid">
            <?php foreach ($empresas as $emp): ?>
            <div class="col-md-6 col-lg-4">
                <div class="empresa-card">
                    <div class="card-img">
                        <i class="bi bi-building" style="font-size: 4rem; color: #ccc;"></i>
                    </div>
                    <div class="card-body">
                        <span class="card-rubro"><?= e($emp['rubro'] ?? 'Sin rubro') ?></span>
                        <h5 class="card-title"><?= e($emp['nombre']) ?></h5>
                        <p class="card-text mb-1"><i class="bi bi-geo-alt text-primary"></i> <?= e($emp['ubicacion'] ?? '-') ?></p>
                        <?php if (!empty($emp['telefono'])): ?>
                        <p class="card-text mb-1"><i class="bi bi-telephone text-primary"></i> <?= e($emp['telefono']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($emp['contacto_nombre'])): ?>
                        <p class="card-text"><i class="bi bi-person text-primary"></i> <?= e($emp['contacto_nombre']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent d-flex justify-content-between">
                        <a href="empresa.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-primary">Ver perfil</a>
                        <?php if (!empty($emp['telefono'])): ?>
                        <a href="tel:<?= $emp['telefono'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-telephone"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($empresas)): ?>
        <div class="text-center py-5">
            <i class="bi bi-search fs-1 text-muted"></i>
            <h4 class="mt-3">No se encontraron empresas</h4>
            <a href="empresas.php" class="btn btn-primary">Ver todas</a>
        </div>
        <?php endif; ?>
        
        <?php if ($total_paginas > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($pagina > 1): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $pagina-1 ?>&q=<?= e($busqueda) ?>&rubro=<?= e($filtro_rubro) ?>&ubicacion=<?= e($filtro_ubicacion) ?>">«</a></li>
                <?php endif; ?>
                <?php for ($i = max(1, $pagina-2); $i <= min($total_paginas, $pagina+2); $i++): ?>
                <li class="page-item <?= $i==$pagina?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&q=<?= e($busqueda) ?>&rubro=<?= e($filtro_rubro) ?>&ubicacion=<?= e($filtro_ubicacion) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <?php if ($pagina < $total_paginas): ?>
                <li class="page-item"><a class="page-link" href="?page=<?= $pagina+1 ?>&q=<?= e($busqueda) ?>&rubro=<?= e($filtro_rubro) ?>&ubicacion=<?= e($filtro_ubicacion) ?>">»</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</section>

<?php require_once BASEPATH . '/includes/footer.php'; ?>
