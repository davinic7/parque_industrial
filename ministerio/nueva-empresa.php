<?php
/**
 * Nueva Empresa - Ministerio
 */
require_once __DIR__ . '/../config/config.php';

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

                    // Crear usuario con contraseña temporal
                    $temp_password = bin2hex(random_bytes(4)); // 8 caracteres hex
                    $result = $auth->register($email_usuario, $temp_password, 'empresa');

                    if (!$result['success']) {
                        throw new Exception($result['error']);
                    }

                    $usuario_id = $result['user_id'];

                    // Crear empresa
                    $stmt = $db->prepare("
                        INSERT INTO empresas (usuario_id, nombre, razon_social, cuit, rubro, ubicacion, direccion,
                            contacto_nombre, telefono, email_contacto, sitio_web, estado)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $usuario_id, $nombre, $razon_social, $cuit, $rubro, $ubicacion, $direccion,
                        $contacto_nombre, $telefono, $email_contacto, $sitio_web, $estado
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

                    $mensaje = "Empresa registrada correctamente. Credenciales de acceso: Email: $email_usuario / Contraseña temporal: $temp_password";
                }
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Error al registrar empresa: " . $e->getMessage());
            $error = 'Error al registrar la empresa. Intente nuevamente.';
        }
    }
}

// Obtener rubros y ubicaciones desde BD
$rubros = $db->query("SELECT DISTINCT nombre FROM rubros WHERE activo = 1 ORDER BY orden, nombre")->fetchAll(PDO::FETCH_COLUMN);
$ubicaciones = $db->query("SELECT nombre FROM ubicaciones WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
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
    <aside class="sidebar">
        <div class="sidebar-header"><span class="text-white fw-bold"><i class="bi bi-building me-2"></i>Ministerio</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="empresas.php"><i class="bi bi-buildings"></i> Empresas</a>
            <a href="nueva-empresa.php" class="active"><i class="bi bi-plus-circle"></i> Nueva Empresa</a>
            <a href="formularios.php"><i class="bi bi-file-earmark-text"></i> Formularios</a>
            <a href="graficos.php"><i class="bi bi-graph-up"></i> Gráficos</a>
            <a href="publicaciones.php"><i class="bi bi-megaphone"></i> Publicaciones</a>
            <hr class="my-3 border-secondary">
            <a href="<?= PUBLIC_URL ?>/" target="_blank"><i class="bi bi-globe"></i> Ver sitio</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1 class="h3 mb-4">Registrar Nueva Empresa</h1>

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
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-info-circle me-2"></i>
                                Se creará una cuenta de usuario. La contraseña temporal se mostrará tras el registro.
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
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
