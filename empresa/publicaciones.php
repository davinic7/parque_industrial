<?php
/**
 * Gestión de Publicaciones - Panel Empresa
 */
require_once __DIR__ . '/../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Publicaciones';
$db = getDB();
$empresa_id = $_SESSION['empresa_id'] ?? null;

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

        if (empty($titulo)) {
            set_flash('error', 'El título es obligatorio');
            redirect('publicaciones.php' . ($id ? "?editar=$id" : '?nueva=1'));
        }

        if (!in_array($tipo, ['noticia', 'evento', 'promocion', 'comunicado'])) {
            $tipo = 'noticia';
        }

        $slug = slugify($titulo);
        $imagen = null;

        if (!empty($_FILES['imagen']['name'])) {
            $resultado = upload_file($_FILES['imagen'], 'publicaciones', ['image/jpeg', 'image/png', 'image/webp'], 2 * 1024 * 1024);
            if ($resultado['success']) {
                $imagen = $resultado['filename'];
            } else {
                set_flash('error', $resultado['error']);
                redirect('publicaciones.php' . ($id ? "?editar=$id" : '?nueva=1'));
            }
        }

        try {
            if ($id > 0) {
                // Verificar que pertenece a esta empresa
                $stmt = $db->prepare("SELECT id, estado FROM publicaciones WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$id, $empresa_id]);
                $existente = $stmt->fetch();

                if (!$existente || $existente['estado'] === 'aprobado') {
                    set_flash('error', 'No se puede editar esta publicación');
                    redirect('publicaciones.php');
                }

                $sql = "UPDATE publicaciones SET titulo = ?, slug = ?, tipo = ?, extracto = ?, contenido = ?, estado = ?";
                $params = [$titulo, $slug, $tipo, $extracto, $contenido, $estado];
                if ($imagen) {
                    $sql .= ", imagen = ?";
                    $params[] = $imagen;
                }
                $sql .= " WHERE id = ? AND empresa_id = ?";
                $params[] = $id;
                $params[] = $empresa_id;
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            } else {
                $sql = "INSERT INTO publicaciones (empresa_id, usuario_id, titulo, slug, tipo, extracto, contenido, imagen, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$empresa_id, $_SESSION['user_id'], $titulo, $slug, $tipo, $extracto, $contenido, $imagen, $estado]);
                $id = $db->lastInsertId();
            }

            log_activity($accion === 'enviar' ? 'publicacion_enviada' : 'publicacion_guardada', 'publicaciones', $empresa_id);

            if ($accion === 'enviar') {
                $nombre_empresa = $_SESSION['empresa_nombre'] ?? 'Empresa';
                $stmt_min = $db->query("SELECT id FROM usuarios WHERE rol IN ('ministerio', 'admin')");
                while ($min = $stmt_min->fetch()) {
                    crear_notificacion($min['id'], 'publicacion_pendiente', 'Publicación para revisar', "$nombre_empresa envió: $titulo", MINISTERIO_URL . '/publicaciones.php');
                }
                set_flash('success', 'Publicación enviada para revisión');
            } else {
                set_flash('success', 'Borrador guardado correctamente');
            }
            redirect('publicaciones.php');
        } catch (Exception $e) {
            error_log("Error publicación: " . $e->getMessage());
            set_flash('error', 'Error al guardar la publicación');
            redirect('publicaciones.php');
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

// Modo edición
$editando = null;
if (isset($_GET['editar'])) {
    $edit_id = (int)$_GET['editar'];
    $stmt = $db->prepare("SELECT * FROM publicaciones WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$edit_id, $empresa_id]);
    $editando = $stmt->fetch();
}
$mostrar_form = isset($_GET['nueva']) || $editando;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - Parque Industrial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= PUBLIC_URL ?>/css/styles.css" rel="stylesheet">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header"><span class="text-white fw-bold">Parque Industrial</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="perfil.php"><i class="bi bi-building"></i> Mi Perfil</a>
            <a href="publicaciones.php" class="active"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <a href="formularios.php"><i class="bi bi-file-earmark-text"></i> Formularios</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio público</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
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
        <div class="card">
            <div class="card-header bg-white"><h5 class="mb-0"><?= $editando ? 'Editar publicación' : 'Nueva publicación' ?></h5></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <?php if ($editando): ?>
                    <input type="hidden" name="publicacion_id" value="<?= $editando['id'] ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Título *</label>
                            <input type="text" name="titulo" class="form-control" required maxlength="255" value="<?= e($editando['titulo'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select">
                                <?php
                                $tipos = ['noticia' => 'Noticia', 'evento' => 'Evento', 'promocion' => 'Promoción', 'comunicado' => 'Comunicado'];
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
                            <label class="form-label">Contenido *</label>
                            <textarea name="contenido" class="form-control" rows="10" required placeholder="Escriba el contenido de su publicación..."><?= e($editando['contenido'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Imagen (JPG, PNG, WebP - máx 2MB)</label>
                            <input type="file" name="imagen" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <?php if (!empty($editando['imagen'])): ?>
                            <small class="text-muted">Imagen actual: <?= e($editando['imagen']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" name="accion" value="guardar" class="btn btn-outline-secondary">
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
                            $tipo_badge = ['noticia' => 'bg-info', 'evento' => 'bg-primary', 'promocion' => 'bg-warning text-dark', 'comunicado' => 'bg-secondary'];
                            ?>
                            <span class="badge <?= $tipo_badge[$pub['tipo']] ?? 'bg-secondary' ?>"><?= ucfirst($pub['tipo']) ?></span>
                        </td>
                        <td>
                            <?php
                            $estado_badge = ['borrador' => 'bg-secondary', 'pendiente' => 'bg-warning text-dark', 'aprobado' => 'bg-success', 'rechazado' => 'bg-danger'];
                            ?>
                            <span class="badge <?= $estado_badge[$pub['estado']] ?? 'bg-secondary' ?>"><?= ucfirst($pub['estado']) ?></span>
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
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
