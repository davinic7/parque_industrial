<?php
/**
 * Formularios Dinamicos - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

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

try {
    $stmt = $db->query("
        SELECT
            f.*,
            (SELECT COUNT(*) FROM formulario_preguntas p WHERE p.formulario_id = f.id) AS total_preguntas,
            (SELECT COUNT(*) FROM formulario_respuestas r WHERE r.formulario_id = f.id AND r.estado = 'enviado') AS total_respuestas,
            (SELECT COUNT(*) FROM formulario_envios fe WHERE fe.formulario_id = f.id) AS total_envios
        FROM formularios_dinamicos f
        ORDER BY f.created_at DESC
    ");
} catch (Exception $e) {
    $stmt = $db->query("
        SELECT
            f.*,
            (SELECT COUNT(*) FROM formulario_preguntas p WHERE p.formulario_id = f.id) AS total_preguntas,
            (SELECT COUNT(*) FROM formulario_respuestas r WHERE r.formulario_id = f.id AND r.estado = 'enviado') AS total_respuestas
        FROM formularios_dinamicos f
        ORDER BY f.created_at DESC
    ");
}
$formularios = $stmt->fetchAll();
foreach ($formularios as &$f) {
    if (!isset($f['total_envios'])) {
        $f['total_envios'] = 0;
    }
}
unset($f);

$ministerio_nav = 'formularios_dinamicos';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
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
                    <tr><td colspan="7" class="text-center text-muted py-4">No hay formularios creados</td></tr>
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
                        <td><?= (int)$f['total_envios'] ?></td>
                        <td><?= format_datetime($f['created_at']) ?></td>
                        <td class="text-nowrap">
                            <a href="formulario-editar.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <a href="formulario-enviar.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-info" title="Enviar a empresas"><i class="bi bi-send"></i></a>
                            <a href="formulario-seguimiento.php?formulario_id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Historial envíos"><i class="bi bi-graph-up-arrow"></i></a>
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

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>
