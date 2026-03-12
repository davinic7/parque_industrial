<?php
/**
 * Sistema de Autenticación
 * Parque Industrial de Catamarca
 */

if (!defined('BASEPATH')) {
    exit('No se permite el acceso directo al script');
}

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Iniciar sesión
     */
    public function login($email, $password) {
        // Verificar intentos de login
        if ($this->isLocked($email)) {
            return ['success' => false, 'error' => 'Cuenta bloqueada temporalmente. Intente en 15 minutos.'];
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, e.id as empresa_id, e.nombre as empresa_nombre
                FROM usuarios u
                LEFT JOIN empresas e ON u.id = e.usuario_id
                WHERE u.email = ? AND u.activo = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password'])) {
                $this->registerFailedAttempt($email);
                return ['success' => false, 'error' => 'Email o contraseña incorrectos'];
            }
            
            // Login exitoso
            $this->clearFailedAttempts($email);
            $this->updateLastAccess($user['id']);
            
            // Establecer sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_rol'] = $user['rol'];
            $_SESSION['empresa_id'] = $user['empresa_id'];
            $_SESSION['empresa_nombre'] = $user['empresa_nombre'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Regenerar ID de sesión por seguridad
            session_regenerate_id(true);
            
            log_activity('login', 'usuarios', $user['id']);
            
            return ['success' => true, 'user' => $user];
            
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error del sistema'];
        }
    }
    
    /**
     * Cerrar sesión
     */
    public function logout() {
        log_activity('logout', 'usuarios', $_SESSION['user_id'] ?? null);
        
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Verificar si está autenticado
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }
        
        // Verificar expiración de sesión
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar rol
     */
    public function hasRole($roles) {
        if (!$this->isLoggedIn()) return false;
        
        if (is_string($roles)) $roles = [$roles];
        
        return in_array($_SESSION['user_rol'], $roles);
    }
    
    /**
     * Requerir autenticación
     */
    public function requireLogin($redirect = null) {
        if (!$this->isLoggedIn()) {
            if ($redirect) {
                set_flash('warning', 'Debe iniciar sesión para acceder');
                redirect($redirect);
            }
            return false;
        }
        return true;
    }
    
    /**
     * Requerir rol específico
     */
    public function requireRole($roles, $redirect = null) {
        if (!$this->requireLogin($redirect)) return false;
        
        if (!$this->hasRole($roles)) {
            if ($redirect) {
                set_flash('error', 'No tiene permisos para acceder a esta sección');
                redirect($redirect);
            }
            return false;
        }
        return true;
    }
    
    /**
     * Registrar usuario
     */
    public function register($email, $password, $rol = 'empresa') {
        try {
            // Verificar si el email ya existe
            $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'El email ya está registrado'];
            }
            
            // Hash de la contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("
                INSERT INTO usuarios (email, password, rol) VALUES (?, ?, ?)
            ");
            $stmt->execute([$email, $password_hash, $rol]);
            
            $user_id = $this->db->lastInsertId();
            
            log_activity('registro', 'usuarios', $user_id);
            
            return ['success' => true, 'user_id' => $user_id];
            
        } catch (Exception $e) {
            error_log("Error en registro: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error al registrar usuario'];
        }
    }
    
    /**
     * Cambiar contraseña
     */
    public function changePassword($user_id, $current_password, $new_password) {
        try {
            $stmt = $this->db->prepare("SELECT password FROM usuarios WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                return ['success' => false, 'error' => 'Contraseña actual incorrecta'];
            }
            
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$new_hash, $user_id]);
            
            log_activity('cambio_password', 'usuarios', $user_id);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error al cambiar contraseña'];
        }
    }
    
    /**
     * Solicitar recuperación de contraseña
     */
    public function requestPasswordReset($email) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = ? AND activo = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // No revelar si el email existe o no
                return ['success' => true];
            }
            
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $this->db->prepare("
                UPDATE usuarios SET token_recuperacion = ?, token_expira = ? WHERE id = ?
            ");
            $stmt->execute([$token, $expiry, $user['id']]);
            
            // TODO: Enviar email con el token
            // mail($email, 'Recuperar contraseña', "Token: $token");
            
            if (defined('APP_ENV') && APP_ENV !== 'production') {
                return ['success' => true, 'token' => $token];
            }
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error al procesar solicitud'];
        }
    }
    
    /**
     * Resetear contraseña con token
     */
    public function resetPassword($token, $new_password) {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM usuarios 
                WHERE token_recuperacion = ? AND token_expira > NOW() AND activo = 1
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'error' => 'Token inválido o expirado'];
            }
            
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("
                UPDATE usuarios 
                SET password = ?, token_recuperacion = NULL, token_expira = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$new_hash, $user['id']]);
            
            log_activity('reset_password', 'usuarios', $user['id']);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error al resetear contraseña'];
        }
    }
    
    /**
     * Obtener usuario actual
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) return null;
        
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, e.id as empresa_id, e.nombre as empresa_nombre
                FROM usuarios u
                LEFT JOIN empresas e ON u.id = e.usuario_id
                WHERE u.id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
    
    // Métodos privados para manejo de intentos fallidos
    
    private function isLocked($email) {
        $key = 'login_attempts_' . md5($email);
        if (isset($_SESSION[$key])) {
            $data = $_SESSION[$key];
            if ($data['count'] >= LOGIN_ATTEMPTS_MAX && (time() - $data['time']) < LOGIN_LOCKOUT_TIME) {
                return true;
            }
            if ((time() - $data['time']) >= LOGIN_LOCKOUT_TIME) {
                unset($_SESSION[$key]);
            }
        }
        return false;
    }
    
    private function registerFailedAttempt($email) {
        $key = 'login_attempts_' . md5($email);
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'time' => time()];
        }
        $_SESSION[$key]['count']++;
        $_SESSION[$key]['time'] = time();
    }
    
    private function clearFailedAttempts($email) {
        $key = 'login_attempts_' . md5($email);
        unset($_SESSION[$key]);
    }
    
    private function updateLastAccess($user_id) {
        try {
            $stmt = $this->db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
        } catch (Exception $e) {
            // Silenciar error
        }
    }
}

// Instancia global
$auth = new Auth();
