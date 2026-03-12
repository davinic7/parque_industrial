<?php
/**
 * Configuración General del Sistema
 * Parque Industrial de Catamarca
 */

// Definir constante de acceso
define('BASEPATH', dirname(__DIR__));

// Cargar variables de entorno desde .env si existe
$env_file = BASEPATH . '/.env';
if (is_readable($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($value === '') {
            continue;
        }
        $first = isset($value[0]) ? $value[0] : '';
        $last = isset($value[strlen($value) - 1]) ? $value[strlen($value) - 1] : '';
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

// Helper de entorno
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

function env_bool($key, $default = false) {
    $value = env($key, null);
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
}

// Entorno de la app
define('APP_ENV', env('APP_ENV', 'development'));

// Configuración de errores (segun entorno)
$debug_enabled = env_bool('APP_DEBUG', APP_ENV !== 'production');
error_reporting(E_ALL);
ini_set('display_errors', $debug_enabled ? '1' : '0');
ini_set('log_errors', 1);
$log_dir = BASEPATH . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
ini_set('error_log', $log_dir . '/error.log');

// Zona horaria
date_default_timezone_set('America/Argentina/Catamarca');

// Configuración de sesiones
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', env('SESSION_COOKIE_SECURE', APP_ENV === 'production' ? '1' : '0'));

// URLs base - CORREGIDAS PARA DESPLIEGUE
// En Render, como el DocumentRoot apunta a /public, SITE_URL ya es la raíz.
define('SITE_URL', rtrim(env('SITE_URL', 'http://localhost/parque_industrial'), '/'));
define('PUBLIC_URL', APP_ENV === 'production' ? SITE_URL : SITE_URL . '/public');
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
