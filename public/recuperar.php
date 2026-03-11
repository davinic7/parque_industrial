<?php
/**
 * Recuperar Contraseña - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../config/config.php';

$page_title = 'Recuperar Contraseña';
$mensaje = '';
$error = '';
$mostrar_reset = false;

$token = trim($_GET['token'] ?? '');

if ($token) {
    // Validar token
    $db = getDB();
    $stmt = $db->prepare("SELECT id, email FROM usuarios WHERE token_recuperacion = ? AND token_expira > NOW() AND activo = 1");
    $stmt->execute([$token]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        $error = 'El enlace de recuperación no es válido o ha expirado.';
    } else {
        $mostrar_reset = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';

            if (strlen($password) < 8) {
                $error = 'La contraseña debe tener al menos 8 caracteres.';
            } elseif ($password !== $password_confirm) {
                $error = 'Las contraseñas no coinciden.';
            } else {
                $result = $auth->resetPassword($token, $password);
                if ($result['success']) {
                    set_flash('success', 'Contraseña actualizada correctamente. Puede iniciar sesión.');
                    redirect(PUBLIC_URL . '/login.php');
                } else {
                    $error = $result['error'] ?? 'Error al actualizar la contraseña.';
                }
            }
        }
    }
} else {
    // Solicitar recuperación
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !is_valid_email($email)) {
            $error = 'Ingrese un email válido.';
        } else {
            $result = $auth->requestPasswordReset($email);
            // Siempre mostrar éxito por seguridad (no revelar si el email existe)
            $mensaje = 'Si el email está registrado, recibirá instrucciones para restablecer su contraseña. Por favor contacte al administrador si no recibe el correo.';

            if ($result['success'] && isset($result['token']) && defined('APP_ENV') && APP_ENV !== 'production') {
                // En desarrollo mostramos el link directamente
                $reset_link = PUBLIC_URL . '/recuperar.php?token=' . $result['token'];
                $mensaje .= '<br><br><strong>Modo desarrollo - Link de recuperacion:</strong><br><a href="' . e($reset_link) . '">' . e($reset_link) . '</a>';
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="text-center mb-4">
                            <i class="bi bi-key me-2"></i>
                            <?= $mostrar_reset ? 'Nueva Contraseña' : 'Recuperar Contraseña' ?>
                        </h3>

                        <?php if ($mensaje): ?>
                        <div class="alert alert-success"><?= $mensaje ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                        <?php endif; ?>

                        <?php if ($mostrar_reset): ?>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label">Nueva contraseña</label>
                                <input type="password" name="password" class="form-control" required minlength="8" placeholder="Mínimo 8 caracteres">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirmar contraseña</label>
                                <input type="password" name="password_confirm" class="form-control" required minlength="8">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Cambiar contraseña</button>
                        </form>

                        <?php elseif (!$mensaje): ?>
                        <p class="text-muted text-center mb-4">Ingrese su email y le enviaremos instrucciones para recuperar su contraseña.</p>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required placeholder="su@email.com" autofocus>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Enviar instrucciones</button>
                        </form>
                        <?php endif; ?>

                        <div class="text-center mt-3">
                            <a href="login.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Volver al login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
