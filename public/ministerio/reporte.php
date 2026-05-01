<?php
/**
 * Reporte Trimestral PDF/Imprimible - Ministerio
 * Genera un informe agregado de un período seleccionado.
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$db = getDB();

// Períodos disponibles
$periodos = $db->query("SELECT DISTINCT periodo FROM datos_empresa ORDER BY periodo DESC")->fetchAll(PDO::FETCH_COLUMN);
$periodo = trim($_GET['periodo'] ?? '');

if (!$periodo && !empty($periodos)) {
    $periodo = $periodos[0]; // último período por defecto
}

if (!$periodo) {
    set_flash('error', 'No hay períodos con datos declarados.');
    redirect('dashboard.php');
}

// --- Datos del reporte ---

// Totales generales
$stmt = $db->prepare("
    SELECT COUNT(*) AS total_empresas,
           SUM(COALESCE(dotacion_total, 0)) AS total_empleados,
           SUM(COALESCE(empleados_masculinos, 0)) AS emp_masc,
           SUM(COALESCE(empleados_femeninos, 0)) AS emp_fem,
           SUM(COALESCE(consumo_energia, 0)) AS energia,
           SUM(COALESCE(consumo_agua, 0)) AS agua,
           SUM(COALESCE(consumo_gas, 0)) AS gas,
           SUM(COALESCE(emisiones_co2, 0)) AS co2,
           AVG(COALESCE(porcentaje_capacidad_uso, 0)) AS cap_promedio
    FROM datos_empresa
    WHERE periodo = ? AND estado IN ('enviado','aprobado')
");
$stmt->execute([$periodo]);
$totales = $stmt->fetch(PDO::FETCH_ASSOC);

// Detalle por empresa
$stmt = $db->prepare("
    SELECT e.nombre, e.rubro, e.cuit,
           de.dotacion_total, de.empleados_masculinos, de.empleados_femeninos,
           de.consumo_energia, de.consumo_agua, de.consumo_gas,
           de.porcentaje_capacidad_uso, de.emisiones_co2, de.estado,
           de.exporta, de.importa
    FROM datos_empresa de
    INNER JOIN empresas e ON de.empresa_id = e.id
    WHERE de.periodo = ? AND de.estado IN ('enviado','aprobado')
    ORDER BY e.nombre
");
$stmt->execute([$periodo]);
$detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Por rubro
$stmt = $db->prepare("
    SELECT e.rubro,
           COUNT(*) AS empresas,
           SUM(COALESCE(de.dotacion_total, 0)) AS empleados,
           SUM(COALESCE(de.consumo_energia, 0)) AS energia,
           SUM(COALESCE(de.consumo_agua, 0)) AS agua,
           SUM(COALESCE(de.consumo_gas, 0)) AS gas
    FROM datos_empresa de
    INNER JOIN empresas e ON de.empresa_id = e.id
    WHERE de.periodo = ? AND de.estado IN ('enviado','aprobado')
    GROUP BY e.rubro ORDER BY empleados DESC
");
$stmt->execute([$periodo]);
$por_rubro = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Empresas activas totales (para contexto)
$total_activas = (int) $db->query("SELECT COUNT(*) FROM empresas WHERE estado IN ('activa','pendiente')")->fetchColumn();
$tasa_respuesta = $total_activas > 0 ? round(($totales['total_empresas'] / $total_activas) * 100) : 0;

$fmt = fn($v, int $d = 0) => number_format((float)($v ?? 0), $d, ',', '.');

// Si es solicitud de selector de periodo (no imprimir), mostrar UI
$modo_imprimir = isset($_GET['imprimir']);
if (!$modo_imprimir):
    $page_title = 'Generar Reporte';
    $ministerio_nav = 'exportar';
    require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
    <h2 class="h4 mb-4 fw-semibold"><i class="bi bi-file-earmark-pdf me-2"></i>Reporte Trimestral</h2>

    <?php show_flash(); ?>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-auto">
                    <label class="form-label fw-semibold">Período</label>
                    <select name="periodo" class="form-select">
                        <?php foreach ($periodos as $p): ?>
                        <option value="<?= e($p) ?>" <?= $p === $periodo ? 'selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-eye me-1"></i>Vista previa</button>
                </div>
                <div class="col-auto">
                    <a href="reporte.php?periodo=<?= e($periodo) ?>&imprimir=1" target="_blank" class="btn btn-success"><i class="bi bi-printer me-1"></i>Imprimir / PDF</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($detalle)): ?>
    <div class="card mt-4">
        <div class="card-header bg-white"><h5 class="mb-0">Vista previa — <?= e($periodo) ?></h5></div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-3 fw-bold text-primary"><?= $totales['total_empresas'] ?></div>
                        <small class="text-muted">Empresas declarantes</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-3 fw-bold text-success"><?= $fmt($totales['total_empleados']) ?></div>
                        <small class="text-muted">Empleados totales</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-3 fw-bold text-warning"><?= $fmt($totales['energia']) ?></div>
                        <small class="text-muted">kWh energia</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center p-3 bg-light rounded">
                        <div class="fs-3 fw-bold text-danger"><?= $fmt($totales['co2'], 1) ?></div>
                        <small class="text-muted">tCO2e emisiones</small>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Empresa</th>
                            <th>Rubro</th>
                            <th class="text-end">Empleados</th>
                            <th class="text-end">Energia kWh</th>
                            <th class="text-end">Agua m3</th>
                            <th class="text-end">Gas m3</th>
                            <th class="text-end">CO2 t</th>
                            <th class="text-end">Cap. %</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($detalle as $d): ?>
                        <tr>
                            <td><?= e($d['nombre']) ?></td>
                            <td><?= e($d['rubro'] ?? '-') ?></td>
                            <td class="text-end"><?= $fmt($d['dotacion_total']) ?></td>
                            <td class="text-end"><?= $fmt($d['consumo_energia']) ?></td>
                            <td class="text-end"><?= $fmt($d['consumo_agua'], 1) ?></td>
                            <td class="text-end"><?= $fmt($d['consumo_gas'], 1) ?></td>
                            <td class="text-end"><?= $fmt($d['emisiones_co2'], 1) ?></td>
                            <td class="text-end"><?= $fmt($d['porcentaje_capacidad_uso'], 1) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php
    require_once BASEPATH . '/includes/ministerio_layout_footer.php';
    exit;
endif;

// --- MODO IMPRIMIR: HTML autónomo optimizado para print/PDF ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte Trimestral — <?= e($periodo) ?></title>
<style>
    :root { --azul: #1a3a5c; --azul-claro: #2563a8; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 11px; color: #212529; background: #fff; }
    .page { max-width: 800px; margin: 0 auto; padding: 20px 30px; }

    .header {
        background: linear-gradient(135deg, var(--azul) 0%, var(--azul-claro) 100%);
        color: #fff; padding: 20px 30px; margin: -20px -30px 20px;
    }
    .header h1 { font-size: 1.3rem; margin-bottom: 2px; }
    .header .sub { font-size: .85rem; opacity: .8; }
    .header .periodo { float: right; background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.35); border-radius: 6px; padding: 4px 14px; font-size: 1rem; font-weight: 700; }

    h2 { font-size: .95rem; color: var(--azul); margin: 18px 0 10px; padding-bottom: 4px; border-bottom: 2px solid #e0e0e0; }
    .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
    .kpi { background: #f0f6ff; border-radius: 8px; padding: 12px; text-align: center; }
    .kpi .val { font-size: 1.4rem; font-weight: 700; color: var(--azul); }
    .kpi .lab { font-size: .75rem; color: #666; margin-top: 2px; }

    table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 14px; }
    th { background: var(--azul); color: #fff; padding: 5px 6px; text-align: left; font-weight: 600; }
    td { padding: 4px 6px; border-bottom: 1px solid #e0e0e0; }
    tr:nth-child(even) td { background: #fafafa; }
    .text-end { text-align: right; }

    .footer { text-align: center; color: #999; font-size: .7rem; margin-top: 24px; padding-top: 10px; border-top: 1px solid #e0e0e0; }

    .no-print { text-align: center; margin-bottom: 16px; }
    .no-print button { padding: 8px 24px; font-size: 14px; cursor: pointer; background: var(--azul); color: #fff; border: none; border-radius: 6px; }

    @media print {
        .no-print { display: none; }
        body { font-size: 10px; }
        .page { padding: 0; max-width: 100%; }
        .header { margin: 0 0 14px; padding: 14px 20px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="no-print">
        <button onclick="window.print()">🖨️ Imprimir / Guardar como PDF</button>
    </div>

    <div class="header">
        <span class="periodo"><?= e($periodo) ?></span>
        <h1>Reporte Trimestral — Parque Industrial de Catamarca</h1>
        <div class="sub">Ministerio de Produccion · Generado el <?= date('d/m/Y H:i') ?></div>
    </div>

    <h2>Indicadores Generales</h2>
    <div class="kpi-grid">
        <div class="kpi">
            <div class="val"><?= $totales['total_empresas'] ?>/<?= $total_activas ?></div>
            <div class="lab">Empresas declarantes (<?= $tasa_respuesta ?>%)</div>
        </div>
        <div class="kpi">
            <div class="val"><?= $fmt($totales['total_empleados']) ?></div>
            <div class="lab">Empleados totales</div>
        </div>
        <div class="kpi">
            <div class="val"><?= $fmt($totales['emp_masc']) ?> / <?= $fmt($totales['emp_fem']) ?></div>
            <div class="lab">Masculinos / Femeninos</div>
        </div>
        <div class="kpi">
            <div class="val"><?= $fmt($totales['cap_promedio'], 1) ?>%</div>
            <div class="lab">Capacidad instalada prom.</div>
        </div>
    </div>

    <h2>Consumos y Emisiones</h2>
    <div class="kpi-grid">
        <div class="kpi">
            <div class="val"><?= $fmt($totales['energia']) ?></div>
            <div class="lab">kWh Energia</div>
        </div>
        <div class="kpi">
            <div class="val"><?= $fmt($totales['agua']) ?></div>
            <div class="lab">m3 Agua</div>
        </div>
        <div class="kpi">
            <div class="val"><?= $fmt($totales['gas']) ?></div>
            <div class="lab">m3 Gas</div>
        </div>
        <div class="kpi">
            <div class="val"><?= $fmt($totales['co2'], 1) ?></div>
            <div class="lab">tCO2e Emisiones</div>
        </div>
    </div>

    <?php if (!empty($por_rubro)): ?>
    <h2>Desglose por Rubro</h2>
    <table>
        <tr><th>Rubro</th><th class="text-end">Empresas</th><th class="text-end">Empleados</th><th class="text-end">Energia kWh</th><th class="text-end">Agua m3</th><th class="text-end">Gas m3</th></tr>
        <?php foreach ($por_rubro as $r): ?>
        <tr>
            <td><?= e($r['rubro'] ?? 'Sin rubro') ?></td>
            <td class="text-end"><?= $r['empresas'] ?></td>
            <td class="text-end"><?= $fmt($r['empleados']) ?></td>
            <td class="text-end"><?= $fmt($r['energia']) ?></td>
            <td class="text-end"><?= $fmt($r['agua'], 1) ?></td>
            <td class="text-end"><?= $fmt($r['gas'], 1) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if (!empty($detalle)): ?>
    <h2>Detalle por Empresa</h2>
    <table>
        <tr>
            <th>Empresa</th><th>Rubro</th>
            <th class="text-end">Empl.</th>
            <th class="text-end">Energia</th><th class="text-end">Agua</th><th class="text-end">Gas</th>
            <th class="text-end">CO2</th><th class="text-end">Cap.%</th>
            <th>Exp.</th><th>Imp.</th>
        </tr>
        <?php foreach ($detalle as $d): ?>
        <tr>
            <td><?= e($d['nombre']) ?></td>
            <td><?= e($d['rubro'] ?? '-') ?></td>
            <td class="text-end"><?= $fmt($d['dotacion_total']) ?></td>
            <td class="text-end"><?= $fmt($d['consumo_energia']) ?></td>
            <td class="text-end"><?= $fmt($d['consumo_agua'], 1) ?></td>
            <td class="text-end"><?= $fmt($d['consumo_gas'], 1) ?></td>
            <td class="text-end"><?= $fmt($d['emisiones_co2'], 1) ?></td>
            <td class="text-end"><?= $fmt($d['porcentaje_capacidad_uso'], 1) ?></td>
            <td><?= $d['exporta'] ? 'Si' : 'No' ?></td>
            <td><?= $d['importa'] ? 'Si' : 'No' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <div class="footer">
        Parque Industrial de Catamarca — Ministerio de Produccion<br>
        Reporte generado automaticamente el <?= date('d/m/Y') ?> a las <?= date('H:i') ?>
    </div>
</div>
</body>
</html>
