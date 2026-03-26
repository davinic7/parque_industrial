<?php
/**
 * Login - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../config/config.php';

if ($auth->isLoggedIn()) {
    redirect($_SESSION['user_rol'] === 'empresa' ? EMPRESA_URL . '/dashboard.php' : MINISTERIO_URL . '/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido. Intente nuevamente.';
    }
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!$error && (empty($email) || empty($password))) {
        $error = 'Complete todos los campos';
    } elseif (!$error) {
        $result = $auth->login($email, $password);
        if ($result['success']) {
            redirect($result['user']['rol'] === 'empresa' ? EMPRESA_URL . '/dashboard.php' : MINISTERIO_URL . '/dashboard.php');
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar - Parque Industrial de Catamarca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; background: linear-gradient(135deg, #1a5276, #0e3a52); }
        .login-box { width: 100%; max-width: 400px; margin: auto; padding: 20px; }
        .login-card { background: #fff; border-radius: 16px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .login-card h1 { font-size: 1.4rem; color: #1a5276; text-align: center; margin-bottom: 30px; }
        .form-control { padding: 12px; border-radius: 8px; }
        .btn-login { background: #1a5276; border: none; padding: 12px; font-weight: 600; border-radius: 8px; width: 100%; }
        .btn-login:hover { background: #0e3a52; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: rgba(255,255,255,0.8); }
    </style>
</head>
<body>
<div class="login-box">
    <div class="login-card">
        <h1><i class="bi bi-building me-2"></i>Parque Industrial</h1>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary btn-login">Ingresar</button>
        </form>
        
        <div class="text-center mt-3">
            <a href="recuperar.php" class="text-muted small">¿Olvidaste tu contraseña?</a>
        </div>
    </div>
    <a href="<?= PUBLIC_URL ?>/" class="back-link"><i class="bi bi-arrow-left me-1"></i>Volver al inicio</a>
</div>
</body>
</html>
