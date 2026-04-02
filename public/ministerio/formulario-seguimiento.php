<?php
/**
 * Seguimiento de un envío de formulario dinámico
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) {
    exit;
}

$db = getDB();
$envio_id = (int)($_GET['envio_id'] ?? 0);
$formulario_id = (int)($_GET['formulario_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';
    $fd_id = (int)($_POST['destinatario_id'] ?? 0);
    $env_post = (int)($_POST['envio_id'] ?? 0);

    if ($fd_id > 0 && $env_post > 0) {
        $stmt = $db->prepare("
            SELECT fd.*, fe.formulario_id, f.titulo, e.usuario_id, e.nombre AS empresa_nombre
            FROM formulario_destinatarios fd
            INNER JOIN formulario_envios fe ON fe.id = fd.envio_id
            INNER JOIN formularios_dinamicos f ON f.id = fe.formulario_id
            INNER JOIN empresas e ON e.id = fd.empresa_id
            WHERE fd.id = ? AND fd.envio_id = ?
        ");
        $stmt->execute([$fd_id, $env_post]);
        $row = $stmt->fetch();

        if ($row) {
            $url_form = rtrim(EMPRESA_URL, '/') . '/formulario_dinamico.php?id=' . (int)$row['formulario_id'];
            if ($accion === 'recordatorio') {
                crear_notificacion(
                    (int)$row['usuario_id'],
                    'formulario_recordatorio',
                    'Recordatorio: ' . $row['titulo'],
                    'Le recordamos completar el formulario pendiente.',
                    $url_form
                );
                set_flash('success', 'Recordatorio enviado a ' . $row['empresa_nombre']);
            }
            if ($accion === 'extender') {
                $db->prepare("
                    UPDATE formulario_destinatarios
                    SET plazo_hasta = DATE_ADD(COALESCE(plazo_hasta, CURDATE()), INTERVAL 7 DAY)
                    WHERE id = ?
                ")->execute([$fd_id]);
                set_flash('success', 'Plazo extendido 7 días para ' . $row['empresa_nombre']);
            }
        }
    }
    redirect('formulario-seguimiento.php?envio_id=' . $env_post);
}

if ($envio_id <= 0 && $formulario_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT fe.*, f.titulo
            FROM formulario_envios fe
            INNER JOIN formularios_dinamicos f ON f.id = fe.formulario_id
            WHERE fe.formulario_id = ?
            ORDER BY fe.created_at DESC
        ");
        $stmt->execute([$formulario_id]);
        $lista_envios = $stmt->fetchAll();
    } catch (Exception $e) {
        $lista_envios = [];
    }

    $stmt = $db->prepare("SELECT titulo FROM formularios_dinamicos WHERE id = ?");
    $stmt->execute([$formulario_id]);
    $ft = $stmt->fetch();
    $page_title = 'Historial de envíos';
    $ministerio_nav = 'formularios_dinamicos';
    require_once BASEPATH . '/includes/ministerio_layout_header.php';
    ?>
        <h2 class="h4 mb-2 fw-semibold">Envíos del formulario</h2>
        <p class="text-muted"><?= e($ft['titulo'] ?? '') ?></p>
        <?php show_flash(); ?>
        <a href="formularios-dinamicos.php" class="btn btn-outline-secondary btn-sm mb-3">Volver</a>
        <div class="table-container">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Filtro</th>
                        <th>Destinatarios</th>
                        <th>Límite</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lista_envios)): ?>
                    <tr><td colspan="5" class="text-muted">Sin envíos registrados. Use «Enviar a empresas».</td></tr>
                    <?php endif; ?>
                    <?php foreach ($lista_envios as $le): ?>
                    <tr>
                        <td><?= format_datetime($le['created_at']) ?></td>
                        <td><?= e($le['tipo_filtro']) ?></td>
                        <td><?= (int)$le['total_destinatarios'] ?></td>
                        <td><?= $le['fecha_limite'] ? e($le['fecha_limite']) : '—' ?></td>
                        <td><a class="btn btn-sm btn-primary" href="formulario-seguimiento.php?envio_id=<?= (int)$le['id'] ?>">Seguimiento</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>
<?php
    exit;
}

if ($envio_id <= 0) {
    set_flash('error', 'Indique un envío o un formulario.');
    redirect('formularios-dinamicos.php');
}

try {
    $stmt = $db->prepare("
        SELECT fe.*, f.titulo AS formulario_titulo
        FROM formulario_envios fe
        INNER JOIN formularios_dinamicos f ON f.id = fe.formulario_id
        WHERE fe.id = ?
    ");
    $stmt->execute([$envio_id]);
    $envio = $stmt->fetch();
} catch (Exception $e) {
    $envio = false;
}

if (!$envio) {
    set_flash('error', 'Envío no encontrado.');
    redirect('formularios-dinamicos.php');
}

$formulario_id = (int)$envio['formulario_id'];

try {
    $stmt = $db->prepare("
        SELECT
            fd.id AS fd_id,
            fd.respondido,
            fd.fecha_respuesta,
            fd.fecha_notificacion,
            fd.plazo_hasta,
            e.id AS empresa_id,
            e.nombre,
            e.rubro,
            fr.estado AS resp_estado,
            fr.enviado_at,
            COALESCE(fd.plazo_hasta, fe.fecha_limite) AS limite
        FROM formulario_destinatarios fd
        INNER JOIN formulario_envios fe ON fe.id = fd.envio_id
        INNER JOIN empresas e ON e.id = fd.empresa_id
        LEFT JOIN formulario_respuestas fr
            ON fr.formulario_id = fe.formulario_id AND fr.empresa_id = fd.empresa_id AND fr.estado = 'enviado'
        WHERE fd.envio_id = ?
        ORDER BY e.nombre
    ");
    $stmt->execute([$envio_id]);
    $filas = $stmt->fetchAll();
} catch (Exception $e) {
    $filas = [];
}

$total = count($filas);
$hechas = count(array_filter($filas, static fn ($r) => !empty($r['enviado_at']) || (int)$r['respondido'] === 1));
$pend = $total - $hechas;

$page_title = 'Seguimiento de envío';
$ministerio_nav = 'formularios_dinamicos';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
            <div>
                <h2 class="h4 mb-1 fw-semibold">Seguimiento</h2>
                <p class="text-muted mb-0"><?= e($envio['formulario_titulo']) ?></p>
                <small class="text-muted">Envío del <?= format_datetime($envio['created_at']) ?>
                    <?php if (!empty($envio['fecha_limite'])): ?> · Límite global: <?= e($envio['fecha_limite']) ?><?php endif; ?>
                </small>
            </div>
            <div class="d-flex gap-2">
                <a href="formulario-seguimiento.php?formulario_id=<?= $formulario_id ?>" class="btn btn-outline-secondary btn-sm">Otros envíos</a>
                <a href="formulario-respuestas.php?id=<?= $formulario_id ?>" class="btn btn-outline-success btn-sm">Ver respuestas</a>
            </div>
        </div>

        <?php show_flash(); ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 bg-light h-100"><div class="card-body text-center">
                    <div class="fs-2 fw-bold text-primary"><?= $total ?></div>
                    <div class="small text-muted">Destinatarios</div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-light h-100"><div class="card-body text-center">
                    <div class="fs-2 fw-bold text-success"><?= $hechas ?></div>
                    <div class="small text-muted">Respondieron</div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-light h-100"><div class="card-body text-center">
                    <div class="fs-2 fw-bold text-warning"><?= $pend ?></div>
                    <div class="small text-muted">Pendientes</div>
                </div></div>
            </div>
        </div>

        <div class="table-container">
            <table class="table table-hover table-sm align-middle">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Rubro</th>
                        <th>Estado</th>
                        <th>Límite</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $f): ?>
                    <?php
                    $ok = !empty($f['enviado_at']) || (int)$f['respondido'] === 1;
                    $lim = $f['limite'] ?? null;
                    $venc = false;
                    if ($lim && !$ok) {
                        $venc = strtotime($lim . ' 23:59:59') < time();
                    }
                    $estado_txt = $ok ? 'Respondido' : ($venc ? 'Vencido' : 'Pendiente');
                    $badge = $ok ? 'success' : ($venc ? 'danger' : 'warning');
                    ?>
                    <tr>
                        <td><?= e($f['nombre']) ?></td>
                        <td><?= e($f['rubro'] ?? '—') ?></td>
                        <td><span class="badge bg-<?= $badge ?>"><?= $estado_txt ?></span></td>
                        <td><?= $lim ? e($lim) : '—' ?></td>
                        <td>
                            <?php if (!$ok): ?>
                            <form method="POST" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="envio_id" value="<?= $envio_id ?>">
                                <input type="hidden" name="destinatario_id" value="<?= (int)$f['fd_id'] ?>">
                                <button type="submit" name="accion" value="recordatorio" class="btn btn-sm btn-outline-primary">Recordatorio</button>
                                <button type="submit" name="accion" value="extender" class="btn btn-sm btn-outline-secondary">+7 días</button>
                            </form>
                            <?php else: ?>
                            <a href="formulario-respuestas.php?id=<?= $formulario_id ?>&empresa=<?= (int)$f['empresa_id'] ?>" class="btn btn-sm btn-outline-success">Ver</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>
