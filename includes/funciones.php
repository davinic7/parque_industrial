<?php
/**
 * Funciones Helper
 * Parque Industrial de Catamarca
 */

if (!defined('BASEPATH')) {
    exit('No se permite el acceso directo al script');
}

/**
 * Escapar HTML para prevenir XSS
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generar token CSRF
 */
function csrf_token() {
    return $_SESSION[CSRF_TOKEN_NAME] ?? '';
}

/**
 * Campo hidden con token CSRF
 */
function csrf_field() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . csrf_token() . '">';
}

/**
 * Verificar token CSRF
 */
function verify_csrf($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Redireccionar
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Mostrar mensaje flash
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function show_flash() {
    $flash = get_flash();
    if ($flash) {
        $type_class = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ];
        $class = $type_class[$flash['type']] ?? 'alert-info';
        echo '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
        echo e($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

/**
 * Formatear fecha
 */
function format_date($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    $dt = new DateTime($date);
    return $dt->format($format);
}

function format_datetime($date, $format = 'd/m/Y H:i') {
    if (empty($date)) return '';
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Formatear números
 */
function format_number($number, $decimals = 0) {
    return number_format($number ?? 0, $decimals, ',', '.');
}

function format_currency($number) {
    return '$ ' . number_format($number ?? 0, 2, ',', '.');
}

/**
 * Generar slug desde texto
 */
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

/**
 * Truncar texto
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Subir archivo
 */
function upload_file($file, $directory = '', $allowed_types = null) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Error al subir el archivo'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'El archivo excede el tamaño máximo'];
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    if ($allowed_types && !in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Tipo de archivo no permitido'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    
    $upload_dir = UPLOADS_PATH . ($directory ? '/' . $directory : '');
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filepath = $upload_dir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => UPLOADS_URL . ($directory ? '/' . $directory : '') . '/' . $filename
        ];
    }
    
    return ['success' => false, 'error' => 'Error al mover el archivo'];
}

/**
 * Obtener configuración del sitio
 */
function get_config($key, $default = null) {
    static $config = null;
    
    if ($config === null) {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT clave, valor FROM configuracion_sitio");
            $config = [];
            while ($row = $stmt->fetch()) {
                $config[$row['clave']] = $row['valor'];
            }
        } catch (Exception $e) {
            $config = [];
        }
    }
    
    return $config[$key] ?? $default;
}

/**
 * Registrar actividad
 */
function log_activity($accion, $tabla = null, $registro_id = null, $datos_anteriores = null, $datos_nuevos = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO log_actividad 
            (usuario_id, empresa_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['empresa_id'] ?? null,
            $accion,
            $tabla,
            $registro_id,
            $datos_anteriores ? json_encode($datos_anteriores) : null,
            $datos_nuevos ? json_encode($datos_nuevos) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Error al registrar actividad: " . $e->getMessage());
    }
}

/**
 * Crear notificación
 */
function crear_notificacion($usuario_id, $tipo, $titulo, $mensaje = null, $url = null, $datos = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, url, datos)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$usuario_id, $tipo, $titulo, $mensaje, $url, $datos ? json_encode($datos) : null]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obtener estadísticas generales
 */
function get_estadisticas_generales() {
    try {
        $db = getDB();
        
        // Total empresas
        $stmt = $db->query("SELECT COUNT(*) as total FROM empresas");
        $total_empresas = $stmt->fetch()['total'];
        
        // Empresas activas (consideramos todas como activas si no tienen estado)
        $stmt = $db->query("SELECT COUNT(*) as total FROM empresas WHERE estado = 'activa' OR estado IS NULL");
        $total_activas = $stmt->fetch()['total'];
        
        // Total rubros únicos
        $stmt = $db->query("SELECT COUNT(DISTINCT rubro) as total FROM empresas WHERE rubro IS NOT NULL");
        $total_rubros = $stmt->fetch()['total'];
        
        // Total empleados (de datos_empresa o campo dotacion si existe)
        $stmt = $db->query("SELECT COALESCE(SUM(dotacion_total), 0) as total FROM datos_empresa");
        $total_empleados = $stmt->fetch()['total'];
        
        // Si no hay datos en datos_empresa, estimar
        if ($total_empleados == 0) {
            $total_empleados = $total_activas * 15; // Estimado promedio
        }
        
        return [
            'total_empresas' => $total_empresas,
            'total_empresas_activas' => $total_activas ?: $total_empresas,
            'total_rubros' => $total_rubros,
            'total_empleados' => $total_empleados
        ];
    } catch (Exception $e) {
        return ['total_empresas_activas' => 0, 'total_empresas' => 0, 'total_empleados' => 0, 'total_rubros' => 0];
    }
}

/**
 * Obtener rubros con conteo (directo de empresas)
 */
function get_rubros_con_conteo() {
    try {
        $db = getDB();
        $stmt = $db->query("
            SELECT 
                rubro as nombre,
                COUNT(*) as total_empresas,
                CASE rubro
                    WHEN 'TEXTIL' THEN '#3498db'
                    WHEN 'CONSTRUCCION' THEN '#e74c3c'
                    WHEN 'CONSTRUCCIÓN' THEN '#e74c3c'
                    WHEN 'METALURGICA' THEN '#95a5a6'
                    WHEN 'ALIMENTOS' THEN '#27ae60'
                    WHEN 'TRANSPORTE' THEN '#f39c12'
                    WHEN 'RECICLADO' THEN '#2ecc71'
                    WHEN 'HORMIGON' THEN '#7f8c8d'
                    WHEN 'ELECTRODOMESTICOS' THEN '#9b59b6'
                    WHEN 'CALZADOS' THEN '#e67e22'
                    WHEN 'MEDICAMENTOS' THEN '#1abc9c'
                    ELSE '#bdc3c7'
                END as color
            FROM empresas 
            WHERE rubro IS NOT NULL AND rubro != ''
            GROUP BY rubro
            ORDER BY total_empresas DESC
            LIMIT 10
        ");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Sanitizar entrada
 */
function sanitize_input($data) {
    if (is_array($data)) return array_map('sanitize_input', $data);
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar email
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar CUIT
 */
function is_valid_cuit($cuit) {
    $cuit = preg_replace('/[^0-9]/', '', $cuit);
    if (strlen($cuit) !== 11) return false;
    
    $mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $sum = 0;
    for ($i = 0; $i < 10; $i++) {
        $sum += $cuit[$i] * $mult[$i];
    }
    $checksum = 11 - ($sum % 11);
    if ($checksum == 11) $checksum = 0;
    if ($checksum == 10) $checksum = 9;
    
    return $cuit[10] == $checksum;
}

/**
 * Período actual
 */
function get_periodo_actual() {
    return date('Y') . '-Q' . ceil(date('n') / 3);
}

/**
 * JSON seguro
 */
function safe_json_encode($data) {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

/**
 * Paginación
 */
function paginate($total, $per_page, $current_page, $url_pattern) {
    $total_pages = ceil($total / $per_page);
    $current_page = max(1, min($current_page, $total_pages));
    
    $pages = [];
    for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
        $pages[] = $i;
    }
    
    return [
        'total' => $total,
        'per_page' => $per_page,
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'pages' => $pages,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages,
        'prev_url' => str_replace('{page}', $current_page - 1, $url_pattern),
        'next_url' => str_replace('{page}', $current_page + 1, $url_pattern),
        'url_pattern' => $url_pattern
    ];
}

/**
 * Renderizar paginación
 */
function render_pagination($p) {
    if ($p['total_pages'] <= 1) return '';
    
    $html = '<nav><ul class="pagination justify-content-center">';
    $html .= $p['has_prev'] 
        ? '<li class="page-item"><a class="page-link" href="'.e($p['prev_url']).'">«</a></li>'
        : '<li class="page-item disabled"><span class="page-link">«</span></li>';
    
    foreach ($p['pages'] as $page) {
        $html .= $page == $p['current_page']
            ? '<li class="page-item active"><span class="page-link">'.$page.'</span></li>'
            : '<li class="page-item"><a class="page-link" href="'.str_replace('{page}', $page, $p['url_pattern']).'">'.$page.'</a></li>';
    }
    
    $html .= $p['has_next']
        ? '<li class="page-item"><a class="page-link" href="'.e($p['next_url']).'">»</a></li>'
        : '<li class="page-item disabled"><span class="page-link">»</span></li>';
    
    return $html . '</ul></nav>';
}
