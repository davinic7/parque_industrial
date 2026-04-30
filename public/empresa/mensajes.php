<?php
/**
 * Mensajes - Panel Empresa (bandeja tipo inbox)
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['empresa'], PUBLIC_URL . '/login.php')) exit;

// Si el Centro de Comunicaciones esta activo, redirigir al nuevo panel.
require_once BASEPATH . '/includes/comunicaciones.php';
if (FEATURE_CENTRO_COMS && coms_schema_disponible()) {
    redirect('comunicaciones.php');
}

$page_title = 'Mensajes';
$db = getDB();
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);

$CATEGORIAS = [
    'consulta' => 'Consulta general',
    'tramite' => 'Trámite / gestión',
    'reclamo' => 'Reclamo',
    'sugerencia' => 'Sugerencia',
    'otro' => 'Otro',
];

function mensajes_decode_adjuntos(?string $json): array {
    if ($json === null || $json === '') {
        return [];
    }
    $d = json_decode($json, true);

    return is_array($d) ? $d : [];
}

$error_form = '';

if (($_GET['upload_error'] ?? '') === 'size') {
    $error_form = 'El archivo adjunto supera el tamaño máximo permitido. Por favor, reducí el tamaño del PDF e intentá nuevamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'leer') {
        $mid = (int) ($_POST['mensaje_id'] ?? 0);
        if ($mid > 0) {
            $stmt = $db->prepare('UPDATE mensajes SET leido = 1, fecha_lectura = NOW() WHERE id = ? AND destinatario_id = ?');
            $stmt->execute([$mid, $user_id]);
        }
        $redir = 'mensajes.php';
        if (!empty($_POST['redirect_id'])) {
            $redir .= '?id=' . (int) $_POST['redirect_id'];
        }
        redirect($redir);
    }

    if ($accion === 'leer_todas') {
        $stmt = $db->prepare('UPDATE mensajes SET leido = 1, fecha_lectura = NOW() WHERE destinatario_id = ? AND leido = 0');
        $stmt->execute([$user_id]);
        set_flash('success', 'Todos los mensajes fueron marcados como leídos.');
        redirect('mensajes.php');
    }

    if (($accion === 'nuevo_mensaje' || $accion === 'responder') && $empresa_id > 0) {
        $asunto = trim($_POST['asunto'] ?? '');
        $contenido = trim($_POST['contenido'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $padre_id = (int) ($_POST['mensaje_padre_id'] ?? 0);

        if ($accion === 'responder') {
            if ($padre_id <= 0) {
                $error_form = 'Mensaje de referencia no válido.';
            } else {
                $chk = $db->prepare('SELECT id, asunto FROM mensajes WHERE id = ? AND destinatario_id = ?');
                $chk->execute([$padre_id, $user_id]);
                $padre = $chk->fetch(PDO::FETCH_ASSOC);
                if (!$padre) {
                    $error_form = 'No podés responder este mensaje.';
                } elseif ($asunto === '') {
                    $asunto = 'Re: ' . $padre['asunto'];
                }
            }
        } elseif ($asunto === '') {
            $error_form = 'El asunto es obligatorio.';
        }

        if ($categoria !== '' && !isset($CATEGORIAS[$categoria])) {
            $categoria = 'otro';
        }

        if ($contenido === '') {
            $error_form = 'Escribí el mensaje.';
        }

        $files = normalize_uploaded_files($accion === 'responder' ? 'adjuntos_respuesta' : 'adjuntos');
        $up = ['success' => true, 'saved' => []];
        if (!empty($files)) {
            $up = upload_pdf_batch($files, 'mensajes', 5);
        }

        if ($error_form === '' && !$up['success']) {
            $error_form = $up['error'];
        }

        if ($error_form === '') {
            $adj_json = !empty($up['saved']) ? json_encode($up['saved'], JSON_UNESCAPED_UNICODE) : null;
            $has_cat = db_table_has_column($db, 'mensajes', 'categoria');
            try {
                if ($has_cat) {
                    $stmt = $db->prepare('
                        INSERT INTO mensajes (remitente_id, destinatario_id, empresa_id, asunto, categoria, contenido, adjuntos, mensaje_padre_id, leido)
                        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 0)
                    ');
                    $stmt->execute([
                        $user_id,
                        $empresa_id,
                        $asunto,
                        $accion === 'nuevo_mensaje' ? ($categoria ?: null) : null,
                        $contenido,
                        $adj_json,
                        $accion === 'responder' ? $padre_id : null,
                    ]);
                } else {
                    $stmt = $db->prepare('
                        INSERT INTO mensajes (remitente_id, destinatario_id, empresa_id, asunto, contenido, adjuntos, mensaje_padre_id, leido)
                        VALUES (?, NULL, ?, ?, ?, ?, ?, 0)
                    ');
                    $stmt->execute([
                        $user_id,
                        $empresa_id,
                        $asunto,
                        $contenido,
                        $adj_json,
                        $accion === 'responder' ? $padre_id : null,
                    ]);
                }
                $new_id = (int) $db->lastInsertId();
                log_activity('mensaje_enviado_ministerio', 'mensajes', $empresa_id);

                try {
                    $stmt_min = $db->query("SELECT id FROM usuarios WHERE rol IN ('ministerio', 'admin')");
                    $preview = mb_substr($asunto, 0, 80);
                    while ($min = $stmt_min->fetch()) {
                        crear_notificacion(
                            (int) $min['id'],
                            'mensaje_empresa',
                            'Mensaje de una empresa',
                            $preview,
                            rtrim(MINISTERIO_URL, '/') . '/mensajes-entrada.php?id=' . $new_id
                        );
                    }
                } catch (Throwable $e) {
                    error_log('mensajes.php notif ministerio: ' . $e->getMessage());
                }

                set_flash('success', $accion === 'responder' ? 'Respuesta enviada al Ministerio.' : 'Mensaje enviado al Ministerio.');
                redirect('mensajes.php');
            } catch (Throwable $e) {
                error_log('mensajes envío empresa: ' . $e->getMessage());
                $error_form = 'No se pudo enviar el mensaje. Si falta la columna categoria, ejecutá database/015_mensajes_categoria.sql';
            }
        }
    }
}

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$sel_id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT COUNT(*) FROM mensajes WHERE destinatario_id = ?');
$stmt->execute([$user_id]);
$total = (int) $stmt->fetchColumn();

$url_pag = 'mensajes.php?pagina={page}' . ($sel_id > 0 ? '&id=' . $sel_id : '');
$pagination = paginate($total, 25, $pagina, $url_pag);
$offset = ($pagination['current_page'] - 1) * 25;

$stmt = $db->prepare("
    SELECT m.id, m.asunto, m.contenido, m.leido, m.created_at, m.adjuntos, u.email AS remitente_email
    FROM mensajes m
    LEFT JOIN usuarios u ON m.remitente_id = u.id
    WHERE m.destinatario_id = ?
    ORDER BY m.created_at DESC
    LIMIT 25 OFFSET $offset
");
$stmt->execute([$user_id]);
$mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare('SELECT COUNT(*) FROM mensajes WHERE destinatario_id = ? AND leido = 0');
$stmt->execute([$user_id]);
$no_leidos = (int) $stmt->fetchColumn();

$detalle = null;
if ($sel_id > 0) {
    $stmt = $db->prepare('
        SELECT m.*, u.email AS remitente_email
        FROM mensajes m
        LEFT JOIN usuarios u ON m.remitente_id = u.id
        WHERE m.id = ? AND m.destinatario_id = ?
    ');
    $stmt->execute([$sel_id, $user_id]);
    $detalle = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($detalle) {
        $db->prepare('UPDATE mensajes SET leido = 1, fecha_lectura = NOW() WHERE id = ? AND destinatario_id = ? AND leido = 0')
            ->execute([$sel_id, $user_id]);
        foreach ($mensajes as &$mm) {
            if ((int) $mm['id'] === $sel_id) {
                $mm['leido'] = 1;
            }
        }
        unset($mm);
    } else {
        $sel_id = 0;
    }
}

$empresa_nav = '';
$puH = htmlspecialchars(PUBLIC_URL, ENT_QUOTES, 'UTF-8');
$extra_head = '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="' . $puH . '/css/empresa-inbox.css" rel="stylesheet">';
require_once BASEPATH . '/includes/empresa_layout_header.php';
?>
        <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mb-3">
            <div class="d-flex flex-wrap gap-2">
                <?php if ($no_leidos > 0): ?>
                <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="accion" value="leer_todas">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-check-all me-1"></i>Marcar todos leídos</button>
                </form>
                <?php endif; ?>
                <button type="button" class="btn btn-inbox-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoMensaje">
                    <i class="bi bi-send-plus me-1"></i>Contactar al Ministerio
                </button>
            </div>
        </div>

        <?php show_flash(); ?>
        <?php if ($error_form !== ''): ?>
        <div class="alert alert-danger"><?= e($error_form) ?></div>
        <?php endif; ?>

        <?php if (empty($mensajes)): ?>
        <div class="inbox-empty border rounded-3 bg-light py-5">
            <i class="bi bi-inbox d-block mb-3"></i>
            <p class="mb-2 fw-semibold text-secondary">No tenés mensajes en la bandeja</p>
            <p class="small text-muted mb-3">Los comunicados del Ministerio aparecerán aquí. Podés escribirles con el botón «Contactar al Ministerio».</p>
            <button type="button" class="btn btn-inbox-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoMensaje">
                <i class="bi bi-envelope-plus me-1"></i>Nuevo mensaje
            </button>
        </div>
        <?php else: ?>
        <div class="inbox-wrap">
            <div class="inbox-toolbar d-flex flex-column gap-2">
                <div class="small text-muted">
                    <?php if ($no_leidos > 0): ?>
                    <span class="badge bg-primary"><?= $no_leidos ?> sin leer</span>
                    <?php else: ?>
                    <span class="text-success"><i class="bi bi-check2-circle me-1"></i>Todo leído</span>
                    <?php endif; ?>
                    <span class="ms-2"><?= $total ?> mensaje(s)</span>
                </div>
            </div>
            <div class="inbox-list-col">
                <div class="inbox-list-scroll">
                    <?php foreach ($mensajes as $m): ?>
                    <?php
                    $is_active = $sel_id === (int) $m['id'];
                    $unread = empty($m['leido']);
                    $cls = 'inbox-item' . ($is_active ? ' inbox-item--active' : '') . ($unread ? ' inbox-item--unread' : '');
                    ?>
                    <?php
                    $q_link = ['id' => (int) $m['id']];
                    if ($pagina > 1) {
                        $q_link['pagina'] = $pagina;
                    }
                    ?>
                    <a href="<?= e('mensajes.php?' . http_build_query($q_link)) ?>"
                       class="<?= $cls ?>"
                       data-msg-id="<?= (int) $m['id'] ?>"
                       data-inbox-link>
                        <div class="inbox-item-subject"><?= e($m['asunto']) ?></div>
                        <div class="inbox-item-meta">
                            <i class="bi bi-building me-1"></i>Ministerio
                            · <?= e(format_datetime($m['created_at'])) ?>
                        </div>
                        <div class="inbox-item-snippet"><?= e(preg_replace('/\s+/', ' ', trim(strip_tags($m['contenido'])))) ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($pagination['total_pages'] > 1): ?>
                <div class="p-2 border-top bg-white small">
                    <?= render_pagination($pagination) ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="inbox-detail-col" id="panelLectura">
                <?php if (!$detalle): ?>
                <div class="inbox-empty" id="inboxEmptyState">
                    <i class="bi bi-envelope-open"></i>
                    <p class="mt-3 mb-0 fw-medium">Seleccioná un mensaje para leerlo</p>
                    <p class="small text-muted mt-2">Hacé clic en un ítem de la lista</p>
                </div>
                <div class="inbox-detail-inner d-none" id="inboxReader"></div>
                <?php else: ?>
                <div class="inbox-detail-inner" id="inboxReader">
                    <?php
                    $rem = !empty($detalle['remitente_email']) ? $detalle['remitente_email'] : 'Ministerio';
                    $adjs = mensajes_decode_adjuntos($detalle['adjuntos'] ?? null);
                    ?>
                    <div class="inbox-detail-head">
                        <h2><?= e($detalle['asunto']) ?></h2>
                        <div class="small text-muted">
                            <strong class="text-secondary">De:</strong> <?= e($rem) ?>
                            <span class="mx-2">·</span>
                            <?= e(format_datetime($detalle['created_at'])) ?>
                        </div>
                    </div>
                    <div class="mensaje-cuerpo text-secondary" style="white-space: pre-wrap;"><?= e($detalle['contenido']) ?></div>
                    <?php if (!empty($adjs)): ?>
                    <div class="mt-3 pt-3 border-top">
                        <div class="small fw-semibold text-muted mb-2">Adjuntos</div>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($adjs as $fn): ?>
                            <?php if (!is_string($fn) || $fn === '') {
                                continue;
                            } ?>
                            <li class="mb-1">
                                <a href="<?= e(uploads_resolve_url($fn, 'mensajes')) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                    <i class="bi bi-file-earmark-pdf text-danger me-1"></i><?= e(basename($fn)) ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div class="inbox-reply-card">
                        <h3 class="h6 text-secondary mb-3"><i class="bi bi-reply me-2"></i>Responder al Ministerio</h3>
                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="responder">
                            <input type="hidden" name="mensaje_padre_id" value="<?= (int) $detalle['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label small">Asunto</label>
                                <input type="text" name="asunto" class="form-control form-control-sm" value="<?= e('Re: ' . $detalle['asunto']) ?>" maxlength="255">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Mensaje *</label>
                                <textarea name="contenido" class="form-control form-control-sm" rows="4" required placeholder="Escribí tu respuesta…"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Adjuntar PDF (hasta 5 archivos)</label>
                                <input type="file" name="adjuntos_respuesta[]" class="form-control form-control-sm" accept=".pdf,application/pdf" multiple>
                            </div>
                            <button type="submit" class="btn btn-inbox-primary btn-sm"><i class="bi bi-send me-1"></i>Enviar respuesta</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    <!-- Modal nuevo mensaje -->
    <div class="modal fade" id="modalNuevoMensaje" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title text-secondary"><i class="bi bi-envelope-plus me-2"></i>Contactar al Ministerio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data" id="formNuevoMensaje">
                        <?= csrf_field() ?>
                        <input type="hidden" name="accion" value="nuevo_mensaje">
                        <div class="mb-3">
                            <label class="form-label">Asunto *</label>
                            <input type="text" name="asunto" class="form-control" required maxlength="255" placeholder="Resumen breve">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Categoría</label>
                            <select name="categoria" class="form-select">
                                <?php foreach ($CATEGORIAS as $k => $lab): ?>
                                <option value="<?= e($k) ?>"><?= e($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mensaje *</label>
                            <textarea name="contenido" class="form-control" rows="6" required placeholder="Detallá tu consulta o gestión…"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adjuntar PDF (opcional, hasta 5)</label>
                            <input type="file" name="adjuntos[]" class="form-control" accept=".pdf,application/pdf" multiple>
                            <div class="form-text">Solo archivos PDF. Tamaño máximo por archivo según configuración del servidor.</div>
                        </div>
                        <button type="submit" class="btn btn-inbox-primary"><i class="bi bi-send me-1"></i>Enviar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <template id="csrfFieldTpl"><?= csrf_field() ?></template>

<?php
$extra_scripts = <<<'EOT'
    <script>
    (function() {
        const apiUrl = 'mensajes_api.php';
        const reader = document.getElementById('inboxReader');
        const emptyState = document.getElementById('inboxEmptyState');
        const links = document.querySelectorAll('a[data-inbox-link]');
        const csrfTpl = document.getElementById('csrfFieldTpl');
        if (!reader || !links.length) return;

        function esc(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function renderDetail(data) {
            let adj = '';
            if (data.adjuntos && data.adjuntos.length) {
                adj = '<div class="mt-3 pt-3 border-top"><div class="small fw-semibold text-muted mb-2">Adjuntos</div><ul class="list-unstyled mb-0">';
                data.adjuntos.forEach(function(a) {
                    adj += '<li class="mb-1"><a href="' + esc(a.url) + '" target="_blank" rel="noopener" class="text-decoration-none"><i class="bi bi-file-earmark-pdf text-danger me-1"></i>' + esc(a.nombre) + '</a></li>';
                });
                adj += '</ul></div>';
            }
            const csrfHtml = csrfTpl ? csrfTpl.innerHTML : '';
            const reply = ''
                + '<div class="inbox-reply-card">'
                + '<h3 class="h6 text-secondary mb-3"><i class="bi bi-reply me-2"></i>Responder al Ministerio</h3>'
                + '<form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>'
                + csrfHtml
                + '<input type="hidden" name="accion" value="responder">'
                + '<input type="hidden" name="mensaje_padre_id" value="' + data.id + '">'
                + '<div class="mb-2"><label class="form-label small">Asunto</label>'
                + '<input type="text" name="asunto" class="form-control form-control-sm" value="' + esc('Re: ' + data.asunto) + '" maxlength="255"></div>'
                + '<div class="mb-2"><label class="form-label small">Mensaje *</label>'
                + '<textarea name="contenido" class="form-control form-control-sm" rows="4" required placeholder="Escribí tu respuesta…"></textarea></div>'
                + '<div class="mb-3"><label class="form-label small">Adjuntar PDF (hasta 5)</label>'
                + '<input type="file" name="adjuntos_respuesta[]" class="form-control form-control-sm" accept=".pdf,application/pdf" multiple></div>'
                + '<button type="submit" class="btn btn-inbox-primary btn-sm"><i class="bi bi-send me-1"></i>Enviar respuesta</button>'
                + '</form></div>';

            reader.innerHTML = ''
                + '<div class="inbox-detail-head"><h2>' + esc(data.asunto) + '</h2>'
                + '<div class="small text-muted"><strong class="text-secondary">De:</strong> ' + esc(data.remitente)
                + '<span class="mx-2">·</span>' + esc(data.fecha) + '</div></div>'
                + '<div class="mensaje-cuerpo text-secondary" style="white-space:pre-wrap;">' + esc(data.contenido) + '</div>'
                + adj + reply;
        }

        links.forEach(function(a) {
            a.addEventListener('click', function(ev) {
                if (ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button !== 0) return;
                ev.preventDefault();
                const id = this.getAttribute('data-msg-id');
                links.forEach(function(x) { x.classList.remove('inbox-item--active'); });
                this.classList.add('inbox-item--active');
                this.classList.remove('inbox-item--unread');
                const subj = this.querySelector('.inbox-item-subject');
                if (subj) subj.style.fontWeight = '400';

                fetch(apiUrl + '?id=' + encodeURIComponent(id), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.ok) throw new Error(data.error || 'Error');
                        if (emptyState) { emptyState.classList.add('d-none'); reader.classList.remove('d-none'); }
                        renderDetail(data);
                        const u = new URL(window.location.href);
                        u.searchParams.set('id', id);
                        history.pushState({ inboxId: id }, '', u);
                    })
                    .catch(function() { window.location.href = a.getAttribute('href'); });
            });
        });
    })();
    </script>
EOT;
require_once BASEPATH . '/includes/empresa_layout_footer.php';
