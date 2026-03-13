<?php
/**
 * Formularios Dinamicos - Ministerio
 */
require_once __DIR__ . '/../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Formularios Dinamicos';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';
    $form_id = (int)($_POST['formulario_id'] ?? 0);

    if ($form_id > 0 && in_array($accion, ['publicar', 'archivar', 'borrador'], true)) {
        $estado = $accion === 'publicar' ? 'publicado' : ($accion === 'archivar' ? 'archivado' : 'borrador');
        $stmt = $db->prepare("UPDATE formularios_dinamicos SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $form_id]);
        log_activity("formulario_dinamico_$estado", 'formularios_dinamicos', $form_id);
        set_flash('success', 'Estado actualizado correctamente.');
        redirect('formularios-dinamicos.php');
    }
}

$stmt = $db->query("
    SELECT 
        f.*,
        (SELECT COUNT(*) FROM formulario_preguntas p WHERE p.formulario_id = f.id) as total_preguntas,
        (SELECT COUNT(*) FROM formulario_respuestas r WHERE r.formulario_id = f.id AND r.estado = 'enviado') as total_respuestas
    FROM formularios_dinamicos f
    ORDER BY f.created_at DESC
");
$formularios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - Ministerio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= PUBLIC_URL ?>/css/styles.css" rel="stylesheet">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header"><span class="text-white fw-bold"><i class="bi bi-building me-2"></i>Ministerio</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="empresas.php"><i class="bi bi-buildings"></i> Empresas</a>
            <a href="nueva-empresa.php"><i class="bi bi-plus-circle"></i> Nueva Empresa</a>
            <a href="formularios.php"><i class="bi bi-file-earmark-text"></i> Formularios</a>
            <a href="formularios-dinamicos.php" class="active"><i class="bi bi-ui-checks"></i> Formularios dinamicos</a>
            <a href="graficos.php"><i class="bi bi-graph-up"></i> Graficos</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesion</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Formularios dinamicos</h1>
            <a href="formulario-nuevo.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Nuevo formulario</a>
        </div>

        <?php show_flash(); ?>

        <div class="table-container">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Titulo</th>
                        <th>Estado</th>
                        <th>Preguntas</th>
                        <th>Respuestas</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($formularios)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay formularios creados</td></tr>
                    <?php endif; ?>
                    <?php foreach ($formularios as $f): ?>
                    <tr>
                        <td>
                            <strong><?= e($f['titulo']) ?></strong>
                            <?php if (!empty($f['descripcion'])): ?>
                                <div class="text-muted small"><?= e(truncate($f['descripcion'], 80)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $badge_class = ['borrador' => 'bg-secondary', 'publicado' => 'bg-success', 'archivado' => 'bg-dark'];
                            ?>
                            <span class="badge <?= $badge_class[$f['estado']] ?? 'bg-secondary' ?>"><?= ucfirst($f['estado']) ?></span>
                        </td>
                        <td><?= (int)$f['total_preguntas'] ?></td>
                        <td><?= (int)$f['total_respuestas'] ?></td>
                        <td><?= format_datetime($f['created_at']) ?></td>
                        <td class="text-nowrap">
                            <a href="formulario-editar.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <a href="formulario-respuestas.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-success" title="Ver respuestas"><i class="bi bi-clipboard-data"></i></a>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="formulario_id" value="<?= $f['id'] ?>">
                                <?php if ($f['estado'] !== 'publicado'): ?>
                                    <button class="btn btn-sm btn-outline-success" name="accion" value="publicar" title="Publicar"><i class="bi bi-check-circle"></i></button>
                                <?php endif; ?>
                                <?php if ($f['estado'] !== 'archivado'): ?>
                                    <button class="btn btn-sm btn-outline-dark" name="accion" value="archivar" title="Archivar"><i class="bi bi-archive"></i></button>
                                <?php endif; ?>
                                <?php if ($f['estado'] !== 'borrador'): ?>
                                    <button class="btn btn-sm btn-outline-secondary" name="accion" value="borrador" title="Pasar a borrador"><i class="bi bi-pencil-square"></i></button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
