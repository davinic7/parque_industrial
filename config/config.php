<?php
/**
 * Configuración General del Sistema
 * Parque Industrial de Catamarca
 */

// Definir constante de acceso
define('BASEPATH', dirname(__DIR__));

// Configuración de errores (cambiar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1); // Cambiar a 0 en producción
ini_set('log_errors', 1);
ini_set('error_log', BASEPATH . '/logs/error.log');

// Zona horaria
date_default_timezone_set('America/Argentina/Catamarca');

// Configuración de sesiones
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Cambiar a 1 si usas HTTPS

// URLs base
define('SITE_URL', 'http://localhost/parque_industrial');
define('PUBLIC_URL', SITE_URL . '/public');
define('EMPRESA_URL', SITE_URL . '/empresa');
define('MINISTERIO_URL', SITE_URL . '/ministerio');

// Rutas de archivos
define('UPLOADS_PATH', BASEPATH . '/public/uploads');
define('UPLOADS_URL', PUBLIC_URL . '/uploads');

// Configuración de archivos
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_DOC_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Configuración de seguridad
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 3600); // 1 hora
define('LOGIN_ATTEMPTS_MAX', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

// Configuración de paginación
define('ITEMS_PER_PAGE', 12);
define('ADMIN_ITEMS_PER_PAGE', 20);

// Configuración del mapa
define('MAP_DEFAULT_LAT', -28.4696);
define('MAP_DEFAULT_LNG', -65.7795);
define('MAP_DEFAULT_ZOOM', 12);

// Cargar configuración de base de datos
require_once BASEPATH . '/config/database.php';

// Cargar funciones helper
require_once BASEPATH . '/includes/funciones.php';

// Cargar clase de autenticación
require_once BASEPATH . '/includes/auth.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generar token CSRF si no existe
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
