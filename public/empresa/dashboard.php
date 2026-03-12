<?php
/**
 * Dashboard Empresa - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Mi Empresa';
$empresa_id = $_SESSION['empresa_id'] ?? null;
$db = getDB();

$empresa = ['nombre' => $_SESSION['empresa_nombre'] ?? 'Mi Empresa', 'estado' => 'activa', 'visitas' => 0];
if ($empresa_id) {
    $stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch() ?: $empresa;
}

// Calcular % perfil completo
$campos_perfil = ['nombre', 'cuit', 'rubro', 'descripcion', 'ubicacion', 'telefono', 'email_contacto', 'contacto_nombre', 'logo'];
$completos = 0;
foreach ($campos_perfil as $c) {
    if (!empty($empresa[$c])) $completos++;
}
$perfil_completo = round(($completos / count($campos_perfil)) * 100);

$stmt = $db->prepare("SELECT COUNT(*) FROM publicaciones WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$total_publicaciones = $stmt->fetchColumn();

$periodo_actual = get_periodo_actual();
$stmt = $db->prepare("SELECT estado FROM datos_empresa WHERE empresa_id = ? AND periodo = ?");
$stmt->execute([$empresa_id, $periodo_actual]);
$form_actual = $stmt->fetch();
$formulario_pendiente = !$form_actual || $form_actual['estado'] === 'borrador' || $form_actual['estado'] === 'rechazado';

$stmt = $db->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? AND leida = 0 ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$notificaciones = $stmt->fetchAll();

$visitas_semana = [];
$labels_semana = [];
for ($i = 6; $i >= 0; $i--) {
    $fecha = date('Y-m-d', strtotime("-$i days"));
    $labels_semana[] = date('D', strtotime($fecha));
    $stmt = $db->prepare("SELECT COUNT(*) FROM visitas_empresa WHERE empresa_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$empresa_id, $fecha]);
    $visitas_semana[] = $stmt->fetchColumn();
}
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
            <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="perfil.php"><i class="bi bi-building"></i> Mi Perfil</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <a href="formularios.php"><i class="bi bi-file-earmark-text"></i> Formularios</a>
            <a href="notificaciones.php"><i class="bi bi-bell"></i> Notificaciones</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio público</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Bienvenido, <?= e($empresa['nombre']) ?></h1>
                <p class="text-muted mb-0">Panel de administración de tu empresa</p>
            </div>
            <span class="badge badge-estado badge-<?= $empresa['estado'] ?>"><?= ucfirst($empresa['estado']) ?></span>
        </div>

        <?php show_flash(); ?>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between">
                        <div><div class="card-value"><?= format_number($empresa['visitas'] ?? 0) ?></div><div class="card-label">Visitas al perfil</div></div>
                        <i class="bi bi-eye fs-2 text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card success">
                    <div class="d-flex justify-content-between">
                        <div><div class="card-value"><?= $perfil_completo ?>%</div><div class="card-label">Perfil completo</div></div>
                        <i class="bi bi-check-circle fs-2 text-success opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card warning">
                    <div class="d-flex justify-content-between">
                        <div><div class="card-value"><?= $total_publicaciones ?></div><div class="card-label">Publicaciones</div></div>
                        <i class="bi bi-file-post fs-2 text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card <?= $formulario_pendiente ? 'danger' : '' ?>">
                    <div class="d-flex justify-content-between">
                        <div><div class="card-value"><?= $formulario_pendiente ? '1' : '0' ?></div><div class="card-label">Formularios pendientes</div></div>
                        <i class="bi bi-clipboard fs-2 text-danger opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white"><h5 class="mb-0">Acciones rápidas</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4"><a href="perfil.php" class="btn btn-outline-primary w-100 py-3"><i class="bi bi-pencil d-block fs-3 mb-2"></i>Editar perfil</a></div>
                            <div class="col-md-4"><a href="publicaciones.php?new=1" class="btn btn-outline-success w-100 py-3"><i class="bi bi-plus-circle d-block fs-3 mb-2"></i>Nueva publicación</a></div>
                            <div class="col-md-4"><a href="formularios.php" class="btn btn-outline-warning w-100 py-3"><i class="bi bi-file-earmark-text d-block fs-3 mb-2"></i>Completar formulario</a></div>
                        </div>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-header bg-white"><h5 class="mb-0">Visitas últimos 7 días</h5></div>
                    <div class="card-body"><canvas id="chartVisitas" height="150"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-white"><h5 class="mb-0">Notificaciones</h5></div>
                    <div class="card-body p-0">
                        <?php if (empty($notificaciones)): ?>
                        <p class="text-muted text-center py-4">No hay notificaciones nuevas</p>
                        <?php endif; ?>
                        <?php foreach ($notificaciones as $notif): ?>
                        <div class="p-3 border-bottom">
                            <div class="d-flex gap-3">
                                <i class="bi bi-bell text-info fs-4"></i>
                                <div>
                                    <p class="mb-1"><strong><?= e($notif['titulo']) ?></strong></p>
                                    <p class="mb-1 small"><?= e($notif['mensaje'] ?? '') ?></p>
                                    <small class="text-muted"><?= format_datetime($notif['created_at']) ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-header bg-white"><h5 class="mb-0">Completar perfil</h5></div>
                    <div class="card-body">
                        <div class="progress mb-3" style="height: 10px;"><div class="progress-bar bg-success" style="width: <?= $perfil_completo ?>%"></div></div>
                        <p class="small text-muted">Tu perfil está al <?= $perfil_completo ?>%. Complétalo para mayor visibilidad.</p>
                        <a href="perfil.php" class="btn btn-sm btn-primary">Completar ahora</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        new Chart(document.getElementById('chartVisitas'), {
            type: 'line',
            data: {
                labels: <?= json_encode($labels_semana) ?>,
                datasets: [{
                    label: 'Visitas',
                    data: <?= json_encode($visitas_semana) ?>,
                    borderColor: '#1a5276',
                    backgroundColor: 'rgba(26, 82, 118, 0.1)',
                    fill: true, tension: 0.4
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
    </script>
</body>
</html>
