<?php
/**
 * Formularios de Empresa - Declaración jurada (datos_empresa)
 * Soporta: historial con acciones, modo consulta, ?id= para deep links, confirmaciones.
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Formularios';
$error = '';
$empresa_id = $_SESSION['empresa_id'] ?? null;

if (!$empresa_id) {
    set_flash('error', 'No se encontró la empresa asociada');
    redirect('dashboard.php');
}

$db = getDB();
$periodo_actual = get_periodo_actual();

/**
 * Estados que no admiten ninguna modificación por POST.
 */
function dj_estado_inmutable(string $estado): bool {
    return in_array($estado, ['aprobado', 'enviado'], true);
}

// ——— Procesar envío ———
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido. Recargue la página.';
    } else {
        try {
            $accion = $_POST['accion'] ?? 'guardar';
            if (!in_array($accion, ['guardar', 'enviar'], true)) {
                $accion = 'guardar';
            }
            $periodo = trim($_POST['periodo'] ?? $periodo_actual);
            $registro_id = (int)($_POST['registro_id'] ?? 0);

            $existente = null;
            if ($registro_id > 0) {
                $stmt = $db->prepare('SELECT * FROM datos_empresa WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$registro_id, $empresa_id]);
                $existente = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$existente) {
                $stmt = $db->prepare('SELECT * FROM datos_empresa WHERE empresa_id = ? AND periodo = ?');
                $stmt->execute([$empresa_id, $periodo]);
                $existente = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($existente && dj_estado_inmutable($existente['estado'])) {
                $error = 'Este formulario ya fue enviado o aprobado y no puede modificarse.';
            }

            if ($error === '') {
                if ($accion === 'enviar') {
                    $estado_final = 'enviado';
                } elseif ($existente && ($existente['estado'] ?? '') === 'rechazado') {
                    $estado_final = 'rechazado';
                } else {
                    $estado_final = 'borrador';
                }
                $dotacion_total = (int)($_POST['dotacion_total'] ?? 0);
                $emp_masc = (int)($_POST['empleados_masculinos'] ?? 0);
                $emp_fem = (int)($_POST['empleados_femeninos'] ?? 0);

                if ($accion === 'enviar' && $dotacion_total <= 0) {
                    $error = 'Debe indicar al menos el total de empleados para enviar.';
                } elseif ($accion === 'enviar' && empty($_POST['declaracion_jurada'])) {
                    $error = 'Debe aceptar la Declaración Jurada para enviar el formulario.';
                } else {
                    $datos = [
                        'empresa_id' => $empresa_id,
                        'periodo' => $periodo,
                        'dotacion_total' => $dotacion_total,
                        'empleados_masculinos' => $emp_masc,
                        'empleados_femeninos' => $emp_fem,
                        'capacidad_instalada' => trim($_POST['capacidad_instalada'] ?? ''),
                        'porcentaje_capacidad_uso' => (isset($_POST['porcentaje_capacidad_uso']) && $_POST['porcentaje_capacidad_uso'] !== '') ? (float) $_POST['porcentaje_capacidad_uso'] : null,
                        'produccion_mensual' => trim($_POST['produccion_mensual'] ?? ''),
                        'unidad_produccion' => trim($_POST['unidad_produccion'] ?? ''),
                        'consumo_energia' => (isset($_POST['consumo_energia']) && $_POST['consumo_energia'] !== '') ? (float) $_POST['consumo_energia'] : null,
                        'consumo_agua' => (isset($_POST['consumo_agua']) && $_POST['consumo_agua'] !== '') ? (float) $_POST['consumo_agua'] : null,
                        'consumo_gas' => (isset($_POST['consumo_gas']) && $_POST['consumo_gas'] !== '') ? (float) $_POST['consumo_gas'] : null,
                        'conexion_red_agua' => isset($_POST['conexion_red_agua']) ? 1 : 0,
                        'pozo_agua' => isset($_POST['pozo_agua']) ? 1 : 0,
                        'conexion_gas_natural' => isset($_POST['conexion_gas_natural']) ? 1 : 0,
                        'conexion_cloacas' => isset($_POST['conexion_cloacas']) ? 1 : 0,
                        'exporta' => isset($_POST['exporta']) ? 1 : 0,
                        'productos_exporta' => trim($_POST['productos_exporta'] ?? ''),
                        'paises_exporta' => trim($_POST['paises_exporta'] ?? ''),
                        'importa' => isset($_POST['importa']) ? 1 : 0,
                        'productos_importa' => trim($_POST['productos_importa'] ?? ''),
                        'paises_importa' => trim($_POST['paises_importa'] ?? ''),
                        'emisiones_co2' => (isset($_POST['emisiones_co2']) && $_POST['emisiones_co2'] !== '') ? (float) $_POST['emisiones_co2'] : null,
                        'fuente_emision_principal' => trim($_POST['fuente_emision_principal'] ?? ''),
                        'estado' => $estado_final,
                        'declaracion_jurada' => isset($_POST['declaracion_jurada']) ? 1 : 0,
                    ];

                    if ($estado_final === 'enviado') {
                        $datos['fecha_declaracion'] = date('Y-m-d H:i:s');
                        $datos['ip_declaracion'] = $_SERVER['REMOTE_ADDR'] ?? '';
                    }

                    if ($existente) {
                        $sets = [];
                        $values = [];
                        foreach ($datos as $key => $val) {
                            if ($key === 'empresa_id' || $key === 'periodo') {
                                continue;
                            }
                            $sets[] = "$key = ?";
                            $values[] = $val;
                        }
                        $values[] = (int) $existente['id'];
                        $stmt = $db->prepare('UPDATE datos_empresa SET ' . implode(', ', $sets) . ' WHERE id = ? AND empresa_id = ? AND estado NOT IN (\'aprobado\', \'enviado\')');
                        $values[] = $empresa_id;
                        $stmt->execute($values);
                        if ($estado_final !== 'enviado') {
                            $chk = $db->prepare('SELECT estado FROM datos_empresa WHERE id = ? AND empresa_id = ?');
                            $chk->execute([(int) $existente['id'], $empresa_id]);
                            $st_db = (string) $chk->fetchColumn();
                            if (dj_estado_inmutable($st_db)) {
                                $error = 'Este formulario ya fue enviado o aprobado. No se aplicaron cambios.';
                            }
                        }
                    } else {
                        $cols = implode(', ', array_keys($datos));
                        $placeholders = implode(', ', array_fill(0, count($datos), '?'));
                        $stmt = $db->prepare("INSERT INTO datos_empresa ($cols) VALUES ($placeholders)");
                        $stmt->execute(array_values($datos));
                    }

                    if ($error === '') {
                        log_activity(
                            $estado_final === 'enviado' ? 'formulario_enviado' : 'formulario_guardado',
                            'datos_empresa',
                            $empresa_id
                        );

                        if ($estado_final === 'enviado') {
                            $msg_ok = 'Formulario enviado correctamente. El Ministerio revisará sus datos.';
                            try {
                                $nombre_empresa = $_SESSION['empresa_nombre'] ?? 'Empresa';
                                $stmt_min = $db->query("SELECT id FROM usuarios WHERE rol IN ('ministerio', 'admin')");
                                while ($min = $stmt_min->fetch()) {
                                    crear_notificacion(
                                        $min['id'],
                                        'formulario_enviado',
                                        'Formulario recibido',
                                        "$nombre_empresa envió su declaración trimestral ($periodo)",
                                        MINISTERIO_URL . '/formularios.php'
                                    );
                                }
                            } catch (Throwable $e) {
                                error_log('formularios.php notificaciones ministerio: ' . $e->getMessage());
                            }
                        } else {
                            $msg_ok = 'Borrador guardado correctamente.';
                        }

                        $rid = $existente['id'] ?? (int) $db->lastInsertId();
                        set_flash('success', $msg_ok);
                        if ($rid > 0) {
                            redirect('formularios.php?id=' . $rid);
                        }
                        redirect('formularios.php');
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('Error en formulario empresa_id=' . ($empresa_id ?? '') . ': ' . $e->getMessage());
            $error = 'Error al procesar el formulario. Intente nuevamente.';
            if (function_exists('env_bool') && env_bool('APP_DEBUG', false)) {
                $error .= ' (' . $e->getMessage() . ')';
            }
        }
    }
}

// ——— Contexto de vista (?id=) ———
$ficha_id = (int)($_GET['id'] ?? 0);
$datos = [];
$periodo_form = $periodo_actual;
$registro_id = null;
$modo_consulta = false;

if ($ficha_id > 0) {
    $stmt = $db->prepare('SELECT * FROM datos_empresa WHERE id = ? AND empresa_id = ?');
    $stmt->execute([$ficha_id, $empresa_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $datos = $row;
        $periodo_form = $row['periodo'];
        $registro_id = (int) $row['id'];
        $modo_consulta = dj_estado_inmutable($row['estado']);
    } else {
        set_flash('error', 'Formulario no encontrado.');
        redirect('formularios.php');
    }
} else {
    $stmt = $db->prepare('SELECT * FROM datos_empresa WHERE empresa_id = ? AND periodo = ?');
    $stmt->execute([$empresa_id, $periodo_actual]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $periodo_form = $periodo_actual;
    $registro_id = isset($datos['id']) ? (int) $datos['id'] : null;
    if ($datos && dj_estado_inmutable($datos['estado'])) {
        $modo_consulta = true;
    }
}

$formulario_bloqueado_aprobado = (($datos['estado'] ?? '') === 'aprobado');
$ro = $modo_consulta ? true : false;

// Tras error de validación en POST, no perder lo que el usuario cargó (misma petición)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $error !== ''
    && !$modo_consulta
    && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')
) {
    $nuevo_sin_fila = empty($datos);
    $rid_post = (int) ($_POST['registro_id'] ?? 0);
    if ($rid_post > 0) {
        $registro_id = $rid_post;
    }
    $p = trim($_POST['periodo'] ?? '');
    if ($p !== '') {
        $periodo_form = $p;
    }
    $merge = [
        'dotacion_total' => (int) ($_POST['dotacion_total'] ?? 0),
        'empleados_masculinos' => (int) ($_POST['empleados_masculinos'] ?? 0),
        'empleados_femeninos' => (int) ($_POST['empleados_femeninos'] ?? 0),
        'capacidad_instalada' => trim($_POST['capacidad_instalada'] ?? ''),
        'porcentaje_capacidad_uso' => ($_POST['porcentaje_capacidad_uso'] ?? '') !== '' ? (float) $_POST['porcentaje_capacidad_uso'] : null,
        'produccion_mensual' => trim($_POST['produccion_mensual'] ?? ''),
        'unidad_produccion' => trim($_POST['unidad_produccion'] ?? ''),
        'consumo_energia' => ($_POST['consumo_energia'] ?? '') !== '' ? (float) $_POST['consumo_energia'] : null,
        'consumo_agua' => ($_POST['consumo_agua'] ?? '') !== '' ? (float) $_POST['consumo_agua'] : null,
        'consumo_gas' => ($_POST['consumo_gas'] ?? '') !== '' ? (float) $_POST['consumo_gas'] : null,
        'conexion_red_agua' => isset($_POST['conexion_red_agua']) ? 1 : 0,
        'pozo_agua' => isset($_POST['pozo_agua']) ? 1 : 0,
        'conexion_gas_natural' => isset($_POST['conexion_gas_natural']) ? 1 : 0,
        'conexion_cloacas' => isset($_POST['conexion_cloacas']) ? 1 : 0,
        'exporta' => isset($_POST['exporta']) ? 1 : 0,
        'productos_exporta' => trim($_POST['productos_exporta'] ?? ''),
        'paises_exporta' => trim($_POST['paises_exporta'] ?? ''),
        'importa' => isset($_POST['importa']) ? 1 : 0,
        'productos_importa' => trim($_POST['productos_importa'] ?? ''),
        'paises_importa' => trim($_POST['paises_importa'] ?? ''),
        'emisiones_co2' => ($_POST['emisiones_co2'] ?? '') !== '' ? (float) $_POST['emisiones_co2'] : null,
        'fuente_emision_principal' => trim($_POST['fuente_emision_principal'] ?? ''),
        'declaracion_jurada' => isset($_POST['declaracion_jurada']) ? 1 : 0,
    ];
    $datos = array_merge($datos ?: [], $merge);
    if ($nuevo_sin_fila) {
        $datos['estado'] = 'borrador';
    }
}

$permite_edicion = $datos && !$modo_consulta && in_array($datos['estado'] ?? '', ['borrador', 'rechazado'], true);
$sin_registro = empty($datos);

// Historial (incluye id para acciones)
$historial = [];
try {
    $stmt = $db->prepare('
        SELECT id, periodo, estado, created_at, fecha_declaracion, observaciones_ministerio
        FROM datos_empresa
        WHERE empresa_id = ?
        ORDER BY periodo DESC
        LIMIT 12
    ');
    $stmt->execute([$empresa_id]);
    $historial = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('formularios.php historial: ' . $e->getMessage());
}

$attr_ro = function () use ($ro) {
    return $ro ? ' readonly class="form-control bg-light"' : ' class="form-control"';
};
$attr_ro_sel = function () use ($ro) {
    return $ro ? ' disabled class="form-select bg-light"' : ' class="form-select"';
};
$chk = function ($on) use ($ro) {
    $x = $on ? ' checked' : '';
    return $ro ? ' disabled' . $x : $x;
};

$empresa_nav = 'formularios';
$empresa_body_extra = $modo_consulta ? 'dj-mod-consulta' : '';
$extra_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
        .dj-page { max-width: 920px; }
        .dj-consulta-banner {
            border-left: 4px solid #0d6efd;
            background: linear-gradient(90deg, rgba(13,110,253,.08), transparent);
            border-radius: 0 12px 12px 0;
            padding: 12px 16px;
            margin-bottom: 1.25rem;
        }
        .dj-section {
            border: 1px solid rgba(0,0,0,.06);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,.04);
            margin-bottom: 1.25rem;
            overflow: hidden;
        }
        .dj-section-head {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: 14px 18px;
            background: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.06);
            font-weight: 600;
            font-size: .95rem;
            color: #212529;
        }
        .dj-section-head i { font-size: 1.15rem; color: var(--primary, #0d6efd); }
        .dj-section-body { padding: 18px; }
        .dj-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
        .dj-stat {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px 14px;
            border: 1px solid rgba(0,0,0,.04);
        }
        .dj-stat label { font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; color: #6c757d; margin-bottom: 4px; }
        .dj-mini { font-size: .8rem; color: #6c757d; }
        .table-dj-actions { white-space: nowrap; }
        /* Consumos/servicios: label arriba + switch abajo (evita solapamiento del margin negativo de BS) */
        .dj-switch-cell {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        .dj-switch-cell > label.form-label {
            line-height: 1.35;
            margin-bottom: 0;
            font-weight: 500;
        }
        .dj-switch-cell .form-check.form-switch {
            padding-left: 0;
            margin-bottom: 0;
            min-height: 1.5rem;
        }
        .dj-switch-cell .form-switch .form-check-input {
            margin-left: 0;
            float: none;
            position: relative;
            cursor: pointer;
        }
    </style>';
require_once BASEPATH . '/includes/empresa_layout_header.php';
?>
        <div class="dj-page">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <h1 class="h3 mb-0">Declaración jurada trimestral</h1>
            <?php if ($ficha_id > 0): ?>
            <a href="formularios.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar3 me-1"></i>Ir al período actual</a>
            <?php endif; ?>
        </div>

        <?php show_flash(); ?>
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= e($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if ($historial): ?>
        <div class="dj-section mb-4">
            <div class="dj-section-head"><i class="bi bi-clock-history"></i> Historial de formularios</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Período</th>
                            <th>Estado</th>
                            <th>Enviado / alta</th>
                            <th>Observaciones</th>
                            <th class="text-end table-dj-actions">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($historial as $h):
                        $st = $h['estado'];
                        $badge_class = ['borrador' => 'bg-secondary', 'enviado' => 'bg-warning text-dark', 'aprobado' => 'bg-success', 'rechazado' => 'bg-danger'];
                        $es_ver = in_array($st, ['enviado', 'aprobado'], true);
                        $es_editar = in_array($st, ['borrador', 'rechazado'], true);
                        ?>
                    <tr class="<?= ($ficha_id === (int) $h['id']) ? 'table-primary' : '' ?>">
                        <td><strong><?= e($h['periodo']) ?></strong></td>
                        <td><span class="badge <?= $badge_class[$st] ?? 'bg-secondary' ?>"><?= ucfirst($st) ?></span></td>
                        <td class="small"><?= $h['fecha_declaracion'] ? format_datetime($h['fecha_declaracion']) : format_datetime($h['created_at']) ?></td>
                        <td class="small"><?= e($h['observaciones_ministerio'] ?? '—') ?></td>
                        <td class="text-end">
                            <?php if ($es_ver): ?>
                            <a href="formularios.php?id=<?= (int) $h['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Ver</a>
                            <?php elseif ($es_editar): ?>
                            <a href="formularios.php?id=<?= (int) $h['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square me-1"></i>Continuar edición</a>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($modo_consulta): ?>
        <div class="dj-consulta-banner d-flex align-items-center gap-3">
            <i class="bi bi-eye-fill fs-3 text-primary"></i>
            <div>
                <div class="fw-semibold">Modo consulta</div>
                <div class="small text-muted mb-0">Estás viendo un envío ya presentado o aprobado. Los datos no se pueden modificar.</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($formulario_bloqueado_aprobado && !$ficha_id): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            El formulario del período <strong><?= e($periodo_actual) ?></strong> ya fue <strong>aprobado</strong> por el Ministerio.
        </div>
        <?php endif; ?>

        <?php if ($sin_registro || $datos): ?>

        <?php if (!$modo_consulta && ($sin_registro || $permite_edicion || ($datos['estado'] ?? '') === 'rechazado')): ?>
        <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Período: <strong><?= e($periodo_form) ?></strong>
            <?php if (($datos['estado'] ?? '') === 'rechazado'): ?>
            <span class="text-danger ms-2"><i class="bi bi-exclamation-triangle me-1"></i>Rechazado: corregí según observaciones y volvé a enviar.</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="djForm" class="needs-validation" novalidate <?= $modo_consulta ? ' onsubmit="return false;"' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="periodo" value="<?= e($periodo_form) ?>">
            <?php if ($registro_id): ?>
            <input type="hidden" name="registro_id" value="<?= (int) $registro_id ?>">
            <?php endif; ?>
            <input type="hidden" name="accion" id="djAccion" value="">

            <!-- Dotación -->
            <div class="dj-section">
                <div class="dj-section-head"><i class="bi bi-people"></i> Dotación de personal</div>
                <div class="dj-section-body">
                    <div class="dj-stat-grid">
                        <div class="dj-stat">
                            <label class="form-label">Total empleados <?= !$ro ? '*' : '' ?></label>
                            <input type="number" name="dotacion_total" min="0" <?= !$ro ? 'required' : '' ?>
                                <?= $ro ? 'readonly class="form-control form-control-lg bg-light border-0 fw-semibold"' : 'class="form-control form-control-lg"' ?>
                                value="<?= e($datos['dotacion_total'] ?? '') ?>">
                        </div>
                        <div class="dj-stat">
                            <label class="form-label">Masculinos</label>
                            <input type="number" name="empleados_masculinos" min="0"<?= $attr_ro() ?> value="<?= e($datos['empleados_masculinos'] ?? '') ?>">
                        </div>
                        <div class="dj-stat">
                            <label class="form-label">Femeninos</label>
                            <input type="number" name="empleados_femeninos" min="0"<?= $attr_ro() ?> value="<?= e($datos['empleados_femeninos'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Capacidad -->
            <div class="dj-section">
                <div class="dj-section-head"><i class="bi bi-speedometer2"></i> Capacidad y producción</div>
                <div class="dj-section-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Capacidad instalada</label>
                            <input type="text" name="capacidad_instalada" placeholder="Ej: 1000 unidades/mes"<?= $attr_ro() ?> value="<?= e($datos['capacidad_instalada'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Uso de capacidad (%)</label>
                            <input type="number" name="porcentaje_capacidad_uso" min="0" max="100"<?= $attr_ro() ?> value="<?= e($datos['porcentaje_capacidad_uso'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Producción mensual</label>
                            <input type="text" name="produccion_mensual"<?= $attr_ro() ?> value="<?= e($datos['produccion_mensual'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unidad</label>
                            <input type="text" name="unidad_produccion" placeholder="kg, litros, unidades…"<?= $attr_ro() ?> value="<?= e($datos['unidad_produccion'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Consumos -->
            <div class="dj-section">
                <div class="dj-section-head"><i class="bi bi-lightning-charge"></i> Consumos y servicios <span class="fw-normal text-muted ms-1">(referencia mensual)</span></div>
                <div class="dj-section-body">
                    <div class="dj-stat-grid mb-3">
                        <div class="dj-stat">
                            <label class="form-label">Energía (kWh)</label>
                            <input type="number" name="consumo_energia" min="0" step="0.01"<?= $attr_ro() ?> value="<?= e($datos['consumo_energia'] ?? '') ?>">
                        </div>
                        <div class="dj-stat">
                            <label class="form-label">Agua (m³)</label>
                            <input type="number" name="consumo_agua" min="0" step="0.01"<?= $attr_ro() ?> value="<?= e($datos['consumo_agua'] ?? '') ?>">
                        </div>
                        <div class="dj-stat">
                            <label class="form-label">Gas (m³)</label>
                            <input type="number" name="consumo_gas" min="0" step="0.01"<?= $attr_ro() ?> value="<?= e($datos['consumo_gas'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row g-3">
                        <?php
                        $checks = [
                            ['conexion_red_agua', 'redAgua', 'Conexión a red de agua'],
                            ['pozo_agua', 'pozoAgua', 'Pozo propio'],
                            ['conexion_gas_natural', 'gasNat', 'Gas natural'],
                            ['conexion_cloacas', 'cloacas', 'Cloacas'],
                        ];
                        foreach ($checks as $c):
                            $field = $c[0];
                            $id = $c[1];
                            $label = $c[2];
                            $on = !empty($datos[$field]);
                        ?>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="dj-switch-cell p-3 rounded border bg-white h-100">
                                <label class="form-label small" for="<?= e($id) ?>"><?= e($label) ?></label>
                                <div class="form-check form-switch">
                                    <input type="checkbox" name="<?= e($field) ?>" class="form-check-input" id="<?= e($id) ?>"<?= $chk($on) ?>>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Comercio exterior -->
            <div class="dj-section">
                <div class="dj-section-head"><i class="bi bi-globe2"></i> Comercio exterior</div>
                <div class="dj-section-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input type="checkbox" name="exporta" class="form-check-input" id="exporta"<?= $chk(!empty($datos['exporta'])) ?>>
                                <label class="form-check-label fw-semibold" for="exporta">Exporta</label>
                            </div>
                            <div id="exportaFields" class="<?= !empty($datos['exporta']) ? '' : 'd-none' ?>">
                                <label class="form-label small">Productos</label>
                                <textarea name="productos_exporta" rows="2" class="form-control mb-2<?= $ro ? ' bg-light' : '' ?>"<?= $ro ? ' readonly' : '' ?>><?= e($datos['productos_exporta'] ?? '') ?></textarea>
                                <label class="form-label small">Países destino</label>
                                <input type="text" name="paises_exporta" class="form-control"<?= $attr_ro() ?> value="<?= e($datos['paises_exporta'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input type="checkbox" name="importa" class="form-check-input" id="importa"<?= $chk(!empty($datos['importa'])) ?>>
                                <label class="form-check-label fw-semibold" for="importa">Importa</label>
                            </div>
                            <div id="importaFields" class="<?= !empty($datos['importa']) ? '' : 'd-none' ?>">
                                <label class="form-label small">Productos</label>
                                <textarea name="productos_importa" rows="2" class="form-control mb-2<?= $ro ? ' bg-light' : '' ?>"<?= $ro ? ' readonly' : '' ?>><?= e($datos['productos_importa'] ?? '') ?></textarea>
                                <label class="form-label small">Países origen</label>
                                <input type="text" name="paises_importa" class="form-control"<?= $attr_ro() ?> value="<?= e($datos['paises_importa'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Huella -->
            <div class="dj-section">
                <div class="dj-section-head"><i class="bi bi-cloud"></i> Huella de carbono</div>
                <div class="dj-section-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Emisiones CO₂e (t)</label>
                            <input type="number" name="emisiones_co2" min="0" step="0.0001"<?= $attr_ro() ?> value="<?= e($datos['emisiones_co2'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Principal fuente</label>
                            <?php if ($ro):
                                $fv = $datos['fuente_emision_principal'] ?? '';
                            ?>
                            <p class="form-control-plaintext border rounded bg-light px-3 py-2 mb-0"><?= $fv !== '' ? e($fv) : '—' ?></p>
                            <input type="hidden" name="fuente_emision_principal" value="<?= e($fv) ?>">
                            <?php else: ?>
                            <select name="fuente_emision_principal" class="form-select">
                                <option value="">Seleccione…</option>
                                <?php foreach (['Electricidad', 'Combustibles', 'Transporte', 'Procesos industriales', 'Otro'] as $f): ?>
                                <option value="<?= e($f) ?>" <?= ($datos['fuente_emision_principal'] ?? '') === $f ? 'selected' : '' ?>><?= e($f) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Declaración -->
            <div class="dj-section border-warning border-2">
                <div class="dj-section-body">
                    <div class="form-check">
                        <input type="checkbox" name="declaracion_jurada" class="form-check-input" id="declaracion"<?= $chk(!empty($datos['declaracion_jurada'])) ?>>
                        <label class="form-check-label" for="declaracion">
                            <strong>Declaración jurada</strong> — Los datos son veraces y autorizo al Ministerio a verificarlos.
                        </label>
                    </div>
                </div>
            </div>

            <?php if (!$modo_consulta): ?>
            <div class="d-flex flex-wrap gap-2 pb-5">
                <button type="button" class="btn btn-outline-secondary" id="djBtnGuardar">
                    <i class="bi bi-save me-2"></i>Guardar borrador
                </button>
                <button type="button" class="btn btn-primary btn-lg" id="djBtnEnviar">
                    <i class="bi bi-send me-2"></i>Enviar declaración jurada
                </button>
            </div>
            <?php endif; ?>
        </form>

        <?php endif; ?>
        </div>

<?php
$extra_scripts = <<<'EOT'
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    (function() {
        const form = document.getElementById('djForm');
        const accionEl = document.getElementById('djAccion');
        const exporta = document.getElementById('exporta');
        const importa = document.getElementById('importa');
        if (exporta && !exporta.disabled) {
            exporta.addEventListener('change', function() {
                const el = document.getElementById('exportaFields');
                if (el) el.classList.toggle('d-none', !this.checked);
            });
        }
        if (importa && !importa.disabled) {
            importa.addEventListener('change', function() {
                const el = document.getElementById('importaFields');
                if (el) el.classList.toggle('d-none', !this.checked);
            });
        }

        if (!form || !accionEl || form.classList.contains('dj-no-js')) return;

        const btnG = document.getElementById('djBtnGuardar');
        const btnE = document.getElementById('djBtnEnviar');
        if (!btnG || !btnE) return;

        btnG.addEventListener('click', function() {
            Swal.fire({
                title: '¿Guardar borrador?',
                text: 'Los datos quedarán guardados sin enviar al Ministerio. Podés continuar más tarde.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#6c757d'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                accionEl.value = 'guardar';
                form.submit();
            });
        });

        btnE.addEventListener('click', function() {
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            Swal.fire({
                title: '¿Enviar declaración jurada?',
                html: 'Al enviar, el Ministerio podrá revisar los datos. <strong>No podrás editarlos</strong> hasta una resolución (salvo que sea rechazado).',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, enviar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd'
            }).then(function(r) {
                if (!r.isConfirmed) return;
                accionEl.value = 'enviar';
                form.submit();
            });
        });
    })();
    </script>
EOT;
require_once BASEPATH . '/includes/empresa_layout_footer.php';
