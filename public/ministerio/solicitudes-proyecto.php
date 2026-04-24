<?php
/**
 * Solicitudes "Presentar proyecto" - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Solicitudes de proyecto';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $id     = (int)($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';

    if ($id > 0) {
        if ($accion === 'actualizar') {
            $estado = $_POST['estado'] ?? '';
            if (in_array($estado, ['nueva', 'vista', 'contactada', 'cerrada'])) {
                $db->prepare("UPDATE solicitudes_proyecto SET estado = ?, observaciones = ? WHERE id = ?")
                   ->execute([$estado, trim($_POST['observaciones'] ?? ''), $id]);
                set_flash('success', 'Solicitud actualizada.');
            }
        } elseif ($accion === 'archivar') {
            $db->prepare("UPDATE solicitudes_proyecto SET estado = 'cerrada' WHERE id = ?")->execute([$id]);
            set_flash('success', 'Solicitud archivada.');
        } elseif ($accion === 'marcar_vista') {
            $db->prepare("UPDATE solicitudes_proyecto SET estado = 'vista' WHERE id = ? AND estado = 'nueva'")->execute([$id]);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true]);
                exit;
            }
        }
        $qs = isset($_GET['filtro']) && $_GET['filtro'] !== 'activas' ? '?filtro=' . urlencode($_GET['filtro']) : '';
        redirect('solicitudes-proyecto.php' . $qs);
    }
}

$filtro = $_GET['filtro'] ?? 'activas';
try {
    $solicitudes = match($filtro) {
        'cerradas' => $db->query("SELECT * FROM solicitudes_proyecto WHERE estado = 'cerrada' ORDER BY created_at DESC")->fetchAll(),
        'nuevas'   => $db->query("SELECT * FROM solicitudes_proyecto WHERE estado = 'nueva' ORDER BY created_at DESC")->fetchAll(),
        default    => $db->query("SELECT * FROM solicitudes_proyecto WHERE estado != 'cerrada' ORDER BY created_at DESC")->fetchAll(),
    };
    $filtro = in_array($filtro, ['activas', 'nuevas', 'cerradas']) ? $filtro : 'activas';

    $counts_raw = $db->query("SELECT estado, COUNT(*) n FROM solicitudes_proyecto GROUP BY estado")->fetchAll();
    $counts = [];
    foreach ($counts_raw as $r) $counts[$r['estado']] = (int)$r['n'];
    $cnt_nuevas   = $counts['nueva'] ?? 0;
    $cnt_activas  = array_sum($counts) - ($counts['cerrada'] ?? 0);
    $cnt_cerradas = $counts['cerrada'] ?? 0;
} catch (Exception $e) {
    $solicitudes = [];
    $counts = [];
    $cnt_nuevas = $cnt_activas = $cnt_cerradas = 0;
}

$ministerio_nav = 'solicitudes';
$ministerio_badge_solicitudes = $cnt_nuevas;
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>

<style>
.sol-card { transition: box-shadow .15s; cursor: default; }
.sol-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1) !important; }
.sol-resumen {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.border-l-warning { border-left: 4px solid #ffc107 !important; }
.border-l-info    { border-left: 4px solid #0dcaf0 !important; }
.border-l-primary { border-left: 4px solid #0d6efd !important; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h2 class="h4 mb-0 fw-semibold"><i class="fa-solid fa-inbox me-2"></i>Solicitudes de proyecto</h2>
    <?php if ($cnt_nuevas > 0): ?>
    <span class="badge bg-warning text-dark fs-6 px-3 py-2">
        <i class="fa-solid fa-bell me-1"></i><?= $cnt_nuevas ?> nueva<?= $cnt_nuevas > 1 ? 's' : '' ?>
    </span>
    <?php endif; ?>
</div>

<?php show_flash(); ?>

<!-- Tabs filtro -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $filtro === 'activas' ? 'active fw-semibold' : '' ?>" href="?filtro=activas">
            Activas <span class="badge bg-secondary ms-1"><?= $cnt_activas ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filtro === 'nuevas' ? 'active fw-semibold' : '' ?>" href="?filtro=nuevas">
            Nuevas <?php if ($cnt_nuevas > 0): ?><span class="badge bg-warning text-dark ms-1"><?= $cnt_nuevas ?></span><?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filtro === 'cerradas' ? 'active fw-semibold' : '' ?>" href="?filtro=cerradas">
            Archivadas <span class="badge bg-secondary ms-1"><?= $cnt_cerradas ?></span>
        </a>
    </li>
</ul>

<?php if (empty($solicitudes)): ?>
<div class="text-center py-5 text-muted">
    <i class="fa-solid fa-inbox fa-3x mb-3 opacity-25 d-block"></i>
    <p class="mb-0">No hay solicitudes<?= $filtro === 'cerradas' ? ' archivadas' : ($filtro === 'nuevas' ? ' nuevas' : ' activas') ?>.</p>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
<?php foreach ($solicitudes as $s):
    $estado = $s['estado'] ?? 'nueva';
    [$badge_class, $border_class, $label] = match($estado) {
        'nueva'      => ['bg-warning text-dark', 'border-l-warning', 'Nueva'],
        'vista'      => ['bg-primary',           'border-l-primary', 'Vista'],
        'contactada' => ['bg-info text-dark',    'border-l-info',    'Contactada'],
        'cerrada'    => ['bg-secondary',         '',                 'Archivada'],
        default      => ['bg-secondary',         '',                 ucfirst($estado)],
    };
?>
<div class="card shadow-sm sol-card <?= $border_class ?>" id="sol-<?= $s['id'] ?>">
    <div class="card-body py-3">
        <div class="row g-3 align-items-center">
            <!-- Info principal -->
            <div class="col">
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <span class="badge <?= $badge_class ?> sol-estado-badge" id="badge-<?= $s['id'] ?>"><?= $label ?></span>
                    <?php if ($s['solicita_cita']): ?>
                    <span class="badge bg-light text-dark border small"><i class="fa-solid fa-calendar-check me-1"></i>Solicita cita</span>
                    <?php endif; ?>
                    <strong><?= e($s['nombre_empresa']) ?></strong>
                </div>
                <p class="mb-1 small text-muted">
                    <i class="fa-solid fa-user me-1"></i><?= e($s['contacto']) ?>
                    &nbsp;·&nbsp;<a href="mailto:<?= e($s['email']) ?>" class="text-muted"><?= e($s['email']) ?></a>
                    <?= $s['telefono'] ? ' &nbsp;·&nbsp;<i class="fa-solid fa-phone me-1"></i>' . e($s['telefono']) : '' ?>
                </p>
                <p class="mb-1 small text-secondary sol-resumen"><?= e($s['resumen_proyecto']) ?></p>
                <small class="text-muted"><i class="fa-regular fa-clock me-1"></i><?= format_datetime($s['created_at']) ?></small>
                <?php if ($s['observaciones']): ?>
                <p class="mb-0 mt-1 small text-muted fst-italic">
                    <i class="fa-solid fa-note-sticky me-1"></i><?= e(truncate($s['observaciones'] ?? '', 80)) ?>
                </p>
                <?php endif; ?>
            </div>
            <!-- Acciones -->
            <div class="col-auto d-flex flex-column gap-2 align-items-stretch" style="min-width:130px;">
                <button type="button" class="btn btn-sm btn-primary btn-ver"
                    data-id="<?= $s['id'] ?>"
                    data-nombre="<?= e($s['nombre_empresa']) ?>"
                    data-contacto="<?= e($s['contacto']) ?>"
                    data-email="<?= e($s['email']) ?>"
                    data-telefono="<?= e($s['telefono'] ?? '') ?>"
                    data-resumen="<?= htmlspecialchars($s['resumen_proyecto'], ENT_QUOTES, 'UTF-8') ?>"
                    data-cita="<?= $s['solicita_cita'] ? '1' : '0' ?>"
                    data-estado="<?= e($estado) ?>"
                    data-obs="<?= e($s['observaciones'] ?? '') ?>"
                    data-fecha="<?= e(format_datetime($s['created_at'])) ?>">
                    <i class="fa-solid fa-eye me-1"></i>Ver detalle
                </button>
                <?php if ($estado !== 'cerrada'): ?>
                <form method="POST" class="d-inline m-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <input type="hidden" name="accion" value="archivar">
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100"
                            onclick="return confirm('¿Archivar esta solicitud?')">
                        <i class="fa-solid fa-box-archive me-1"></i>Archivar
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="mNombre"></h5>
                    <small class="text-muted" id="mFecha"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formModal">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="mId">
                <input type="hidden" name="accion" value="actualizar">
                <div class="modal-body">
                    <!-- Contacto -->
                    <div class="row g-2 mb-3 text-sm">
                        <div class="col-sm-4 d-flex align-items-center gap-2 small">
                            <i class="fa-solid fa-user text-primary fa-fw"></i><span id="mContacto"></span>
                        </div>
                        <div class="col-sm-4 d-flex align-items-center gap-2 small">
                            <i class="fa-solid fa-envelope text-primary fa-fw"></i><a id="mEmail" href="#" class="text-truncate"></a>
                        </div>
                        <div class="col-sm-4 d-flex align-items-center gap-2 small" id="mTelWrap">
                            <i class="fa-solid fa-phone text-primary fa-fw"></i><span id="mTel"></span>
                        </div>
                    </div>
                    <div id="mCitaBadge" class="mb-3 d-none">
                        <span class="badge bg-warning text-dark">
                            <i class="fa-solid fa-calendar-check me-1"></i>Solicita cita presencial
                        </span>
                    </div>
                    <!-- Resumen -->
                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body py-3">
                            <p class="text-uppercase text-muted small fw-semibold mb-2">Resumen del proyecto</p>
                            <p class="mb-0" id="mResumen" style="white-space:pre-wrap;"></p>
                        </div>
                    </div>
                    <!-- Estado + Obs -->
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Estado</label>
                            <select name="estado" id="mEstado" class="form-select form-select-sm">
                                <option value="nueva">Nueva</option>
                                <option value="vista">Vista</option>
                                <option value="contactada">Contactada</option>
                                <option value="cerrada">Archivada</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Observaciones internas</label>
                            <textarea name="observaciones" id="mObs" class="form-control form-control-sm" rows="2"
                                      placeholder="Notas del ministerio..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer flex-wrap gap-2">
                    <a id="mBtnEmail" href="#" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-envelope me-1"></i>Enviar email
                    </a>
                    <a id="mBtnEmpresa" href="#" class="btn btn-success btn-sm">
                        <i class="fa-solid fa-building-circle-check me-1"></i>Registrar como empresa
                    </a>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$csrf_name = CSRF_TOKEN_NAME;
$csrf_val  = $_SESSION[CSRF_TOKEN_NAME] ?? '';
$extra_scripts = <<<HTML
<script>
(function () {
    const CSRF_NAME = <?= json_encode($csrf_name) ?>;
    const CSRF_VAL  = <?= json_encode($csrf_val) ?>;

    document.querySelectorAll('.btn-ver').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = this.dataset;

            document.getElementById('mId').value        = d.id;
            document.getElementById('mNombre').textContent = d.nombre;
            document.getElementById('mFecha').textContent  = d.fecha;
            document.getElementById('mContacto').textContent = d.contacto;

            const emailEl = document.getElementById('mEmail');
            emailEl.textContent = d.email;
            emailEl.href = 'mailto:' + d.email;

            const telWrap = document.getElementById('mTelWrap');
            if (d.telefono) {
                document.getElementById('mTel').textContent = d.telefono;
                telWrap.classList.remove('d-none');
            } else {
                telWrap.classList.add('d-none');
            }

            document.getElementById('mCitaBadge').classList.toggle('d-none', d.cita !== '1');
            document.getElementById('mResumen').textContent = d.resumen;
            document.getElementById('mEstado').value = d.estado;
            document.getElementById('mObs').value    = d.obs;

            const params = new URLSearchParams({
                prefill_nombre:   d.nombre,
                prefill_email:    d.email,
                prefill_contacto: d.contacto,
                prefill_telefono: d.telefono || '',
            });
            document.getElementById('mBtnEmpresa').href =
                'nueva-empresa.php?' + params.toString();
            document.getElementById('mBtnEmail').href =
                'mailto:' + d.email + '?subject=Parque Industrial de Catamarca - Su solicitud de proyecto';

            // Mark as vista silently if nueva
            if (d.estado === 'nueva') {
                const fd = new FormData();
                fd.append(CSRF_NAME, CSRF_VAL);
                fd.append('id', d.id);
                fd.append('accion', 'marcar_vista');
                fetch('solicitudes-proyecto.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                }).then(() => {
                    const badge = document.getElementById('badge-' + d.id);
                    if (badge) {
                        badge.className = 'badge bg-primary sol-estado-badge';
                        badge.textContent = 'Vista';
                    }
                    const card = document.getElementById('sol-' + d.id);
                    if (card) card.classList.remove('border-l-warning');
                }).catch(() => {});
            }

            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        });
    });
})();
</script>
HTML;
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
