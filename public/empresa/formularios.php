<?php
/**
 * Formularios de Empresa - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Formularios';
$mensaje = '';
$error = '';
$empresa_id = $_SESSION['empresa_id'] ?? null;

if (!$empresa_id) {
    set_flash('error', 'No se encontró la empresa asociada');
    redirect('dashboard.php');
}

$db = getDB();
$periodo_actual = get_periodo_actual();

// Procesar envío
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido. Recargue la página.';
    } else {
        try {
            $accion = $_POST['accion'] ?? 'guardar';
            $estado = ($accion === 'enviar') ? 'enviado' : 'borrador';
            $periodo = $_POST['periodo'] ?? $periodo_actual;

            // Validar que el total de empleados sea coherente
            $dotacion_total = (int)($_POST['dotacion_total'] ?? 0);
            $emp_masc = (int)($_POST['empleados_masculinos'] ?? 0);
            $emp_fem = (int)($_POST['empleados_femeninos'] ?? 0);

            if ($accion === 'enviar' && $dotacion_total <= 0) {
                $error = 'Debe indicar al menos el total de empleados para enviar.';
            } elseif ($accion === 'enviar' && empty($_POST['declaracion_jurada'])) {
                $error = 'Debe aceptar la Declaración Jurada para enviar el formulario.';
            } else {
                // Verificar si ya existe un registro para este periodo
                $stmt = $db->prepare("SELECT id, estado FROM datos_empresa WHERE empresa_id = ? AND periodo = ?");
                $stmt->execute([$empresa_id, $periodo]);
                $existente = $stmt->fetch();

                // No permitir editar si ya fue aprobado
                if ($existente && $existente['estado'] === 'aprobado') {
                    $error = 'Este formulario ya fue aprobado y no puede modificarse.';
                } else {
                    $datos = [
                        'empresa_id' => $empresa_id,
                        'periodo' => $periodo,
                        'dotacion_total' => $dotacion_total,
                        'empleados_masculinos' => $emp_masc,
                        'empleados_femeninos' => $emp_fem,
                        'capacidad_instalada' => trim($_POST['capacidad_instalada'] ?? ''),
                        'porcentaje_capacidad_uso' => !empty($_POST['porcentaje_capacidad_uso']) ? (float)$_POST['porcentaje_capacidad_uso'] : null,
                        'produccion_mensual' => trim($_POST['produccion_mensual'] ?? ''),
                        'unidad_produccion' => trim($_POST['unidad_produccion'] ?? ''),
                        'consumo_energia' => !empty($_POST['consumo_energia']) ? (float)$_POST['consumo_energia'] : null,
                        'consumo_agua' => !empty($_POST['consumo_agua']) ? (float)$_POST['consumo_agua'] : null,
                        'consumo_gas' => !empty($_POST['consumo_gas']) ? (float)$_POST['consumo_gas'] : null,
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
                        'emisiones_co2' => !empty($_POST['emisiones_co2']) ? (float)$_POST['emisiones_co2'] : null,
                        'fuente_emision_principal' => trim($_POST['fuente_emision_principal'] ?? ''),
                        'estado' => $estado,
                        'declaracion_jurada' => isset($_POST['declaracion_jurada']) ? 1 : 0,
                    ];

                    if ($estado === 'enviado') {
                        $datos['fecha_declaracion'] = date('Y-m-d H:i:s');
                        $datos['ip_declaracion'] = $_SERVER['REMOTE_ADDR'] ?? '';
                    }

                    if ($existente) {
                        // UPDATE
                        $sets = [];
                        $values = [];
                        foreach ($datos as $key => $val) {
                            if ($key === 'empresa_id' || $key === 'periodo') continue;
                            $sets[] = "$key = ?";
                            $values[] = $val;
                        }
                        $values[] = $existente['id'];
                        $stmt = $db->prepare("UPDATE datos_empresa SET " . implode(', ', $sets) . " WHERE id = ?");
                        $stmt->execute($values);
                    } else {
                        // INSERT
                        $cols = implode(', ', array_keys($datos));
                        $placeholders = implode(', ', array_fill(0, count($datos), '?'));
                        $stmt = $db->prepare("INSERT INTO datos_empresa ($cols) VALUES ($placeholders)");
                        $stmt->execute(array_values($datos));
                    }

                    log_activity(
                        $estado === 'enviado' ? 'formulario_enviado' : 'formulario_guardado',
                        'datos_empresa',
                        $empresa_id
                    );

                    if ($estado === 'enviado') {
                        // Notificar al ministerio
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
                        $mensaje = 'Formulario enviado correctamente. El Ministerio revisará sus datos.';
                    } else {
                        $mensaje = 'Borrador guardado correctamente.';
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error en formulario empresa_id=" . ($empresa_id ?? '') . ": " . $e->getMessage());
            $error = 'Error al procesar el formulario. Intente nuevamente.';
        }
    }
}

// Cargar datos existentes del periodo actual
$stmt = $db->prepare("SELECT * FROM datos_empresa WHERE empresa_id = ? AND periodo = ?");
$stmt->execute([$empresa_id, $periodo_actual]);
$datos = $stmt->fetch();

// Cargar historial de formularios enviados
$stmt = $db->prepare("
    SELECT periodo, estado, created_at, fecha_declaracion, observaciones_ministerio
    FROM datos_empresa
    WHERE empresa_id = ?
    ORDER BY periodo DESC
    LIMIT 8
");
$stmt->execute([$empresa_id]);
$historial = $stmt->fetchAll();

$formulario_bloqueado = ($datos && $datos['estado'] === 'aprobado');
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
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="perfil.php"><i class="bi bi-building"></i> Mi Perfil</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <a href="formularios.php" class="active"><i class="bi bi-file-earmark-text"></i> Formularios</a>
            <a href="mensajes.php"><i class="bi bi-envelope"></i> Mensajes</a>
            <a href="notificaciones.php"><i class="bi bi-bell"></i> Notificaciones</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio público</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 class="h3 mb-4">Formularios - Declaración Jurada</h1>

        <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= e($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= e($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Historial -->
        <?php if ($historial): ?>
        <div class="card mb-4">
            <div class="card-header bg-white"><h5 class="mb-0">Historial de Formularios</h5></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Período</th><th>Estado</th><th>Fecha envío</th><th>Observaciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($historial as $h): ?>
                    <tr>
                        <td><strong><?= e($h['periodo']) ?></strong></td>
                        <td>
                            <?php
                            $badge_class = ['borrador' => 'bg-secondary', 'enviado' => 'bg-warning text-dark', 'aprobado' => 'bg-success', 'rechazado' => 'bg-danger'];
                            ?>
                            <span class="badge <?= $badge_class[$h['estado']] ?? 'bg-secondary' ?>"><?= ucfirst($h['estado']) ?></span>
                        </td>
                        <td><?= $h['fecha_declaracion'] ? format_datetime($h['fecha_declaracion']) : '-' ?></td>
                        <td><?= e($h['observaciones_ministerio'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($formulario_bloqueado): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            El formulario del período <strong><?= e($periodo_actual) ?></strong> ya fue aprobado por el Ministerio.
        </div>
        <?php else: ?>

        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Complete el formulario trimestral con datos reales de su empresa. Esta información tiene carácter de Declaración Jurada.
            <br><strong>Período actual: <?= e($periodo_actual) ?></strong>
            <?php if ($datos && $datos['estado'] === 'borrador'): ?>
                <br><small class="text-muted">Tiene un borrador guardado. Puede continuar editándolo.</small>
            <?php elseif ($datos && $datos['estado'] === 'enviado'): ?>
                <br><small>El formulario fue enviado y está pendiente de revisión. Puede modificarlo hasta que sea aprobado.</small>
            <?php elseif ($datos && $datos['estado'] === 'rechazado'): ?>
                <br><span class="text-danger">El formulario fue rechazado. Corrija las observaciones y reenvíe.</span>
            <?php endif; ?>
        </div>

        <form method="POST" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="periodo" value="<?= e($periodo_actual) ?>">

            <!-- Personal -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="bi bi-people me-2"></i>Dotación de Personal</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Total empleados *</label>
                            <input type="number" name="dotacion_total" class="form-control" min="0" required value="<?= e($datos['dotacion_total'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Empleados masculinos</label>
                            <input type="number" name="empleados_masculinos" class="form-control" min="0" value="<?= e($datos['empleados_masculinos'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Empleados femeninos</label>
                            <input type="number" name="empleados_femeninos" class="form-control" min="0" value="<?= e($datos['empleados_femeninos'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Producción -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="bi bi-gear me-2"></i>Capacidad y Producción</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Capacidad instalada</label>
                            <input type="text" name="capacidad_instalada" class="form-control" placeholder="Ej: 1000 unidades/mes" value="<?= e($datos['capacidad_instalada'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Porcentaje de uso de capacidad (%)</label>
                            <input type="number" name="porcentaje_capacidad_uso" class="form-control" min="0" max="100" value="<?= e($datos['porcentaje_capacidad_uso'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Producción mensual</label>
                            <input type="text" name="produccion_mensual" class="form-control" value="<?= e($datos['produccion_mensual'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unidad de medida</label>
                            <input type="text" name="unidad_produccion" class="form-control" placeholder="Ej: unidades, kg, litros" value="<?= e($datos['unidad_produccion'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Consumos -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Consumos Mensuales</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Energía eléctrica (kWh)</label>
                            <input type="number" name="consumo_energia" class="form-control" min="0" step="0.01" value="<?= e($datos['consumo_energia'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Agua (m³)</label>
                            <input type="number" name="consumo_agua" class="form-control" min="0" step="0.01" value="<?= e($datos['consumo_agua'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gas (m³)</label>
                            <input type="number" name="consumo_gas" class="form-control" min="0" step="0.01" value="<?= e($datos['consumo_gas'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" name="conexion_red_agua" class="form-check-input" id="redAgua" <?= !empty($datos['conexion_red_agua']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="redAgua">Conexión a red de agua</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" name="pozo_agua" class="form-check-input" id="pozoAgua" <?= !empty($datos['pozo_agua']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pozoAgua">Pozo de agua propio</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" name="conexion_gas_natural" class="form-check-input" id="gasNat" <?= !empty($datos['conexion_gas_natural']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="gasNat">Gas natural</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" name="conexion_cloacas" class="form-check-input" id="cloacas" <?= !empty($datos['conexion_cloacas']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="cloacas">Conexión a cloacas</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comercio exterior -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="bi bi-globe me-2"></i>Comercio Exterior</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="exporta" class="form-check-input" id="exporta" <?= !empty($datos['exporta']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="exporta"><strong>¿Exporta?</strong></label>
                            </div>
                            <div id="exportaFields" style="display: <?= !empty($datos['exporta']) ? 'block' : 'none' ?>;">
                                <label class="form-label">Productos que exporta</label>
                                <textarea name="productos_exporta" class="form-control mb-2" rows="2"><?= e($datos['productos_exporta'] ?? '') ?></textarea>
                                <label class="form-label">Países destino</label>
                                <input type="text" name="paises_exporta" class="form-control" value="<?= e($datos['paises_exporta'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="importa" class="form-check-input" id="importa" <?= !empty($datos['importa']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="importa"><strong>¿Importa?</strong></label>
                            </div>
                            <div id="importaFields" style="display: <?= !empty($datos['importa']) ? 'block' : 'none' ?>;">
                                <label class="form-label">Productos que importa</label>
                                <textarea name="productos_importa" class="form-control mb-2" rows="2"><?= e($datos['productos_importa'] ?? '') ?></textarea>
                                <label class="form-label">Países origen</label>
                                <input type="text" name="paises_importa" class="form-control" value="<?= e($datos['paises_importa'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Huella carbono -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="bi bi-cloud me-2"></i>Huella de Carbono</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Emisiones CO2 equivalente (toneladas)</label>
                            <input type="number" name="emisiones_co2" class="form-control" min="0" step="0.0001" value="<?= e($datos['emisiones_co2'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Principal fuente de emisión</label>
                            <select name="fuente_emision_principal" class="form-select">
                                <option value="">Seleccione...</option>
                                <?php
                                $fuentes = ['Electricidad', 'Combustibles', 'Transporte', 'Procesos industriales', 'Otro'];
                                foreach ($fuentes as $f):
                                ?>
                                <option value="<?= e($f) ?>" <?= ($datos['fuente_emision_principal'] ?? '') === $f ? 'selected' : '' ?>><?= e($f) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Declaración jurada -->
            <div class="card mb-4 border-warning">
                <div class="card-body">
                    <div class="form-check">
                        <input type="checkbox" name="declaracion_jurada" class="form-check-input" id="declaracion" <?= !empty($datos['declaracion_jurada']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="declaracion">
                            <strong>Declaro bajo juramento</strong> que los datos consignados en el presente formulario son veraces y
                            corresponden a la realidad de mi empresa. Autorizo al Ministerio a verificar esta información.
                        </label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-3">
                <button type="submit" name="accion" value="guardar" class="btn btn-outline-secondary">
                    <i class="bi bi-save me-2"></i>Guardar borrador
                </button>
                <button type="submit" name="accion" value="enviar" class="btn btn-primary btn-lg">
                    <i class="bi bi-send me-2"></i>Enviar formulario
                </button>
            </div>
        </form>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('exporta')?.addEventListener('change', function() {
            document.getElementById('exportaFields').style.display = this.checked ? 'block' : 'none';
        });
        document.getElementById('importa')?.addEventListener('change', function() {
            document.getElementById('importaFields').style.display = this.checked ? 'block' : 'none';
        });
    </script>
</body>
</html>
