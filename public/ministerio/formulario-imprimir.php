<?php
/**
 * Imprimir / Exportar PDF - Respuestas de Formulario Dinámico
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$db  = getDB();
$form_id    = (int)($_GET['id'] ?? 0);
$empresa_id = isset($_GET['empresa']) ? (int)$_GET['empresa'] : null;

if ($form_id <= 0) { header('Location: formularios-dinamicos.php'); exit; }

$stmt = $db->prepare("SELECT * FROM formularios_dinamicos WHERE id = ?");
$stmt->execute([$form_id]);
$formulario = $stmt->fetch();
if (!$formulario) { header('Location: formularios-dinamicos.php'); exit; }

$stmt = $db->prepare("SELECT * FROM formulario_preguntas WHERE formulario_id = ? ORDER BY orden, id");
$stmt->execute([$form_id]);
$preguntas = $stmt->fetchAll(PDO::FETCH_UNIQUE);

$where_r = "r.formulario_id = ?";
$params_r = [$form_id];
if ($empresa_id) {
    $where_r .= " AND r.empresa_id = ?";
    $params_r[] = $empresa_id;
}

$stmt = $db->prepare("
    SELECT r.*, e.nombre AS empresa_nombre, e.cuit, e.rubro
    FROM formulario_respuestas r
    INNER JOIN empresas e ON r.empresa_id = e.id
    WHERE $where_r AND r.estado = 'enviado'
    ORDER BY e.nombre ASC
");
$stmt->execute($params_r);
$respuestas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir - <?= e($formulario['titulo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; background: #fff; color: #000; }
        .page-header { border-bottom: 2px solid #1a5276; padding-bottom: 10px; margin-bottom: 20px; }
        .page-header h1 { font-size: 1.4rem; margin: 0; }
        .empresa-block { border: 1px solid #ccc; border-radius: 6px; padding: 15px; margin-bottom: 25px; page-break-inside: avoid; }
        .empresa-title { font-size: 1rem; font-weight: bold; color: #1a5276; border-bottom: 1px solid #eee; padding-bottom: 6px; margin-bottom: 12px; }
        .campo-row { margin-bottom: 10px; }
        .campo-label { font-size: 0.8rem; color: #555; margin-bottom: 2px; }
        .campo-valor { font-weight: 600; }
        .no-print { }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .empresa-block { border: 1px solid #999; page-break-inside: avoid; }
            a { color: #000; text-decoration: none; }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <div class="no-print mb-3 d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="bi bi-printer me-1"></i>Imprimir / Guardar PDF
            </button>
            <a href="formulario-gestion.php?id=<?= $form_id ?>&tab=respuestas" class="btn btn-outline-secondary btn-sm">
                ← Volver
            </a>
        </div>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

        <div class="page-header">
            <h1><?= e($formulario['titulo']) ?></h1>
            <?php if (!empty($formulario['descripcion'])): ?>
            <p class="text-muted mb-0"><?= e($formulario['descripcion']) ?></p>
            <?php endif; ?>
            <small class="text-muted">Exportado: <?= date('d/m/Y H:i') ?> — <?= count($respuestas) ?> respuesta(s)</small>
        </div>

        <?php if (empty($respuestas)): ?>
        <p class="text-muted">No hay respuestas enviadas para este formulario.</p>
        <?php endif; ?>

        <?php foreach ($respuestas as $r):
            $valores = json_decode($r['respuestas'] ?? '{}', true) ?: [];
        ?>
        <div class="empresa-block">
            <div class="empresa-title">
                <?= e($r['empresa_nombre']) ?>
                <?php if (!empty($r['cuit'])): ?> — CUIT: <?= e($r['cuit']) ?><?php endif; ?>
                <?php if (!empty($r['rubro'])): ?> — <?= e($r['rubro']) ?><?php endif; ?>
                <span class="float-end text-muted" style="font-size:0.8rem;font-weight:normal;">
                    Enviado: <?= $r['enviado_at'] ? date('d/m/Y H:i', strtotime($r['enviado_at'])) : '-' ?>
                </span>
            </div>
            <div class="row g-2">
                <?php foreach ($preguntas as $pid => $p):
                    $valor = $valores[$pid] ?? null;
                ?>
                <div class="col-md-6 campo-row">
                    <div class="campo-label"><?= e($p['etiqueta']) ?></div>
                    <div class="campo-valor">
                        <?php if ($p['tipo'] === 'archivo' && !empty($valor)): ?>
                            <a href="<?= UPLOADS_URL ?>/formularios/<?= e($valor) ?>" target="_blank">[Archivo adjunto]</a>
                        <?php elseif (is_array($valor)): ?>
                            <?= e(implode(', ', $valor)) ?: '—' ?>
                        <?php else: ?>
                            <?= ($valor !== null && $valor !== '') ? e((string)$valor) : '—' ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
