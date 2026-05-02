<?php
/**
 * Cambiar contraseña - Panel Empresa
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Cambiar contraseña';
$empresa_nav = '';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido. Recargue la página.';
    } else {
        $actual      = $_POST['password_actual']      ?? '';
        $nueva       = $_POST['password_nueva']       ?? '';
        $confirmacion = $_POST['password_confirmacion'] ?? '';

        if ($actual === '' || $nueva === '' || $confirmacion === '') {
            $error = 'Complete todos los campos.';
        } elseif (strlen($nueva) < 8) {
            $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
        } elseif ($nueva !== $confirmacion) {
            $error = 'La nueva contraseña y su confirmación no coinciden.';
        } else {
            $resultado = $auth->changePassword((int) $_SESSION['user_id'], $actual, $nueva);
            if ($resultado['success']) {
                set_flash('success', 'Contraseña actualizada correctamente.');
                redirect('cambiar-contrasena.php');
            } else {
                $error = $resultado['error'];
            }
        }
    }
}

require_once BASEPATH . '/includes/empresa_layout_header.php';
?>

<div class="container-fluid px-0">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">

            <?php if ($success): ?>
                <div class="alert alert-success"><?= e($success) ?></div>
            <?php endif; ?>
            <?php show_flash(); ?>

            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fa-solid fa-lock me-2"></i>Cambiar contraseña</h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form id="form-cambiar-pass" method="post" autocomplete="off" novalidate>
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="password_actual" class="form-label">Contraseña actual</label>
                            <input type="password" id="password_actual" name="password_actual"
                                   class="form-control" required autocomplete="current-password">
                        </div>

                        <div class="mb-3">
                            <label for="password_nueva" class="form-label">Nueva contraseña</label>
                            <input type="password" id="password_nueva" name="password_nueva"
                                   class="form-control" required minlength="8" autocomplete="new-password">
                            <div class="form-text">Mínimo 8 caracteres.</div>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmacion" class="form-label">Confirmar nueva contraseña</label>
                            <input type="password" id="password_confirmacion" name="password_confirmacion"
                                   class="form-control" required minlength="8" autocomplete="new-password">
                        </div>

                        <div class="d-grid gap-2 d-sm-flex justify-content-sm-end">
                            <a href="perfil.php" class="btn btn-secondary">Cancelar</a>
                            <button type="button" class="btn btn-primary" id="btn-abrir-confirm">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Guardar contraseña
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal de confirmación -->
<div class="modal fade" id="modalConfirmarPass" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-shield-halved me-2 text-warning"></i>Confirmar cambio de contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro de que desea cambiar su contraseña?</p>
                <p class="mb-0 text-muted small">
                    <i class="fa-solid fa-info-circle me-1"></i>
                    Tras confirmar, deberá usar la nueva contraseña en su próximo inicio de sesión. La contraseña anterior dejará de ser válida.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-pass">
                    <i class="fa-solid fa-check me-1"></i> Sí, cambiar contraseña
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$extra_scripts = <<<'JSEOF'
<script>
(function () {
    const form        = document.getElementById('form-cambiar-pass');
    const btnAbrir    = document.getElementById('btn-abrir-confirm');
    const btnConfirm  = document.getElementById('btn-confirmar-pass');
    const modalEl     = document.getElementById('modalConfirmarPass');
    const modal       = new bootstrap.Modal(modalEl);

    btnAbrir.addEventListener('click', function () {
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        const nueva   = document.getElementById('password_nueva').value;
        const confirm = document.getElementById('password_confirmacion').value;
        if (nueva !== confirm) {
            alert('La nueva contraseña y su confirmación no coinciden.');
            return;
        }
        modal.show();
    });

    btnConfirm.addEventListener('click', function () {
        modal.hide();
        form.submit();
    });
})();
</script>
JSEOF;
require_once BASEPATH . '/includes/empresa_layout_footer.php';
?>
