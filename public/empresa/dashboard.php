<?php
/**
 * Dashboard Empresa - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) {
    exit;
}

$page_title = 'Dashboard';
$empresa_nav = 'dashboard';
require_once BASEPATH . '/includes/empresa_layout_header.php';

$empresa_id = $_SESSION['empresa_id'] ?? null;
$db = getDB();

$empresa = ['nombre' => $_SESSION['empresa_nombre'] ?? 'Mi Empresa', 'estado' => 'activa', 'visitas' => 0];
if ($empresa_id) {
    $stmt = $db->prepare('SELECT * FROM empresas WHERE id = ?');
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch() ?: $empresa;
}

$campos_perfil = ['nombre', 'cuit', 'rubro', 'descripcion', 'ubicacion', 'telefono', 'email_contacto', 'contacto_nombre', 'logo'];
$completos = 0;
foreach ($campos_perfil as $c) {
    if (!empty($empresa[$c])) {
        $completos++;
    }
}
$perfil_completo = (int) round(($completos / count($campos_perfil)) * 100);

$stmt = $db->prepare('SELECT COUNT(*) FROM publicaciones WHERE empresa_id = ?');
$stmt->execute([$empresa_id]);
$total_publicaciones = (int) $stmt->fetchColumn();

$periodo_actual = get_periodo_actual();
$stmt = $db->prepare('SELECT estado FROM datos_empresa WHERE empresa_id = ? AND periodo = ?');
$stmt->execute([$empresa_id, $periodo_actual]);
$form_actual = $stmt->fetch();
$formulario_pendiente = !$form_actual || $form_actual['estado'] === 'borrador' || $form_actual['estado'] === 'rechazado';

$stmt = $db->prepare('SELECT * FROM notificaciones WHERE usuario_id = ? AND leida = 0 ORDER BY created_at DESC LIMIT 5');
$stmt->execute([$_SESSION['user_id']]);
$notificaciones = $stmt->fetchAll();

$ultimo_datos = null;
try {
    $st = $db->prepare('
        SELECT periodo, consumo_energia, consumo_agua, consumo_gas, produccion_mensual, unidad_produccion,
               porcentaje_capacidad_uso, estado
        FROM datos_empresa
        WHERE empresa_id = ?
        ORDER BY periodo DESC
        LIMIT 1
    ');
    $st->execute([$empresa_id]);
    $ultimo_datos = $st->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    error_log('dashboard ultimo_datos: ' . $e->getMessage());
}

$timeline = [];
try {
    $st = $db->prepare('
        SELECT accion, created_at
        FROM log_actividad
        WHERE empresa_id = ?
        ORDER BY created_at DESC
        LIMIT 6
    ');
    $st->execute([$empresa_id]);
    $timeline = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('dashboard timeline: ' . $e->getMessage());
}

$fmtDec = static function ($v, int $dec = 1): string {
    if ($v === null || $v === '') {
        return '—';
    }

    return format_number((float) $v, $dec);
};
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <p class="text-muted small mb-1">Panel de administración</p>
        <h2 class="h4 mb-0 fw-semibold text-dark">Bienvenido, <?= e($empresa['nombre']) ?></h2>
    </div>
    <span class="badge badge-estado badge-<?= e($empresa['estado']) ?> align-self-center"><?= ucfirst((string) $empresa['estado']) ?></span>
</div>

<?php show_flash(); ?>

<div class="row g-4 mb-4">
    <div class="col-6 col-xl-3">
        <div class="empresa-stat-tile">
            <div class="val"><?= format_number((int) ($empresa['visitas'] ?? 0)) ?></div>
            <div class="lab"><i class="fa-regular fa-eye me-1 opacity-75"></i>Visitas al perfil</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="empresa-stat-tile">
            <div class="val text-success"><?= (int) $perfil_completo ?>%</div>
            <div class="lab"><i class="fa-regular fa-circle-check me-1 opacity-75"></i>Perfil completo</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="empresa-stat-tile">
            <div class="val"><?= (int) $total_publicaciones ?></div>
            <div class="lab"><i class="fa-solid fa-bullhorn me-1 opacity-75"></i>Publicaciones</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="empresa-stat-tile">
            <div class="val <?= $formulario_pendiente ? 'text-danger' : '' ?>"><?= $formulario_pendiente ? '1' : '0' ?></div>
            <div class="lab"><i class="fa-regular fa-file-lines me-1 opacity-75"></i>Formularios pendientes</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <a href="perfil.php" class="empresa-action-card empresa-action-card--primary">
            <i class="fa-solid fa-pen-to-square"></i>
            <span class="label">Editar perfil</span>
            <span class="hint">Datos comerciales y visibilidad pública</span>
        </a>
    </div>
    <div class="col-md-4">
        <a href="publicaciones.php?nueva=1" class="empresa-action-card empresa-action-card--success">
            <i class="fa-solid fa-plus"></i>
            <span class="label">Nueva publicación</span>
            <span class="hint">Noticias, eventos y comunicados</span>
        </a>
    </div>
    <div class="col-md-4">
        <a href="formularios.php" class="empresa-action-card empresa-action-card--amber">
            <i class="fa-solid fa-file-signature"></i>
            <span class="label">Declaración jurada</span>
            <span class="hint">Consumos, producción y período actual</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card empresa-card-soft mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>Consumos y producción</span>
                <?php if ($ultimo_datos && !empty($ultimo_datos['periodo'])): ?>
                <span class="small text-muted fw-normal">Último registro: <?= e($ultimo_datos['periodo']) ?>
                    <?php if (!empty($ultimo_datos['estado'])): ?> · <?= e(ucfirst((string) $ultimo_datos['estado'])) ?><?php endif; ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$ultimo_datos): ?>
                <p class="text-muted mb-0">Aún no hay datos declarados. Completá el formulario en <a href="formularios.php">Formularios</a> para ver energía, agua, gas y producción.</p>
                <?php else: ?>
                <div class="empresa-kpi-grid mb-3">
                    <div class="empresa-kpi-pill">
                        <div class="k">Energía</div>
                        <div class="v"><?= e($fmtDec($ultimo_datos['consumo_energia'] ?? null, 0)) ?></div>
                        <div class="u">kWh / mes</div>
                    </div>
                    <div class="empresa-kpi-pill">
                        <div class="k">Agua</div>
                        <div class="v"><?= e($fmtDec($ultimo_datos['consumo_agua'] ?? null, 1)) ?></div>
                        <div class="u">m³ / mes</div>
                    </div>
                    <div class="empresa-kpi-pill">
                        <div class="k">Gas</div>
                        <div class="v"><?= e($fmtDec($ultimo_datos['consumo_gas'] ?? null, 1)) ?></div>
                        <div class="u">m³ / mes</div>
                    </div>
                    <div class="empresa-kpi-pill">
                        <div class="k">Uso capacidad</div>
                        <div class="v"><?= $ultimo_datos['porcentaje_capacidad_uso'] !== null && $ultimo_datos['porcentaje_capacidad_uso'] !== '' ? e($fmtDec($ultimo_datos['porcentaje_capacidad_uso'], 1)) . '%' : '—' ?></div>
                        <div class="u">Instalaciones</div>
                    </div>
                </div>
                <div class="rounded-3 p-3" style="background: rgba(26, 82, 118, 0.06);">
                    <div class="small text-uppercase text-muted fw-semibold mb-1">Producción declarada</div>
                    <p class="mb-0 fw-medium text-dark">
                        <?= $ultimo_datos['produccion_mensual'] !== null && trim((string) $ultimo_datos['produccion_mensual']) !== '' ? e($ultimo_datos['produccion_mensual']) : '—' ?>
                        <?php if (!empty($ultimo_datos['unidad_produccion'])): ?>
                        <span class="text-muted fw-normal">(<?= e($ultimo_datos['unidad_produccion']) ?>)</span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card empresa-card-soft">
            <div class="card-header">Actividad reciente</div>
            <div class="card-body">
                <?php if (empty($timeline)): ?>
                <p class="text-muted mb-0">No hay movimientos registrados todavía.</p>
                <?php else: ?>
                <ul class="empresa-timeline">
                    <?php foreach ($timeline as $row):
                        $accion = (string) $row['accion'];
                        if (str_contains($accion, 'login') && !str_contains($accion, 'logout')) {
                            $ic_class = 'ic-login'; $ic_bi = 'bi-box-arrow-in-right';
                        } elseif (str_contains($accion, 'logout') || str_contains($accion, 'cierre')) {
                            $ic_class = 'ic-logout'; $ic_bi = 'bi-box-arrow-right';
                        } elseif (str_contains($accion, 'perfil') || str_contains($accion, 'empresa')) {
                            $ic_class = 'ic-perfil'; $ic_bi = 'bi-person-check';
                        } elseif (str_contains($accion, 'formulario') || str_contains($accion, 'datos')) {
                            $ic_class = 'ic-form'; $ic_bi = 'bi-file-earmark-check';
                        } elseif (str_contains($accion, 'publicacion') || str_contains($accion, 'noticia')) {
                            $ic_class = 'ic-pub'; $ic_bi = 'bi-megaphone';
                        } else {
                            $ic_class = 'ic-default'; $ic_bi = 'bi-activity';
                        }
                    ?>
                    <li>
                        <span class="tl-icon <?= $ic_class ?>"><i class="bi <?= $ic_bi ?>"></i></span>
                        <div class="tl-body">
                            <div class="tl-what"><?= e(empresa_traducir_accion_log($accion)) ?></div>
                            <div class="tl-when"><?= e(format_datetime($row['created_at'])) ?></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card empresa-card-soft mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Notificaciones</span>
                <a href="notificaciones.php" class="small">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($notificaciones)): ?>
                <p class="text-muted text-center py-4 px-3 mb-0">No hay notificaciones nuevas</p>
                <?php else: ?>
                <?php foreach ($notificaciones as $notif): ?>
                <div class="px-3 py-3 border-bottom border-light">
                    <div class="d-flex gap-2">
                        <i class="fa-regular fa-bell text-primary mt-1"></i>
                        <div class="min-w-0">
                            <p class="mb-1 fw-semibold small"><?= e($notif['titulo']) ?></p>
                            <p class="mb-1 small text-secondary"><?= e($notif['mensaje'] ?? '') ?></p>
                            <small class="text-muted"><?= e(format_datetime($notif['created_at'])) ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="card empresa-card-soft">
            <div class="card-header">Completar perfil</div>
            <div class="card-body">
                <div class="progress mb-3 rounded-pill" style="height: 10px;">
                    <div class="progress-bar bg-success" style="width: <?= (int) $perfil_completo ?>%"></div>
                </div>
                <p class="small text-muted mb-3">Tu perfil está al <?= (int) $perfil_completo ?>%. Mejorá los datos para ganar visibilidad en el directorio.</p>
                <a href="perfil.php" class="btn btn-sm btn-primary rounded-pill px-3">Completar ahora</a>
            </div>
        </div>
    </div>
</div>

<?php require_once BASEPATH . '/includes/empresa_layout_footer.php'; ?>
