<?php
/**
 * Helpers de dominio del Centro de Comunicaciones (Fase 2).
 *
 * Este archivo expone funciones puras sobre el modelo de mensajeria nuevo:
 *   conversaciones / mensajes_v2 / adjuntos_mensajes / comunicado_visto
 *
 * Las vistas y endpoints API delegan aca toda la logica de negocio para
 * mantener un solo lugar donde validar permisos, paginacion y queries.
 *
 * Convencion de retorno: arrays asociativos. En errores, throw Exception
 * con mensaje en espanol.
 */

if (!defined('BASEPATH')) {
    exit('No se permite el acceso directo al script');
}

// =====================================================
// Configuracion del modulo (autonoma).
// Se definen aca con guards para no requerir cambios en config.php.
// Los valores se pueden override via .env si se desea.
// =====================================================

// Feature flag: activa el centro nuevo. Por defecto OFF hasta que la
// migracion 016 este aplicada y se haya validado en produccion.
if (!defined('FEATURE_CENTRO_COMS')) {
    $__coms_flag = getenv('FEATURE_CENTRO_COMS');
    define(
        'FEATURE_CENTRO_COMS',
        $__coms_flag !== false &&
        in_array(strtolower((string)$__coms_flag), ['1', 'true', 'yes', 'on'], true)
    );
    unset($__coms_flag);
}

// Tope de tamano TOTAL por mensaje (suma de todos los adjuntos).
if (!defined('COMS_MAX_TOTAL_BYTES')) {
    define('COMS_MAX_TOTAL_BYTES', 25 * 1024 * 1024); // 25 MB
}

// Tipos MIME permitidos para adjuntos.
if (!defined('COMS_ALLOWED_MIMES')) {
    define('COMS_ALLOWED_MIMES', [
        // Imagenes
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        // Documentos
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // Archivos comprimidos
        'application/zip', 'application/x-zip-compressed',
        // Texto plano
        'text/plain', 'text/csv',
    ]);
}

/**
 * Verifica que la migracion 016 se haya aplicado. Devuelve true si las 4
 * tablas existen. Util para mostrar UI degradada antes de aplicar la
 * migracion en produccion.
 */
function coms_schema_disponible(): bool {
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
        $db = getDB();
        $needed = ['conversaciones', 'mensajes_v2', 'adjuntos_mensajes', 'comunicado_visto'];
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name IN ('conversaciones','mensajes_v2','adjuntos_mensajes','comunicado_visto')
        ");
        $stmt->execute();
        $cache = ((int)$stmt->fetchColumn() === count($needed));
    } catch (Exception $e) {
        error_log('coms_schema_disponible: ' . $e->getMessage());
        $cache = false;
    }
    return $cache;
}

/**
 * Crea una conversacion nueva.
 *
 * @param array $data {
 *   titulo: string (max 200),
 *   empresa_id: int|null   (NULL = comunicado global del ministerio),
 *   iniciada_por: 'empresa'|'ministerio'|'sistema',
 *   categoria: 'tramite'|'consulta'|'reclamo'|'comunicado'|'formulario'|'sistema',
 *   referencia_tipo?: string,
 *   referencia_id?: int,
 * }
 * @return int  ID de la conversacion creada
 */
function coms_crear_conversacion(array $data): int {
    $db = getDB();

    $titulo       = trim($data['titulo'] ?? '');
    $empresa_id   = isset($data['empresa_id']) && $data['empresa_id'] !== ''
                  ? (int)$data['empresa_id'] : null;
    $iniciada_por = $data['iniciada_por'] ?? '';
    $categoria    = $data['categoria']    ?? 'consulta';
    $ref_tipo     = $data['referencia_tipo'] ?? null;
    $ref_id       = isset($data['referencia_id']) && $data['referencia_id'] !== ''
                  ? (int)$data['referencia_id'] : null;

    if ($titulo === '') {
        throw new InvalidArgumentException('El titulo de la conversacion es obligatorio.');
    }
    if (!in_array($iniciada_por, ['empresa', 'ministerio', 'sistema'], true)) {
        throw new InvalidArgumentException('iniciada_por invalido.');
    }
    if (!in_array($categoria, ['tramite','consulta','reclamo','comunicado','formulario','sistema'], true)) {
        throw new InvalidArgumentException('categoria invalida.');
    }

    $stmt = $db->prepare("
        INSERT INTO conversaciones
            (titulo, empresa_id, iniciada_por, categoria, estado, referencia_tipo, referencia_id)
        VALUES (?, ?, ?, ?, 'abierta', ?, ?)
    ");
    $stmt->execute([$titulo, $empresa_id, $iniciada_por, $categoria, $ref_tipo, $ref_id]);

    return (int)$db->lastInsertId();
}

/**
 * Inserta un mensaje en una conversacion existente.
 * Si es_borrador=0, actualiza ultimo_mensaje_at de la conversacion.
 *
 * @param array $data {
 *   conversacion_id: int,
 *   remitente_id: int,
 *   remitente_tipo: 'empresa'|'ministerio'|'sistema',
 *   contenido: string (no vacio salvo borrador),
 *   es_borrador?: bool (default 0),
 * }
 * @return int  ID del mensaje creado
 */
function coms_enviar_mensaje(array $data): int {
    $db = getDB();

    $conversacion_id = (int)($data['conversacion_id'] ?? 0);
    $remitente_id    = (int)($data['remitente_id'] ?? 0);
    $remitente_tipo  = $data['remitente_tipo'] ?? '';
    $contenido       = trim($data['contenido'] ?? '');
    $es_borrador     = !empty($data['es_borrador']) ? 1 : 0;

    if ($conversacion_id <= 0) {
        throw new InvalidArgumentException('conversacion_id invalido.');
    }
    if ($remitente_id <= 0) {
        throw new InvalidArgumentException('remitente_id invalido.');
    }
    if (!in_array($remitente_tipo, ['empresa','ministerio','sistema'], true)) {
        throw new InvalidArgumentException('remitente_tipo invalido.');
    }
    if (!$es_borrador && $contenido === '') {
        throw new InvalidArgumentException('El contenido es obligatorio salvo en borrador.');
    }

    $stmt = $db->prepare("
        INSERT INTO mensajes_v2
            (conversacion_id, remitente_id, remitente_tipo, contenido, es_borrador)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$conversacion_id, $remitente_id, $remitente_tipo, $contenido, $es_borrador]);
    $msg_id = (int)$db->lastInsertId();

    // Actualizar timestamp del ultimo mensaje "real" (no borrador)
    if (!$es_borrador) {
        $db->prepare("UPDATE conversaciones SET ultimo_mensaje_at = NOW() WHERE id = ?")
           ->execute([$conversacion_id]);
    }

    return $msg_id;
}

/**
 * Marca como leidos todos los mensajes recibidos por el usuario actual en
 * una conversacion. NO marca los propios.
 *
 * Para conversaciones de empresa: marca leido_at en mensajes_v2.
 * Para comunicados globales (empresa_id IS NULL): upsert en comunicado_visto.
 *
 * @param int    $conversacion_id
 * @param string $tipo_actor  'empresa' o 'ministerio'
 * @param int|null $empresa_id  (solo si actor es empresa)
 * @return int  Cantidad de mensajes recien marcados
 */
function coms_marcar_leida(int $conversacion_id, string $tipo_actor, ?int $empresa_id = null): int {
    $db = getDB();

    $stmt = $db->prepare("SELECT empresa_id FROM conversaciones WHERE id = ?");
    $stmt->execute([$conversacion_id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Conversacion no encontrada.');
    }

    // Comunicado global: upsert en comunicado_visto.
    if ($row['empresa_id'] === null && $tipo_actor === 'empresa' && $empresa_id !== null) {
        $stmt = $db->prepare("
            INSERT INTO comunicado_visto (conversacion_id, empresa_id, leido_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE leido_at = COALESCE(leido_at, NOW())
        ");
        $stmt->execute([$conversacion_id, $empresa_id]);
        return 1;
    }

    // Conversacion 1-a-1: marcar mensajes del OTRO interlocutor como leidos.
    $stmt = $db->prepare("
        UPDATE mensajes_v2
           SET leido_at = NOW()
         WHERE conversacion_id = ?
           AND remitente_tipo <> ?
           AND es_borrador = 0
           AND leido_at IS NULL
    ");
    $stmt->execute([$conversacion_id, $tipo_actor]);
    return $stmt->rowCount();
}

/**
 * Marca el ultimo mensaje recibido como NO LEIDO (para "recordar mas tarde").
 *
 * @return bool  true si afecto alguna fila
 */
function coms_marcar_no_leida(int $conversacion_id, string $tipo_actor, ?int $empresa_id = null): bool {
    $db = getDB();

    $stmt = $db->prepare("SELECT empresa_id FROM conversaciones WHERE id = ?");
    $stmt->execute([$conversacion_id]);
    $row = $stmt->fetch();
    if (!$row) return false;

    if ($row['empresa_id'] === null && $tipo_actor === 'empresa' && $empresa_id !== null) {
        $stmt = $db->prepare("
            UPDATE comunicado_visto
               SET leido_at = NULL
             WHERE conversacion_id = ? AND empresa_id = ?
        ");
        $stmt->execute([$conversacion_id, $empresa_id]);
        return $stmt->rowCount() > 0;
    }

    // Buscar el ULTIMO mensaje recibido (no propio) y marcarlo como no leido.
    $stmt = $db->prepare("
        SELECT id FROM mensajes_v2
         WHERE conversacion_id = ? AND remitente_tipo <> ? AND es_borrador = 0
         ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$conversacion_id, $tipo_actor]);
    $ultimo = $stmt->fetch();
    if (!$ultimo) return false;

    $db->prepare("UPDATE mensajes_v2 SET leido_at = NULL WHERE id = ?")
       ->execute([$ultimo['id']]);
    return true;
}

/**
 * Cambia el estado de archivado de una conversacion.
 *
 * Para conversaciones 1-a-1 modifica conversaciones.estado.
 * Para comunicados globales solo afecta a la empresa que llama (via comunicado_visto).
 */
function coms_archivar(int $conversacion_id, bool $archivada, string $tipo_actor, ?int $empresa_id = null): void {
    $db = getDB();

    $stmt = $db->prepare("SELECT empresa_id FROM conversaciones WHERE id = ?");
    $stmt->execute([$conversacion_id]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('Conversacion no encontrada.');

    if ($row['empresa_id'] === null && $tipo_actor === 'empresa' && $empresa_id !== null) {
        $db->prepare("
            INSERT INTO comunicado_visto (conversacion_id, empresa_id, archivado)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE archivado = VALUES(archivado)
        ")->execute([$conversacion_id, $empresa_id, $archivada ? 1 : 0]);
        return;
    }

    $nuevo_estado = $archivada ? 'archivada' : 'abierta';
    $db->prepare("UPDATE conversaciones SET estado = ? WHERE id = ?")
       ->execute([$nuevo_estado, $conversacion_id]);
}

/**
 * Lista conversaciones para una bandeja, con filtros opcionales y conteo
 * de no-leidos por hilo.
 *
 * @param array $opts {
 *   actor: 'empresa'|'ministerio',
 *   empresa_id?: int      (obligatorio si actor=empresa),
 *   categoria?: string,
 *   estado?: 'abierta'|'cerrada'|'archivada' (default 'abierta'),
 *   buscar?: string       (busqueda por titulo, LIKE),
 *   limit?: int (default 50),
 *   offset?: int (default 0),
 * }
 * @return array  Lista de conversaciones (cada una con keys agregadas: no_leidos, ultimo_remitente_tipo)
 */
function coms_listar_conversaciones(array $opts): array {
    $db = getDB();

    $actor      = $opts['actor'] ?? '';
    $empresa_id = isset($opts['empresa_id']) ? (int)$opts['empresa_id'] : null;
    $categoria  = $opts['categoria'] ?? '';
    $estado     = $opts['estado']    ?? 'abierta';
    $buscar     = trim($opts['buscar'] ?? '');
    $limit      = max(1, min(200, (int)($opts['limit'] ?? 50)));
    $offset     = max(0, (int)($opts['offset'] ?? 0));

    if (!in_array($actor, ['empresa', 'ministerio'], true)) {
        throw new InvalidArgumentException('actor invalido.');
    }
    if ($actor === 'empresa' && !$empresa_id) {
        throw new InvalidArgumentException('empresa_id es obligatorio cuando actor=empresa.');
    }

    $where  = ['c.estado = ?'];
    $params = [$estado];

    if ($actor === 'empresa') {
        // Conversaciones de la empresa + comunicados globales (empresa_id IS NULL)
        // que no esten archivados por la empresa.
        $where[] = "(c.empresa_id = ? OR (c.empresa_id IS NULL AND c.iniciada_por = 'ministerio'))";
        $params[] = $empresa_id;
    }

    if ($categoria !== '') {
        $where[] = 'c.categoria = ?';
        $params[] = $categoria;
    }

    if ($buscar !== '') {
        $where[] = 'c.titulo LIKE ?';
        $params[] = "%$buscar%";
    }

    $where_sql = implode(' AND ', $where);

    // Subquery: cantidad de mensajes no leidos para el actor en cada conversacion.
    // Para empresa con comunicado global, no contamos por mensaje sino por comunicado_visto.
    $sql = "
        SELECT
            c.id, c.titulo, c.empresa_id, c.iniciada_por, c.categoria, c.estado,
            c.referencia_tipo, c.referencia_id,
            c.ultimo_mensaje_at, c.created_at,
            e.nombre AS empresa_nombre,
            (
                SELECT COUNT(*)
                FROM mensajes_v2 m
                WHERE m.conversacion_id = c.id
                  AND m.es_borrador = 0
                  AND m.leido_at IS NULL
                  AND m.remitente_tipo <> ?
            ) AS no_leidos,
            (
                SELECT m2.remitente_tipo
                FROM mensajes_v2 m2
                WHERE m2.conversacion_id = c.id AND m2.es_borrador = 0
                ORDER BY m2.created_at DESC LIMIT 1
            ) AS ultimo_remitente_tipo
        FROM conversaciones c
        LEFT JOIN empresas e ON e.id = c.empresa_id
        WHERE $where_sql
        ORDER BY (c.ultimo_mensaje_at IS NULL), c.ultimo_mensaje_at DESC, c.id DESC
        LIMIT $limit OFFSET $offset
    ";

    // El primer placeholder de no_leidos es el tipo del actor (para excluir mensajes propios).
    $params_full = array_merge([$actor], $params);

    $stmt = $db->prepare($sql);
    $stmt->execute($params_full);
    return $stmt->fetchAll();
}

/**
 * Devuelve los mensajes (no borradores) de una conversacion, en orden cronologico
 * ascendente, con sus adjuntos cargados.
 */
function coms_obtener_hilo(int $conversacion_id): array {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT m.*, u.email AS remitente_email
        FROM mensajes_v2 m
        LEFT JOIN usuarios u ON u.id = m.remitente_id
        WHERE m.conversacion_id = ? AND m.es_borrador = 0
        ORDER BY m.created_at ASC, m.id ASC
    ");
    $stmt->execute([$conversacion_id]);
    $mensajes = $stmt->fetchAll();

    if (!$mensajes) return [];

    $ids = array_column($mensajes, 'id');
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("
        SELECT * FROM adjuntos_mensajes
        WHERE mensaje_id IN ($place)
        ORDER BY id ASC
    ");
    $stmt->execute($ids);
    $adjuntos_por_mensaje = [];
    foreach ($stmt->fetchAll() as $adj) {
        $adjuntos_por_mensaje[(int)$adj['mensaje_id']][] = $adj;
    }

    foreach ($mensajes as &$m) {
        $m['adjuntos'] = $adjuntos_por_mensaje[(int)$m['id']] ?? [];
    }
    return $mensajes;
}

/**
 * Devuelve el conteo de no-leidos para el badge global de un actor.
 *
 * Para empresa: cuenta mensajes no leidos de conversaciones suyas + comunicados
 * globales no leidos (segun comunicado_visto).
 * Para ministerio: cuenta mensajes no leidos en TODAS las conversaciones.
 */
function coms_contar_no_leidos(string $actor, ?int $empresa_id = null): int {
    if (!coms_schema_disponible()) return 0;

    $db = getDB();

    if ($actor === 'empresa') {
        if (!$empresa_id) return 0;

        // Mensajes 1-a-1 no leidos
        $stmt = $db->prepare("
            SELECT COUNT(*)
              FROM mensajes_v2 m
              JOIN conversaciones c ON c.id = m.conversacion_id
             WHERE c.empresa_id = ?
               AND c.estado <> 'archivada'
               AND m.es_borrador = 0
               AND m.leido_at IS NULL
               AND m.remitente_tipo <> 'empresa'
        ");
        $stmt->execute([$empresa_id]);
        $directos = (int)$stmt->fetchColumn();

        // Comunicados globales no leidos por esta empresa
        $stmt = $db->prepare("
            SELECT COUNT(*)
              FROM conversaciones c
              LEFT JOIN comunicado_visto cv
                ON cv.conversacion_id = c.id AND cv.empresa_id = ?
             WHERE c.empresa_id IS NULL
               AND c.estado <> 'archivada'
               AND (cv.leido_at IS NULL)
               AND COALESCE(cv.archivado, 0) = 0
        ");
        $stmt->execute([$empresa_id]);
        $globales = (int)$stmt->fetchColumn();

        return $directos + $globales;
    }

    // Ministerio
    $stmt = $db->query("
        SELECT COUNT(*)
          FROM mensajes_v2 m
          JOIN conversaciones c ON c.id = m.conversacion_id
         WHERE c.estado <> 'archivada'
           AND m.es_borrador = 0
           AND m.leido_at IS NULL
           AND m.remitente_tipo <> 'ministerio'
    ");
    return (int)$stmt->fetchColumn();
}

/**
 * Verifica que un actor tenga permiso para acceder a una conversacion.
 *
 * @return bool
 */
function coms_puede_acceder(int $conversacion_id, string $actor, ?int $empresa_id = null): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT empresa_id FROM conversaciones WHERE id = ?");
    $stmt->execute([$conversacion_id]);
    $row = $stmt->fetch();
    if (!$row) return false;

    if ($actor === 'ministerio') return true;

    if ($actor === 'empresa') {
        if (!$empresa_id) return false;
        // Acceso si es su propia conversacion o si es un comunicado global
        return ((int)$row['empresa_id'] === $empresa_id) || ($row['empresa_id'] === null);
    }
    return false;
}

/**
 * Inserta un adjunto en un mensaje. NO valida tipo/tamano: eso lo hace
 * el endpoint que recibe el upload con COMS_ALLOWED_MIMES y COMS_MAX_TOTAL_BYTES.
 */
function coms_agregar_adjunto(int $mensaje_id, array $archivo): int {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO adjuntos_mensajes
            (mensaje_id, archivo_url, archivo_nombre, archivo_tipo, archivo_tamano)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $mensaje_id,
        $archivo['url'],
        $archivo['nombre'],
        $archivo['tipo'],
        (int)$archivo['tamano'],
    ]);
    return (int)$db->lastInsertId();
}

/**
 * Suma el peso (bytes) de los adjuntos de un mensaje. Util para validar
 * el limite de 25MB total cuando el usuario sube en varias tandas.
 */
function coms_suma_adjuntos_bytes(int $mensaje_id): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COALESCE(SUM(archivo_tamano), 0) FROM adjuntos_mensajes WHERE mensaje_id = ?");
    $stmt->execute([$mensaje_id]);
    return (int)$stmt->fetchColumn();
}
