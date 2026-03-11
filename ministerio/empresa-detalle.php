<?php
/**
 * Detalle de Empresa - Ministerio
 */
require_once __DIR__ . '/../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$db = getDB();
$emp_id = (int)($_GET['id'] ?? 0);

if ($emp_id <= 0) {
    set_flash('error', 'Empresa no encontrada');
    redirect('empresas.php');
}

$stmt = $db->prepare("SELECT e.*, u.email as usuario_email, u.ultimo_acceso, u.activo as usuario_activo FROM empresas e LEFT JOIN usuarios u ON e.usuario_id = u.id WHERE e.id = ?");
$stmt->execute([$emp_id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    set_flash('error', 'Empresa no encontrada');
    redirect('empresas.php');
}

$page_title = $empresa['nombre'];

// Últimos formularios
$stmt = $db->prepare("SELECT * FROM datos_empresa WHERE empresa_id = ? ORDER BY periodo DESC LIMIT 8");
$stmt->execute([$emp_id]);
$formularios = $stmt->fetchAll();

// Publicaciones
$stmt = $db->prepare("SELECT id, titulo, tipo, estado, created_at FROM publicaciones WHERE empresa_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$emp_id]);
$publicaciones = $stmt->fetchAll();

// Actividad reciente
$stmt = $db->prepare("
    SELECT la.accion, la.created_at, u.email
    FROM log_actividad la
    LEFT JOIN usuarios u ON la.usuario_id = u.id
    WHERE la.empresa_id = ?
    ORDER BY la.created_at DESC LIMIT 10
");
$stmt->execute([$emp_id]);
$actividad = $stmt->fetchAll();

// Visitas últimos 30 días
$stmt = $db->prepare("SELECT COUNT(*) FROM visitas_empresa WHERE empresa_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute([$emp_id]);
$visitas_mes = $stmt->fetchColumn();

// Perfil completo
$campos_perfil = ['nombre', 'cuit', 'rubro', 'descripcion', 'ubicacion', 'telefono', 'email_contacto', 'contacto_nombre', 'logo'];
$completos = 0;
foreach ($campos_perfil as $c) {
    if (!empty($empresa[$c])) $completos++;
}
$perfil_completo = round(($completos / count($campos_perfil)) * 100);
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header"><span class="text-white fw-bold"><i class="bi bi-building me-2"></i>Ministerio</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="empresas.php" class="active"><i class="bi bi-buildings"></i> Empresas</a>
            <a href="nueva-empresa.php"><i class="bi bi-plus-circle"></i> Nueva Empresa</a>
            <a href="formularios.php"><i class="bi bi-file-earmark-text"></i> Formularios</a>
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
                <a href="empresas.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left me-1"></i>Volver</a>
                <h1 class="h3 mb-0 mt-2"><?= e($empresa['nombre']) ?></h1>
                <?php if ($empresa['razon_social']): ?>
                <p class="text-muted mb-0"><?= e($empresa['razon_social']) ?></p>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <?php
                $badge_estado = ['activa' => 'bg-success', 'pendiente' => 'bg-warning text-dark', 'suspendida' => 'bg-danger', 'inactiva' => 'bg-secondary'];
                ?>
                <span class="badge <?= $badge_estado[$empresa['estado']] ?? 'bg-secondary' ?> fs-6"><?= ucfirst($empresa['estado']) ?></span>
                <a href="empresa-editar.php?id=<?= $emp_id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
            </div>
        </div>

        <?php show_flash(); ?>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <div class="card-value"><?= $perfil_completo ?>%</div>
                    <div class="card-label">Perfil completo</div>
                    <div class="progress mt-2" style="height: 5px;"><div class="progress-bar bg-success" style="width: <?= $perfil_completo ?>%"></div></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <div class="card-value"><?= format_number($empresa['visitas'] ?? 0) ?></div>
                    <div class="card-label">Visitas totales</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <div class="card-value"><?= format_number($visitas_mes) ?></div>
                    <div class="card-label">Visitas/mes</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card text-center">
                    <div class="card-value"><?= count($formularios) ?></div>
                    <div class="card-label">Formularios enviados</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Información general -->
                <div class="card mb-4">
                    <div class="card-header bg-white"><h5 class="mb-0">Información General</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <small class="text-muted">CUIT</small>
                                <p class="mb-2"><?= e($empresa['cuit'] ?: 'No informado') ?></p>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Rubro</small>
                                <p class="mb-2"><?= e($empresa['rubro'] ?: 'No informado') ?></p>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Ubicación</small>
                                <p class="mb-2"><?= e($empresa['ubicacion'] ?: 'No informada') ?></p>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Dirección</small>
                                <p class="mb-2"><?= e($empresa['direccion'] ?: 'No informada') ?></p>
                            </div>
                            <div class="col-12">
                                <small class="text-muted">Descripción</small>
                                <p class="mb-2"><?= e($empresa['descripcion'] ?: 'Sin descripción') ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contacto -->
                <div class="card mb-4">
                    <div class="card-header bg-white"><h5 class="mb-0">Contacto</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <small class="text-muted">Persona de contacto</small>
                                <p class="mb-2"><?= e($empresa['contacto_nombre'] ?: '-') ?></p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Teléfono</small>
                                <p class="mb-2"><?= e($empresa['telefono'] ?: '-') ?></p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Email</small>
                                <p class="mb-2"><?= e($empresa['email_contacto'] ?: '-') ?></p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Sitio web</small>
                                <p class="mb-2"><?= $empresa['sitio_web'] ? '<a href="' . e($empresa['sitio_web']) . '" target="_blank">' . e($empresa['sitio_web']) . '</a>' : '-' ?></p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Usuario del sistema</small>
                                <p class="mb-2"><?= e($empresa['usuario_email'] ?? '-') ?>
                                    <?php if (isset($empresa['usuario_activo'])): ?>
                                    <span class="badge <?= $empresa['usuario_activo'] ? 'bg-success' : 'bg-danger' ?>"><?= $empresa['usuario_activo'] ? 'Activo' : 'Inactivo' ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Último acceso</small>
                                <p class="mb-2"><?= $empresa['ultimo_acceso'] ? format_datetime($empresa['ultimo_acceso']) : 'Nunca' ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formularios -->
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <h5 class="mb-0">Formularios</h5>
                        <a href="formularios.php?buscar=<?= urlencode($empresa['nombre']) ?>" class="btn btn-sm btn-outline-primary">Ver todos</a>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Período</th><th>Empleados</th><th>Capacidad</th><th>Estado</th><th>Fecha</th></tr></thead>
                            <tbody>
                                <?php if (empty($formularios)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">Sin formularios enviados</td></tr>
                                <?php endif; ?>
                                <?php foreach ($formularios as $f): ?>
                                <tr>
                                    <td><strong><?= e($f['periodo']) ?></strong></td>
                                    <td><?= $f['dotacion_total'] ?></td>
                                    <td><?= $f['porcentaje_capacidad_uso'] ? $f['porcentaje_capacidad_uso'] . '%' : '-' ?></td>
                                    <td>
                                        <?php $badge_form = ['borrador' => 'bg-secondary', 'enviado' => 'bg-warning text-dark', 'aprobado' => 'bg-success', 'rechazado' => 'bg-danger']; ?>
                                        <span class="badge <?= $badge_form[$f['estado']] ?? 'bg-secondary' ?>"><?= ucfirst($f['estado']) ?></span>
                                    </td>
                                    <td><?= $f['fecha_declaracion'] ? format_datetime($f['fecha_declaracion']) : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Logo -->
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <?php
                        $logo_src = PUBLIC_URL . '/img/placeholder-logo.png';
                        if (!empty($empresa['logo'])) {
                            $logo_src = UPLOADS_URL . '/logos/' . $empresa['logo'];
                        }
                        ?>
                        <img src="<?= e($logo_src) ?>" alt="Logo" class="img-fluid rounded mb-3" style="max-height: 150px; background: #f8f9fa;">
                        <h5><?= e($empresa['nombre']) ?></h5>
                        <p class="text-muted"><?= e($empresa['rubro'] ?: 'Sin rubro') ?></p>
                        <?php if ($empresa['facebook'] || $empresa['instagram']): ?>
                        <div class="d-flex justify-content-center gap-2">
                            <?php if ($empresa['facebook']): ?>
                            <a href="<?= e($empresa['facebook']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-facebook"></i></a>
                            <?php endif; ?>
                            <?php if ($empresa['instagram']): ?>
                            <a href="<?= e($empresa['instagram']) ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-instagram"></i></a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Mapa -->
                <?php if ($empresa['latitud'] && $empresa['longitud']): ?>
                <div class="card mb-4">
                    <div class="card-header bg-white"><h5 class="mb-0">Ubicación</h5></div>
                    <div class="card-body p-0">
                        <div id="mapDetalle" style="height: 200px;"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Publicaciones -->
                <div class="card mb-4">
                    <div class="card-header bg-white"><h5 class="mb-0">Publicaciones</h5></div>
                    <div class="card-body p-0">
                        <?php if (empty($publicaciones)): ?>
                        <p class="text-muted text-center py-3">Sin publicaciones</p>
                        <?php endif; ?>
                        <?php foreach ($publicaciones as $pub): ?>
                        <div class="p-3 border-bottom">
                            <strong><?= e($pub['titulo']) ?></strong>
                            <br><small class="text-muted"><?= ucfirst($pub['tipo']) ?> | <?= format_datetime($pub['created_at']) ?></small>
                            <span class="badge <?= $pub['estado'] === 'aprobado' ? 'bg-success' : ($pub['estado'] === 'pendiente' ? 'bg-warning text-dark' : 'bg-secondary') ?> float-end"><?= ucfirst($pub['estado']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Actividad -->
                <div class="card">
                    <div class="card-header bg-white"><h5 class="mb-0">Actividad Reciente</h5></div>
                    <div class="card-body p-0">
                        <?php if (empty($actividad)): ?>
                        <p class="text-muted text-center py-3">Sin actividad</p>
                        <?php endif; ?>
                        <?php foreach ($actividad as $act): ?>
                        <div class="p-2 px-3 border-bottom">
                            <small><strong><?= e(str_replace('_', ' ', ucfirst($act['accion']))) ?></strong></small>
                            <br><small class="text-muted"><?= e($act['email'] ?? '') ?> - <?= format_datetime($act['created_at']) ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($empresa['latitud'] && $empresa['longitud']): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const map = L.map('mapDetalle').setView([<?= (float)$empresa['latitud'] ?>, <?= (float)$empresa['longitud'] ?>], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        L.marker([<?= (float)$empresa['latitud'] ?>, <?= (float)$empresa['longitud'] ?>]).addTo(map).bindPopup('<?= e($empresa['nombre']) ?>');
    </script>
    <?php endif; ?>
</body>
</html>
