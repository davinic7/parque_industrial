<?php
/**
 * Configuración de Base de Datos
 * Parque Industrial de Catamarca
 */
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        return ($value !== false) ? $value : $default;
    }
}
// Prevenir acceso directo
if (!defined('BASEPATH')) {
    exit('No se permite el acceso directo al script');
}

// Configuración de la base de datos
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'parque_industrial'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', '')); // Cambiar en producción
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Clase de conexión PDO
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                PDO::MYSQL_ATTR_SSL_CA => __DIR__ . '/ca.pem',
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Error de conexión: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    // Prevenir clonación
    private function __clone() {}
    
    // Prevenir deserialización
    public function __wakeup() {
        throw new Exception("No se puede deserializar singleton");
    }
}

// Función helper para obtener conexión
function getDB() {
    return Database::getInstance()->getConnection();
}
