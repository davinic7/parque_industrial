<?php
/**
 * Formulario Dinámico Genérico - Empresa
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Formulario';
$db = getDB();
$empresa_id = $_SESSION['empresa_id'] ?? null;

if (!$empresa_id) {
    set_flash('error', 'No se encontró la empresa asociada a su cuenta');
    redirect('dashboard.php');
}

$formulario_id = (int)($_GET['id'] ?? 0);
$mensaje = '';
$error = '';

if ($formulario_id <= 0) {
    set_flash('error', 'Formulario no especificado.');
    redirect('dashboard.php');
}

// Cargar formulario
$stmt = $db->prepare("SELECT * FROM formularios_dinamicos WHERE id = ? AND estado = 'publicado'");
$stmt->execute([$formulario_id]);
$formulario = $stmt->fetch();

if (!$formulario) {
    $error = 'El formulario solicitado no está disponible.';
} else {
    $page_title = $formulario['titulo'];

    $plazo_info = null;
    try {
        $stPl = $db->prepare("
            SELECT COALESCE(fd.plazo_hasta, fe.fecha_limite) AS limite
            FROM formulario_destinatarios fd
            INNER JOIN formulario_envios fe ON fe.id = fd.envio_id
            WHERE fd.empresa_id = ? AND fe.formulario_id = ? AND fd.respondido = 0
            ORDER BY fe.created_at DESC
            LIMIT 1
        ");
        $stPl->execute([$empresa_id, $formulario_id]);
        $plazo_info = $stPl->fetch();
    } catch (Exception $e) {
        $plazo_info = null;
    }

    // Cargar preguntas
    $stmt = $db->prepare("SELECT * FROM formulario_preguntas WHERE formulario_id = ? ORDER BY orden, id");
    $stmt->execute([$formulario_id]);
    $preguntas = $stmt->fetchAll();

    // Cargar respuesta existente
    $stmt = $db->prepare("
        SELECT * FROM formulario_respuestas
        WHERE formulario_id = ? AND empresa_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$formulario_id, $empresa_id]);
    $respuesta_existente = $stmt->fetch();

    $valores_actuales = [];
    if ($respuesta_existente) {
        $json = json_decode($respuesta_existente['respuestas'] ?? '{}', true);
        if (is_array($json)) {
            $valores_actuales = $json;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
            $error = 'Token de seguridad inválido. Recargue la página.';
        } else {
            try {
                $accion = $_POST['accion'] ?? 'guardar';
                $estado = $accion === 'enviar' ? 'enviado' : 'borrador';

                $respuestas = [];
                $errores_campos = [];

                foreach ($preguntas as $p) {
                    $campo_name = 'campo_' . $p['id'];
                    $tipo = $p['tipo'];
                    $requerido = (bool)$p['requerido'];

                    if ($tipo === 'archivo') {
                        $valor = $valores_actuales[$p['id']] ?? null;
                        if (!empty($_FILES[$campo_name]['name'])) {
                            $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
                            $res = upload_file($_FILES[$campo_name], 'formularios', $allowed);
                            if ($res['success']) {
                                $valor = $res['filename'];
                            } else {
                                $errores_campos[$p['id']] = $res['error'];
                            }
                        }
                    } else {
                        $valor = $_POST[$campo_name] ?? null;
                        if (is_array($valor)) {
                            $valor = array_values(array_filter($valor, static function ($v) {
                                return $v !== '' && $v !== null;
                            }));
                        } elseif ($tipo === 'checkbox') {
                            $valor = isset($_POST[$campo_name]) ? 1 : 0;
                        }
                    }

                    if ($estado === 'enviado' && $requerido) {
                        $vacio = ($valor === null || $valor === '' || (is_array($valor) && empty($valor)));
                        if ($vacio) {
                            $errores_campos[$p['id']] = 'Este campo es obligatorio.';
                        }
                    }

                    $respuestas[$p['id']] = $valor;
                }

                if (!empty($errores_campos)) {
                    $error = 'Revise los campos marcados en rojo.';
                    $valores_actuales = $respuestas;
                } else {
                    $json_respuestas = json_encode($respuestas, JSON_UNESCAPED_UNICODE);
                    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                    $usuario_id = $_SESSION['user_id'] ?? null;

                    if ($respuesta_existente) {
                        $stmt = $db->prepare("
                            UPDATE formulario_respuestas
                            SET estado = ?, respuestas = ?, ip = ?, usuario_id = ?,
                                enviado_at = CASE WHEN ? = 'enviado' THEN NOW() ELSE enviado_at END
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $estado,
                            $json_respuestas,
                            $ip,
                            $usuario_id,
                            $estado,
                            $respuesta_existente['id'],
                        ]);
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO formulario_respuestas
                            (formulario_id, empresa_id, usuario_id, estado, respuestas, ip, enviado_at)
                            VALUES (?, ?, ?, ?, ?, ?, CASE WHEN ? = 'enviado' THEN NOW() ELSE NULL END)
                        ");
                        $stmt->execute([
                            $formulario_id,
                            $empresa_id,
                            $usuario_id,
                            $estado,
                            $json_respuestas,
                            $ip,
                            $estado,
                        ]);
                    }

                    log_activity(
                        $estado === 'enviado' ? 'formulario_dinamico_enviado' : 'formulario_dinamico_borrador',
                        'formulario_respuestas',
                        $empresa_id
                    );

                    if ($estado === 'enviado') {
                        try {
                            $db->prepare("
                                UPDATE formulario_destinatarios fd
                                INNER JOIN formulario_envios fe ON fe.id = fd.envio_id
                                SET fd.respondido = 1, fd.fecha_respuesta = NOW()
                                WHERE fe.formulario_id = ? AND fd.empresa_id = ? AND fd.respondido = 0
                            ")->execute([$formulario_id, $empresa_id]);
                        } catch (Exception $e) {
                            // sin tablas de envío
                        }
                    }

                    $mensaje = $estado === 'enviado'
                        ? 'Formulario enviado correctamente.'
                        : 'Borrador guardado correctamente.';

                    $stmt = $db->prepare("
                        SELECT * FROM formulario_respuestas
                        WHERE formulario_id = ? AND empresa_id = ?
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $stmt->execute([$formulario_id, $empresa_id]);
                    $respuesta_existente = $stmt->fetch();
                    $valores_actuales = json_decode($respuesta_existente['respuestas'] ?? '{}', true) ?? [];
                }
            } catch (Exception $e) {
                error_log('Error formulario dinámico: ' . $e->getMessage());
                $error = 'Ocurrió un error al procesar el formulario. Intente nuevamente.';
            }
        }
    }
}

$empresa_nav = 'formularios';
$extra_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">';
require_once BASEPATH . '/includes/empresa_layout_header.php';
?>
        <h1 class="h3 mb-4"><?= e($page_title) ?></h1>

        <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= e($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php
        if (!empty($plazo_info['limite']) && empty($error)) {
            $ts = strtotime($plazo_info['limite'] . ' 23:59:59');
            $dias = (int)floor(($ts - time()) / 86400);
            if ($dias < 0) {
                echo '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>La fecha límite para este formulario ya venció (' . e($plazo_info['limite']) . '). Contacte al ministerio si necesita una prórroga.</div>';
            } elseif ($dias <= 3) {
                echo '<div class="alert alert-warning"><i class="bi bi-clock me-2"></i>Fecha límite: <strong>' . e($plazo_info['limite']) . '</strong> (quedan ' . $dias . ' día(s)).</div>';
            } else {
                echo '<div class="alert alert-info py-2 small mb-3">Fecha límite sugerida: <strong>' . e($plazo_info['limite']) . '</strong></div>';
            }
        }
        ?>

        <?php if (!empty($formulario) && !empty($preguntas)): ?>
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><?= e($formulario['titulo']) ?></h5>
            </div>
            <div class="card-body">
                <p class="mb-0 text-muted small"><?= e($formulario['descripcion'] ?? '') ?></p>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <?= csrf_field() ?>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($preguntas as $p):
                            $campo_name = 'campo_' . $p['id'];
                            $tipo = $p['tipo'];
                            $valor = $valores_actuales[$p['id']] ?? '';
                            $opciones = null;
                            if (!empty($p['opciones'])) {
                                $decoded = json_decode($p['opciones'], true);
                                if (isset($decoded['items']) && is_array($decoded['items'])) {
                                    $opciones = $decoded['items'];
                                }
                            }
                            $es_obligatorio = (bool)$p['requerido'];
                            $es_ubicacion = (stripos($p['etiqueta'], 'ubicacion') !== false || stripos($p['etiqueta'], 'ubicación') !== false);
                        ?>
                        <div class="col-12">
                            <label class="form-label">
                                <?= e($p['etiqueta']) ?>
                                <?php if ($es_obligatorio): ?><span class="text-danger">*</span><?php endif; ?>
                            </label>
                            <?php if (!empty($p['ayuda'])): ?>
                                <div class="form-text mb-1"><?= e($p['ayuda']) ?></div>
                            <?php endif; ?>

                            <?php if ($es_ubicacion): ?>
                                <div id="mapUbicacion<?= $p['id'] ?>" style="height: 250px; border-radius: 8px;"></div>
                                <input type="hidden"
                                       name="<?= e($campo_name) ?>"
                                       id="<?= e($campo_name) ?>"
                                       value="<?= e(is_array($valor) ? '' : (string)$valor) ?>">
                                <div class="form-text">
                                    Haga clic en el mapa para indicar la ubicación. Se guardarán las coordenadas.
                                </div>
                            <?php elseif ($tipo === 'archivo'): ?>
                                <input
                                    type="file"
                                    name="<?= e($campo_name) ?>"
                                    class="form-control"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    <?= $es_obligatorio && empty($valor) ? 'required' : '' ?>
                                >
                                <?php if (!empty($valor)): ?>
                                <div class="form-text">
                                    Archivo actual:
                                    <a href="<?= UPLOADS_URL ?>/formularios/<?= e($valor) ?>" target="_blank"><?= e($valor) ?></a>
                                    — Subí uno nuevo para reemplazarlo.
                                </div>
                                <?php endif; ?>
                            <?php elseif ($tipo === 'direccion'): ?>
                                <input
                                    type="text"
                                    name="<?= e($campo_name) ?>"
                                    class="form-control"
                                    placeholder="Ej: Av. Siempre Viva 742, Catamarca"
                                    value="<?= e(is_array($valor) ? '' : (string)$valor) ?>"
                                    <?= $es_obligatorio ? 'required' : '' ?>
                                >
                            <?php elseif ($tipo === 'numero'): ?>
                                <input
                                    type="number"
                                    name="<?= e($campo_name) ?>"
                                    class="form-control"
                                    value="<?= e(is_array($valor) ? '' : (string)$valor) ?>"
                                    <?= $es_obligatorio ? 'required' : '' ?>
                                    <?= $p['min_valor'] !== null ? 'min="' . $p['min_valor'] . '"' : '' ?>
                                    <?= $p['max_valor'] !== null ? 'max="' . $p['max_valor'] . '"' : '' ?>
                                    step="any"
                                >
                                <?php if ($p['min_valor'] !== null || $p['max_valor'] !== null): ?>
                                <div class="form-text">
                                    <?php if ($p['min_valor'] !== null && $p['max_valor'] !== null): ?>
                                        Valor entre <?= $p['min_valor'] ?> y <?= $p['max_valor'] ?>
                                    <?php elseif ($p['min_valor'] !== null): ?>
                                        Valor mínimo: <?= $p['min_valor'] ?>
                                    <?php else: ?>
                                        Valor máximo: <?= $p['max_valor'] ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            <?php elseif ($tipo === 'texto' || $tipo === 'fecha' || $tipo === 'email'): ?>
                                <input
                                    type="<?= $tipo === 'texto' ? 'text' : ($tipo === 'fecha' ? 'date' : 'email') ?>"
                                    name="<?= e($campo_name) ?>"
                                    class="form-control"
                                    value="<?= e(is_array($valor) ? '' : (string)$valor) ?>"
                                    <?= $es_obligatorio ? 'required' : '' ?>
                                >
                            <?php elseif ($tipo === 'textarea'): ?>
                                <textarea
                                    name="<?= e($campo_name) ?>"
                                    class="form-control"
                                    rows="4"
                                    <?= $es_obligatorio ? 'required' : '' ?>
                                ><?= e(is_array($valor) ? '' : (string)$valor) ?></textarea>
                            <?php elseif ($tipo === 'select' && $opciones): ?>
                                <select
                                    name="<?= e($campo_name) ?>"
                                    class="form-select"
                                    <?= $es_obligatorio ? 'required' : '' ?>
                                >
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($opciones as $opt): ?>
                                        <option value="<?= e($opt) ?>" <?= (string)$valor === (string)$opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($tipo === 'radio' && $opciones): ?>
                                <div class="d-flex flex-wrap gap-3">
                                    <?php foreach ($opciones as $opt): ?>
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="radio"
                                            name="<?= e($campo_name) ?>"
                                            id="<?= e($campo_name . '_' . md5($opt)) ?>"
                                            value="<?= e($opt) ?>"
                                            <?= (string)$valor === (string)$opt ? 'checked' : '' ?>
                                            <?= $es_obligatorio ? 'required' : '' ?>
                                        >
                                        <label class="form-check-label" for="<?= e($campo_name . '_' . md5($opt)) ?>"><?= e($opt) ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($tipo === 'checkbox' && $opciones): ?>
                                <?php $vals = is_array($valor) ? $valor : []; ?>
                                <div class="d-flex flex-wrap gap-3">
                                    <?php foreach ($opciones as $opt): ?>
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="<?= e($campo_name) ?>[]"
                                            id="<?= e($campo_name . '_' . md5($opt)) ?>"
                                            value="<?= e($opt) ?>"
                                            <?= in_array($opt, $vals, true) ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label" for="<?= e($campo_name . '_' . md5($opt)) ?>"><?= e($opt) ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($tipo === 'checkbox' && !$opciones): ?>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="<?= e($campo_name) ?>"
                                        id="<?= e($campo_name) ?>"
                                        value="1"
                                        <?= !empty($valor) ? 'checked' : '' ?>
                                        <?= $es_obligatorio ? 'required' : '' ?>
                                    >
                                    <label class="form-check-label" for="<?= e($campo_name) ?>">Sí</label>
                                </div>
                            <?php else: ?>
                                <input
                                    type="text"
                                    name="<?= e($campo_name) ?>"
                                    class="form-control"
                                    value="<?= e(is_array($valor) ? '' : (string)$valor) ?>"
                                    <?= $es_obligatorio ? 'required' : '' ?>
                                >
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-3">
                <button type="submit" name="accion" value="guardar" class="btn btn-outline-secondary">
                    <i class="bi bi-save me-2"></i>Guardar borrador
                </button>
                <button type="submit" name="accion" value="enviar" class="btn btn-primary btn-lg">
                    <i class="bi bi-send me-2"></i>Enviar formulario
                </button>
            </div>
        </form>
        <?php endif; ?>

<?php
ob_start();
$puJs = htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8');
?>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
    <script src="<?= $puJs ?>/js/parque-leaflet.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($preguntas)): ?>
        <?php foreach ($preguntas as $p):
            $campo_name = 'campo_' . $p['id'];
            $es_ubicacion = (stripos($p['etiqueta'], 'ubicacion') !== false || stripos($p['etiqueta'], 'ubicación') !== false);
            if (!$es_ubicacion) {
                continue;
            }
        ?>
        (function() {
            const input = document.getElementById('<?= e($campo_name) ?>');
            const mapEl = document.getElementById('mapUbicacion<?= (int) $p['id'] ?>');
            if (!input || !mapEl) return;

            let lat = <?= (float) MAP_DEFAULT_LAT ?>;
            let lng = <?= (float) MAP_DEFAULT_LNG ?>;

            if (input.value && input.value.includes(',')) {
                const parts = input.value.split(',');
                const latParsed = parseFloat(parts[0]);
                const lngParsed = parseFloat(parts[1]);
                if (!isNaN(latParsed) && !isNaN(lngParsed)) {
                    lat = latParsed;
                    lng = lngParsed;
                }
            }

            const map = L.map(mapEl).setView([lat, lng], 14);
            ParqueLeaflet.addSatelliteLayer(map);
            ParqueLeaflet.addParquePolygon(map);

            let marker = null;
            if (input.value && input.value.includes(',')) {
                marker = L.marker([lat, lng]).addTo(map);
            }

            map.on('click', function(e) {
                if (marker) map.removeLayer(marker);
                marker = L.marker(e.latlng).addTo(map);
                const latStr = e.latlng.lat.toFixed(8);
                const lngStr = e.latlng.lng.toFixed(8);
                input.value = latStr + ',' + lngStr;
            });
        })();
        <?php endforeach; ?>
        <?php endif; ?>
    });
    </script>
<?php
$extra_scripts = ob_get_clean();
require_once BASEPATH . '/includes/empresa_layout_footer.php';

