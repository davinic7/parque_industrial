<?php
/**
 * Mensajes recibidos de empresas (destinatario_id IS NULL)
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Mensajes de empresas';
$db = getDB();

$sel_id = (int) ($_GET['id'] ?? 0);

if ($sel_id > 0) {
    $stmt = $db->prepare('
        SELECT m.*, e.nombre AS empresa_nombre, u.email AS remitente_email
        FROM mensajes m
        LEFT JOIN empresas e ON m.empresa_id = e.id
        LEFT JOIN usuarios u ON m.remitente_id = u.id
        WHERE m.id = ? AND m.destinatario_id IS NULL
    ');
    $stmt->execute([$sel_id]);
    $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($detalle) {
        $db->prepare('UPDATE mensajes SET leido = 1, fecha_lectura = NOW() WHERE id = ? AND destinatario_id IS NULL AND leido = 0')
            ->execute([$sel_id]);
    } else {
        $sel_id = 0;
        $detalle = null;
    }
} else {
    $detalle = null;
}

$stmt = $db->query('
    SELECT m.id, m.asunto, m.contenido, m.leido, m.created_at, m.empresa_id,
           e.nombre AS empresa_nombre, u.email AS remitente_email
    FROM mensajes m
    LEFT JOIN empresas e ON m.empresa_id = e.id
    LEFT JOIN usuarios u ON m.remitente_id = u.id
    WHERE m.destinatario_id IS NULL
    ORDER BY m.created_at DESC
    LIMIT 200
');
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

$no_leidos = 0;
foreach ($lista as $row) {
    if (empty($row['leido'])) {
        $no_leidos++;
    }
}

function ministerio_adjuntos_list(?string $json): array {
    if ($json === null || $json === '') {
        return [];
    }
    $d = json_decode($json, true);

    return is_array($d) ? $d : [];
}

$ministerio_nav = 'mensajes_entrada';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <p class="text-muted small mb-4">Mensajes y respuestas enviados por empresas al Ministerio. Los comunicados masivos salientes siguen en «Comunicados a empresas».</p>

        <?php if (empty($lista)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox display-4 d-block mb-3"></i>
            <p>No hay mensajes entrantes de empresas.</p>
        </div>
        <?php else: ?>
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                        <span class="small fw-semibold">Bandeja</span>
                        <?php if ($no_leidos > 0): ?>
                        <span class="badge bg-primary"><?= $no_leidos ?> sin leer</span>
                        <?php endif; ?>
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 70vh; overflow-y: auto;">
                        <?php foreach ($lista as $row): ?>
                        <?php
                        $active = $sel_id === (int) $row['id'];
                        $unread = empty($row['leido']);
                        ?>
                        <a href="mensajes-entrada.php?id=<?= (int) $row['id'] ?>"
                           class="list-group-item list-group-item-action <?= $active ? 'active' : '' ?> <?= $unread && !$active ? 'fw-semibold' : '' ?>">
                            <div class="small text-truncate"><?= e($row['asunto']) ?></div>
                            <div class="small <?= $active ? 'text-white-50' : 'text-muted' ?>" style="font-size: .7rem;">
                                <?= e($row['empresa_nombre'] ?: 'Empresa') ?>
                                · <?= e(format_datetime($row['created_at'])) ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <?php if (!$detalle): ?>
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-muted py-5">
                        <i class="bi bi-envelope-open display-4 mb-3 opacity-50"></i>
                        <p class="mb-0">Seleccioná un mensaje de la lista</p>
                    </div>
                </div>
                <?php else: ?>
                <?php
                $adjs = ministerio_adjuntos_list($detalle['adjuntos'] ?? null);
                $cat = $detalle['categoria'] ?? '';
                ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h2 class="h5 mb-1"><?= e($detalle['asunto']) ?></h2>
                        <div class="small text-muted">
                            <strong>Empresa:</strong> <?= e($detalle['empresa_nombre'] ?: '—') ?>
                            <?php if (!empty($detalle['remitente_email'])): ?>
                            · <strong>Usuario:</strong> <?= e($detalle['remitente_email']) ?>
                            <?php endif; ?>
                            <br>
                            <?= e(format_datetime($detalle['created_at'])) ?>
                            <?php if ($cat !== '' && $cat !== null): ?>
                            · <span class="badge bg-secondary"><?= e($cat) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="text-secondary" style="white-space: pre-wrap;"><?= e($detalle['contenido']) ?></div>
                        <?php if (!empty($adjs)): ?>
                        <hr>
                        <p class="small fw-semibold text-muted mb-2">Adjuntos PDF</p>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($adjs as $fn): ?>
                            <?php if (!is_string($fn) || $fn === '') {
                                continue;
                            } ?>
                            <li class="mb-1">
                                <a href="<?= e(uploads_resolve_url($fn, 'mensajes')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-file-earmark-pdf me-1"></i><?= e(basename($fn)) ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <?php if (!empty($detalle['mensaje_padre_id'])): ?>
                        <p class="small text-muted mt-3 mb-0">
                            <i class="bi bi-reply me-1"></i>Respuesta en hilo (mensaje padre ID <?= (int) $detalle['mensaje_padre_id'] ?>).
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>
