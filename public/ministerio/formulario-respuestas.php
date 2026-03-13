<?php
/**
 * Respuestas de Formulario Dinámico - Ministerio
 */
require_once __DIR__ . '/../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$db = getDB();
$page_title = 'Respuestas de formulario';
$form_id = (int)($_GET['id'] ?? 0);

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
$stmt = $db->prepare("
    SELECT r.*, e.nombre AS empresa_nombre, e.cuit
    FROM formulario_respuestas r
    INNER JOIN empresas e ON r.empresa_id = e.id
    WHERE r.formulario_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$form_id]);
$respuestas = $stmt->fetchAll();

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
            <a href="formularios.php"><i class="bi bi-file-earmark-text"></i> Formularios</a>
            <a href="formularios-dinamicos.php" class="active"><i class="bi bi-ui-checks"></i> Formularios dinámicos</a>
            <a href="graficos.php"><i class="bi bi-graph-up"></i> Gráficos</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Respuestas - <?= e($formulario['titulo']) ?></h1>
                <?php if (!empty($formulario['descripcion'])): ?>
                    <p class="text-muted mb-0 small"><?= e($formulario['descripcion']) ?></p>
                <?php endif; ?>
            </div>
            <a href="formularios-dinamicos.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
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
                                if (is_array($valor)) {
                                    $valor_str = implode(', ', $valor);
                                } else {
                                    $valor_str = (string)$valor;
                                }
                            ?>
                            <div class="col-12">
                                <div class="border rounded p-2">
                                    <div class="small text-muted mb-1"><?= e($p['etiqueta']) ?></div>
                                    <div><strong><?= $valor_str !== '' ? e($valor_str) : '-' ?></strong></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

