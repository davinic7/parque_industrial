<?php
/**
 * Solicitudes "Presentar proyecto al ministerio" - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Solicitudes de proyecto';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $id = (int)($_POST['id'] ?? 0);
    $estado = $_POST['estado'] ?? '';
    if ($id > 0 && in_array($estado, ['nueva', 'vista', 'contactada', 'cerrada'])) {
        $obs = trim($_POST['observaciones'] ?? '');
        $db->prepare("UPDATE solicitudes_proyecto SET estado = ?, observaciones = ? WHERE id = ?")->execute([$estado, $obs, $id]);
        set_flash('success', 'Estado actualizado.');
        redirect('solicitudes-proyecto.php');
    }
}

try {
    $solicitudes = $db->query("SELECT * FROM solicitudes_proyecto ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {
    $solicitudes = [];
}
$nuevas = count(array_filter($solicitudes, function($s) { return ($s['estado'] ?? '') === 'nueva'; }));

$ministerio_nav = 'solicitudes';
$ministerio_badge_solicitudes = $nuevas;
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <h2 class="h4 mb-4 fw-semibold"><i class="bi bi-inbox me-2"></i>Solicitudes "Presentar proyecto"</h2>
        <?php show_flash(); ?>

        <?php if (empty($solicitudes)): ?>
        <div class="alert alert-info">No hay solicitudes aún.</div>
        <?php else: ?>
        <div class="list-group">
            <?php foreach ($solicitudes as $s): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                            <h6 class="mb-0"><?= e($s['nombre_empresa']) ?></h6>
                            <?php if (!empty($s['empresa_id'])): ?>
                            <a href="empresa-detalle.php?id=<?= (int)$s['empresa_id'] ?>" class="badge bg-light text-primary border text-decoration-none small">
                                <i class="bi bi-building me-1"></i>Ver empresa
                            </a>
                            <?php endif; ?>
                        </div>
                        <p class="mb-1 small text-muted">
                            <?= e($s['contacto']) ?> · <?= e($s['email']) ?>
                            <?= $s['telefono'] ? ' · ' . e($s['telefono']) : '' ?>
                        </p>
                        <p class="mb-1"><?= nl2br(e(truncate($s['resumen_proyecto'], 200))) ?></p>

                        <?php if (!empty($s['link_externo'])): ?>
                        <a href="<?= e($s['link_externo']) ?>" target="_blank" rel="noopener"
                           class="btn btn-sm btn-outline-primary mb-1">
                            <i class="bi bi-link-45deg me-1"></i>Ver enlace del proyecto
                        </a>
                        <?php endif; ?>

                        <?php if (!empty($s['archivo_url'])): ?>
                        <a href="<?= e($s['archivo_url']) ?>" target="_blank" rel="noopener"
                           class="btn btn-sm btn-outline-secondary mb-1">
                            <i class="bi bi-file-earmark-arrow-down me-1"></i><?= e($s['archivo_nombre'] ?: 'Descargar archivo') ?>
                        </a>
                        <?php endif; ?>

                        <div class="mt-1">
                            <small class="text-muted">
                                <?= format_datetime($s['created_at']) ?>
                                <?php if ($s['solicita_cita']): ?>
                                · <span class="text-info"><i class="bi bi-calendar-check me-1"></i>Solicita reunión presencial</span>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-2">
                        <span class="badge bg-<?= $s['estado'] === 'nueva' ? 'warning' : ($s['estado'] === 'contactada' ? 'info' : ($s['estado'] === 'cerrada' ? 'secondary' : 'primary')) ?> text-uppercase">
                            <?= ucfirst($s['estado']) ?>
                        </span>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <div class="d-flex gap-1 align-items-center">
                                <select name="estado" class="form-select form-select-sm" style="width: auto;">
                                    <option value="nueva"      <?= $s['estado'] === 'nueva'      ? 'selected' : '' ?>>Nueva</option>
                                    <option value="vista"      <?= $s['estado'] === 'vista'      ? 'selected' : '' ?>>Vista</option>
                                    <option value="contactada" <?= $s['estado'] === 'contactada' ? 'selected' : '' ?>>Contactada</option>
                                    <option value="cerrada"    <?= $s['estado'] === 'cerrada'    ? 'selected' : '' ?>>Cerrada</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Actualizar</button>
                            </div>
                            <input type="text" name="observaciones" class="form-control form-control-sm mt-1"
                                   placeholder="Observación (opcional)"
                                   value="<?= e($s['observaciones'] ?? '') ?>">
                        </form>
                    </div>
                </div>
                <?php if ($s['observaciones']): ?>
                <p class="mb-0 mt-2 small p-2 bg-light rounded">
                    <strong>Obs.:</strong> <?= e($s['observaciones']) ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>
