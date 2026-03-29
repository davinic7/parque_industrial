<?php
/**
 * Activación de cuenta empresa (token por email)
 */
require_once __DIR__ . '/../config/config.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$mostrar_form = false;
$usuario = null;

if ($token === '') {
    $error = 'Enlace de activación inválido.';
} else {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            SELECT id, email
            FROM usuarios
            WHERE token_activacion = ?
              AND token_activacion_expira > NOW()
              AND activo = 0
        ");
        $stmt->execute([$token]);
        $usuario = $stmt->fetch();
    } catch (Exception $e) {
        error_log('activar-cuenta: ' . $e->getMessage());
        $error = 'El sistema no está configurado para activación por enlace. Ejecute la migración SQL 010 o contacte al administrador.';
    }

    if (!$error && !$usuario) {
        $error = 'El enlace no es válido o ha expirado. Solicite al ministerio un nuevo registro o contacto.';
    } elseif ($usuario) {
        $mostrar_form = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';

            if (strlen($password) < 8) {
                $error = 'La contraseña debe tener al menos 8 caracteres.';
            } elseif ($password !== $password_confirm) {
                $error = 'Las contraseñas no coinciden.';
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("
                        UPDATE usuarios
                        SET password = ?,
                            activo = 1,
                            token_activacion = NULL,
                            token_activacion_expira = NULL,
                            email_verificado = 1
                        WHERE id = ? AND token_activacion = ?
                    ");
                    $stmt->execute([$hash, $usuario['id'], $token]);

                    if ($stmt->rowCount() === 0) {
                        $error = 'No se pudo activar la cuenta. Solicite un nuevo enlace.';
                        $mostrar_form = false;
                    } else {
                        log_activity('cuenta_activada', 'usuarios', $usuario['id']);
                        set_flash('success', 'Cuenta activada correctamente. Ya puede iniciar sesión.');
                        redirect(PUBLIC_URL . '/login.php');
                    }
                } catch (Exception $e) {
                    error_log('activar-cuenta POST: ' . $e->getMessage());
                    $error = 'Error al activar. Intente nuevamente.';
                }
            }
        }
    }
}

$page_title = 'Activar cuenta';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - Parque Industrial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; background: linear-gradient(135deg, #1a5276, #0e3a52); }
        .activation-box { width: 100%; max-width: 450px; margin: auto; padding: 20px; }
        .activation-card { background: #fff; border-radius: 16px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
    </style>
</head>
<body>
    <div class="activation-box">
        <div class="activation-card">
            <div class="text-center mb-4">
                <i class="bi bi-shield-check display-4 text-success"></i>
                <h1 class="h4 mt-3">Activar cuenta</h1>
                <p class="text-muted small">Parque Industrial de Catamarca</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
            <div class="text-center">
                <a href="<?= e(PUBLIC_URL) ?>/login.php" class="btn btn-primary">Ir al inicio de sesión</a>
            </div>
            <?php elseif ($mostrar_form && $usuario): ?>
            <div class="alert alert-info small">
                Cuenta: <strong><?= e($usuario['email']) ?></strong>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" name="password" class="form-control" required minlength="8" autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirmar contraseña</label>
                    <input type="password" name="password_confirm" class="form-control" required minlength="8">
                </div>
                <button type="submit" class="btn btn-success w-100 btn-lg">Activar mi cuenta</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
