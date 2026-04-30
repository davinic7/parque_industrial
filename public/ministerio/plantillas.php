<?php
/**
 * Plantillas de respuesta rápida - Ministerio
 * CRUD completo para gestionar mensajes prefabricados.
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Plantillas de respuesta';
$ministerio_nav = 'plantillas';
$db = getDB();

// ── Verificar que la tabla exista ──
try {
    $db->query('SELECT 1 FROM plantillas_respuesta LIMIT 1');
} catch (Throwable $e) {
    set_flash('warning', 'La tabla de plantillas no está creada. Aplique database/018_plantillas_respuesta.sql primero.');
    redirect('dashboard.php');
}

// ── POST: crear / editar / eliminar ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id        = (int) ($_POST['id'] ?? 0);
        $titulo    = trim($_POST['titulo'] ?? '');
        $contenido = trim($_POST['contenido'] ?? '');
        $categoria = $_POST['categoria'] ?? 'general';
        $orden     = (int) ($_POST['orden'] ?? 0);
        $activa    = isset($_POST['activa']) ? 1 : 0;

        $categorias_validas = ['tramite','consulta','reclamo','comunicado','formulario','sistema','general'];
        if (!in_array($categoria, $categorias_validas)) $categoria = 'general';

        if ($titulo === '' || $contenido === '') {
            set_flash('error', 'El título y contenido son obligatorios.');
        } elseif ($id > 0) {
            $db->prepare("UPDATE plantillas_respuesta SET titulo=?, contenido=?, categoria=?, orden=?, activa=? WHERE id=?")
               ->execute([$titulo, $contenido, $categoria, $orden, $activa, $id]);
            set_flash('success', 'Plantilla actualizada.');
        } else {
            $db->prepare("INSERT INTO plantillas_respuesta (titulo, contenido, categoria, orden, activa) VALUES (?,?,?,?,?)")
               ->execute([$titulo, $contenido, $categoria, $orden, $activa]);
            set_flash('success', 'Plantilla creada.');
        }
        redirect('plantillas.php');
    }

    if ($accion === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM plantillas_respuesta WHERE id = ?")->execute([$id]);
            set_flash('success', 'Plantilla eliminada.');
        }
        redirect('plantillas.php');
    }
}

// ── Leer todas las plantillas ──
$plantillas = $db->query("SELECT * FROM plantillas_respuesta ORDER BY orden ASC, titulo ASC")->fetchAll();

// ── Edición inline ──
$editando = null;
if (!empty($_GET['editar'])) {
    $eid = (int) $_GET['editar'];
    foreach ($plantillas as $p) {
        if ($p['id'] === $eid || (int) $p['id'] === $eid) { $editando = $p; break; }
    }
}

require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-file-earmark-text me-2"></i>Plantillas de respuesta</h1>
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#formPlantilla" aria-expanded="<?= $editando ? 'true' : 'false' ?>">
        <i class="bi bi-plus-lg me-1"></i><?= $editando ? 'Editando plantilla' : 'Nueva plantilla' ?>
    </button>
</div>

<?php show_flash(); ?>

<!-- Formulario crear / editar -->
<div class="collapse <?= $editando ? 'show' : '' ?> mb-4" id="formPlantilla">
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="<?= $editando['id'] ?? 0 ?>">

                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Título</label>
                        <input type="text" name="titulo" class="form-control" required maxlength="120"
                               value="<?= e($editando['titulo'] ?? '') ?>"
                               placeholder="Ej: Recibimos su consulta">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Categoría</label>
                        <select name="categoria" class="form-select">
                            <?php
                            $cats = ['general'=>'General','consulta'=>'Consulta','tramite'=>'Trámite','reclamo'=>'Reclamo','comunicado'=>'Comunicado','formulario'=>'Formulario','sistema'=>'Sistema'];
                            foreach ($cats as $k => $v):
                                $sel = ($editando['categoria'] ?? 'general') === $k ? ' selected' : '';
                            ?>
                            <option value="<?= $k ?>"<?= $sel ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Orden</label>
                        <input type="number" name="orden" class="form-control" min="0"
                               value="<?= (int) ($editando['orden'] ?? 0) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="activa" class="form-check-input" id="chkActiva"
                                   <?= ($editando === null || !empty($editando['activa'])) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="chkActiva">Activa</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Contenido del mensaje</label>
                        <textarea name="contenido" class="form-control" rows="6" required
                                  placeholder="Escriba el texto de la plantilla..."><?= e($editando['contenido'] ?? '') ?></textarea>
                        <div class="form-text">Use \n para saltos de línea. El texto se insertará tal cual en el compositor de mensajes.</div>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i><?= $editando ? 'Actualizar' : 'Crear' ?> plantilla
                        </button>
                        <?php if ($editando): ?>
                        <a href="plantillas.php" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Lista de plantillas -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>Título</th>
                    <th>Categoría</th>
                    <th>Vista previa</th>
                    <th style="width:80px">Estado</th>
                    <th style="width:120px" class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($plantillas)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No hay plantillas. Cree la primera.</td></tr>
                <?php endif; ?>
                <?php foreach ($plantillas as $p): ?>
                <tr class="<?= empty($p['activa']) ? 'table-secondary' : '' ?>">
                    <td class="text-muted small"><?= (int) $p['orden'] ?></td>
                    <td class="fw-semibold"><?= e($p['titulo']) ?></td>
                    <td><span class="badge bg-secondary"><?= e(ucfirst($p['categoria'])) ?></span></td>
                    <td class="small text-muted" style="max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= e(mb_substr(str_replace('\n', ' ', $p['contenido']), 0, 80)) ?>…
                    </td>
                    <td>
                        <?php if ($p['activa']): ?>
                        <span class="badge bg-success">Activa</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Inactiva</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="plantillas.php?editar=<?= (int) $p['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta plantilla?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button class="btn btn-outline-danger btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>
