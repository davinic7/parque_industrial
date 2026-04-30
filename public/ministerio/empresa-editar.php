<?php
/**
 * DEPRECATED: Editar Empresa - Ministerio
 *
 * El Ministerio ya no edita los datos de las empresas (principio de integridad de datos).
 * Las empresas administran su propio perfil. El Ministerio solo controla:
 *   - Estado (activar / suspender / inactivar)
 *   - Acceso del usuario (reset de contraseña)
 *
 * Esas acciones viven ahora en empresa-detalle.php y empresas.php.
 * Este archivo se conserva como redirect para que enlaces o bookmarks viejos no rompan.
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$emp_id = (int)($_GET['id'] ?? 0);

set_flash('info', 'La edición de datos de empresas se desactivó. Las empresas administran su propio perfil. Desde acá podés cambiar el estado o enviar un reset de contraseña.');

if ($emp_id > 0) {
    redirect("empresa-detalle.php?id=$emp_id");
}
redirect('empresas.php');
