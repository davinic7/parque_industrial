<?php
/**
 * Formulario de Presentación y Pedido de Lote - Empresa
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Presentación y pedido de lote';
$db = getDB();
$empresa_id = $_SESSION['empresa_id'] ?? null;

if (!$empresa_id) {
    set_flash('error', 'No se encontró la empresa asociada a su cuenta');
    redirect('dashboard.php');
}

$mensaje = '';
$error = '';

// Buscar formulario dinámico por título
$stmt = $db->prepare("SELECT * FROM formularios_dinamicos WHERE titulo = ? AND estado = 'publicado' LIMIT 1");
$stmt->execute(['Presentación y pedido de lote']);
$formulario = $stmt->fetch();

if (!$formulario) {
    // Intentar sin tilde por si el registro se creó así
    $stmt = $db->prepare("SELECT * FROM formularios_dinamicos WHERE titulo LIKE ? AND estado = 'publicado' LIMIT 1");
    $stmt->execute(['Presentacion y pedido de lote%']);
    $formulario = $stmt->fetch();
}

if ($formulario) {
    header('Location: ' . EMPRESA_URL . '/formulario_dinamico.php?id=' . (int)$formulario['id']);
    exit;
} else {
    $error = 'El formulario de presentación aún no fue configurado por el Ministerio.';
}

$empresa_nav = 'formularios';
$extra_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">';
require_once BASEPATH . '/includes/empresa_layout_header.php';
?>
        <h1 class="h3 mb-4">Formulario de presentación y pedido de lote</h1>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

<?php require_once BASEPATH . '/includes/empresa_layout_footer.php'; ?>

