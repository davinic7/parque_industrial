<?php
/**
 * Formulario público: Presentar proyecto al ministerio (solicitar cita / enviar esquema)
 */
require_once __DIR__ . '/../config/config.php';

$page_title = 'Presentar proyecto al ministerio';
$mensaje = '';
$error = '';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido. Recargue la página.';
    } else {
        $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
        $contacto = trim($_POST['contacto'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $resumen_proyecto = trim($_POST['resumen_proyecto'] ?? '');
        $solicita_cita = isset($_POST['solicita_cita']) ? 1 : 0;

        if (empty($nombre_empresa) || empty($contacto) || empty($email)) {
            $error = 'Complete los campos obligatorios: nombre o empresa, persona de contacto y email.';
        } elseif (!is_valid_email($email)) {
            $error = 'El email ingresado no es válido.';
        } elseif (empty($resumen_proyecto)) {
            $error = 'Debe describir brevemente su proyecto.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO solicitudes_proyecto (nombre_empresa, contacto, email, telefono, resumen_proyecto, solicita_cita) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre_empresa, $contacto, $email, $telefono, $resumen_proyecto, $solicita_cita]);
                $mensaje = 'Su solicitud fue enviada correctamente. El Ministerio se pondrá en contacto a la brevedad.';
                $_POST = []; // limpiar para no rellenar
            } catch (Exception $e) {
                error_log("presentar-proyecto: " . $e->getMessage());
                $error = 'No se pudo enviar la solicitud. Intente nuevamente.';
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h1 class="text-center mb-4">Presentar proyecto al ministerio</h1>
                <p class="text-center text-muted mb-4">Complete el formulario con un esquema de su proyecto. Puede solicitar una cita presencial con el encargado del ministerio.</p>

                <?php if ($mensaje): ?>
                <div class="alert alert-success"><?= e($mensaje) ?></div>
                <p class="text-center"><a href="<?= PUBLIC_URL ?>/nosotros.php" class="btn btn-primary">Volver a Nosotros</a></p>
                <?php else: ?>
                <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <form method="POST">
                            <?= csrf_field() ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre o empresa / emprendimiento *</label>
                                    <input type="text" name="nombre_empresa" class="form-control" required value="<?= e($_POST['nombre_empresa'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Persona de contacto *</label>
                                    <input type="text" name="contacto" class="form-control" required value="<?= e($_POST['contacto'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono</label>
                                    <input type="tel" name="telefono" class="form-control" value="<?= e($_POST['telefono'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Resumen del proyecto *</label>
                                    <textarea name="resumen_proyecto" class="form-control" rows="5" required placeholder="Describa brevemente su proyecto o idea..."><?= e($_POST['resumen_proyecto'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input type="checkbox" name="solicita_cita" class="form-check-input" id="solicita_cita" value="1" <?= isset($_POST['solicita_cita']) ? 'checked' : 'checked' ?>>
                                        <label class="form-check-label" for="solicita_cita">Solicitar cita presencial con el encargado del ministerio</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-send me-2"></i>Enviar solicitud</button>
                                    <a href="<?= PUBLIC_URL ?>/nosotros.php" class="btn btn-outline-secondary">Cancelar</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
