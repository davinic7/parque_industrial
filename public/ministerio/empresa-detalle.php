<?php
/**
 * Detalle de Empresa - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$db = getDB();
$emp_id = (int)($_GET['id'] ?? 0);

if ($emp_id <= 0) {
    set_flash('error', 'Empresa no encontrada');
    redirect('empresas.php');
}

$stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->execute([$emp_id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    set_flash('error', 'Empresa no encontrada');
    redirect('empresas.php');
}

$empresa['usuario_email'] = null;
$empresa['ultimo_acceso'] = null;
$empresa['usuario_activo'] = null;
if (!empty($empresa['usuario_id'])) {
    try {
        $stmt = $db->prepare("SELECT email, ultimo_acceso, activo FROM usuarios WHERE id = ?");
        $stmt->execute([$empresa['usuario_id']]);
        $u = $stmt->fetch();
        if ($u) {
            $empresa['usuario_email'] = $u['email'];
            $empresa['ultimo_acceso'] = $u['ultimo_acceso'] ?? null;
            $empresa['usuario_activo'] = $u['activo'] ?? null;
        }
    } catch (Exception $e) {
        error_log("empresa-detalle usuario: " . $e->getMessage());
    }
}

$page_title = $empresa['nombre'];

// Últimos formularios
$stmt = $db->prepare("SELECT * FROM datos_empresa WHERE empresa_id = ? ORDER BY periodo DESC LIMIT 8");
$stmt->execute([$emp_id]);
$formularios = $stmt->fetchAll();

// Publicaciones
$stmt = $db->prepare('SELECT id, titulo, tipo, estado, slug, created_at FROM publicaciones WHERE empresa_id = ? ORDER BY created_at DESC LIMIT 5');
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

// Visitas últimos 30 días (si existe la tabla)
$visitas_mes = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM visitas_empresa WHERE empresa_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute([$emp_id]);
    $visitas_mes = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    error_log("empresa-detalle visitas_empresa: " . $e->getMessage());
}

// Perfil completo
$campos_perfil = ['nombre', 'cuit', 'rubro', 'descripcion', 'ubicacion', 'telefono', 'email_contacto', 'contacto_nombre', 'logo'];
$completos = 0;
foreach ($campos_perfil as $c) {
    if (!empty($empresa[$c])) $completos++;
}
$perfil_completo = round(($completos / count($campos_perfil)) * 100);

$ministerio_nav = 'empresas';
$extra_head = ($empresa['latitud'] && $empresa['longitud'])
    ? '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">'
    : '';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
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
                            <thead><tr><th>Período</th><th>Empleados</th><th>Capacidad</th><th>Estado</th><th>Fecha</th><th class="text-end">Panel empresa</th></tr></thead>
                            <tbody>
                                <?php if (empty($formularios)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">Sin formularios enviados</td></tr>
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
                                    <td class="text-end">
                                        <a href="<?= e(rtrim(EMPRESA_URL, '/') . '/formularios.php?id=' . (int) $f['id']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Mismo registro en el panel empresa (sesión como usuario empresa)"><i class="bi bi-box-arrow-up-right"></i></a>
                                    </td>
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
                        $logo_src = '';
                        if (!empty($empresa['logo'])) {
                            $logo_src = uploads_resolve_url($empresa['logo'], 'logos');
                        } else {
                            $logo_src = 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><rect fill="#e9ecef" width="120" height="120"/><text x="60" y="68" font-size="48" fill="#6c757d" text-anchor="middle">🏢</text></svg>');
                        }
                        ?>
                        <img src="<?= $logo_src ?>" alt="Logo" class="img-fluid rounded mb-3" style="max-height: 150px; background: #f8f9fa;">
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
                            <?php if (in_array($pub['estado'], ['borrador', 'rechazado'], true)): ?>
                            <br><a href="<?= e(rtrim(EMPRESA_URL, '/') . '/publicaciones.php?editar=' . (int) $pub['id']) ?>" target="_blank" rel="noopener" class="small">Editar en panel empresa</a>
                            <?php elseif ($pub['estado'] === 'aprobado' && !empty($pub['slug'])): ?>
                            <br><a href="<?= e(rtrim(PUBLIC_URL, '/') . '/publicacion.php?slug=' . rawurlencode($pub['slug'])) ?>" target="_blank" rel="noopener" class="small">Ver publicada</a>
                            <?php endif; ?>
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

<?php
$extra_scripts = '';
if ($empresa['latitud'] && $empresa['longitud']) {
    $pu = htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8');
    $la = (float) $empresa['latitud'];
    $lo = (float) $empresa['longitud'];
    $popupJs = json_encode($empresa['nombre'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
    $extra_scripts = <<<HTML
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
    <script src="{$pu}/js/parque-leaflet.js"></script>
    <script>
        const map = L.map('mapDetalle', { zoomControl: false }).setView([{$la}, {$lo}], 15);
        ParqueLeaflet.addSatelliteLayer(map);
        ParqueLeaflet.freezeMap(map);
        L.marker([{$la}, {$lo}]).addTo(map).bindPopup({$popupJs});
    </script>
HTML;
}
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
