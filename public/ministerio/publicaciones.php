<?php
/**
 * Gestión y moderación de publicaciones - Ministerio
 * Pestaña 1: contenido propio del ministerio (crear / editar / eliminar)
 * Pestaña 2: revisión de publicaciones enviadas por empresas
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Publicaciones';
$db = getDB();
$user_id = (int) ($_SESSION['user_id'] ?? 0);

$pub_form   = null;
$pub_errors = [];

// ─────────────────────────────────────────────────────────────────
// ACCIONES POST
// ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';
    $pub_id = (int) ($_POST['publicacion_id'] ?? 0);

    // ── Aprobar / rechazar publicación de empresa ──────────────────
    if (in_array($accion, ['aprobar', 'rechazar'], true) && $pub_id > 0) {
        $nuevo_estado  = ($accion === 'aprobar') ? 'aprobado' : 'rechazado';
        $observaciones = trim($_POST['observaciones'] ?? '');

        if ($accion === 'aprobar') {
            $db->prepare("UPDATE publicaciones SET estado = ?, aprobado_por = ?, fecha_aprobacion = NOW(), motivo_rechazo = NULL WHERE id = ? AND empresa_id IS NOT NULL")
               ->execute([$nuevo_estado, $user_id, $pub_id]);
        } else {
            $db->prepare("UPDATE publicaciones SET estado = ?, motivo_rechazo = ? WHERE id = ? AND empresa_id IS NOT NULL")
               ->execute([$nuevo_estado, $observaciones ?: null, $pub_id]);
        }

        // Notificar empresa
        $stmt = $db->prepare('SELECT p.titulo, p.empresa_id, p.slug, e.usuario_id FROM publicaciones p INNER JOIN empresas e ON p.empresa_id = e.id WHERE p.id = ?');
        $stmt->execute([$pub_id]);
        $pub_data = $stmt->fetch();
        if ($pub_data) {
            $titulo_notif = ($accion === 'aprobar') ? 'Publicación aprobada' : 'Publicación rechazada';
            $msg = ($accion === 'aprobar')
                ? "Su publicación \"{$pub_data['titulo']}\" fue aprobada y ya es visible."
                : "Su publicación \"{$pub_data['titulo']}\" fue rechazada." . ($observaciones ? " Motivo: $observaciones" : '');
            if ($accion === 'aprobar' && !empty($pub_data['slug'])) {
                $url_emp = rtrim(PUBLIC_URL, '/') . '/publicacion.php?slug=' . rawurlencode($pub_data['slug']);
            } else {
                $url_emp = rtrim(EMPRESA_URL, '/') . '/publicaciones.php' . ($accion === 'rechazar' ? '?editar=' . $pub_id : '');
            }
            crear_notificacion($pub_data['usuario_id'], 'publicacion_revisada', $titulo_notif, $msg, $url_emp);
            log_activity("publicacion_$accion", 'publicaciones', $pub_data['empresa_id']);
        }

        set_flash('success', 'Publicación ' . ($accion === 'aprobar' ? 'aprobada' : 'rechazada'));
        redirect('publicaciones.php?tab=revision&' . http_build_query(array_filter([
            'estado' => $_GET['estado'] ?? '',
            'tipo'   => $_GET['tipo']   ?? '',
            'buscar' => $_GET['buscar'] ?? '',
        ])));
    }

    // ── Guardar publicación propia del ministerio ──────────────────
    if (in_array($accion, ['guardar', 'publicar'], true)) {
        $titulo           = trim($_POST['titulo'] ?? '');
        $tipo             = trim($_POST['tipo'] ?? 'noticia');
        $extracto         = trim($_POST['extracto'] ?? '');
        $contenido        = trim($_POST['contenido'] ?? '');
        $destacado        = isset($_POST['destacado']) ? 1 : 0;
        $mostrar_inicio   = isset($_POST['mostrar_en_inicio']) ? 1 : 0;
        $fecha_pub        = trim($_POST['fecha_publicacion'] ?? '');
        $estado           = ($accion === 'publicar') ? 'aprobado' : 'borrador';
        $publicado        = ($accion === 'publicar') ? 1 : 0;

        if ($titulo === '') $pub_errors['titulo'] = 'El título es obligatorio';
        if ($accion === 'publicar' && $contenido === '') $pub_errors['contenido'] = 'El contenido es obligatorio para publicar';
        if (!in_array($tipo, ['noticia', 'evento', 'promocion', 'comunicado', 'empleados'], true)) $tipo = 'noticia';

        $imagen = null;
        if (empty($pub_errors) && !empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $res = upload_image_storage($_FILES['imagen'], 'publicaciones', ALLOWED_IMAGE_TYPES);
            if ($res['success']) {
                $imagen = $res['filename'];
            } else {
                $pub_errors['imagen'] = $res['error'];
            }
        }

        if (empty($pub_errors)) {
            // Slug único
            $slug_base = slugify($titulo);
            $slug = $slug_base;
            for ($i = 1; $i < 50; $i++) {
                $chk = $pub_id > 0
                    ? $db->prepare('SELECT 1 FROM publicaciones WHERE slug = ? AND id != ? LIMIT 1')
                    : $db->prepare('SELECT 1 FROM publicaciones WHERE slug = ? LIMIT 1');
                $chk->execute($pub_id > 0 ? [$slug, $pub_id] : [$slug]);
                if (!$chk->fetch()) break;
                $slug = $slug_base . '-' . $i;
            }

            try {
                if ($pub_id > 0) {
                    // Editar: solo publicaciones propias del ministerio
                    $stmt = $db->prepare('SELECT id FROM publicaciones WHERE id = ? AND empresa_id IS NULL');
                    $stmt->execute([$pub_id]);
                    if (!$stmt->fetch()) {
                        set_flash('error', 'No se puede editar esa publicación');
                        redirect('publicaciones.php?tab=propias');
                    }
                    $sql = 'UPDATE publicaciones SET titulo=?, slug=?, tipo=?, extracto=?, contenido=?, estado=?, publicado=?, destacado=?, mostrar_en_inicio=?, fecha_publicacion=?';
                    $params = [$titulo, $slug, $tipo, $extracto, $contenido, $estado, $publicado, $destacado, $mostrar_inicio, $fecha_pub ?: null];
                    if ($imagen) { $sql .= ', imagen=?'; $params[] = $imagen; }
                    $sql .= ' WHERE id = ? AND empresa_id IS NULL';
                    $params[] = $pub_id;
                    $db->prepare($sql)->execute($params);
                } else {
                    $db->prepare('INSERT INTO publicaciones (empresa_id, usuario_id, titulo, slug, tipo, extracto, contenido, imagen, estado, publicado, destacado, mostrar_en_inicio, fecha_publicacion, aprobado_por, fecha_aprobacion)
                                  VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                       ->execute([
                           $user_id, $titulo, $slug, $tipo, $extracto, $contenido, $imagen,
                           $estado, $publicado, $destacado, $mostrar_inicio,
                           $fecha_pub ?: null,
                           $publicado ? $user_id : null,
                           $publicado ? date('Y-m-d H:i:s') : null,
                       ]);
                    $pub_id = (int) $db->lastInsertId();
                }
                log_activity($accion === 'publicar' ? 'publicacion_publicada' : 'publicacion_guardada', 'publicaciones', null);
                set_flash('success', $accion === 'publicar' ? 'Publicación publicada correctamente' : 'Borrador guardado');
                redirect('publicaciones.php?tab=propias');
            } catch (Throwable $e) {
                error_log('ministerio/publicaciones guardar: ' . $e->getMessage());
                $pub_errors['general'] = 'Error al guardar. Vuelva a intentar.';
            }
        }

        if (!empty($pub_errors)) {
            $pub_form = compact('pub_id', 'titulo', 'tipo', 'extracto', 'contenido', 'destacado', 'mostrar_inicio', 'fecha_pub');
        }
    }

    // ── Eliminar publicación propia del ministerio ─────────────────
    if ($accion === 'eliminar' && $pub_id > 0) {
        $db->prepare("DELETE FROM publicaciones WHERE id = ? AND empresa_id IS NULL")->execute([$pub_id]);
        set_flash('success', 'Publicación eliminada');
        redirect('publicaciones.php?tab=propias');
    }

    // ── Despublicar publicación propia ────────────────────────────
    if ($accion === 'despublicar' && $pub_id > 0) {
        $db->prepare("UPDATE publicaciones SET publicado = 0, estado = 'borrador' WHERE id = ? AND empresa_id IS NULL")->execute([$pub_id]);
        set_flash('success', 'Publicación despublicada');
        redirect('publicaciones.php?tab=propias');
    }
}

// ─────────────────────────────────────────────────────────────────
// TAB ACTIVA
// ─────────────────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'propias';
if (!in_array($tab, ['propias', 'revision'], true)) $tab = 'propias';

// ─────────────────────────────────────────────────────────────────
// DATOS TAB PROPIAS
// ─────────────────────────────────────────────────────────────────
$editando = null;
if ($tab === 'propias') {
    if (isset($_GET['editar'])) {
        $stmt = $db->prepare('SELECT * FROM publicaciones WHERE id = ? AND empresa_id IS NULL');
        $stmt->execute([(int) $_GET['editar']]);
        $editando = $stmt->fetch();
    }
    if ($pub_form !== null) {
        $pid = (int) ($pub_form['pub_id'] ?? 0);
        if ($pid > 0) {
            $stmt = $db->prepare('SELECT * FROM publicaciones WHERE id = ? AND empresa_id IS NULL');
            $stmt->execute([$pid]);
            $base = $stmt->fetch();
            $editando = $base ? array_merge($base, $pub_form) : array_merge(['id' => 0, 'imagen' => null], $pub_form);
        } else {
            $editando = array_merge(['id' => 0, 'imagen' => null], $pub_form);
        }
    }
    $mostrar_form = isset($_GET['nueva']) || $editando;

    $stmt = $db->query("SELECT * FROM publicaciones WHERE empresa_id IS NULL ORDER BY created_at DESC");
    $propias = $stmt->fetchAll();
}

// ─────────────────────────────────────────────────────────────────
// DATOS TAB REVISIÓN
// ─────────────────────────────────────────────────────────────────
if ($tab === 'revision') {
    $filtro_estado = trim($_GET['estado'] ?? 'pendiente');
    $filtro_tipo   = trim($_GET['tipo']   ?? '');
    $buscar        = trim($_GET['buscar'] ?? '');
    $pagina        = max(1, (int) ($_GET['pagina'] ?? 1));

    $where  = ["p.empresa_id IS NOT NULL"];
    $params = [];
    if ($filtro_estado !== '' && $filtro_estado !== 'todos') { $where[] = "p.estado = ?"; $params[] = $filtro_estado; }
    if ($filtro_tipo   !== '')                               { $where[] = "p.tipo = ?";   $params[] = $filtro_tipo; }
    if ($buscar        !== '')                               { $where[] = "(p.titulo LIKE ? OR e.nombre LIKE ?)"; $params[] = "%$buscar%"; $params[] = "%$buscar%"; }

    $where_sql = 'WHERE ' . implode(' AND ', $where);

    $stmt = $db->prepare("SELECT COUNT(*) FROM publicaciones p INNER JOIN empresas e ON p.empresa_id = e.id $where_sql");
    $stmt->execute($params);
    $total_revision = (int) $stmt->fetchColumn();

    $pagination = paginate($total_revision, ADMIN_ITEMS_PER_PAGE, $pagina, 'publicaciones.php?tab=revision&' . http_build_query(array_merge($_GET, ['pagina' => '{page}'])));
    $offset = ($pagination['current_page'] - 1) * ADMIN_ITEMS_PER_PAGE;

    $stmt = $db->prepare("
        SELECT p.*, e.nombre AS empresa_nombre
        FROM publicaciones p
        INNER JOIN empresas e ON p.empresa_id = e.id
        $where_sql
        ORDER BY p.created_at DESC
        LIMIT " . ADMIN_ITEMS_PER_PAGE . " OFFSET $offset");
    $stmt->execute($params);
    $publicaciones_revision = $stmt->fetchAll();

    // Badge pendientes
    $stmt_pend = $db->query("SELECT COUNT(*) FROM publicaciones WHERE empresa_id IS NOT NULL AND estado = 'pendiente'");
    $badge_pendientes = (int) $stmt_pend->fetchColumn();
}

// Badge para el menú (siempre lo calculamos)
$stmt_b = $db->query("SELECT COUNT(*) FROM publicaciones WHERE empresa_id IS NOT NULL AND estado = 'pendiente'");
$badge_pendientes_menu = (int) $stmt_b->fetchColumn();

$ministerio_nav = 'publicaciones';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 fw-semibold mb-0">Publicaciones</h2>
    <?php if ($tab === 'propias' && !isset($mostrar_form)): ?>
    <a href="publicaciones.php?tab=propias&nueva=1" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Nueva publicación
    </a>
    <?php elseif ($tab === 'propias' && isset($mostrar_form) && $mostrar_form): ?>
    <a href="publicaciones.php?tab=propias" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver al listado
    </a>
    <?php endif; ?>
</div>

<?php show_flash(); ?>

<!-- Pestañas -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'propias' ? 'active' : '' ?>" href="publicaciones.php?tab=propias">
            <i class="bi bi-pencil-square me-1"></i>Contenido propio
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'revision' ? 'active' : '' ?>" href="publicaciones.php?tab=revision">
            <i class="bi bi-clipboard-check me-1"></i>Revisión de empresas
            <?php if ($badge_pendientes_menu > 0): ?>
            <span class="badge bg-warning text-dark ms-1"><?= $badge_pendientes_menu ?></span>
            <?php endif; ?>
        </a>
    </li>
</ul>

<?php // ══════════════════════════════════════════════════════════
      // TAB: CONTENIDO PROPIO
      // ══════════════════════════════════════════════════════════
      if ($tab === 'propias'): ?>

<?php if ($mostrar_form): ?>
<!-- ── Formulario crear / editar ──────────────────────────────── -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><?= !empty($editando['id']) ? 'Editar publicación' : 'Nueva publicación' ?></h5>
    </div>
    <div class="card-body">
        <?php if (!empty($pub_errors['general'])): ?>
        <div class="alert alert-danger"><?= e($pub_errors['general']) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <?php if (!empty($editando['id'])): ?>
            <input type="hidden" name="publicacion_id" value="<?= (int) $editando['id'] ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Título *</label>
                    <input type="text" name="titulo" class="form-control<?= isset($pub_errors['titulo']) ? ' is-invalid' : '' ?>" required maxlength="255" value="<?= e($editando['titulo'] ?? '') ?>">
                    <?php if (isset($pub_errors['titulo'])): ?><div class="invalid-feedback"><?= e($pub_errors['titulo']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select">
                        <?php foreach (['noticia' => 'Noticia', 'evento' => 'Evento', 'promocion' => 'Promoción', 'comunicado' => 'Comunicado', 'empleados' => 'Empleados'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($editando['tipo'] ?? 'noticia') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Extracto <span class="text-muted fw-normal small">(se muestra en listados)</span></label>
                    <input type="text" name="extracto" class="form-control" maxlength="500" value="<?= e($editando['extracto'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Contenido <span class="text-muted fw-normal small">(obligatorio al publicar)</span></label>
                    <textarea name="contenido" class="form-control<?= isset($pub_errors['contenido']) ? ' is-invalid' : '' ?>" rows="12"><?= e($editando['contenido'] ?? '') ?></textarea>
                    <?php if (isset($pub_errors['contenido'])): ?><div class="invalid-feedback"><?= e($pub_errors['contenido']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Imagen <span class="text-muted fw-normal small">(JPG, PNG, WebP)</span></label>
                    <input type="file" name="imagen" class="form-control<?= isset($pub_errors['imagen']) ? ' is-invalid' : '' ?>" accept="image/jpeg,image/png,image/gif,image/webp">
                    <?php if (isset($pub_errors['imagen'])): ?><div class="invalid-feedback"><?= e($pub_errors['imagen']) ?></div><?php endif; ?>
                    <?php if (!empty($editando['imagen'])): ?>
                    <div class="mt-2">
                        <img src="<?= e(uploads_resolve_url($editando['imagen'], 'publicaciones')) ?>" alt="" style="max-height:80px;border-radius:4px;">
                        <small class="text-muted d-block mt-1">Imagen actual — subir nueva para reemplazar</small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fecha de publicación <span class="text-muted fw-normal small">(opcional)</span></label>
                    <input type="datetime-local" name="fecha_publicacion" class="form-control" value="<?= e(substr($editando['fecha_publicacion'] ?? '', 0, 16)) ?>">
                </div>
                <div class="col-12">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="destacado" id="chk_destacado" value="1" <?= !empty($editando['destacado']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_destacado">Destacada</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="mostrar_en_inicio" id="chk_inicio" value="1" <?= !empty($editando['mostrar_en_inicio']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_inicio">Mostrar en inicio</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" name="accion" value="guardar" class="btn btn-outline-secondary" formnovalidate>
                    <i class="bi bi-save me-1"></i>Guardar borrador
                </button>
                <button type="submit" name="accion" value="publicar" class="btn btn-primary">
                    <i class="bi bi-globe me-1"></i>Publicar
                </button>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<!-- ── Listado publicaciones propias ──────────────────────────── -->
<?php if (empty($propias)): ?>
<div class="text-center py-5 text-muted">
    <i class="bi bi-newspaper display-1"></i>
    <p class="mt-3">Aún no hay publicaciones propias del ministerio.</p>
    <a href="publicaciones.php?tab=propias&nueva=1" class="btn btn-primary mt-2">
        <i class="bi bi-plus-circle me-1"></i>Crear primera publicación
    </a>
</div>
<?php else: ?>
<div class="table-container">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Título</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Opciones</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $tipo_badge   = ['noticia' => 'bg-info', 'evento' => 'bg-primary', 'promocion' => 'bg-warning text-dark', 'comunicado' => 'bg-secondary', 'empleados' => 'bg-success'];
            $estado_badge = ['borrador' => 'bg-secondary', 'aprobado' => 'bg-success'];
            foreach ($propias as $pub):
            ?>
            <tr>
                <td>
                    <strong><?= e($pub['titulo']) ?></strong>
                    <?php if ($pub['extracto']): ?><br><small class="text-muted"><?= e(truncate($pub['extracto'], 70)) ?></small><?php endif; ?>
                </td>
                <td><span class="badge <?= $tipo_badge[$pub['tipo']] ?? 'bg-secondary' ?>"><?= ucfirst($pub['tipo']) ?></span></td>
                <td>
                    <span class="badge <?= $estado_badge[$pub['estado']] ?? 'bg-secondary' ?>">
                        <?= $pub['publicado'] ? 'Publicada' : ucfirst($pub['estado']) ?>
                    </span>
                    <?php if ($pub['destacado']): ?> <span class="badge bg-warning text-dark">Destacada</span><?php endif; ?>
                    <?php if ($pub['mostrar_en_inicio']): ?> <span class="badge bg-info">Inicio</span><?php endif; ?>
                </td>
                <td class="text-center"></td>
                <td><small class="text-muted"><?= format_datetime($pub['created_at']) ?></small></td>
                <td>
                    <a href="publicaciones.php?tab=propias&editar=<?= $pub['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                    <?php if ($pub['publicado']): ?>
                    <a href="<?= e(rtrim(PUBLIC_URL, '/')) ?>/publicacion.php?slug=<?= e($pub['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Ver"><i class="bi bi-eye"></i></a>
                    <form method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="publicacion_id" value="<?= $pub['id'] ?>">
                        <input type="hidden" name="accion" value="despublicar">
                        <button class="btn btn-sm btn-outline-warning" title="Despublicar" onclick="return confirm('¿Despublicar?')"><i class="bi bi-eye-slash"></i></button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta publicación?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="publicacion_id" value="<?= $pub['id'] ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php endif; // fin mostrar_form ?>

<?php // ══════════════════════════════════════════════════════════
      // TAB: REVISIÓN DE EMPRESAS
      // ══════════════════════════════════════════════════════════
      elseif ($tab === 'revision'): ?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="GET">
            <input type="hidden" name="tab" value="revision">
            <div class="col-md-3">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar título o empresa..." value="<?= e($buscar) ?>">
            </div>
            <div class="col-md-2">
                <select name="estado" class="form-select">
                    <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                    <option value="todos"     <?= $filtro_estado === 'todos'     ? 'selected' : '' ?>>Todos</option>
                    <option value="aprobado"  <?= $filtro_estado === 'aprobado'  ? 'selected' : '' ?>>Aprobados</option>
                    <option value="rechazado" <?= $filtro_estado === 'rechazado' ? 'selected' : '' ?>>Rechazados</option>
                    <option value="borrador"  <?= $filtro_estado === 'borrador'  ? 'selected' : '' ?>>Borradores</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="tipo" class="form-select">
                    <option value="">Todos los tipos</option>
                    <?php foreach (['noticia' => 'Noticia', 'evento' => 'Evento', 'promocion' => 'Promoción', 'comunicado' => 'Comunicado', 'empleados' => 'Empleados'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $filtro_tipo === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search me-1"></i>Buscar</button>
                <a href="publicaciones.php?tab=revision" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($publicaciones_revision)): ?>
<div class="text-center py-5 text-muted">
    <i class="bi bi-clipboard-check display-1"></i>
    <p class="mt-3">No hay publicaciones con los filtros seleccionados</p>
</div>
<?php else: ?>

<p class="text-muted small mb-3">Total: <?= $total_revision ?> publicación<?= $total_revision !== 1 ? 'es' : '' ?></p>

<div class="row g-4">
    <?php
    $tipo_badge   = ['noticia' => 'bg-info', 'evento' => 'bg-primary', 'promocion' => 'bg-warning text-dark', 'comunicado' => 'bg-secondary', 'empleados' => 'bg-success'];
    $estado_badge = ['borrador' => 'bg-secondary', 'pendiente' => 'bg-warning text-dark', 'aprobado' => 'bg-success', 'rechazado' => 'bg-danger'];
    foreach ($publicaciones_revision as $pub):
    ?>
    <div class="col-md-6">
        <div class="card h-100">
            <?php if ($pub['imagen']): ?>
            <img src="<?= e(uploads_resolve_url($pub['imagen'], 'publicaciones')) ?>" class="card-img-top" style="height:170px;object-fit:cover;" alt="">
            <?php endif; ?>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="badge <?= $tipo_badge[$pub['tipo']] ?? 'bg-secondary' ?>"><?= ucfirst($pub['tipo']) ?></span>
                    <span class="badge <?= $estado_badge[$pub['estado']] ?? 'bg-secondary' ?>"><?= ucfirst($pub['estado']) ?></span>
                </div>
                <h5 class="card-title"><?= e($pub['titulo']) ?></h5>
                <p class="text-muted small mb-2"><i class="bi bi-building me-1"></i><?= e($pub['empresa_nombre']) ?></p>
                <?php if ($pub['extracto']): ?>
                <p class="card-text"><?= e(truncate($pub['extracto'], 140)) ?></p>
                <?php elseif ($pub['contenido']): ?>
                <p class="card-text"><?= e(truncate(strip_tags($pub['contenido']), 140)) ?></p>
                <?php endif; ?>
                <small class="text-muted"><?= format_datetime($pub['created_at']) ?></small>
            </div>
            <?php if ($pub['estado'] === 'pendiente'): ?>
            <div class="card-footer bg-white">
                <div class="d-flex gap-2">
                    <form method="POST" class="flex-fill">
                        <?= csrf_field() ?>
                        <input type="hidden" name="publicacion_id" value="<?= $pub['id'] ?>">
                        <input type="hidden" name="accion" value="aprobar">
                        <button class="btn btn-success w-100 btn-sm"><i class="bi bi-check-circle me-1"></i>Aprobar</button>
                    </form>
                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalRechazar<?= $pub['id'] ?>">
                        <i class="bi bi-x-circle me-1"></i>Rechazar
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($pub['estado'] === 'pendiente'): ?>
    <div class="modal fade" id="modalRechazar<?= $pub['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rechazar: <?= e($pub['titulo']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="publicacion_id" value="<?= $pub['id'] ?>">
                    <input type="hidden" name="accion" value="rechazar">
                    <div class="modal-body">
                        <label class="form-label">Motivo del rechazo <span class="text-muted small">(opcional)</span></label>
                        <textarea name="observaciones" class="form-control" rows="3" placeholder="Indicar motivo..."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Confirmar rechazo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

<?= render_pagination($pagination) ?>
<?php endif; ?>
<?php endif; // fin tab revision ?>

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>
