<?php
/**
 * Respuestas de Formulario Dinámico - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$db = getDB();
$page_title = 'Respuestas de formulario';
$form_id = (int)($_GET['id'] ?? 0);
$filtro_empresa = isset($_GET['empresa']) ? (int)$_GET['empresa'] : 0;

if ($form_id <= 0) {
    set_flash('error', 'Formulario no especificado.');
    redirect('formularios-dinamicos.php');
}

// Cargar formulario
$stmt = $db->prepare('SELECT * FROM formularios_dinamicos WHERE id = ?');
$stmt->execute([$form_id]);
$formulario = $stmt->fetch();

if (!$formulario) {
    set_flash('error', 'Formulario no encontrado.');
    redirect('formularios-dinamicos.php');
}

// Cargar preguntas
$stmt = $db->prepare('SELECT * FROM formulario_preguntas WHERE formulario_id = ? ORDER BY orden, id');
$stmt->execute([$form_id]);
$preguntas = $stmt->fetchAll(PDO::FETCH_UNIQUE);

// Cargar respuestas
$sqlResp = "
    SELECT r.*, e.nombre AS empresa_nombre, e.cuit
    FROM formulario_respuestas r
    INNER JOIN empresas e ON r.empresa_id = e.id
    WHERE r.formulario_id = ?
";
$paramsResp = [$form_id];
if ($filtro_empresa > 0) {
    $sqlResp .= " AND r.empresa_id = ?";
    $paramsResp[] = $filtro_empresa;
}
$sqlResp .= " ORDER BY r.created_at DESC";
$stmt = $db->prepare($sqlResp);
$stmt->execute($paramsResp);
$respuestas = $stmt->fetchAll();

$ministerio_nav = 'formularios_dinamicos';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="h4 mb-0 fw-semibold">Respuestas — <?= e($formulario['titulo']) ?></h2>
                <?php if (!empty($formulario['descripcion'])): ?>
                    <p class="text-muted mb-0 small"><?= e($formulario['descripcion']) ?></p>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <a href="formulario-imprimir.php?id=<?= $form_id ?>" target="_blank" class="btn btn-outline-secondary">
                    <i class="bi bi-printer me-1"></i>Imprimir / PDF
                </a>
                <a href="formularios-dinamicos.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Volver
                </a>
            </div>
        </div>

        <div class="table-container mb-4">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>CUIT</th>
                        <th>Estado</th>
                        <th>Enviado</th>
                        <th>IP</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($respuestas)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay respuestas para este formulario.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($respuestas as $r): ?>
                    <tr>
                        <td><strong><?= e($r['empresa_nombre']) ?></strong></td>
                        <td><?= e($r['cuit'] ?? '-') ?></td>
                        <td>
                            <?php
                            $badge_class = ['borrador' => 'bg-secondary', 'enviado' => 'bg-success'];
                            ?>
                            <span class="badge <?= $badge_class[$r['estado']] ?? 'bg-secondary' ?>"><?= ucfirst($r['estado']) ?></span>
                        </td>
                        <td><?= $r['enviado_at'] ? format_datetime($r['enviado_at']) : '-' ?></td>
                        <td><?= e($r['ip'] ?? '-') ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detalle<?= $r['id'] ?>">
                                <i class="bi bi-eye"></i> Ver detalle
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php foreach ($respuestas as $r): 
            $valores = json_decode($r['respuestas'] ?? '{}', true) ?: [];
        ?>
        <div class="modal fade" id="detalle<?= $r['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <?= e($r['empresa_nombre']) ?> 
                            <span class="text-muted small d-block">Respuesta #<?= $r['id'] ?></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <small class="text-muted">
                                Estado: <?= ucfirst($r['estado']) ?> |
                                Enviado: <?= $r['enviado_at'] ? format_datetime($r['enviado_at']) : '-' ?> |
                                IP: <?= e($r['ip'] ?? '-') ?>
                            </small>
                        </div>
                        <div class="row g-3">
                            <?php foreach ($preguntas as $pid => $p):
                                $valor = $valores[$pid] ?? null;
                            ?>
                            <div class="col-12">
                                <div class="border rounded p-2">
                                    <div class="small text-muted mb-1"><?= e($p['etiqueta']) ?></div>
                                    <?php if ($p['tipo'] === 'archivo' && !empty($valor)): ?>
                                        <a href="<?= UPLOADS_URL ?>/formularios/<?= e($valor) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-paperclip me-1"></i><?= e($valor) ?>
                                        </a>
                                    <?php elseif (is_array($valor)): ?>
                                        <strong><?= e(implode(', ', $valor)) ?: '-' ?></strong>
                                    <?php else: ?>
                                        <strong><?= ($valor !== null && $valor !== '') ? e((string)$valor) : '-' ?></strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>

