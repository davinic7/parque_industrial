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
    $where = ["estado IN ('activa', 'pendiente')"];
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
    
    $offset = (int)(($pagina - 1) * $por_pagina);
    $stmt = $db->prepare("SELECT * FROM empresas WHERE $where_sql ORDER BY nombre ASC LIMIT " . (int)$por_pagina . " OFFSET " . $offset);
    $stmt->execute($params);
    $empresas = $stmt->fetchAll();
    
    $rubros_lista = $db->query("SELECT rubro, COUNT(*) AS cnt FROM empresas WHERE rubro IS NOT NULL AND rubro != '' GROUP BY rubro ORDER BY rubro")->fetchAll();
    $ubicaciones_lista = $db->query("SELECT ubicacion, COUNT(*) AS cnt FROM empresas WHERE ubicacion IS NOT NULL AND ubicacion != '' GROUP BY ubicacion ORDER BY ubicacion")->fetchAll();
    $total_paginas = ceil($total / $por_pagina);
} catch (Exception $e) {
    $empresas = [];
    $rubros_lista = [];
    $ubicaciones_lista = [];
    $total = 0;
    $total_paginas = 0;
}

$filtros_activos = ($busqueda !== '' || $filtro_rubro !== '' || $filtro_ubicacion !== '');

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
                            <?php foreach ($rubros_lista as $row): $r = $row['rubro']; $c = (int) $row['cnt']; ?>
                            <option value="<?= e($r) ?>" <?= $filtro_rubro === $r ? 'selected' : '' ?>><?= e($r) ?> (<?= $c ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ubicación</label>
                        <select name="ubicacion" class="form-select">
                            <option value="">Todas</option>
                            <?php foreach ($ubicaciones_lista as $row): $ub = $row['ubicacion']; $c = (int) $row['cnt']; ?>
                            <option value="<?= e($ub) ?>" <?= $filtro_ubicacion === $ub ? 'selected' : '' ?>><?= e($ub) ?> (<?= $c ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Buscar</button>
                        <?php if ($filtros_activos): ?>
                        <a href="empresas.php" class="btn btn-outline-secondary btn-sm w-100">Limpiar filtros</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted"><?= $total ?> empresas encontradas</span>
        </div>
        
        <div class="row g-4" id="empresasGrid">
            <?php
            $card_options = ['show_visitas' => false, 'show_contact' => true, 'show_tel_button' => true];
            foreach ($empresas as $emp) {
                require BASEPATH . '/includes/partials/card_empresa.php';
            }
            ?>
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
