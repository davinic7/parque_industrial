<?php
/**
 * Dashboard / Espacio de trabajo · Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Espacio de trabajo';
$db = getDB();

// ── Contadores ─────────────────────────────────────────────────
$c = [
    // Empresas
    'empresas_activas'    => 0,
    'empresas_pendientes' => 0,
    'formularios_pend'    => 0,
    'mensajes_sin_leer'   => 0,
    'solicitudes_nuevas'  => 0,
    'total_empleados'     => 0,
    // Página
    'pub_pendientes'      => 0,
    'pub_publicadas'      => 0,
    'comunicados'         => 0,
    'banners'             => 0,
    'visitas_mes'         => 0,
];

$rubros_labels = [];
$rubros_values = [];
$actividad     = [];

try {
    $c['empresas_activas']    = (int) $db->query("SELECT COUNT(*) FROM empresas WHERE estado = 'activa'")->fetchColumn();
    $c['empresas_pendientes'] = (int) $db->query("SELECT COUNT(*) FROM empresas WHERE estado = 'pendiente'")->fetchColumn();
    $c['formularios_pend']    = (int) $db->query("SELECT COUNT(*) FROM datos_empresa WHERE estado = 'enviado'")->fetchColumn();
    $c['mensajes_sin_leer']   = (int) $db->query("SELECT COUNT(*) FROM mensajes WHERE destinatario_id IS NULL AND leido = 0")->fetchColumn();
    $c['solicitudes_nuevas']  = (int) $db->query("SELECT COUNT(*) FROM solicitudes_proyecto WHERE estado = 'nueva'")->fetchColumn();
    $emp_sum = $db->query("
        SELECT COALESCE(SUM(de.dotacion_total),0) FROM datos_empresa de
        INNER JOIN (SELECT empresa_id, MAX(periodo) p FROM datos_empresa WHERE estado IN ('enviado','aprobado') GROUP BY empresa_id) l
        ON de.empresa_id = l.empresa_id AND de.periodo = l.p
    ")->fetchColumn();
    $c['total_empleados'] = $emp_sum > 0 ? (int) $emp_sum : $c['empresas_activas'] * 15;

    $c['pub_pendientes']  = (int) $db->query("SELECT COUNT(*) FROM publicaciones WHERE estado = 'pendiente' AND empresa_id IS NOT NULL")->fetchColumn();
    $c['pub_publicadas']  = (int) $db->query("SELECT COUNT(*) FROM publicaciones WHERE publicado = 1")->fetchColumn();
    $c['comunicados']     = (int) $db->query("SELECT COUNT(*) FROM comunicados WHERE estado = 'enviado'")->fetchColumn();

    $rubros_data   = $db->query("SELECT rubro, COUNT(*) t FROM empresas WHERE rubro IS NOT NULL AND rubro != '' AND estado = 'activa' GROUP BY rubro ORDER BY t DESC LIMIT 8")->fetchAll();
    $rubros_labels = array_column($rubros_data, 'rubro');
    $rubros_values = array_column($rubros_data, 't');

    $actividad = $db->query("
        SELECT la.accion, la.created_at, u.email, e.nombre empresa_nombre
        FROM log_actividad la
        LEFT JOIN usuarios u ON la.usuario_id = u.id
        LEFT JOIN empresas e ON la.empresa_id = e.id
        ORDER BY la.created_at DESC LIMIT 8
    ")->fetchAll();
} catch (Throwable $e) {
    error_log("Dashboard ministerio: " . $e->getMessage());
}

try { $c['banners'] = (int) $db->query("SELECT COUNT(*) FROM banners_home WHERE activo = 1")->fetchColumn(); } catch (Throwable $e) {}
try { $c['visitas_mes'] = (int) $db->query("SELECT COUNT(*) FROM visitas_empresa WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(); } catch (Throwable $e) {}

$ministerio_nav = 'dashboard';
$extra_head = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="text-muted small mb-0">Panel de control</p>
        <h2 class="h4 fw-semibold mb-0">Espacio de trabajo</h2>
    </div>
    <span class="text-muted small d-none d-md-inline">
        <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y') ?>
    </span>
</div>

<?php show_flash(); ?>

<!-- ═══════════════════════════════════════════════════════════════
     WORKSPACE: dos paneles principales
     ═══════════════════════════════════════════════════════════════ -->
<div class="row g-4 mb-4">

    <!-- ── PANEL EMPRESAS ──────────────────────────────────────── -->
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header border-0 py-3" style="background: linear-gradient(135deg,#1a56db 0%,#1e40af 100%);">
                <div class="d-flex align-items-center gap-2 text-white">
                    <i class="fa-solid fa-buildings fs-4"></i>
                    <div>
                        <div class="fw-semibold fs-5">Empresas</div>
                        <small class="opacity-75">Gestión y seguimiento del parque industrial</small>
                    </div>
                </div>
            </div>

            <!-- Mini stats -->
            <div class="row g-0 text-center border-bottom">
                <div class="col border-end py-3">
                    <div class="fw-bold fs-4 text-primary"><?= $c['empresas_activas'] ?></div>
                    <div class="text-muted" style="font-size:.75rem;">Activas</div>
                </div>
                <div class="col border-end py-3">
                    <div class="fw-bold fs-4 <?= $c['empresas_pendientes'] > 0 ? 'text-warning' : 'text-secondary' ?>"><?= $c['empresas_pendientes'] ?></div>
                    <div class="text-muted" style="font-size:.75rem;">Pendientes</div>
                </div>
                <div class="col border-end py-3">
                    <div class="fw-bold fs-4 <?= $c['formularios_pend'] > 0 ? 'text-danger' : 'text-secondary' ?>"><?= $c['formularios_pend'] ?></div>
                    <div class="text-muted" style="font-size:.75rem;">Formularios</div>
                </div>
                <div class="col py-3">
                    <div class="fw-bold fs-4 text-info"><?= format_number($c['total_empleados']) ?></div>
                    <div class="text-muted" style="font-size:.75rem;">Empleados</div>
                </div>
            </div>

            <!-- Acciones rápidas empresas -->
            <div class="card-body">
                <p class="text-muted small fw-semibold text-uppercase mb-3" style="letter-spacing:.05em;">Acciones rápidas</p>
                <div class="row g-2">

                    <div class="col-6">
                        <a href="empresas.php" class="ws-action-btn">
                            <i class="fa-solid fa-buildings text-primary"></i>
                            <span>Ver empresas</span>
                            <small class="text-muted"><?= $c['empresas_activas'] ?> activas</small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="nueva-empresa.php" class="ws-action-btn">
                            <i class="fa-solid fa-user-plus text-success"></i>
                            <span>Nueva empresa</span>
                            <small class="text-muted">Dar de alta</small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="formularios.php" class="ws-action-btn position-relative">
                            <i class="fa-solid fa-file-lines text-warning"></i>
                            <span>Formularios de datos</span>
                            <small class="<?= $c['formularios_pend'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                <?= $c['formularios_pend'] > 0 ? $c['formularios_pend'] . ' por revisar' : 'Sin pendientes' ?>
                            </small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="mensajes-entrada.php" class="ws-action-btn">
                            <i class="fa-solid fa-inbox text-info"></i>
                            <span>Mensajes</span>
                            <small class="<?= $c['mensajes_sin_leer'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                <?= $c['mensajes_sin_leer'] > 0 ? $c['mensajes_sin_leer'] . ' sin leer' : 'Sin nuevos' ?>
                            </small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="solicitudes-proyecto.php" class="ws-action-btn">
                            <i class="fa-solid fa-folder-open text-purple"></i>
                            <span>Solicitudes</span>
                            <small class="<?= $c['solicitudes_nuevas'] > 0 ? 'text-warning fw-semibold' : 'text-muted' ?>">
                                <?= $c['solicitudes_nuevas'] > 0 ? $c['solicitudes_nuevas'] . ' nuevas' : 'Sin nuevas' ?>
                            </small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="formularios-dinamicos.php" class="ws-action-btn">
                            <i class="fa-solid fa-list-check text-secondary"></i>
                            <span>Formularios din.</span>
                            <small class="text-muted">Configurar</small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="graficos.php" class="ws-action-btn">
                            <i class="fa-solid fa-chart-line text-success"></i>
                            <span>Gráficos y datos</span>
                            <small class="text-muted">Estadísticas</small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="exportar.php" class="ws-action-btn">
                            <i class="fa-solid fa-download text-secondary"></i>
                            <span>Exportar</span>
                            <small class="text-muted">CSV / Excel</small>
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- ── PANEL PÁGINA PÚBLICA ────────────────────────────────── -->
    <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header border-0 py-3" style="background: linear-gradient(135deg,#0e9f6e 0%,#057a55 100%);">
                <div class="d-flex align-items-center gap-2 text-white">
                    <i class="fa-solid fa-globe fs-4"></i>
                    <div>
                        <div class="fw-semibold fs-5">Página pública</div>
                        <small class="opacity-75">Contenido visible en el sitio web</small>
                    </div>
                </div>
            </div>

            <!-- Mini stats -->
            <div class="row g-0 text-center border-bottom">
                <div class="col border-end py-3">
                    <div class="fw-bold fs-4 text-success"><?= $c['pub_publicadas'] ?></div>
                    <div class="text-muted" style="font-size:.75rem;">Publicadas</div>
                </div>
                <div class="col border-end py-3">
                    <div class="fw-bold fs-4 <?= $c['pub_pendientes'] > 0 ? 'text-warning' : 'text-secondary' ?>"><?= $c['pub_pendientes'] ?></div>
                    <div class="text-muted" style="font-size:.75rem;">Por revisar</div>
                </div>
                <div class="col border-end py-3">
                    <div class="fw-bold fs-4 text-info"><?= $c['banners'] ?></div>
                    <div class="text-muted" style="font-size:.75rem;">Banners</div>
                </div>
                <div class="col py-3">
                    <div class="fw-bold fs-4 text-primary"><?= format_number($c['visitas_mes']) ?></div>
                    <div class="text-muted" style="font-size:.75rem;">Visitas/mes</div>
                </div>
            </div>

            <!-- Acciones rápidas página -->
            <div class="card-body">
                <p class="text-muted small fw-semibold text-uppercase mb-3" style="letter-spacing:.05em;">Acciones rápidas</p>
                <div class="row g-2">

                    <div class="col-6">
                        <a href="publicaciones.php?tab=propias&nueva=1" class="ws-action-btn">
                            <i class="fa-solid fa-plus-circle text-success"></i>
                            <span>Nueva publicación</span>
                            <small class="text-muted">Crear contenido</small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="publicaciones.php?tab=revision" class="ws-action-btn">
                            <i class="fa-solid fa-clipboard-check text-warning"></i>
                            <span>Revisar empresas</span>
                            <small class="<?= $c['pub_pendientes'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                <?= $c['pub_pendientes'] > 0 ? $c['pub_pendientes'] . ' pendientes' : 'Sin pendientes' ?>
                            </small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="publicaciones.php?tab=propias" class="ws-action-btn">
                            <i class="fa-solid fa-newspaper text-primary"></i>
                            <span>Publicaciones propias</span>
                            <small class="text-muted"><?= $c['pub_publicadas'] ?> publicadas</small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="banners.php" class="ws-action-btn">
                            <i class="fa-solid fa-images text-info"></i>
                            <span>Banners del inicio</span>
                            <small class="text-muted"><?= $c['banners'] ?> activos</small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="nosotros-editar.php" class="ws-action-btn">
                            <i class="fa-solid fa-pen-to-square text-secondary"></i>
                            <span>Página Nosotros</span>
                            <small class="text-muted">Editar contenido</small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="estadisticas-config.php" class="ws-action-btn">
                            <i class="fa-solid fa-chart-column text-success"></i>
                            <span>Estadísticas públicas</span>
                            <small class="text-muted">Cifras del parque</small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="comunicados.php" class="ws-action-btn">
                            <i class="fa-solid fa-paper-plane text-primary"></i>
                            <span>Comunicados</span>
                            <small class="text-muted"><?= $c['comunicados'] > 0 ? $c['comunicados'] . ' enviados' : 'Enviar aviso' ?></small>
                        </a>
                    </div>

                    <div class="col-6">
                        <a href="<?= e(PUBLIC_URL) ?>/" target="_blank" rel="noopener" class="ws-action-btn">
                            <i class="fa-solid fa-arrow-up-right-from-square text-secondary"></i>
                            <span>Ver sitio público</span>
                            <small class="text-muted">Abre en nueva pestaña</small>
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     FILA INFERIOR: gráfico + actividad + mapa
     ═══════════════════════════════════════════════════════════════ -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Empresas por rubro</h6>
                <a href="graficos.php" class="btn btn-sm btn-outline-primary">Ver más</a>
            </div>
            <div class="card-body"><canvas id="chartRubros" height="160"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-semibold">Actividad reciente</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($actividad)): ?>
                    <div class="list-group-item text-center text-muted py-4 small">Sin actividad reciente</div>
                    <?php endif; ?>
                    <?php
                    $iconos = [
                        'login'                => 'bi-box-arrow-in-right text-primary',
                        'logout'               => 'bi-box-arrow-left text-secondary',
                        'perfil_actualizado'   => 'bi-pencil text-warning',
                        'formulario_enviado'   => 'bi-file-earmark-check text-success',
                        'empresa_registrada'   => 'bi-building text-primary',
                        'publicacion_enviada'  => 'bi-send text-info',
                        'publicacion_aprobada' => 'bi-check-circle text-success',
                        'publicacion_rechazada'=> 'bi-x-circle text-danger',
                    ];
                    foreach ($actividad as $act):
                        $icono = $iconos[$act['accion']] ?? 'bi-circle text-muted';
                    ?>
                    <div class="list-group-item py-2">
                        <div class="d-flex gap-2 align-items-start">
                            <i class="bi <?= $icono ?> mt-1"></i>
                            <div style="min-width:0;">
                                <p class="mb-0 small fw-semibold text-truncate"><?= e(str_replace('_', ' ', ucfirst($act['accion']))) ?></p>
                                <small class="text-muted"><?= e($act['empresa_nombre'] ?? $act['email'] ?? '') ?></small>
                            </div>
                            <small class="text-muted ms-auto text-nowrap"><?= format_datetime($act['created_at']) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Mapa del parque</h6>
                <a href="<?= PUBLIC_URL ?>/mapa.php" target="_blank" class="btn btn-sm btn-outline-secondary">Ampliar</a>
            </div>
            <div class="card-body p-0"><div id="miniMap" style="height:180px;"></div></div>
        </div>
    </div>
</div>

<style>
/* Botones de acción del workspace */
.ws-action-btn {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    padding: .65rem .85rem;
    border-radius: .5rem;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    text-decoration: none;
    color: inherit;
    transition: background .15s, border-color .15s, box-shadow .15s;
    width: 100%;
}
.ws-action-btn:hover {
    background: #eff6ff;
    border-color: #93c5fd;
    box-shadow: 0 2px 8px rgba(59,130,246,.1);
    color: inherit;
}
.ws-action-btn i { font-size: 1.15rem; }
.ws-action-btn span { font-size: .85rem; font-weight: 600; line-height: 1.2; }
.ws-action-btn small { font-size: .72rem; }
</style>

<?php
$pu         = htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8');
$labelsJson = safe_json_encode($rubros_labels);
$dataJson   = json_encode($rubros_values);
$lat        = (float) MAP_DEFAULT_LAT;
$lng        = (float) MAP_DEFAULT_LNG;
$extra_scripts = <<<HTML
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
    <script src="{$pu}/js/parque-leaflet.js"></script>
    <script>
        new Chart(document.getElementById('chartRubros'), {
            type: 'bar',
            data: {
                labels: {$labelsJson},
                datasets: [{ label: 'Empresas', data: {$dataJson},
                    backgroundColor: ['#3b82f6','#ef4444','#94a3b8','#22c55e','#f59e0b','#8b5cf6','#f97316','#06b6d4'] }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
        const map = L.map('miniMap', { zoomControl: false }).setView([{$lat}, {$lng}], 13);
        ParqueLeaflet.addSatelliteLayer(map);
        ParqueLeaflet.freezeMap(map);
        L.marker([{$lat}, {$lng}]).addTo(map).bindPopup('Parque Industrial');
    </script>
HTML;
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
?>
