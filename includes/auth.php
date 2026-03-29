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
        if ($this->isIpLockedDb()) {
            return ['success' => false, 'error' => 'Demasiados intentos fallidos. Intente nuevamente en 15 minutos.'];
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

            if (!$user || $user['password'] === '' || !password_verify($password, $user['password'])) {
                $this->recordFailedLoginIp($email);
                return ['success' => false, 'error' => 'Email o contraseña incorrectos'];
            }

            $this->clearLoginAttemptsIp();
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
     * Registro empresa pendiente de activación (contraseña vacía hasta activar-cuenta.php).
     */
    public function registerEmpresaPending($email, $token, $tokenExp) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'El email ya está registrado'];
            }

            $stmt = $this->db->prepare("
                INSERT INTO usuarios (email, password, rol, activo, token_activacion, token_activacion_expira, email_verificado)
                VALUES (?, '', 'empresa', 0, ?, ?, 0)
            ");
            $stmt->execute([$email, $token, $tokenExp]);
            $user_id = (int)$this->db->lastInsertId();
            log_activity('registro_pendiente_activacion', 'usuarios', $user_id);
            return ['success' => true, 'user_id' => $user_id];
        } catch (Exception $e) {
            error_log('registerEmpresaPending: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Error al registrar usuario'];
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
            $ip = client_ip();
            try {
                $c = $this->db->prepare("
                    SELECT COUNT(*) FROM password_reset_requests
                    WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ");
                $c->execute([$ip]);
                if ((int)$c->fetchColumn() >= 3) {
                    return ['success' => true];
                }
                $this->db->prepare("INSERT INTO password_reset_requests (ip) VALUES (?)")->execute([$ip]);
            } catch (Exception $e) {
                // Tabla aún no migrada: continuar sin rate limit por IP
            }

            $stmt = $this->db->prepare("SELECT id, email FROM usuarios WHERE email = ? AND activo = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => true];
            }

            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $this->db->prepare("
                UPDATE usuarios SET token_recuperacion = ?, token_expira = ? WHERE id = ?
            ");
            $stmt->execute([$token, $expiry, $user['id']]);

            $reset_link = rtrim(PUBLIC_URL, '/') . '/recuperar.php?token=' . urlencode($token);
            if (can_send_mail()) {
                enviar_email_recuperacion_password((string)$user['email'], $reset_link);
            }

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
            
            $stmt = $this->db->prepare("SELECT email FROM usuarios WHERE id = ?");
            $stmt->execute([$user['id']]);
            $rowMail = $stmt->fetch();

            $stmt = $this->db->prepare("
                UPDATE usuarios
                SET password = ?, token_recuperacion = NULL, token_expira = NULL
                WHERE id = ?
            ");
            $stmt->execute([$new_hash, $user['id']]);

            log_activity('reset_password', 'usuarios', $user['id']);

            if ($rowMail && can_send_mail()) {
                enviar_email_password_cambiada($rowMail['email']);
            }

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
    
    private function isIpLockedDb(): bool {
        $ip = client_ip();
        try {
            $stmt = $this->db->prepare("
                SELECT 1 FROM login_attempts
                WHERE ip = ? AND bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()
            ");
            $stmt->execute([$ip]);
            return (bool)$stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }

    private function recordFailedLoginIp(string $email): void {
        $ip = client_ip();
        $max = (int)LOGIN_ATTEMPTS_MAX;
        try {
            $this->db->prepare("
                INSERT INTO login_attempts (ip, email, intentos, ultimo_intento)
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    intentos = login_attempts.intentos + 1,
                    ultimo_intento = NOW(),
                    email = COALESCE(VALUES(email), login_attempts.email),
                    bloqueado_hasta = IF(login_attempts.intentos + 1 >= ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL)
            ")->execute([$ip, $email, $max]);
        } catch (Exception $e) {
            error_log('recordFailedLoginIp: ' . $e->getMessage());
        }
    }

    private function clearLoginAttemptsIp(): void {
        $ip = client_ip();
        try {
            $this->db->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
        } catch (Exception $e) {
            // ignorar
        }
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
