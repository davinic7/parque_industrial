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
    $text = preg_replace('~[^\pL\d]+~u', '-', (string) $text);
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($converted !== false) {
        $text = $converted;
    }
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    $text = substr($text, 0, 240);
    return $text === '' ? 'n-a' : $text;
}

/**
 * Truncar texto
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Cloudinary configurado (.env)
 */
function cloudinary_configured(): bool {
    return defined('CLOUDINARY_CLOUD_NAME') && CLOUDINARY_CLOUD_NAME !== ''
        && defined('CLOUDINARY_API_KEY') && CLOUDINARY_API_KEY !== ''
        && defined('CLOUDINARY_API_SECRET') && CLOUDINARY_API_SECRET !== '';
}

/**
 * Sube una imagen a Cloudinary; devuelve secure_url o null.
 */
function cloudinary_upload_image(string $file_path): ?string {
    if (!cloudinary_configured() || !is_readable($file_path)) {
        return null;
    }
    if (!function_exists('curl_init')) {
        error_log('cloudinary_upload_image: extensión curl no disponible');
        return null;
    }
    $cloud_name = CLOUDINARY_CLOUD_NAME;
    $api_key = CLOUDINARY_API_KEY;
    $api_secret = CLOUDINARY_API_SECRET;
    $timestamp = time();
    $signature = sha1('timestamp=' . $timestamp . $api_secret);
    $url = 'https://api.cloudinary.com/v1_1/' . $cloud_name . '/image/upload';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'file' => new CURLFile($file_path),
        'api_key' => $api_key,
        'timestamp' => $timestamp,
        'signature' => $signature,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode((string) $response, true);
    return $result['secure_url'] ?? null;
}

/**
 * Sube un archivo arbitrario (p. ej. PDF) a Cloudinary como recurso raw; devuelve secure_url o null.
 * Imprescindible en hosts con disco efímero (p. ej. Render): los PDF en public/uploads desaparecen al redeploy.
 */
function cloudinary_upload_raw(string $file_path): ?string {
    if (!cloudinary_configured() || !is_readable($file_path)) {
        return null;
    }
    if (!function_exists('curl_init')) {
        error_log('cloudinary_upload_raw: extensión curl no disponible');
        return null;
    }
    $cloud_name = CLOUDINARY_CLOUD_NAME;
    $api_key = CLOUDINARY_API_KEY;
    $api_secret = CLOUDINARY_API_SECRET;
    $timestamp = time();
    $signature = sha1('timestamp=' . $timestamp . $api_secret);
    $endpoint = 'https://api.cloudinary.com/v1_1/' . $cloud_name . '/raw/upload';
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'file' => new CURLFile($file_path, 'application/pdf', basename($file_path)),
        'api_key' => $api_key,
        'timestamp' => $timestamp,
        'signature' => $signature,
    ]);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        error_log('cloudinary_upload_raw: HTTP ' . $code . ' ' . substr((string) $response, 0, 300));
        return null;
    }
    $result = json_decode((string) $response, true);

    return $result['secure_url'] ?? null;
}

/**
 * URL para mostrar imagen guardada: URL absoluta (Cloudinary) o ruta bajo uploads.
 */
function uploads_resolve_url(?string $stored, string $subdir): string {
    if ($stored === null || $stored === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $stored)) {
        return $stored;
    }
    return rtrim(UPLOADS_URL, '/') . '/' . trim($subdir, '/') . '/' . ltrim($stored, '/');
}

/**
 * MIME detectado y normalizado a uno de la lista permitida (JPG a veces llega como octet-stream).
 */
function resolve_upload_mime_to_allowed(array $file, array $allowed): ?string {
    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    if (in_array($mime, $allowed, true)) {
        return $mime;
    }
    static $aliases = [
        'image/jpg' => 'image/jpeg',
        'image/pjpeg' => 'image/jpeg',
        'image/x-png' => 'image/png',
    ];
    if (isset($aliases[$mime]) && in_array($aliases[$mime], $allowed, true)) {
        return $aliases[$mime];
    }
    // SVG (logos ministerio): finfo suele dar xml/octet-stream
    if (in_array('image/svg+xml', $allowed, true)) {
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            $head = @file_get_contents($tmp, false, null, 0, 500);
            if (is_string($head) && preg_match('/<\s*svg\b/i', $head)) {
                $svg_mimes = [
                    'image/svg+xml', 'text/xml', 'application/xml', 'text/plain',
                    'application/octet-stream', 'binary/octet-stream', 'text/svg+xml',
                ];
                if ($mime === '' || in_array($mime, $svg_mimes, true)) {
                    return 'image/svg+xml';
                }
            }
        }
    }
    if (in_array($mime, ['application/octet-stream', 'binary/octet-stream', 'application/x-empty'], true) || $mime === '') {
        $info = @getimagesize($tmp);
        if ($info === false) {
            return null;
        }
        $map = [
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_PNG => 'image/png',
            IMAGETYPE_GIF => 'image/gif',
            IMAGETYPE_WEBP => 'image/webp',
        ];
        $canon = $map[$info[2] ?? 0] ?? null;
        if ($canon && in_array($canon, $allowed, true)) {
            return $canon;
        }
    }
    return null;
}

/**
 * Subida de imagen: Cloudinary si está configurado; si no, disco local (upload_file).
 * En BD se guarda la URL completa o el nombre de archivo local.
 */
function upload_image_storage(array $file, string $directory, ?array $allowed_types = null): array {
    $allowed = $allowed_types ?? ALLOWED_IMAGE_TYPES;
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Error al subir el archivo'];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'El archivo excede el tamaño máximo permitido'];
    }
    $mime_type = resolve_upload_mime_to_allowed($file, $allowed);
    if ($mime_type === null) {
        return ['success' => false, 'error' => 'Tipo de archivo no permitido para esta carga.'];
    }
    if (cloudinary_configured()) {
        $url = cloudinary_upload_image($file['tmp_name']);
        if ($url) {
            return ['success' => true, 'filename' => $url, 'filepath' => null, 'url' => $url];
        }
        error_log('upload_image_storage: Cloudinary falló, usando almacenamiento local');
    }
    return upload_file($file, $directory, $allowed, $mime_type);
}

/**
 * Subir archivo
 * @param string|null $verified_mime Si ya se validó el MIME (p. ej. resolve_upload_mime_to_allowed), reutilizarlo
 */
function upload_file($file, $directory = '', $allowed_types = null, ?string $verified_mime = null) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Error al subir el archivo'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'El archivo excede el tamaño máximo'];
    }

    if ($verified_mime !== null && $allowed_types && in_array($verified_mime, $allowed_types, true)) {
        $mime_type = $verified_mime;
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);
    }
    
    if ($allowed_types && !in_array($mime_type, $allowed_types, true)) {
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
 * Convierte $_FILES['nombre'] (simple o múltiple) en lista de arrays para upload_file.
 */
function normalize_uploaded_files(string $key): array {
    if (empty($_FILES[$key])) {
        return [];
    }
    $f = $_FILES[$key];
    if (!is_array($f['name'])) {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        return [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $name) {
        if ($name === '') {
            continue;
        }
        $err = $f['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name' => $f['name'][$i],
            'type' => $f['type'][$i] ?? '',
            'tmp_name' => $f['tmp_name'][$i],
            'error' => $err,
            'size' => $f['size'][$i] ?? 0,
        ];
    }
    return $out;
}

/**
 * Sube hasta $max PDFs bajo uploads/$directory.
 */
function upload_pdf_batch(array $fileStructs, string $directory = 'mensajes', int $max = 5): array {
    $saved = [];
    $slice = array_slice($fileStructs, 0, max(1, $max));
    foreach ($slice as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Error al subir el archivo', 'saved' => $saved];
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if ($mime !== 'application/pdf') {
            return ['success' => false, 'error' => 'Solo se permiten archivos PDF', 'saved' => $saved];
        }

        if (cloudinary_configured()) {
            $cloudUrl = cloudinary_upload_raw($file['tmp_name']);
            if ($cloudUrl !== null) {
                $saved[] = $cloudUrl;
                continue;
            }
            error_log('upload_pdf_batch: Cloudinary raw no disponible, intentando disco local');
        }

        $r = upload_file($file, $directory, ['application/pdf'], 'application/pdf');
        if (!$r['success']) {
            return ['success' => false, 'error' => $r['error'], 'saved' => $saved];
        }
        $saved[] = $r['filename'];
    }

    return ['success' => true, 'saved' => $saved];
}

/**
 * Comprueba si existe una columna en una tabla (MySQL).
 */
function db_table_has_column(PDO $db, string $table, string $column): bool {
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $c = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($t === '' || $c === '') {
        return false;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");

        return $stmt && (bool) $stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
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
 * Indica si una columna tiene AUTO_INCREMENT (SHOW COLUMNS).
 * Tabla y columna: solo letras, números y guión bajo.
 */
function db_column_is_auto_increment(PDO $db, string $table, string $column = 'id'): bool {
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $c = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($t === '' || $c === '') {
        return true;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `{$t}` WHERE Field = '{$c}'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$row) {
            return true;
        }
        return stripos((string) ($row['Extra'] ?? ''), 'auto_increment') !== false;
    } catch (Throwable $e) {
        return true;
    }
}

/**
 * Texto amigable para la línea de tiempo del panel empresa (log_actividad.accion).
 */
function empresa_traducir_accion_log(string $accion): string {
    static $map = [
        'perfil_actualizado' => 'Perfil de empresa actualizado',
        'publicacion_enviada' => 'Publicación enviada a revisión',
        'publicacion_guardada' => 'Borrador de publicación guardado',
        'formulario_enviado' => 'Declaración jurada enviada',
        'formulario_guardado' => 'Borrador de formulario guardado',
        'mensaje_enviado_ministerio' => 'Mensaje enviado al Ministerio',
        'logout' => 'Cierre de sesión',
        'login' => 'Inicio de sesión',
    ];

    return $map[$accion] ?? ucfirst(str_replace('_', ' ', $accion));
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
 * Solo dígitos del CUIT / CUIL (11 caracteres).
 */
function cuit_digits_only($cuit): string {
    return preg_replace('/\D/', '', (string) $cuit);
}

/**
 * Validar CUIT (acepta con o sin guiones; se normaliza a 11 dígitos).
 */
function is_valid_cuit($cuit) {
    $cuit = cuit_digits_only($cuit);
    if (strlen($cuit) !== 11) {
        return false;
    }

    $mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $sum = 0;
    for ($i = 0; $i < 10; $i++) {
        $sum += (int) $cuit[$i] * $mult[$i];
    }
    $checksum = 11 - ($sum % 11);
    if ($checksum == 11) {
        $checksum = 0;
    }
    if ($checksum == 10) {
        $checksum = 9;
    }

    return (int) $cuit[10] === $checksum;
}

/**
 * Formato visual estándar XX-XXXXXXXX-X (11 dígitos válidos).
 */
function format_cuit_argentina(string $digits11): string {
    if (strlen($digits11) !== 11) {
        return $digits11;
    }
    return substr($digits11, 0, 2) . '-' . substr($digits11, 2, 8) . '-' . substr($digits11, 10, 1);
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
 * Enviar email con credenciales de acceso a una empresa recién registrada.
 * Usa mail() de PHP. Requiere servidor de correo configurado.
 * @return bool true si se envió, false si falló
 */
/**
 * IP del cliente (evitar confiar en X-Forwarded-For salvo proxy conocido).
 */
function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Indica si hay soporte mínimo para enviar correos (mail() de PHP).
 */
function can_send_mail(): bool {
    return function_exists('mail');
}

/**
 * Email de activación de cuenta empresa (enlace con token).
 */
function enviar_email_activacion_empresa(string $destino_email, string $nombre_empresa, string $url_activacion): bool {
    $asunto = 'Active su cuenta - Parque Industrial';
    $cuerpo = "Estimado/a,\n\n";
    $cuerpo .= "Se ha registrado la empresa \"" . $nombre_empresa . "\" en el sistema del Parque Industrial.\n\n";
    $cuerpo .= "Para crear su contraseña y activar el acceso, abra el siguiente enlace (válido por tiempo limitado):\n\n";
    $cuerpo .= $url_activacion . "\n\n";
    $cuerpo .= "Si usted no solicitó este registro, ignore este mensaje.\n\n";
    $cuerpo .= "Saludos cordiales,\nMinisterio - Parque Industrial";
    $headers = "From: noreply@parqueindustrial.gob.ar\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $ok = @mail($destino_email, $asunto, $cuerpo, $headers);
    if (!$ok) {
        error_log("enviar_email_activacion: fallo al enviar a $destino_email");
    }
    return $ok;
}

/**
 * Email con enlace para restablecer contraseña.
 */
function enviar_email_recuperacion_password(string $destino_email, string $url_reset): bool {
    $asunto = 'Restablecer contraseña - Parque Industrial';
    $cuerpo = "Recibimos una solicitud para restablecer la contraseña de su cuenta.\n\n";
    $cuerpo .= "Si fue usted, abra este enlace (válido por 1 hora):\n\n";
    $cuerpo .= $url_reset . "\n\n";
    $cuerpo .= "Si no solicitó el cambio, ignore este correo.\n\n";
    $cuerpo .= "Parque Industrial de Catamarca";
    $headers = "From: noreply@parqueindustrial.gob.ar\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $ok = @mail($destino_email, $asunto, $cuerpo, $headers);
    if (!$ok) {
        error_log("enviar_email_recuperacion: fallo al enviar a $destino_email");
    }
    return $ok;
}

/**
 * Aviso de contraseña cambiada (opcional).
 */
function enviar_email_password_cambiada(string $destino_email): bool {
    $asunto = 'Su contraseña fue actualizada - Parque Industrial';
    $cuerpo = "Le informamos que la contraseña de su cuenta se modificó correctamente.\n\n";
    $cuerpo .= "Si no fue usted, contacte de inmediato al administrador.\n";
    $headers = "From: noreply@parqueindustrial.gob.ar\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($destino_email, $asunto, $cuerpo, $headers);
}

/**
 * Notificación por correo: nuevo formulario dinámico asignado.
 */
function enviar_email_formulario_nuevo(string $destino_email, string $titulo_formulario, string $url_formulario): bool {
    $asunto = 'Nuevo formulario disponible - Parque Industrial';
    $cuerpo = "Tiene un formulario pendiente de completar:\n\n";
    $cuerpo .= $titulo_formulario . "\n\n";
    $cuerpo .= "Acceda desde:\n" . $url_formulario . "\n\n";
    $cuerpo .= "Parque Industrial de Catamarca";
    $headers = "From: noreply@parqueindustrial.gob.ar\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($destino_email, $asunto, $cuerpo, $headers);
}

function enviar_email_credenciales_empresa($destino_email, $nombre_empresa, $password_temporal, $url_login = '') {
    $asunto = 'Credenciales de acceso - Parque Industrial';
    $url_login = $url_login ?: (defined('EMPRESA_URL') ? EMPRESA_URL . '/../login.php' : '');
    $cuerpo = "Estimado/a,\n\n";
    $cuerpo .= "Se ha registrado a su empresa \"$nombre_empresa\" en el sistema del Parque Industrial.\n\n";
    $cuerpo .= "Sus credenciales de acceso son:\n";
    $cuerpo .= "  Email: $destino_email\n";
    $cuerpo .= "  Contraseña temporal: $password_temporal\n\n";
    $cuerpo .= "Le recomendamos cambiar la contraseña al primer ingreso.\n\n";
    if ($url_login) {
        $cuerpo .= "Acceso al panel: $url_login\n\n";
    }
    $cuerpo .= "Saludos cordiales,\nMinisterio - Parque Industrial";
    $headers = "From: noreply@parqueindustrial.gob.ar\r\n";
    $headers .= "Reply-To: noreply@parqueindustrial.gob.ar\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $enviado = mail($destino_email, $asunto, $cuerpo, $headers);
    if (!$enviado) {
        error_log("enviar_credenciales: fallo al enviar email a $destino_email");
    }
    return $enviado;
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
