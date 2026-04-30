<?php
/**
 * Gestión de Empresas - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Gestión de Empresas';
$db = getDB();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';
    $emp_id = (int)($_POST['empresa_id'] ?? 0);

    if ($emp_id > 0 && in_array($accion, ['activar', 'suspender', 'inactivar'])) {
        $estados = ['activar' => 'activa', 'suspender' => 'suspendida', 'inactivar' => 'inactiva'];
        $nuevo_estado = $estados[$accion];
        $stmt = $db->prepare("UPDATE empresas SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $emp_id]);
        log_activity("empresa_$accion", 'empresas', $emp_id);
        set_flash('success', "Estado de la empresa actualizado a: $nuevo_estado");
        redirect('empresas.php?' . http_build_query($_GET));
    }

    // Reset de contraseña: el ministerio NO ve ni elige la nueva contraseña.
    // Genera un token de recuperación y se lo envía por email al usuario titular.
    if ($emp_id > 0 && $accion === 'resetear_password') {
        $stmt = $db->prepare("
            SELECT u.id, u.email
            FROM empresas e
            JOIN usuarios u ON u.id = e.usuario_id
            WHERE e.id = ? AND u.activo = 1
        ");
        $stmt->execute([$emp_id]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            set_flash('error', 'La empresa no tiene un usuario activo asociado.');
        } else {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $db->prepare("UPDATE usuarios SET token_recuperacion = ?, token_expira = ? WHERE id = ?")
               ->execute([$token, $expiry, $usuario['id']]);

            $reset_link = rtrim(PUBLIC_URL, '/') . '/recuperar.php?token=' . urlencode($token);
            $enviado = can_send_mail() && enviar_email_recuperacion_password((string)$usuario['email'], $reset_link);

            log_activity('reset_password_solicitado_ministerio', 'usuarios', $usuario['id']);

            if ($enviado) {
                set_flash('success', "Se envió un email de recuperación a {$usuario['email']}. El token expira en 1 hora.");
            } else {
                set_flash('warning', "Token generado pero no se pudo enviar el email. Comparta este enlace en privado: $reset_link");
            }
        }
        redirect('empresas.php?' . http_build_query($_GET));
    }
}

// Filtros
$buscar = trim($_GET['buscar'] ?? '');
$filtro_rubro = trim($_GET['rubro'] ?? '');
$filtro_estado = trim($_GET['estado'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

$where = [];
$params = [];

if ($buscar !== '') {
    $where[] = "(e.nombre LIKE ? OR e.cuit LIKE ? OR e.contacto_nombre LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}
if ($filtro_rubro !== '') {
    $where[] = "e.rubro = ?";
    $params[] = $filtro_rubro;
}
if ($filtro_estado !== '') {
    $where[] = "e.estado = ?";
    $params[] = $filtro_estado;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT COUNT(*) FROM empresas e $where_sql");
$stmt->execute($params);
$total = $stmt->fetchColumn();

$pagination = paginate($total, ADMIN_ITEMS_PER_PAGE, $pagina, 'empresas.php?' . http_build_query(array_merge($_GET, ['pagina' => '{page}'])));
$offset = ($pagination['current_page'] - 1) * ADMIN_ITEMS_PER_PAGE;

$stmt = $db->prepare("
    SELECT e.*,
        (SELECT de.estado FROM datos_empresa de WHERE de.empresa_id = e.id ORDER BY de.periodo DESC LIMIT 1) as form_estado,
        u.email as usuario_email,
        u.activo as usuario_activo,
        u.token_activacion
    FROM empresas e
    LEFT JOIN usuarios u ON u.id = e.usuario_id
    $where_sql
    ORDER BY e.nombre ASC
    LIMIT " . ADMIN_ITEMS_PER_PAGE . " OFFSET $offset
");
$stmt->execute($params);
$empresas = $stmt->fetchAll();

$rubros = $db->query("SELECT DISTINCT rubro FROM empresas WHERE rubro IS NOT NULL AND rubro != '' ORDER BY rubro")->fetchAll(PDO::FETCH_COLUMN);

$ministerio_nav = 'empresas';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Gestión de Empresas <span class="badge bg-primary"><?= $total ?></span></h1>
            <a href="nueva-empresa.php" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Nueva Empresa</a>
        </div>

        <?php show_flash(); ?>

        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-end" method="GET">
                    <div class="col-md-3">
                        <input type="text" name="buscar" class="form-control" placeholder="Buscar empresa..." value="<?= e($buscar) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="rubro" class="form-select">
                            <option value="">Todos los rubros</option>
                            <?php foreach ($rubros as $r): ?>
                            <option value="<?= e($r) ?>" <?= $filtro_rubro === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="estado" class="form-select">
                            <option value="">Todos los estados</option>
                            <option value="activa" <?= $filtro_estado === 'activa' ? 'selected' : '' ?>>Activa</option>
                            <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="suspendida" <?= $filtro_estado === 'suspendida' ? 'selected' : '' ?>>Suspendida</option>
                            <option value="inactiva" <?= $filtro_estado === 'inactiva' ? 'selected' : '' ?>>Inactiva</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Buscar</button>
                        <a href="empresas.php" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-container">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Empresa</th><th>Rubro</th><th>Ubicación</th><th>Estado</th><th>Formulario</th><th>Visitas</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($empresas)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No se encontraron empresas</td></tr>
                    <?php endif; ?>
                    <?php foreach ($empresas as $emp): ?>
                    <tr>
                        <td>
                            <strong><?= e($emp['nombre']) ?></strong>
                            <?php if ($emp['cuit']): ?><br><small class="text-muted"><?= e($emp['cuit']) ?></small><?php endif; ?>
                        </td>
                        <td><?= e($emp['rubro'] ?? '-') ?></td>
                        <td><?= e($emp['ubicacion'] ?? '-') ?></td>
                        <td>
                            <?php $badge_estado = ['activa' => 'bg-success', 'pendiente' => 'bg-warning text-dark', 'suspendida' => 'bg-danger', 'inactiva' => 'bg-secondary']; ?>
                            <span class="badge <?= $badge_estado[$emp['estado']] ?? 'bg-secondary' ?>"><?= ucfirst($emp['estado']) ?></span>
                        </td>
                        <td>
                            <?php if ($emp['form_estado']): ?>
                                <?php $badge_form = ['borrador' => 'bg-secondary', 'enviado' => 'bg-warning text-dark', 'aprobado' => 'bg-success', 'rechazado' => 'bg-danger']; ?>
                                <span class="badge <?= $badge_form[$emp['form_estado']] ?? 'bg-secondary' ?>"><?= ucfirst($emp['form_estado']) ?></span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark">Sin datos</span>
                            <?php endif; ?>
                        </td>
                        <td><?= format_number($emp['visitas'] ?? 0) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="empresa-detalle.php?id=<?= $emp['id'] ?>" class="btn btn-outline-primary" title="Ver detalle"><i class="bi bi-eye"></i></a>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="Acciones"></button>
                                    <ul class="dropdown-menu">
                                        <?php if (!empty($emp['token_activacion']) && !$emp['usuario_activo']): ?>
                                        <?php $url_act = rtrim(PUBLIC_URL, '/') . '/activar-cuenta.php?token=' . urlencode($emp['token_activacion']); ?>
                                        <li>
                                            <button type="button" class="dropdown-item text-primary btn-copiar-link"
                                                    data-url="<?= e($url_act) ?>">
                                                <i class="bi bi-link-45deg me-2"></i>Copiar enlace de activación
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <?php endif; ?>
                                        <li><form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="empresa_id" value="<?= $emp['id'] ?>"><button name="accion" value="activar" class="dropdown-item"><i class="bi bi-check-circle me-2"></i>Activar</button></form></li>
                                        <li><form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="empresa_id" value="<?= $emp['id'] ?>"><button name="accion" value="suspender" class="dropdown-item"><i class="bi bi-pause-circle me-2"></i>Suspender</button></form></li>
                                        <?php if (!empty($emp['usuario_activo'])): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="empresa_id" value="<?= $emp['id'] ?>"><button name="accion" value="resetear_password" class="dropdown-item" onclick="return confirm('¿Enviar email de recuperación de contraseña al usuario titular de esta empresa?')"><i class="bi bi-key me-2"></i>Enviar reset de contraseña</button></form></li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="empresa_id" value="<?= $emp['id'] ?>"><button name="accion" value="inactivar" class="dropdown-item text-danger" onclick="return confirm('¿Desactivar esta empresa?')"><i class="bi bi-x-circle me-2"></i>Desactivar</button></form></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= render_pagination($pagination) ?>

<?php
$extra_scripts = <<<'JS'
<script>
document.querySelectorAll('.btn-copiar-link').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var url = this.dataset.url;
        navigator.clipboard.writeText(url).then(function() {
            var orig = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2 me-2"></i>¡Copiado!';
            setTimeout(function() { btn.innerHTML = orig; }, 2000);
        }).catch(function() {
            prompt('Copiá el enlace manualmente:', url);
        });
    });
});
</script>
JS;
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
