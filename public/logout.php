<?php
/**
 * Logout - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../config/config.php';

$auth->logout();
set_flash('success', 'Sesión cerrada correctamente');
redirect(PUBLIC_URL . '/');
