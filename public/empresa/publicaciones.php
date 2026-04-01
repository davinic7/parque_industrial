<?php
/**
 * Gestión de Publicaciones - Panel Empresa
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Publicaciones';
$db = getDB();
$empresa_id = $_SESSION['empresa_id'] ?? null;

/** Repoblación del formulario si hubo error de validación (sin redirect). */
$pub_form = null;
$pub_errors = [];

if (!$empresa_id) {
    set_flash('error', 'No se encontró la empresa asociada');
    redirect('dashboard.php');
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar' || $accion === 'enviar') {
        $id = (int)($_POST['publicacion_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $tipo = trim($_POST['tipo'] ?? 'noticia');
        $extracto = trim($_POST['extracto'] ?? '');
        $contenido = trim($_POST['contenido'] ?? '');
        $estado = ($accion === 'enviar') ? 'pendiente' : 'borrador';

        if ($titulo === '') {
            $pub_errors['titulo'] = 'El título es obligatorio';
        }
        if ($accion === 'enviar' && $contenido === '') {
            $pub_errors['contenido'] = 'El contenido es obligatorio para enviar a revisión';
        }
        if (!in_array($tipo, ['noticia', 'evento', 'promocion', 'comunicado', 'empleados'], true)) {
            $tipo = 'noticia';
        }

        $imagen = null;
        $slug = '';

        if (empty($pub_errors)) {
            $slug = slugify($titulo);
            $slug_base = $slug;
            $slug_ok = false;
            for ($i = 0; $i < 50; $i++) {
                $slug_try = $i === 0 ? $slug_base : ($slug_base . '-' . $i);
                if ($id > 0) {
                    $chk = $db->prepare('SELECT 1 FROM publicaciones WHERE slug = ? AND id != ? LIMIT 1');
                    $chk->execute([$slug_try, $id]);
                } else {
                    $chk = $db->prepare('SELECT 1 FROM publicaciones WHERE slug = ? LIMIT 1');
                    $chk->execute([$slug_try]);
                }
                if (!$chk->fetch()) {
                    $slug = $slug_try;
                    $slug_ok = true;
                    break;
                }
            }
            if (!$slug_ok) {
                $slug = $slug_base . '-' . bin2hex(random_bytes(3));
            }

            if (!empty($_FILES['imagen']['name']) && isset($_FILES['imagen']['error']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $resultado = upload_image_storage($_FILES['imagen'], 'publicaciones', ALLOWED_IMAGE_TYPES);
                if ($resultado['success']) {
                    $imagen = $resultado['filename'];
                } else {
                    $pub_errors['imagen'] = $resultado['error'];
                }
            }
        }

        $usuario_id = $_SESSION['user_id'] ?? null;
        if (!$usuario_id && $empresa_id) {
            $stmt = $db->prepare('SELECT usuario_id FROM empresas WHERE id = ?');
            $stmt->execute([$empresa_id]);
            $usuario_id = $stmt->fetchColumn();
        }
        if (empty($pub_errors) && !$usuario_id) {
            $pub_errors['general'] = 'Sesión inválida. Vuelva a iniciar sesión.';
        }

        if (!empty($pub_errors)) {
            $pub_form = [
                'publicacion_id' => $id,
                'titulo' => $titulo,
                'tipo' => $tipo,
                'extracto' => $extracto,
                'contenido' => $contenido,
            ];
        } else {
            try {
                if ($id > 0) {
                    $stmt = $db->prepare('SELECT id, estado FROM publicaciones WHERE id = ? AND empresa_id = ?');
                    $stmt->execute([$id, $empresa_id]);
                    $existente = $stmt->fetch();

                    if (!$existente || $existente['estado'] === 'aprobado') {
                        set_flash('error', 'No se puede editar esta publicación');
                        redirect('publicaciones.php');
                    }

                    $sql = 'UPDATE publicaciones SET titulo = ?, slug = ?, tipo = ?, extracto = ?, contenido = ?, estado = ?';
                    $params = [$titulo, $slug, $tipo, $extracto, $contenido, $estado];
                    if ($imagen) {
                        $sql .= ', imagen = ?';
                        $params[] = $imagen;
                    }
                    $sql .= ' WHERE id = ? AND empresa_id = ?';
                    $params[] = $id;
                    $params[] = $empresa_id;
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                } else {
                    if (!db_column_is_auto_increment($db, 'publicaciones', 'id')) {
                        set_flash('error', 'La base de datos debe corregirse: la tabla publicaciones no tiene la columna id con AUTO_INCREMENT. Ejecutá en MySQL el script database/014_publicaciones_id_autoincrement.sql (comando ALTER del archivo).');
                        redirect('publicaciones.php?nueva=1');
                    }
                    $sql = 'INSERT INTO publicaciones (empresa_id, usuario_id, titulo, slug, tipo, extracto, contenido, imagen, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$empresa_id, $usuario_id, $titulo, $slug, $tipo, $extracto, $contenido, $imagen, $estado]);
                    $id = (int) $db->lastInsertId();
                }

                log_activity($accion === 'enviar' ? 'publicacion_enviada' : 'publicacion_guardada', 'publicaciones', $empresa_id);

                if ($accion === 'enviar') {
                    set_flash('success', 'Publicación enviada para revisión');
                    try {
                        $nombre_empresa = $_SESSION['empresa_nombre'] ?? 'Empresa';
                        $stmt_min = $db->query("SELECT id FROM usuarios WHERE rol IN ('ministerio', 'admin')");
                        while ($min = $stmt_min->fetch()) {
                            crear_notificacion($min['id'], 'publicacion_pendiente', 'Publicación para revisar', "$nombre_empresa envió: $titulo", MINISTERIO_URL . '/publicaciones.php');
                        }
                    } catch (Throwable $e) {
                        error_log('publicaciones.php notificaciones: ' . $e->getMessage());
                    }
                } else {
                    set_flash('success', 'Borrador guardado correctamente');
                }
                redirect('publicaciones.php');
            } catch (Throwable $e) {
                error_log("Error publicación empresa_id=$empresa_id: " . $e->getMessage());
                $msg = 'Error al guardar la publicación. Vuelva a intentar.';
                $detail = $e->getMessage();
                if (strpos($detail, '1364') !== false && stripos($detail, 'id') !== false) {
                    $msg = 'La tabla publicaciones en el servidor no tiene id AUTO_INCREMENT. Ejecutá en MySQL: database/014_publicaciones_id_autoincrement.sql';
                } elseif (function_exists('env_bool') && env_bool('APP_DEBUG', false)) {
                    $msg .= ' (' . $detail . ')';
                }
                $pub_errors['general'] = $msg;
                $pub_form = [
                    'publicacion_id' => $id,
                    'titulo' => $titulo,
                    'tipo' => $tipo,
                    'extracto' => $extracto,
                    'contenido' => $contenido,
                ];
            }
        }
    }

    if ($accion === 'eliminar') {
        $id = (int)($_POST['publicacion_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM publicaciones WHERE id = ? AND empresa_id = ? AND estado IN ('borrador', 'rechazado')");
        $stmt->execute([$id, $empresa_id]);
        if ($stmt->rowCount()) {
            set_flash('success', 'Publicación eliminada');
        }
        redirect('publicaciones.php');
    }
}

// Cargar publicaciones de la empresa
$stmt = $db->prepare("SELECT * FROM publicaciones WHERE empresa_id = ? ORDER BY created_at DESC");
$stmt->execute([$empresa_id]);
$publicaciones = $stmt->fetchAll();

// Modo edición (GET) o repoblación tras error de validación (POST)
$editando = null;
if (isset($_GET['editar'])) {
    $edit_id = (int) $_GET['editar'];
    $stmt = $db->prepare('SELECT * FROM publicaciones WHERE id = ? AND empresa_id = ?');
    $stmt->execute([$edit_id, $empresa_id]);
    $editando = $stmt->fetch();
}

if ($pub_form !== null) {
    $pid = (int) ($pub_form['publicacion_id'] ?? 0);
    if ($pid > 0) {
        $stmt = $db->prepare('SELECT * FROM publicaciones WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$pid, $empresa_id]);
        $base = $stmt->fetch();
        if ($base) {
            $editando = array_merge($base, [
                'titulo' => $pub_form['titulo'],
                'tipo' => $pub_form['tipo'],
                'extracto' => $pub_form['extracto'],
                'contenido' => $pub_form['contenido'],
            ]);
        } else {
            $editando = [
                'id' => 0,
                'titulo' => $pub_form['titulo'],
                'tipo' => $pub_form['tipo'],
                'extracto' => $pub_form['extracto'],
                'contenido' => $pub_form['contenido'],
                'imagen' => null,
            ];
        }
    } else {
        $editando = [
            'id' => 0,
            'titulo' => $pub_form['titulo'],
            'tipo' => $pub_form['tipo'],
            'extracto' => $pub_form['extracto'],
            'contenido' => $pub_form['contenido'],
            'imagen' => null,
        ];
    }
}

$mostrar_form = isset($_GET['nueva']) || $editando || $pub_form !== null;

$empresa_nav = 'publicaciones';
$extra_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">';
require_once BASEPATH . '/includes/empresa_layout_header.php';
?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Mis Publicaciones</h1>
            <?php if (!$mostrar_form): ?>
            <a href="publicaciones.php?nueva=1" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Nueva publicación</a>
            <?php else: ?>
            <a href="publicaciones.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver al listado</a>
            <?php endif; ?>
        </div>

        <?php show_flash(); ?>

        <?php if ($mostrar_form): ?>
        <?php
        $max_img_mb = max(1, (int) ceil(MAX_FILE_SIZE / 1048576));
        ?>
        <div class="card">
            <div class="card-header bg-white"><h5 class="mb-0"><?= !empty($editando['id']) ? 'Editar publicación' : 'Nueva publicación' ?></h5></div>
            <div class="card-body">
                <?php if (!empty($pub_errors['general'])): ?>
                <div class="alert alert-danger"><?= e($pub_errors['general']) ?></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" action="<?= e($_SERVER['REQUEST_URI'] ?? 'publicaciones.php') ?>">
                    <?= csrf_field() ?>
                    <?php if (!empty($editando['id'])): ?>
                    <input type="hidden" name="publicacion_id" value="<?= (int) $editando['id'] ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Título *</label>
                            <input type="text" name="titulo" class="form-control<?= isset($pub_errors['titulo']) ? ' is-invalid' : '' ?>" required maxlength="255" value="<?= e($editando['titulo'] ?? '') ?>">
                            <?php if (isset($pub_errors['titulo'])): ?>
                            <div class="invalid-feedback d-block"><?= e($pub_errors['titulo']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select">
                                <?php
                                $tipos = ['noticia' => 'Noticia', 'evento' => 'Evento', 'promocion' => 'Promoción', 'comunicado' => 'Comunicado', 'empleados' => 'Empleados'];
                                foreach ($tipos as $val => $label):
                                ?>
                                <option value="<?= $val ?>" <?= ($editando['tipo'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Extracto</label>
                            <input type="text" name="extracto" class="form-control" maxlength="500" placeholder="Resumen breve (se muestra en listados)" value="<?= e($editando['extracto'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Contenido <span class="text-muted small fw-normal">(obligatorio al enviar a revisión)</span></label>
                            <textarea name="contenido" class="form-control<?= isset($pub_errors['contenido']) ? ' is-invalid' : '' ?>" rows="10" placeholder="Escriba el contenido de su publicación..."><?= e($editando['contenido'] ?? '') ?></textarea>
                            <?php if (isset($pub_errors['contenido'])): ?>
                            <div class="invalid-feedback d-block"><?= e($pub_errors['contenido']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Imagen (JPG, PNG, GIF, WebP — máx. <?= (int) $max_img_mb ?> MB)</label>
                            <input type="file" name="imagen" class="form-control<?= isset($pub_errors['imagen']) ? ' is-invalid' : '' ?>" accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp">
                            <?php if (isset($pub_errors['imagen'])): ?>
                            <div class="invalid-feedback d-block"><?= e($pub_errors['imagen']) ?></div>
                            <p class="small text-muted mb-0 mt-1">Volvé a elegir el archivo: por seguridad el navegador no guarda la imagen tras un error.</p>
                            <?php endif; ?>
                            <?php if (!empty($editando['imagen'])): ?>
                            <small class="text-muted d-block mt-1">Imagen actual guardada: <?= e($editando['imagen']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" name="accion" value="guardar" class="btn btn-outline-secondary" formnovalidate>
                            <i class="bi bi-save me-1"></i>Guardar borrador
                        </button>
                        <button type="submit" name="accion" value="enviar" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i>Enviar para revisión
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>

        <?php if (empty($publicaciones)): ?>
        <div class="text-center py-5">
            <i class="bi bi-megaphone display-1 text-muted"></i>
            <p class="mt-3 text-muted">Aún no tiene publicaciones. Cree su primera publicación para promocionar su empresa.</p>
            <a href="publicaciones.php?nueva=1" class="btn btn-primary mt-2"><i class="bi bi-plus-circle me-2"></i>Crear publicación</a>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Título</th><th>Tipo</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($publicaciones as $pub): ?>
                    <tr>
                        <td>
                            <strong><?= e($pub['titulo']) ?></strong>
                            <?php if ($pub['extracto']): ?><br><small class="text-muted"><?= e(truncate($pub['extracto'], 80)) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $tipo_badge = ['noticia' => 'bg-info', 'evento' => 'bg-primary', 'promocion' => 'bg-warning text-dark', 'comunicado' => 'bg-secondary', 'empleados' => 'bg-success'];
                            ?>
                            <span class="badge <?= $tipo_badge[$pub['tipo']] ?? 'bg-secondary' ?>"><?= ucfirst($pub['tipo']) ?></span>
                        </td>
                        <td>
                            <?php
                            $estado_badge = ['borrador' => 'bg-secondary', 'pendiente' => 'bg-warning text-dark', 'aprobado' => 'bg-success', 'rechazado' => 'bg-danger'];
                            ?>
                            <span class="badge <?= $estado_badge[$pub['estado']] ?? 'bg-secondary' ?>"><?= ucfirst($pub['estado']) ?></span>
                            <?php if ($pub['estado'] === 'rechazado' && !empty($pub['motivo_rechazo'])): ?>
                                <br><small class="text-danger">Motivo: <?= e(truncate($pub['motivo_rechazo'], 60)) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><small><?= format_datetime($pub['created_at']) ?></small></td>
                        <td>
                            <?php if (in_array($pub['estado'], ['borrador', 'rechazado'])): ?>
                            <a href="publicaciones.php?editar=<?= $pub['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta publicación?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="publicacion_id" value="<?= $pub['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php elseif ($pub['estado'] === 'pendiente'): ?>
                            <span class="text-muted"><small>En revisión</small></span>
                            <?php elseif ($pub['estado'] === 'aprobado'): ?>
                            <a href="<?= PUBLIC_URL ?>/publicacion.php?slug=<?= e($pub['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Ver publicada"><i class="bi bi-eye"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>

<?php require_once BASEPATH . '/includes/empresa_layout_footer.php'; ?>
