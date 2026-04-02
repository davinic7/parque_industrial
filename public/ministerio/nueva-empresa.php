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
            $nombre = trim($_POST['nombre'] ?? '');
            $razon_social = trim($_POST['razon_social'] ?? '');
            $cuit = trim($_POST['cuit'] ?? '');
            $rubro = trim($_POST['rubro'] ?? '');
            $ubicacion = trim($_POST['ubicacion'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');
            $latitud = !empty($_POST['latitud']) ? (float)$_POST['latitud'] : null;
            $longitud = !empty($_POST['longitud']) ? (float)$_POST['longitud'] : null;
            $contacto_nombre = trim($_POST['contacto_nombre'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email_contacto = trim($_POST['email_contacto'] ?? '');
            $sitio_web = trim($_POST['sitio_web'] ?? '');
            $email_usuario = trim($_POST['email_usuario'] ?? '');
            $estado = $_POST['estado'] ?? 'pendiente';

            // Validaciones
            if (empty($nombre)) {
                $error = 'El nombre comercial es obligatorio.';
            } elseif (empty($rubro)) {
                $error = 'Debe seleccionar un rubro.';
            } elseif (empty($email_usuario) || !is_valid_email($email_usuario)) {
                $error = 'Debe ingresar un email de acceso válido.';
            } elseif (!empty($cuit) && !is_valid_cuit($cuit)) {
                $error = 'El CUIT ingresado no es válido.';
            } else {
                // Verificar que el email no exista
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$email_usuario]);
                if ($stmt->fetch()) {
                    $error = 'El email de acceso ya está registrado en el sistema.';
                } else {
                    $db->beginTransaction();

                    $modo_registro = $_POST['modo_registro'] ?? 'activacion_email';
                    $usuario_id = null;
                    $temp_password = null;
                    $token_activacion = null;

                    if ($modo_registro === 'activacion_email') {
                        $token_activacion = bin2hex(random_bytes(32));
                        $token_exp = date('Y-m-d H:i:s', strtotime('+48 hours'));
                        $result = $auth->registerEmpresaPending($email_usuario, $token_activacion, $token_exp);
                        if (!$result['success']) {
                            throw new Exception($result['error']);
                        }
                        $usuario_id = $result['user_id'];
                    } else {
                        $temp_password = bin2hex(random_bytes(4));
                        $result = $auth->register($email_usuario, $temp_password, 'empresa');
                        if (!$result['success']) {
                            throw new Exception($result['error']);
                        }
                        $usuario_id = $result['user_id'];
                    }

                    // Crear empresa
                    $stmt = $db->prepare("
                        INSERT INTO empresas (usuario_id, nombre, razon_social, cuit, rubro, ubicacion, direccion,
                            latitud, longitud, contacto_nombre, telefono, email_contacto, sitio_web, estado)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $usuario_id, $nombre, $razon_social, $cuit, $rubro, $ubicacion, $direccion,
                        $latitud, $longitud, $contacto_nombre, $telefono, $email_contacto, $sitio_web, $estado
                    ]);

                    $empresa_id = $db->lastInsertId();

                    // Si se solicita formulario, crear notificación
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
                        $mensaje = "Empresa registrada correctamente. Credenciales de acceso: Email: $email_usuario / Contraseña temporal: $temp_password";
                        if (!empty($_POST['enviar_credenciales_email'])) {
                            $url_login = defined('PUBLIC_URL') ? (PUBLIC_URL . '/login.php') : '';
                            if (enviar_email_credenciales_empresa($email_usuario, $nombre, $temp_password, $url_login)) {
                                $mensaje .= " Se envió un email con las credenciales al usuario.";
                            } else {
                                $mensaje .= " No se pudo enviar el email (revise la configuración del servidor); las credenciales se muestran aquí.";
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
                $error = 'Error de base de datos (falta alguna tabla o columna). Revise las migraciones en Aiven.';
            } else {
                $error = 'Error al registrar la empresa: ' . (strlen($msg) > 120 ? substr($msg, 0, 120) . '…' : $msg);
            }
        }
    }
}

// Obtener rubros y ubicaciones desde BD
// En MySQL 8 (modo estricto) no se puede usar DISTINCT con ORDER BY en una columna no seleccionada
// por eso se quita DISTINCT y se ordena por orden, nombre
$rubros = $db->query("SELECT nombre FROM rubros WHERE activo = 1 ORDER BY orden, nombre")->fetchAll(PDO::FETCH_COLUMN);
$ubicaciones = $db->query("SELECT nombre FROM ubicaciones WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

$ministerio_nav = 'nueva_empresa';
$extra_head = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">';
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
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white"><h5 class="mb-0">Datos de la Empresa</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre comercial *</label>
                                    <input type="text" name="nombre" class="form-control" required value="<?= e($_POST['nombre'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Razón social</label>
                                    <input type="text" name="razon_social" class="form-control" value="<?= e($_POST['razon_social'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">CUIT</label>
                                    <input type="text" name="cuit" class="form-control" placeholder="XX-XXXXXXXX-X" value="<?= e($_POST['cuit'] ?? '') ?>">
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
                                <div class="col-md-6">
                                    <label class="form-label">Ubicación</label>
                                    <select name="ubicacion" class="form-select">
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($ubicaciones as $ub): ?>
                                        <option value="<?= e($ub) ?>" <?= ($_POST['ubicacion'] ?? '') === $ub ? 'selected' : '' ?>><?= e($ub) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Dirección</label>
                                    <input type="text" name="direccion" class="form-control" value="<?= e($_POST['direccion'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Latitud</label>
                                    <input type="number" name="latitud" id="latitud" class="form-control" step="any" value="<?= e($_POST['latitud'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Longitud</label>
                                    <input type="number" name="longitud" id="longitud" class="form-control" step="any" value="<?= e($_POST['longitud'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">
                                    <div id="mapNueva" style="height: 300px; border-radius: 8px;"></div>
                                    <small class="text-muted">Haga clic en el mapa para fijar la ubicación de la empresa</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white"><h5 class="mb-0">Información de Contacto</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Persona de contacto *</label>
                                    <input type="text" name="contacto_nombre" class="form-control" required value="<?= e($_POST['contacto_nombre'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono *</label>
                                    <input type="tel" name="telefono" class="form-control" required value="<?= e($_POST['telefono'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email de contacto</label>
                                    <input type="email" name="email_contacto" class="form-control" value="<?= e($_POST['email_contacto'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sitio web</label>
                                    <input type="url" name="sitio_web" class="form-control" placeholder="https://" value="<?= e($_POST['sitio_web'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-success text-white"><h5 class="mb-0">Acceso al Sistema</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label d-block">Modo de alta de usuario</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="modo_registro" id="modoActivacion" value="activacion_email" <?= ($_POST['modo_registro'] ?? 'activacion_email') !== 'password_temporal' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="modoActivacion">Activación por email (recomendado): la empresa define su contraseña con un enlace seguro</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="modo_registro" id="modoTemp" value="password_temporal" <?= ($_POST['modo_registro'] ?? '') === 'password_temporal' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="modoTemp">Contraseña temporal (clásico): se genera y se muestra o envía por email</label>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email de acceso *</label>
                                    <input type="email" name="email_usuario" class="form-control" required value="<?= e($_POST['email_usuario'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Estado inicial</label>
                                    <select name="estado" class="form-select">
                                        <option value="pendiente">Pendiente de verificación</option>
                                        <option value="activa">Activa</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-white"><h5 class="mb-0">Opciones</h5></div>
                        <div class="card-body">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="solicitar_formulario" class="form-check-input" id="solicitarForm" checked>
                                <label class="form-check-label" for="solicitarForm">Solicitar completar formulario</label>
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" name="enviar_credenciales_email" class="form-check-input" id="enviarEmail" checked>
                                <label class="form-check-label" for="enviarEmail">Enviar email al usuario (activación o credenciales según el modo elegido)</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="perfil_publico" class="form-check-input" id="perfilPublico">
                                <label class="form-check-label" for="perfilPublico">Perfil público inmediato</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-plus-lg me-2"></i>Registrar Empresa
                        </button>
                        <a href="empresas.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </div>
        </form>

<?php
$pu = htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8');
$mlat = (float) MAP_DEFAULT_LAT;
$mlng = (float) MAP_DEFAULT_LNG;
$mzoom = (int) MAP_DEFAULT_ZOOM;
$extra_scripts = <<<HTML
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
    <script src="{$pu}/js/parque-leaflet.js"></script>
    <script>
        const map = L.map('mapNueva').setView([{$mlat}, {$mlng}], {$mzoom});
        ParqueLeaflet.addSatelliteLayer(map);
        let marker = null;
        map.on('click', function(e) {
            if (marker) {
                marker.setLatLng(e.latlng);
            } else {
                marker = L.marker(e.latlng, {draggable: true}).addTo(map);
                marker.on('dragend', function(ev) {
                    const pos = ev.target.getLatLng();
                    document.getElementById('latitud').value = pos.lat.toFixed(6);
                    document.getElementById('longitud').value = pos.lng.toFixed(6);
                });
            }
            document.getElementById('latitud').value = e.latlng.lat.toFixed(6);
            document.getElementById('longitud').value = e.latlng.lng.toFixed(6);
        });
    </script>
HTML;
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
