<?php
/**
 * Gestión unificada de un formulario dinámico.
 * Tabs: Respuestas · Envíos y seguimiento · Enviar
 * Reemplaza el acceso fragmentado a formulario-respuestas.php,
 * formulario-seguimiento.php y formulario-enviar.php.
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) {
    exit;
}

$db = getDB();
$form_id = (int)($_GET['id'] ?? 0);
$tab_activo = $_GET['tab'] ?? 'respuestas';
if (!in_array($tab_activo, ['respuestas', 'envios', 'enviar'], true)) {
    $tab_activo = 'respuestas';
}

if ($form_id <= 0) {
    set_flash('error', 'Formulario no especificado.');
    redirect('formularios-dinamicos.php');
}

$stmt = $db->prepare('SELECT * FROM formularios_dinamicos WHERE id = ?');
$stmt->execute([$form_id]);
$formulario = $stmt->fetch();
if (!$formulario) {
    set_flash('error', 'Formulario no encontrado.');
    redirect('formularios-dinamicos.php');
}

// ── POST: seguimiento (recordatorio / extender) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion  = $_POST['accion'] ?? '';
    $fd_id   = (int)($_POST['destinatario_id'] ?? 0);
    $env_id  = (int)($_POST['envio_id'] ?? 0);

    if (in_array($accion, ['recordatorio', 'extender'], true) && $fd_id > 0 && $env_id > 0) {
        $stmt = $db->prepare("
            SELECT fd.*, fe.formulario_id, f.titulo, e.usuario_id, e.nombre AS empresa_nombre
            FROM formulario_destinatarios fd
            INNER JOIN formulario_envios fe ON fe.id = fd.envio_id
            INNER JOIN formularios_dinamicos f ON f.id = fe.formulario_id
            INNER JOIN empresas e ON e.id = fd.empresa_id
            WHERE fd.id = ? AND fd.envio_id = ?
        ");
        $stmt->execute([$fd_id, $env_id]);
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
        redirect('formulario-gestion.php?id=' . $form_id . '&tab=envios&envio_id=' . $env_id);
    }

    // POST: enviar formulario a empresas
    if ($accion === '' || in_array($_POST['paso'] ?? '', ['vista_previa', 'confirmar'], true)) {
        $tab_activo = 'enviar';
    }
}

// ── Datos: respuestas ────────────────────────────────────────────────────────
$stmt = $db->prepare('SELECT * FROM formulario_preguntas WHERE formulario_id = ? ORDER BY orden, id');
$stmt->execute([$form_id]);
$preguntas = $stmt->fetchAll(PDO::FETCH_UNIQUE);

$stmt = $db->prepare("
    SELECT r.*, e.nombre AS empresa_nombre, e.cuit
    FROM formulario_respuestas r
    INNER JOIN empresas e ON r.empresa_id = e.id
    WHERE r.formulario_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$form_id]);
$respuestas = $stmt->fetchAll();

// ── Datos: historial de envíos ───────────────────────────────────────────────
try {
    $stmt = $db->prepare("
        SELECT fe.*, f.titulo
        FROM formulario_envios fe
        INNER JOIN formularios_dinamicos f ON f.id = fe.formulario_id
        WHERE fe.formulario_id = ?
        ORDER BY fe.created_at DESC
    ");
    $stmt->execute([$form_id]);
    $lista_envios = $stmt->fetchAll();
} catch (Throwable $e) {
    $lista_envios = [];
}

// Detalle de un envío específico
$envio_id = (int)($_GET['envio_id'] ?? 0);
$envio = null;
$filas_envio = [];
if ($envio_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT fe.*, f.titulo AS formulario_titulo
            FROM formulario_envios fe
            INNER JOIN formularios_dinamicos f ON f.id = fe.formulario_id
            WHERE fe.id = ?
        ");
        $stmt->execute([$envio_id]);
        $envio = $stmt->fetch();
        if ($envio) {
            $stmt = $db->prepare("
                SELECT
                    fd.id AS fd_id, fd.respondido, fd.fecha_respuesta, fd.fecha_notificacion, fd.plazo_hasta,
                    e.id AS empresa_id, e.nombre, e.rubro,
                    fr.estado AS resp_estado, fr.enviado_at,
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
            $filas_envio = $stmt->fetchAll();
        }
    } catch (Throwable $e) {
        $envio = null;
    }
}

// ── Datos: formulario enviar ─────────────────────────────────────────────────
$lista_empresas_todas = $db->query("
    SELECT e.id, e.nombre, e.rubro, e.estado
    FROM empresas e
    INNER JOIN usuarios u ON u.id = e.usuario_id AND u.activo = 1
    ORDER BY e.nombre
")->fetchAll();

$rubros_opts = $db->query("SELECT DISTINCT rubro FROM empresas WHERE rubro IS NOT NULL AND rubro != '' ORDER BY rubro")->fetchAll(PDO::FETCH_COLUMN);
$ubic_opts   = $db->query("SELECT DISTINCT ubicacion FROM empresas WHERE ubicacion IS NOT NULL AND ubicacion != '' ORDER BY ubicacion")->fetchAll(PDO::FETCH_COLUMN);

$preview_envio = null;
$error_envio   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $paso = $_POST['paso'] ?? '';
    if (in_array($paso, ['vista_previa', 'confirmar'], true)) {
        $tab_activo = 'enviar';

        $tipo_filtro = $_POST['tipo_filtro'] ?? 'todos';
        if (!in_array($tipo_filtro, ['todos', 'rubro', 'ubicacion', 'estado', 'empresas_especificas'], true)) {
            $tipo_filtro = 'todos';
        }
        $filtros = [
            'rubros'      => array_values(array_filter((array)($_POST['rubros'] ?? []))),
            'ubicaciones' => array_values(array_filter((array)($_POST['ubicaciones'] ?? []))),
            'estados'     => array_values(array_filter((array)($_POST['estados'] ?? []))),
            'empresa_ids' => array_map('intval', (array)($_POST['empresas_ids'] ?? [])),
        ];
        $fecha_limite     = trim($_POST['fecha_limite'] ?? '');
        $fecha_limite_sql = $fecha_limite !== '' ? $fecha_limite : null;

        try {
            $empresas_sel = ministerio_empresas_envio($db, $tipo_filtro, $filtros);
        } catch (Exception $e) {
            error_log('formulario-gestion enviar: ' . $e->getMessage());
            $empresas_sel = [];
            $error_envio  = 'Error al aplicar filtros.';
        }

        if ($paso === 'vista_previa') {
            $preview_envio = $empresas_sel;
        }

        if ($paso === 'confirmar' && $error_envio === '') {
            if ($empresas_sel === []) {
                $error_envio = 'No hay empresas que coincidan con el criterio elegido.';
            } elseif ($formulario['estado'] !== 'publicado') {
                $error_envio = 'El formulario debe estar en estado Publicado para enviarlo.';
            } else {
                try {
                    $db->beginTransaction();
                    $uid          = $_SESSION['user_id'] ?? null;
                    $filtros_json = safe_json_encode($filtros);

                    $stmt = $db->prepare("
                        INSERT INTO formulario_envios (formulario_id, tipo_filtro, filtros_json, total_destinatarios, fecha_limite, enviado_por)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$form_id, $tipo_filtro, $filtros_json, count($empresas_sel), $fecha_limite_sql, $uid]);
                    $nuevo_envio_id = (int)$db->lastInsertId();

                    $insD     = $db->prepare("
                        INSERT INTO formulario_destinatarios (envio_id, empresa_id, notificado, fecha_notificacion, plazo_hasta)
                        VALUES (?, ?, 1, NOW(), ?)
                    ");
                    $url_form = rtrim(EMPRESA_URL, '/') . '/formulario_dinamico.php?id=' . $form_id;

                    foreach ($empresas_sel as $emp) {
                        $insD->execute([$nuevo_envio_id, (int)$emp['id'], $fecha_limite_sql]);
                        crear_notificacion(
                            (int)$emp['usuario_id'],
                            'formulario_nuevo',
                            'Nuevo formulario: ' . $formulario['titulo'],
                            'Debe completar el formulario asignado por el ministerio.',
                            $url_form
                        );
                        $mail_to = !empty($emp['email_contacto']) && is_valid_email($emp['email_contacto'])
                            ? $emp['email_contacto']
                            : ($emp['email_acceso'] ?? '');
                        if ($mail_to !== '' && can_send_mail()) {
                            enviar_email_formulario_nuevo($mail_to, $formulario['titulo'], $url_form);
                        }
                    }

                    $db->commit();
                    log_activity('formulario_envio_masivo', 'formulario_envios', $nuevo_envio_id);
                    set_flash('success', 'Envío registrado a ' . count($empresas_sel) . ' empresa(s).');
                    redirect('formulario-gestion.php?id=' . $form_id . '&tab=envios&envio_id=' . $nuevo_envio_id);
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log('formulario-gestion confirmar: ' . $e->getMessage());
                    $error_envio = 'No se pudo completar el envío.';
                }
            }
        }
    }
}

function ministerio_empresas_envio(PDO $db, string $tipo_filtro, array $filtros): array
{
    $where  = ['e.usuario_id IS NOT NULL'];
    $params = [];

    switch ($tipo_filtro) {
        case 'todos':
            $where[] = "e.estado = 'activa'";
            break;
        case 'rubro':
            $rubros = $filtros['rubros'] ?? [];
            if (!is_array($rubros) || $rubros === []) return [];
            $ph      = implode(',', array_fill(0, count($rubros), '?'));
            $where[] = "e.rubro IN ($ph)";
            $params  = array_values($rubros);
            break;
        case 'ubicacion':
            $ubs = $filtros['ubicaciones'] ?? [];
            if (!is_array($ubs) || $ubs === []) return [];
            $ph      = implode(',', array_fill(0, count($ubs), '?'));
            $where[] = "e.ubicacion IN ($ph)";
            $params  = array_values($ubs);
            break;
        case 'estado':
            $estados = $filtros['estados'] ?? [];
            if (!is_array($estados) || $estados === []) return [];
            $ph      = implode(',', array_fill(0, count($estados), '?'));
            $where[] = "e.estado IN ($ph)";
            $params  = array_values($estados);
            break;
        case 'empresas_especificas':
            $ids = array_values(array_filter(array_map('intval', $filtros['empresa_ids'] ?? []), static fn($x) => $x > 0));
            if ($ids === []) return [];
            $ph      = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "e.id IN ($ph)";
            $params  = $ids;
            break;
        default:
            return [];
    }

    $sql = 'SELECT e.id, e.nombre, e.rubro, e.ubicacion, e.estado, e.usuario_id, e.email_contacto,
                   u.email AS email_acceso
            FROM empresas e
            INNER JOIN usuarios u ON u.id = e.usuario_id AND u.activo = 1
            WHERE ' . implode(' AND ', $where) . ' ORDER BY e.nombre';
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

$total_resp  = count($respuestas);
$total_envios = count($lista_envios);

$page_title      = e($formulario['titulo']);
$ministerio_nav  = 'formularios_dinamicos';

$extra_head = '<style>
.nav-tabs .nav-link { font-weight: 500; }
.tab-badge { font-size: .7rem; vertical-align: middle; }
</style>';

require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h2 class="h4 mb-1 fw-semibold"><?= e($formulario['titulo']) ?></h2>
        <?php if (!empty($formulario['descripcion'])): ?>
            <p class="text-muted mb-1 small"><?= e($formulario['descripcion']) ?></p>
        <?php endif; ?>
        <?php
        $badge_estados = ['borrador' => 'bg-secondary', 'publicado' => 'bg-success', 'archivado' => 'bg-dark'];
        ?>
        <span class="badge <?= $badge_estados[$formulario['estado']] ?? 'bg-secondary' ?>"><?= ucfirst($formulario['estado']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="formulario-imprimir.php?id=<?= $form_id ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Imprimir
        </a>
        <a href="formulario-editar.php?id=<?= $form_id ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <a href="formularios-dinamicos.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?php show_flash(); ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <a class="nav-link<?= $tab_activo === 'respuestas' ? ' active' : '' ?>"
           href="formulario-gestion.php?id=<?= $form_id ?>&tab=respuestas">
            <i class="bi bi-clipboard-data me-1"></i>Respuestas
            <?php if ($total_resp > 0): ?>
                <span class="badge bg-success rounded-pill tab-badge"><?= $total_resp ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $tab_activo === 'envios' ? ' active' : '' ?>"
           href="formulario-gestion.php?id=<?= $form_id ?>&tab=envios">
            <i class="bi bi-graph-up-arrow me-1"></i>Envíos y seguimiento
            <?php if ($total_envios > 0): ?>
                <span class="badge bg-secondary rounded-pill tab-badge"><?= $total_envios ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $tab_activo === 'enviar' ? ' active' : '' ?>"
           href="formulario-gestion.php?id=<?= $form_id ?>&tab=enviar">
            <i class="bi bi-send me-1"></i>Enviar a empresas
        </a>
    </li>
</ul>

<?php /* ═══════════════════ TAB: RESPUESTAS ═══════════════════ */ ?>
<?php if ($tab_activo === 'respuestas'): ?>

<div class="table-container mb-4">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Empresa</th>
                <th>CUIT</th>
                <th>Estado</th>
                <th>Enviado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($respuestas)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No hay respuestas para este formulario.</td></tr>
            <?php endif; ?>
            <?php foreach ($respuestas as $r): ?>
            <tr>
                <td><strong><?= e($r['empresa_nombre']) ?></strong></td>
                <td><?= e($r['cuit'] ?? '-') ?></td>
                <td>
                    <?php $bc = ['borrador' => 'bg-secondary', 'enviado' => 'bg-success']; ?>
                    <span class="badge <?= $bc[$r['estado']] ?? 'bg-secondary' ?>"><?= ucfirst($r['estado']) ?></span>
                </td>
                <td><?= $r['enviado_at'] ? format_datetime($r['enviado_at']) : '-' ?></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detalle<?= $r['id'] ?>">
                        <i class="bi bi-eye"></i> Ver
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
                <h5 class="modal-title"><?= e($r['empresa_nombre']) ?> <span class="text-muted small d-block">Respuesta #<?= $r['id'] ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <small class="text-muted d-block mb-3">
                    Estado: <?= ucfirst($r['estado']) ?> ·
                    Enviado: <?= $r['enviado_at'] ? format_datetime($r['enviado_at']) : '-' ?>
                </small>
                <div class="row g-3">
                    <?php foreach ($preguntas as $pid => $p):
                        $valor = $valores[$pid] ?? null;
                    ?>
                    <div class="col-12">
                        <div class="border rounded p-2">
                            <div class="small text-muted mb-1"><?= e($p['etiqueta']) ?></div>
                            <?php if ($p['tipo'] === 'archivo' && !empty($valor)): ?>
                                <a href="<?= UPLOADS_URL ?>/formularios/<?= e($valor) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-paperclip me-1"></i><?= e($valor) ?>
                                </a>
                            <?php elseif (is_array($valor)): ?>
                                <strong><?= e(implode(', ', $valor)) ?: '-' ?></strong>
                            <?php else: ?>
                                <strong><?= ($valor !== null && $valor !== '') ? e((string)$valor) : '-' ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php /* ═══════════════════ TAB: ENVÍOS Y SEGUIMIENTO ═══════════════════ */ ?>
<?php elseif ($tab_activo === 'envios'): ?>

<?php if ($envio_id > 0 && $envio): ?>

    <!-- Detalle de un envío -->
    <?php
    $total_f = count($filas_envio);
    $hechas_f = count(array_filter($filas_envio, static fn($r) => !empty($r['enviado_at']) || (int)$r['respondido'] === 1));
    $pend_f = $total_f - $hechas_f;
    ?>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
        <div>
            <p class="text-muted mb-0">Envío del <?= format_datetime($envio['created_at']) ?>
                <?php if (!empty($envio['fecha_limite'])): ?> · Límite: <?= e($envio['fecha_limite']) ?><?php endif; ?>
            </p>
        </div>
        <a href="formulario-gestion.php?id=<?= $form_id ?>&tab=envios" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Todos los envíos
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 bg-light"><div class="card-body text-center">
                <div class="fs-2 fw-bold text-primary"><?= $total_f ?></div>
                <div class="small text-muted">Destinatarios</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-light"><div class="card-body text-center">
                <div class="fs-2 fw-bold text-success"><?= $hechas_f ?></div>
                <div class="small text-muted">Respondieron</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-light"><div class="card-body text-center">
                <div class="fs-2 fw-bold text-warning"><?= $pend_f ?></div>
                <div class="small text-muted">Pendientes</div>
            </div></div>
        </div>
    </div>

    <div class="table-container">
        <table class="table table-hover table-sm align-middle">
            <thead>
                <tr><th>Empresa</th><th>Rubro</th><th>Estado</th><th>Límite</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($filas_envio as $f):
                    $ok   = !empty($f['enviado_at']) || (int)$f['respondido'] === 1;
                    $lim  = $f['limite'] ?? null;
                    $venc = $lim && !$ok && strtotime($lim . ' 23:59:59') < time();
                    $txt  = $ok ? 'Respondido' : ($venc ? 'Vencido' : 'Pendiente');
                    $bg   = $ok ? 'success' : ($venc ? 'danger' : 'warning');
                ?>
                <tr>
                    <td><?= e($f['nombre']) ?></td>
                    <td><?= e($f['rubro'] ?? '—') ?></td>
                    <td><span class="badge bg-<?= $bg ?>"><?= $txt ?></span></td>
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
                        <a href="formulario-gestion.php?id=<?= $form_id ?>&tab=respuestas" class="btn btn-sm btn-outline-success">Ver</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>

    <!-- Lista de todos los envíos -->
    <div class="table-container">
        <table class="table table-hover">
            <thead>
                <tr><th>Fecha</th><th>Filtro</th><th>Destinatarios</th><th>Límite</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($lista_envios)): ?>
                <tr><td colspan="5" class="text-muted text-center py-4">Sin envíos registrados. Use la pestaña «Enviar a empresas».</td></tr>
                <?php endif; ?>
                <?php foreach ($lista_envios as $le): ?>
                <tr>
                    <td><?= format_datetime($le['created_at']) ?></td>
                    <td><?= e($le['tipo_filtro']) ?></td>
                    <td><?= (int)$le['total_destinatarios'] ?></td>
                    <td><?= $le['fecha_limite'] ? e($le['fecha_limite']) : '—' ?></td>
                    <td>
                        <a class="btn btn-sm btn-primary"
                           href="formulario-gestion.php?id=<?= $form_id ?>&tab=envios&envio_id=<?= (int)$le['id'] ?>">
                            Seguimiento
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<?php /* ═══════════════════ TAB: ENVIAR ═══════════════════ */ ?>
<?php elseif ($tab_activo === 'enviar'): ?>

<?php if ($error_envio): ?>
<div class="alert alert-danger"><?= e($error_envio) ?></div>
<?php endif; ?>

<?php if ($formulario['estado'] !== 'publicado'): ?>
<div class="alert alert-warning">
    Este formulario no está publicado. Publíquelo desde
    <a href="formularios-dinamicos.php">Plantillas</a> antes de enviarlo.
</div>
<?php endif; ?>

<?php if ($preview_envio !== null): ?>
<div class="card mb-4">
    <div class="card-header bg-white"><strong>Vista previa</strong> — <?= count($preview_envio) ?> empresa(s)</div>
    <div class="card-body" style="max-height:320px;overflow-y:auto;">
        <?php if ($preview_envio === []): ?>
        <p class="text-muted mb-0">Ninguna empresa coincide con los filtros.</p>
        <?php else: ?>
        <ul class="list-unstyled mb-0 small">
            <?php foreach ($preview_envio as $pe): ?>
            <li class="mb-1"><strong><?= e($pe['nombre']) ?></strong> — <?= e($pe['rubro'] ?? '-') ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<form method="POST" class="card">
    <div class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="formulario_id" value="<?= $form_id ?>">

        <div class="mb-3">
            <label class="form-label">Destinatarios</label>
            <select name="tipo_filtro" class="form-select" id="tipoFiltro">
                <option value="todos" <?= ($_POST['tipo_filtro'] ?? 'todos') === 'todos' ? 'selected' : '' ?>>Todas las empresas activas</option>
                <option value="rubro" <?= ($_POST['tipo_filtro'] ?? '') === 'rubro' ? 'selected' : '' ?>>Por rubro</option>
                <option value="ubicacion" <?= ($_POST['tipo_filtro'] ?? '') === 'ubicacion' ? 'selected' : '' ?>>Por ubicación</option>
                <option value="estado" <?= ($_POST['tipo_filtro'] ?? '') === 'estado' ? 'selected' : '' ?>>Por estado de empresa</option>
                <option value="empresas_especificas" <?= ($_POST['tipo_filtro'] ?? '') === 'empresas_especificas' ? 'selected' : '' ?>>Empresas específicas</option>
            </select>
        </div>

        <div class="mb-3 filtro-opt" id="boxRubros" style="display:none;">
            <label class="form-label">Rubros</label>
            <select name="rubros[]" class="form-select" multiple size="6">
                <?php foreach ($rubros_opts as $r): ?>
                <option value="<?= e($r) ?>"><?= e($r) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Ctrl+clic para varios</small>
        </div>

        <div class="mb-3 filtro-opt" id="boxUbic" style="display:none;">
            <label class="form-label">Ubicaciones</label>
            <select name="ubicaciones[]" class="form-select" multiple size="5">
                <?php foreach ($ubic_opts as $u): ?>
                <option value="<?= e($u) ?>"><?= e($u) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3 filtro-opt" id="boxEstado" style="display:none;">
            <label class="form-label">Estado empresa</label>
            <?php foreach (['pendiente', 'activa', 'suspendida', 'inactiva'] as $es): ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="estados[]" value="<?= e($es) ?>" id="est_<?= e($es) ?>"
                    <?= $es === 'activa' ? 'checked' : '' ?>>
                <label class="form-check-label" for="est_<?= e($es) ?>"><?= ucfirst($es) ?></label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mb-3 filtro-opt" id="boxEmp" style="display:none;">
            <label class="form-label">Empresas</label>
            <select name="empresas_ids[]" class="form-select" multiple size="10">
                <?php foreach ($lista_empresas_todas as $le): ?>
                <option value="<?= (int)$le['id'] ?>"><?= e($le['nombre']) ?> (<?= e($le['rubro'] ?? '') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-4">
            <label class="form-label">Fecha límite (opcional)</label>
            <input type="date" name="fecha_limite" class="form-control" value="<?= e($_POST['fecha_limite'] ?? '') ?>">
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" name="paso" value="vista_previa" class="btn btn-outline-primary">
                <i class="bi bi-eye me-1"></i>Vista previa
            </button>
            <button type="submit" name="paso" value="confirmar" class="btn btn-success"
                <?= $formulario['estado'] !== 'publicado' ? 'disabled' : '' ?>>
                <i class="bi bi-send me-1"></i>Confirmar y enviar
            </button>
        </div>
    </div>
</form>

<?php endif; ?>

<?php
$extra_scripts = '
<script>
(function() {
    function syncFiltros() {
        const sel = document.getElementById("tipoFiltro");
        if (!sel) return;
        const t = sel.value;
        document.querySelectorAll(".filtro-opt").forEach(el => el.style.display = "none");
        if (t === "rubro")               document.getElementById("boxRubros").style.display = "block";
        if (t === "ubicacion")           document.getElementById("boxUbic").style.display = "block";
        if (t === "estado")              document.getElementById("boxEstado").style.display = "block";
        if (t === "empresas_especificas") document.getElementById("boxEmp").style.display = "block";
    }
    const sel = document.getElementById("tipoFiltro");
    if (sel) { sel.addEventListener("change", syncFiltros); syncFiltros(); }
})();
</script>';
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
?>
