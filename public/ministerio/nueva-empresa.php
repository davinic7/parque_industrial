<?php
/**
 * Nueva Empresa - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Nueva Empresa';
$mensaje = '';
$error = '';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } else {
        try {
            $nombre       = trim($_POST['nombre'] ?? '');
            $rubro        = trim($_POST['rubro'] ?? '');
            $email_usuario = trim($_POST['email_usuario'] ?? '');
            $estado       = $_POST['estado'] ?? 'pendiente';

            if (empty($nombre)) {
                $error = 'El nombre comercial es obligatorio.';
            } elseif (empty($rubro)) {
                $error = 'Debe seleccionar un rubro.';
            } elseif (empty($email_usuario) || !is_valid_email($email_usuario)) {
                $error = 'Debe ingresar un email de acceso válido.';
            } else {
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$email_usuario]);
                if ($stmt->fetch()) {
                    $error = 'El email de acceso ya está registrado en el sistema.';
                } else {
                    $db->beginTransaction();

                    $modo_registro   = $_POST['modo_registro'] ?? 'activacion_email';
                    $usuario_id      = null;
                    $temp_password   = null;
                    $token_activacion = null;

                    if ($modo_registro === 'activacion_email') {
                        $token_activacion = bin2hex(random_bytes(32));
                        $token_exp = date('Y-m-d H:i:s', strtotime('+48 hours'));
                        $result = $auth->registerEmpresaPending($email_usuario, $token_activacion, $token_exp);
                        if (!$result['success']) throw new Exception($result['error']);
                        $usuario_id = $result['user_id'];
                    } else {
                        $temp_password = bin2hex(random_bytes(4));
                        $result = $auth->register($email_usuario, $temp_password, 'empresa');
                        if (!$result['success']) throw new Exception($result['error']);
                        $usuario_id = $result['user_id'];
                    }

                    $stmt = $db->prepare("
                        INSERT INTO empresas (usuario_id, nombre, rubro, estado)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$usuario_id, $nombre, $rubro, $estado]);
                    $empresa_id = $db->lastInsertId();

                    if (isset($_POST['solicitar_formulario'])) {
                        crear_notificacion(
                            $usuario_id,
                            'formulario_pendiente',
                            'Complete su formulario trimestral',
                            'El Ministerio solicita que complete su declaración jurada trimestral.',
                            EMPRESA_URL . '/formularios.php'
                        );
                    }

                    $db->commit();
                    log_activity('empresa_registrada', 'empresas', $empresa_id);

                    if ($modo_registro === 'activacion_email' && $token_activacion) {
                        $url_act = rtrim(PUBLIC_URL, '/') . '/activar-cuenta.php?token=' . urlencode($token_activacion);
                        $mensaje = 'Empresa registrada. El usuario debe activar la cuenta con el enlace enviado por email.';
                        if (!empty($_POST['enviar_credenciales_email'])) {
                            if (can_send_mail() && enviar_email_activacion_empresa($email_usuario, $nombre, $url_act)) {
                                $mensaje .= ' Email de activación enviado.';
                            } else {
                                $mensaje .= ' No se pudo enviar el email. Enlace de activación: ' . $url_act;
                            }
                        } else {
                            $mensaje .= ' Enlace de activación (guarde o envíe manualmente): ' . $url_act;
                        }
                    } else {
                        $mensaje = "Empresa registrada correctamente. Credenciales de acceso — Email: $email_usuario / Contraseña temporal: $temp_password";
                        if (!empty($_POST['enviar_credenciales_email'])) {
                            $url_login = PUBLIC_URL . '/login.php';
                            if (enviar_email_credenciales_empresa($email_usuario, $nombre, $temp_password, $url_login)) {
                                $mensaje .= ' Se envió un email con las credenciales al usuario.';
                            } else {
                                $mensaje .= ' No se pudo enviar el email; las credenciales se muestran aquí.';
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Error al registrar empresa: " . $e->getMessage());
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'UNIQUE') !== false) {
                $error = 'El email de acceso ya está registrado. Use otro email.';
            } elseif (stripos($msg, 'Column') !== false || stripos($msg, 'Unknown column') !== false) {
                $error = 'Error de base de datos. Revise las migraciones.';
            } else {
                $error = 'Error al registrar la empresa: ' . (strlen($msg) > 120 ? substr($msg, 0, 120) . '…' : $msg);
            }
        }
    }
}

$rubros = $db->query("SELECT nombre FROM rubros WHERE activo = 1 ORDER BY orden, nombre")->fetchAll(PDO::FETCH_COLUMN);

// Prefill desde solicitud (GET params enviados por solicitudes-proyecto.php)
$pre_nombre   = trim($_GET['prefill_nombre']   ?? '');
$pre_email    = trim($_GET['prefill_email']    ?? '');
$pre_contacto = trim($_GET['prefill_contacto'] ?? '');
$pre_telefono = trim($_GET['prefill_telefono'] ?? '');

$ministerio_nav = 'nueva_empresa';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <h2 class="h4 mb-4 fw-semibold">Registrar nueva empresa</h2>

        <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= e($mensaje) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <div class="row g-4">
                <div class="col-lg-8">

                    <!-- Datos mínimos -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white"><h5 class="mb-0">Datos de la Empresa</h5></div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Solo nombre y rubro son necesarios para crear la empresa. La empresa completa el resto de su perfil desde su panel.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre comercial *</label>
                                    <input type="text" name="nombre" class="form-control" required value="<?= e($_POST['nombre'] ?? $pre_nombre) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Rubro *</label>
                                    <select name="rubro" class="form-select" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($rubros as $r): ?>
                                        <option value="<?= e($r) ?>" <?= ($_POST['rubro'] ?? '') === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acceso al sistema -->
                    <div class="card">
                        <div class="card-header bg-success text-white"><h5 class="mb-0">Acceso al Sistema</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label d-block">Modo de alta</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="modo_registro" id="modoActivacion" value="activacion_email"
                                        <?= ($_POST['modo_registro'] ?? 'activacion_email') !== 'password_temporal' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="modoActivacion">
                                        Activación por email <span class="badge bg-success ms-1">Recomendado</span> — la empresa define su contraseña con un enlace seguro
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="modo_registro" id="modoTemp" value="password_temporal"
                                        <?= ($_POST['modo_registro'] ?? '') === 'password_temporal' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="modoTemp">
                                        Contraseña temporal — se genera y se muestra aquí (o se envía por email)
                                    </label>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email de acceso *</label>
                                    <input type="email" name="email_usuario" class="form-control" required value="<?= e($_POST['email_usuario'] ?? $pre_email) ?>">
                                    <?php if ($pre_nombre || $pre_email): ?>
                                    <div class="form-text text-success"><i class="fa-solid fa-circle-check me-1"></i>Datos pre-cargados desde la solicitud.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Estado inicial</label>
                                    <select name="estado" class="form-select">
                                        <option value="pendiente" <?= ($_POST['estado'] ?? 'pendiente') === 'pendiente' ? 'selected' : '' ?>>Pendiente de verificación</option>
                                        <option value="activa" <?= ($_POST['estado'] ?? '') === 'activa' ? 'selected' : '' ?>>Activa</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Opciones y acción -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-white"><h5 class="mb-0">Opciones</h5></div>
                        <div class="card-body">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="solicitar_formulario" class="form-check-input" id="solicitarForm" checked>
                                <label class="form-check-label" for="solicitarForm">Solicitar completar formulario</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="enviar_credenciales_email" class="form-check-input" id="enviarEmail" checked>
                                <label class="form-check-label" for="enviarEmail">Enviar email al usuario (activación o credenciales)</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fa-solid fa-plus me-2"></i>Registrar Empresa
                        </button>
                        <a href="empresas.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </div>
        </form>

<?php require_once BASEPATH . '/includes/ministerio_layout_footer.php'; ?>
