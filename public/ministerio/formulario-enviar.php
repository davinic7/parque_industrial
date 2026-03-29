<?php
/**
 * Envío selectivo de formulario dinámico a empresas (notificación + seguimiento)
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) {
    exit;
}

$db = getDB();
$formulario_id = (int)($_GET['id'] ?? $_POST['formulario_id'] ?? 0);
if ($formulario_id <= 0) {
    set_flash('error', 'Formulario no especificado.');
    redirect('formularios-dinamicos.php');
}

$stmt = $db->prepare("SELECT * FROM formularios_dinamicos WHERE id = ?");
$stmt->execute([$formulario_id]);
$formulario = $stmt->fetch();
if (!$formulario) {
    set_flash('error', 'Formulario no encontrado.');
    redirect('formularios-dinamicos.php');
}

/**
 * @return array<int, array<string,mixed>>
 */
function ministerio_empresas_para_envio(PDO $db, string $tipo_filtro, array $filtros): array
{
    $where = ['e.usuario_id IS NOT NULL'];
    $params = [];

    switch ($tipo_filtro) {
        case 'todos':
            $where[] = "e.estado = 'activa'";
            break;
        case 'rubro':
            $rubros = $filtros['rubros'] ?? [];
            if (!is_array($rubros) || $rubros === []) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($rubros), '?'));
            $where[] = "e.rubro IN ($placeholders)";
            $params = array_values($rubros);
            break;
        case 'ubicacion':
            $ubs = $filtros['ubicaciones'] ?? [];
            if (!is_array($ubs) || $ubs === []) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($ubs), '?'));
            $where[] = "e.ubicacion IN ($placeholders)";
            $params = array_values($ubs);
            break;
        case 'estado':
            $estados = $filtros['estados'] ?? [];
            if (!is_array($estados) || $estados === []) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($estados), '?'));
            $where[] = "e.estado IN ($placeholders)";
            $params = array_values($estados);
            break;
        case 'empresas_especificas':
            $ids = $filtros['empresa_ids'] ?? [];
            if (!is_array($ids) || $ids === []) {
                return [];
            }
            $ids = array_values(array_filter(array_map('intval', $ids), static fn ($x) => $x > 0));
            if ($ids === []) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "e.id IN ($placeholders)";
            $params = $ids;
            break;
        default:
            return [];
    }

    $sql = 'SELECT e.id, e.nombre, e.rubro, e.ubicacion, e.estado, e.usuario_id, e.email_contacto, u.email AS email_acceso
            FROM empresas e
            INNER JOIN usuarios u ON u.id = e.usuario_id AND u.activo = 1
            WHERE ' . implode(' AND ', $where) . ' ORDER BY e.nombre';

    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

$lista_empresas_todas = $db->query("
    SELECT e.id, e.nombre, e.rubro, e.estado
    FROM empresas e
    INNER JOIN usuarios u ON u.id = e.usuario_id AND u.activo = 1
    ORDER BY e.nombre
")->fetchAll();

$rubros_opts = $db->query("SELECT DISTINCT rubro FROM empresas WHERE rubro IS NOT NULL AND rubro != '' ORDER BY rubro")->fetchAll(PDO::FETCH_COLUMN);
$ubic_opts = $db->query("SELECT DISTINCT ubicacion FROM empresas WHERE ubicacion IS NOT NULL AND ubicacion != '' ORDER BY ubicacion")->fetchAll(PDO::FETCH_COLUMN);

$preview = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $paso = $_POST['paso'] ?? '';

    $tipo_filtro = $_POST['tipo_filtro'] ?? 'todos';
    if (!in_array($tipo_filtro, ['todos', 'rubro', 'ubicacion', 'estado', 'empresas_especificas'], true)) {
        $tipo_filtro = 'todos';
    }

    $filtros = [
        'rubros' => array_values(array_filter((array)($_POST['rubros'] ?? []))),
        'ubicaciones' => array_values(array_filter((array)($_POST['ubicaciones'] ?? []))),
        'estados' => array_values(array_filter((array)($_POST['estados'] ?? []))),
        'empresa_ids' => array_map('intval', (array)($_POST['empresas_ids'] ?? [])),
    ];

    $fecha_limite = trim($_POST['fecha_limite'] ?? '');
    $fecha_limite_sql = $fecha_limite !== '' ? $fecha_limite : null;

    try {
        $empresas_sel = ministerio_empresas_para_envio($db, $tipo_filtro, $filtros);
    } catch (Exception $e) {
        error_log('formulario-enviar: ' . $e->getMessage());
        $empresas_sel = [];
        $error = 'Error al aplicar filtros.';
    }

    if ($paso === 'vista_previa') {
        $preview = $empresas_sel;
    }

    if ($paso === 'confirmar' && $error === '') {
        if ($empresas_sel === []) {
            $error = 'No hay empresas que coincidan con el criterio elegido.';
        } elseif ($formulario['estado'] !== 'publicado') {
            $error = 'El formulario debe estar en estado Publicado para enviarlo.';
        } else {
            try {
                $db->beginTransaction();
                $uid = $_SESSION['user_id'] ?? null;
                $filtros_json = safe_json_encode($filtros);

                $stmt = $db->prepare("
                    INSERT INTO formulario_envios (formulario_id, tipo_filtro, filtros_json, total_destinatarios, fecha_limite, enviado_por)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $formulario_id,
                    $tipo_filtro,
                    $filtros_json,
                    count($empresas_sel),
                    $fecha_limite_sql,
                    $uid,
                ]);
                $envio_id = (int)$db->lastInsertId();

                $insD = $db->prepare("
                    INSERT INTO formulario_destinatarios (envio_id, empresa_id, notificado, fecha_notificacion, plazo_hasta)
                    VALUES (?, ?, 1, NOW(), ?)
                ");

                $url_form = rtrim(EMPRESA_URL, '/') . '/formulario_dinamico.php?id=' . $formulario_id;

                foreach ($empresas_sel as $emp) {
                    $insD->execute([$envio_id, (int)$emp['id'], $fecha_limite_sql]);
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
                log_activity('formulario_envio_masivo', 'formulario_envios', $envio_id);
                set_flash('success', 'Envío registrado a ' . count($empresas_sel) . ' empresa(s).');
                redirect('formulario-seguimiento.php?envio_id=' . $envio_id);
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('formulario-enviar confirm: ' . $e->getMessage());
                $error = 'No se pudo completar el envío. ¿Ejecutó la migración SQL 010 (tablas formulario_envios / formulario_destinatarios)?';
            }
        }
    }
}

$page_title = 'Enviar formulario';
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
</head>
<body>
    <?php
    $ministerio_nav = 'formularios_dinamicos';
    require __DIR__ . '/../../includes/ministerio_sidebar.php';
    ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h1 class="h3 mb-0">Enviar formulario a empresas</h1>
                <p class="text-muted mb-0"><?= e($formulario['titulo']) ?></p>
            </div>
            <a href="formularios-dinamicos.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($formulario['estado'] !== 'publicado'): ?>
        <div class="alert alert-warning">Este formulario no está publicado. Publíquelo antes de enviarlo.</div>
        <?php endif; ?>

        <?php if ($preview !== null): ?>
        <div class="card mb-4">
            <div class="card-header bg-white"><strong>Vista previa</strong> — <?= count($preview) ?> empresa(s)</div>
            <div class="card-body" style="max-height: 320px; overflow-y: auto;">
                <?php if ($preview === []): ?>
                <p class="text-muted mb-0">Ninguna empresa coincide con los filtros.</p>
                <?php else: ?>
                <ul class="list-unstyled mb-0 small">
                    <?php foreach ($preview as $pe): ?>
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
                <input type="hidden" name="formulario_id" value="<?= $formulario_id ?>">

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
                    <button type="submit" name="paso" value="confirmar" class="btn btn-success" <?= $formulario['estado'] !== 'publicado' ? 'disabled' : '' ?>>
                        <i class="bi bi-send me-1"></i>Confirmar y enviar
                    </button>
                </div>
            </div>
        </form>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function syncFiltros() {
            const t = document.getElementById('tipoFiltro').value;
            document.querySelectorAll('.filtro-opt').forEach(el => el.style.display = 'none');
            if (t === 'rubro') document.getElementById('boxRubros').style.display = 'block';
            if (t === 'ubicacion') document.getElementById('boxUbic').style.display = 'block';
            if (t === 'estado') document.getElementById('boxEstado').style.display = 'block';
            if (t === 'empresas_especificas') document.getElementById('boxEmp').style.display = 'block';
        }
        document.getElementById('tipoFiltro').addEventListener('change', syncFiltros);
        syncFiltros();
    </script>
</body>
</html>
