<?php
/**
 * Gestión de Banners y Encabezados de páginas públicas
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Banners del inicio';
$db = getDB();

// ── Verificar tabla carousel ────────────────────────────────────────────────
$table_exists = false;
try { $db->query("SELECT 1 FROM banners_home LIMIT 1"); $table_exists = true; } catch (Exception $e) {}

// ── Helper: guardar clave de config ─────────────────────────────────────────
function save_config(PDO $db, string $clave, string $valor, string $tipo = 'text', string $grupo = 'heroes'): void {
    $db->prepare("INSERT INTO configuracion_sitio (clave, valor, tipo, grupo)
                  VALUES (?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo), grupo = VALUES(grupo)")
       ->execute([$clave, $valor, $tipo, $grupo]);
}

// ── POST: carrusel inicio ────────────────────────────────────────────────────
if ($table_exists && $_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($accion === 'eliminar' && $id > 0) {
        $db->prepare("DELETE FROM banners_home WHERE id = ?")->execute([$id]);
        set_flash('success', 'Banner eliminado.');
        redirect('banners.php?tab=inicio');
    }

    if ($accion === 'guardar') {
        $titulo    = trim($_POST['titulo'] ?? '');
        $subtitulo = trim($_POST['subtitulo'] ?? '');
        $orden     = (int)($_POST['orden'] ?? 0);
        $activo    = isset($_POST['activo']) ? 1 : 0;
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
            $db->prepare("UPDATE banners_home SET titulo=?, subtitulo=?, imagen=?, tipo='imagen', url_video=NULL, orden=?, activo=? WHERE id=?")
               ->execute([$titulo, $subtitulo, $imagen_url, $orden, $activo, $id]);
            set_flash('success', 'Banner actualizado.');
        } else {
            if ($imagen_url) {
                $db->prepare("INSERT INTO banners_home (titulo, subtitulo, imagen, tipo, url_video, orden, activo) VALUES (?,?,?,'imagen',NULL,?,?)")
                   ->execute([$titulo, $subtitulo, $imagen_url, $orden, $activo]);
                set_flash('success', 'Banner agregado.');
            } else {
                set_flash('error', 'Suba una imagen para el nuevo banner.');
            }
        }
        redirect('banners.php?tab=inicio');
    }

    // ── POST: encabezados de otras páginas ──────────────────────────────────
    if ($accion === 'guardar_hero') {
        $pagina    = $_POST['pagina'] ?? '';
        $titulo    = trim($_POST['hero_titulo']    ?? '');
        $subtitulo = trim($_POST['hero_subtitulo'] ?? '');
        $grupo     = 'heroes';

        $paginas_validas = ['parque', 'empresas', 'noticias', 'estadisticas'];
        if (!in_array($pagina, $paginas_validas, true)) {
            set_flash('error', 'Página no válida.'); redirect('banners.php');
        }

        // Prefijos de config
        $pref = $pagina === 'parque' ? 'nosotros' : 'hero_' . $pagina;

        save_config($db, $pref . '_titulo',    $titulo,    'text',  $grupo);
        save_config($db, $pref . '_subtitulo', $subtitulo, 'text',  $grupo);

        // Imagen de fondo (opcional)
        if (!empty($_FILES['hero_imagen']['name']) && $_FILES['hero_imagen']['error'] === UPLOAD_ERR_OK) {
            $url = cloudinary_upload_image($_FILES['hero_imagen']['tmp_name']);
            if ($url) save_config($db, $pref . '_imagen', $url, 'image', $grupo);
        }

        set_flash('success', 'Encabezado guardado correctamente.');
        redirect('banners.php?tab=' . $pagina);
    }
}

// ── Cargar datos ─────────────────────────────────────────────────────────────
$banners = [];
if ($table_exists) {
    try { $banners = $db->query("SELECT * FROM banners_home ORDER BY orden ASC, id ASC")->fetchAll(); }
    catch (Exception $e) {}
}

$editar = null;
if ($table_exists && isset($_GET['editar'])) {
    $stmt = $db->prepare("SELECT * FROM banners_home WHERE id = ?");
    $stmt->execute([(int)$_GET['editar']]);
    $editar = $stmt->fetch() ?: null;
}

// Leer configs de heroes
$cfg = [];
foreach ([
    'nosotros_titulo', 'nosotros_subtitulo', 'nosotros_imagen',
    'hero_empresas_titulo', 'hero_empresas_subtitulo', 'hero_empresas_imagen',
    'hero_noticias_titulo', 'hero_noticias_subtitulo', 'hero_noticias_imagen',
    'hero_estadisticas_titulo', 'hero_estadisticas_subtitulo', 'hero_estadisticas_imagen',
] as $k) {
    $cfg[$k] = get_config($k, '');
}

$tab_activo = $_GET['tab'] ?? 'inicio';
$ministerio_nav = 'banners';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0 fw-semibold"><i class="bi bi-images me-2"></i>Banners y encabezados</h2>
    <a href="<?= e(PUBLIC_URL) ?>/" target="_blank" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-box-arrow-up-right me-1"></i>Ver sitio público
    </a>
</div>

<?php show_flash(); ?>

<!-- Pestañas -->
<ul class="nav nav-tabs mb-4" id="bannerTabs">
    <?php
    $tabs = [
        'inicio'       => ['icon' => 'bi-house',         'label' => 'Inicio (Carrusel)'],
        'parque'       => ['icon' => 'bi-building',      'label' => 'El Parque'],
        'empresas'     => ['icon' => 'bi-buildings',     'label' => 'Directorio'],
        'noticias'     => ['icon' => 'bi-newspaper',     'label' => 'Noticias'],
        'estadisticas' => ['icon' => 'bi-graph-up',      'label' => 'Estadísticas'],
    ];
    foreach ($tabs as $key => $t):
        $active = $tab_activo === $key ? 'active' : '';
    ?>
    <li class="nav-item">
        <a class="nav-link <?= $active ?>" href="banners.php?tab=<?= $key ?>">
            <i class="bi <?= $t['icon'] ?> me-1"></i><?= $t['label'] ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- ══ TAB INICIO ══ -->
<?php if ($tab_activo === 'inicio'): ?>

<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><?= $editar ? 'Editar slide' : 'Nuevo slide' ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= $editar['id'] ?? 0 ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Título</label>
                    <input type="text" name="titulo" class="form-control"
                           value="<?= e($editar['titulo'] ?? '') ?>"
                           placeholder="Ej: Bienvenidos al Parque Industrial">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Subtítulo</label>
                    <input type="text" name="subtitulo" class="form-control"
                           value="<?= e($editar['subtitulo'] ?? '') ?>"
                           placeholder="Texto secundario">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Imagen</label>
                    <input type="file" name="imagen" class="form-control" accept="image/*">
                    <?php if (!empty($editar['imagen'])): ?>
                    <div class="mt-2">
                        <img src="<?= e($editar['imagen']) ?>" alt="" style="height:60px;border-radius:6px;">
                        <small class="text-muted ms-2">Se mantiene si no elige otra.</small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Orden</label>
                    <input type="number" name="orden" class="form-control" value="<?= (int)($editar['orden'] ?? 0) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="checkbox" name="activo" class="form-check-input" id="activo"
                               <?= ($editar['activo'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">Activo</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <?= $editar ? 'Guardar cambios' : 'Agregar slide' ?>
                    </button>
                    <?php if ($editar): ?>
                    <a href="banners.php?tab=inicio" class="btn btn-outline-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Slides del carrusel</h5>
        <span class="badge bg-secondary"><?= count($banners) ?> slides</span>
    </div>
    <?php if (empty($banners)): ?>
    <div class="card-body"><p class="text-muted mb-0">No hay slides creados aún.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
                <tr><th>Vista</th><th>Título</th><th>Orden</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($banners as $b): ?>
            <tr>
                <td>
                    <?php if (!empty($b['imagen'])): ?>
                    <img src="<?= e($b['imagen']) ?>" alt="" style="height:50px;width:80px;object-fit:cover;border-radius:5px;">
                    <?php else: ?>
                    <div class="bg-secondary text-white d-flex align-items-center justify-content-center" style="height:50px;width:80px;border-radius:5px;"><i class="bi bi-image"></i></div>
                    <?php endif; ?>
                </td>
                <td><strong><?= e($b['titulo'] ?: 'Sin título') ?></strong></td>
                <td><?= $b['orden'] ?></td>
                <td><?= $b['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' ?></td>
                <td>
                    <a href="banners.php?tab=inicio&editar=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este slide?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<!-- ══ TABs DE PÁGINAS (El Parque / Directorio / Noticias / Estadísticas) ══ -->
<?php

$hero_pages = [
    'parque'       => [
        'titulo_label' => 'Página "El Parque"',
        'pref'         => 'nosotros',
        'default_t'    => 'Parque Industrial de Catamarca',
        'default_s'    => 'Impulsando el desarrollo productivo de la provincia',
        'url'          => PUBLIC_URL . '/el-parque.php',
        'nota'         => 'También controla el título y subtítulo de la sección Nosotros.',
    ],
    'empresas'     => [
        'titulo_label' => 'Página "Directorio de Empresas"',
        'pref'         => 'hero_empresas',
        'default_t'    => 'Directorio de Empresas',
        'default_s'    => 'Encontrá las empresas del Parque Industrial de Catamarca',
        'url'          => PUBLIC_URL . '/empresas.php',
        'nota'         => null,
    ],
    'noticias'     => [
        'titulo_label' => 'Página "Noticias"',
        'pref'         => 'hero_noticias',
        'default_t'    => 'Noticias y Publicaciones',
        'default_s'    => 'Novedades del Parque Industrial',
        'url'          => PUBLIC_URL . '/noticias.php',
        'nota'         => null,
    ],
    'estadisticas' => [
        'titulo_label' => 'Página "Estadísticas"',
        'pref'         => 'hero_estadisticas',
        'default_t'    => 'Estadísticas del Parque Industrial',
        'default_s'    => 'Datos del desarrollo industrial de Catamarca',
        'url'          => PUBLIC_URL . '/estadisticas.php',
        'nota'         => null,
    ],
];

if (isset($hero_pages[$tab_activo])):
    $hp    = $hero_pages[$tab_activo];
    $pref  = $hp['pref'];
    $t_val = $cfg[$pref . '_titulo']    ?: $hp['default_t'];
    $s_val = $cfg[$pref . '_subtitulo'] ?: $hp['default_s'];
    $i_val = $cfg[$pref . '_imagen']    ?? '';
?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Editar encabezado — <?= $hp['titulo_label'] ?></h5>
                <a href="<?= e($hp['url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Ver página
                </a>
            </div>
            <div class="card-body">
                <?php if ($hp['nota']): ?>
                <div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i><?= $hp['nota'] ?></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="accion"  value="guardar_hero">
                    <input type="hidden" name="pagina"  value="<?= $tab_activo ?>">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Título</label>
                            <input type="text" name="hero_titulo" class="form-control"
                                   value="<?= e($t_val) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Subtítulo</label>
                            <input type="text" name="hero_subtitulo" class="form-control"
                                   value="<?= e($s_val) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Imagen de fondo (opcional)</label>
                            <input type="file" name="hero_imagen" class="form-control" accept="image/*">
                            <?php if ($i_val): ?>
                            <div class="mt-2 d-flex align-items-center gap-3">
                                <img src="<?= e($i_val) ?>" alt="" style="height:60px;border-radius:6px;object-fit:cover;">
                                <small class="text-muted">Imagen actual. Se mantiene si no carga una nueva.</small>
                            </div>
                            <?php else: ?>
                            <div class="form-text">Si no sube imagen, el encabezado usará el color de fondo por defecto.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Guardar encabezado
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white"><h5 class="mb-0">Vista previa</h5></div>
            <div class="card-body p-0">
                <div style="
                    background: <?= $i_val ? 'linear-gradient(rgba(0,0,0,.45),rgba(0,0,0,.45)), url(' . e($i_val) . ') center/cover' : 'linear-gradient(135deg, #1a5276, #154360)' ?>;
                    color: #fff;
                    padding: 36px 24px;
                    border-radius: 0 0 8px 8px;
                    min-height: 140px;
                ">
                    <h2 style="font-size:1.35rem;font-weight:700;margin-bottom:6px;" id="prev_titulo">
                        <?= e($t_val) ?>
                    </h2>
                    <p style="opacity:.85;margin:0;font-size:.9rem;" id="prev_subtitulo">
                        <?= e($s_val) ?>
                    </p>
                </div>
                <div class="p-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        El preview se actualiza al escribir.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
$extra_scripts = <<<'JS'
<script>
(function() {
    const t = document.querySelector('[name="hero_titulo"]');
    const s = document.querySelector('[name="hero_subtitulo"]');
    const pt = document.getElementById('prev_titulo');
    const ps = document.getElementById('prev_subtitulo');
    if (!t || !pt) return;
    t.addEventListener('input', () => pt.textContent = t.value || '…');
    if (s && ps) s.addEventListener('input', () => ps.textContent = s.value || '');
})();
</script>
JS;
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
