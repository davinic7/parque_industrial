<?php
/**
 * Partial compartido del Centro de Comunicaciones (Fase 2).
 *
 * Renderiza el panel completo (3 columnas: categorias | lista | hilo).
 * Se incluye desde /empresa/comunicaciones.php y /ministerio/comunicaciones.php.
 *
 * Variables esperadas (definidas por el include padre):
 *   $coms_actor             string  'empresa' | 'ministerio'
 *   $coms_api_base          string  URL base de los endpoints, ej: '../api/comunicaciones'
 *   $coms_puede_broadcast   bool    si puede crear comunicado global
 *   $coms_puede_elegir_empresa bool si puede elegir empresa destinataria (solo ministerio)
 *   $coms_empresas_destino  array<{id,nombre}>  lista para el select (solo si puede_elegir_empresa)
 *
 * El layout asume Bootstrap 5 + Bootstrap Icons ya cargados por el header.
 */

if (!isset($coms_actor) || !isset($coms_api_base)) {
    throw new RuntimeException('comunicaciones_panel.php: faltan variables requeridas.');
}
$coms_puede_broadcast       = $coms_puede_broadcast       ?? false;
$coms_puede_elegir_empresa  = $coms_puede_elegir_empresa  ?? false;
$coms_empresas_destino      = $coms_empresas_destino      ?? [];
?>
<style>
.coms-shell {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 0;
    height: calc(100vh - 200px);
    min-height: 520px;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
}
.coms-sidebar {
    background: #f8f9fa;
    border-right: 1px solid #dee2e6;
    padding: 1rem .75rem;
    overflow-y: auto;
}
.coms-sidebar h6 {
    text-transform: uppercase;
    font-size: .7rem;
    color: #6c757d;
    letter-spacing: .5px;
    margin: 1rem 0 .5rem;
}
.coms-sidebar h6:first-child { margin-top: 0; }
.coms-sidebar .nav-link {
    color: #495057;
    border-radius: 8px;
    padding: .4rem .65rem;
    margin-bottom: 2px;
    font-size: .9rem;
    display: flex; align-items: center; justify-content: space-between;
    cursor: pointer;
}
.coms-sidebar .nav-link:hover { background: #e9ecef; }
.coms-sidebar .nav-link.active { background: #0d6efd; color: #fff; }
.coms-sidebar .nav-link.active:hover { background: #0b5ed7; }
.coms-sidebar .badge-count {
    background: #fff;
    color: #0d6efd;
    border-radius: 10px;
    padding: 1px 7px;
    font-size: .7rem;
    font-weight: 700;
}
.coms-sidebar .nav-link:not(.active) .badge-count {
    background: #0d6efd;
    color: #fff;
}

.coms-main {
    display: grid;
    grid-template-columns: 380px 1fr;
    overflow: hidden;
}
.coms-list {
    border-right: 1px solid #dee2e6;
    overflow-y: auto;
    background: #fff;
}
.coms-list .coms-search {
    padding: .75rem;
    border-bottom: 1px solid #dee2e6;
    background: #fff;
    position: sticky; top: 0; z-index: 2;
}
.coms-list .conv-item {
    padding: .75rem 1rem;
    border-bottom: 1px solid #f1f3f5;
    cursor: pointer;
    display: flex; gap: .75rem; align-items: flex-start;
}
.coms-list .conv-item:hover { background: #f8f9fa; }
.coms-list .conv-item.active { background: #e7f1ff; }
.coms-list .conv-item.unread { font-weight: 600; }
.coms-list .conv-item .conv-dot {
    width: 8px; height: 8px; border-radius: 50%;
    margin-top: 7px; flex-shrink: 0;
}
.coms-list .conv-item.unread .conv-dot { background: #0d6efd; }
.coms-list .conv-item:not(.unread) .conv-dot { background: transparent; }
.coms-list .conv-body { flex: 1; min-width: 0; }
.coms-list .conv-title {
    font-size: .9rem; color: #212529;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.coms-list .conv-meta {
    font-size: .75rem; color: #6c757d;
    margin-top: 2px;
    display: flex; gap: .35rem; align-items: center;
}
.coms-list .conv-cat {
    display: inline-block; padding: 1px 6px; border-radius: 4px;
    font-size: .65rem; text-transform: uppercase; letter-spacing: .3px;
}
.coms-cat-tramite    { background: #cfe2ff; color: #084298; }
.coms-cat-consulta   { background: #d1e7dd; color: #0f5132; }
.coms-cat-reclamo    { background: #f8d7da; color: #842029; }
.coms-cat-comunicado { background: #fff3cd; color: #664d03; }
.coms-cat-formulario { background: #e2e3e5; color: #41464b; }
.coms-cat-sistema    { background: #ced4da; color: #495057; }

.coms-thread {
    display: flex; flex-direction: column;
    overflow: hidden;
    background: #fff;
}
.coms-thread-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #dee2e6;
    background: #f8f9fa;
}
.coms-thread-body {
    flex: 1; overflow-y: auto;
    padding: 1rem 1.25rem;
}
.coms-msg {
    display: flex;
    margin-bottom: 1rem;
}
.coms-msg.out { justify-content: flex-end; }
.coms-msg .bubble {
    max-width: 70%;
    padding: .65rem .9rem;
    border-radius: 12px;
    font-size: .9rem;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.coms-msg.out .bubble  { background: #0d6efd; color: #fff; border-bottom-right-radius: 2px; }
.coms-msg.in  .bubble  { background: #e9ecef; color: #212529; border-bottom-left-radius: 2px; }
.coms-msg .meta {
    font-size: .7rem; color: #6c757d; margin-top: 4px;
}
.coms-msg.out .meta { text-align: right; }
.coms-msg .adj {
    display: inline-block;
    background: rgba(255,255,255,.2);
    padding: 2px 8px;
    border-radius: 6px;
    margin-right: 4px;
    margin-top: 6px;
    font-size: .75rem;
}
.coms-msg.in .adj { background: rgba(0,0,0,.06); }
.coms-msg .adj a { color: inherit; text-decoration: underline; }

.coms-editor {
    border-top: 1px solid #dee2e6;
    padding: .75rem 1rem;
    background: #fff;
}
.coms-editor textarea {
    resize: vertical;
    min-height: 60px;
    max-height: 220px;
    border-radius: 8px;
}
.coms-editor .editor-toolbar {
    display: flex; align-items: center; gap: .5rem;
    margin-top: .5rem;
}
.coms-editor .adj-pill {
    display: inline-flex; align-items: center; gap: 4px;
    background: #e9ecef; padding: 3px 8px; border-radius: 12px;
    font-size: .75rem; color: #495057;
}
.coms-editor .adj-pill button {
    border: none; background: none; padding: 0; line-height: 1; color: #6c757d;
}
.coms-empty {
    flex: 1; display: flex; align-items: center; justify-content: center;
    color: #6c757d; font-size: .9rem; text-align: center; padding: 2rem;
}

@media (max-width: 991px) {
    .coms-shell { grid-template-columns: 1fr; height: auto; min-height: 600px; }
    .coms-sidebar { display: none; }      /* sustituido por dropdown en move-mobile */
    .coms-main { grid-template-columns: 1fr; }
    .coms-list { display: block; }
    .coms-list[data-mobile-state="thread"] { display: none; }
    .coms-thread { display: none; }
    .coms-thread[data-mobile-state="open"] { display: flex; }
}
</style>

<div class="coms-shell" id="coms-shell" data-actor="<?= e($coms_actor) ?>" data-api="<?= e($coms_api_base) ?>" data-csrf="<?= e($_SESSION[CSRF_TOKEN_NAME] ?? '') ?>">
    <!-- Sidebar de filtros -->
    <aside class="coms-sidebar">
        <h6>Bandeja</h6>
        <a class="nav-link active" data-filter-estado="abierta" data-filter-categoria="">
            <span><i class="bi bi-inbox me-2"></i>Activas</span>
            <span class="badge-count" data-counter></span>
        </a>
        <a class="nav-link" data-filter-estado="archivada" data-filter-categoria="">
            <span><i class="bi bi-archive me-2"></i>Archivadas</span>
        </a>

        <h6>Categorias</h6>
        <a class="nav-link" data-filter-estado="abierta" data-filter-categoria="tramite">
            <span><i class="bi bi-file-earmark-text me-2"></i>Tramites</span>
        </a>
        <a class="nav-link" data-filter-estado="abierta" data-filter-categoria="consulta">
            <span><i class="bi bi-question-circle me-2"></i>Consultas</span>
        </a>
        <a class="nav-link" data-filter-estado="abierta" data-filter-categoria="reclamo">
            <span><i class="bi bi-exclamation-triangle me-2"></i>Reclamos</span>
        </a>
        <a class="nav-link" data-filter-estado="abierta" data-filter-categoria="comunicado">
            <span><i class="bi bi-megaphone me-2"></i>Comunicados</span>
        </a>
        <a class="nav-link" data-filter-estado="abierta" data-filter-categoria="formulario">
            <span><i class="bi bi-clipboard-check me-2"></i>Formularios</span>
        </a>

        <hr>
        <button class="btn btn-primary btn-sm w-100" id="btn-nueva-conv">
            <i class="bi bi-pencil-square me-1"></i>Nueva conversacion
        </button>
    </aside>

    <!-- Lista + hilo -->
    <div class="coms-main">
        <div class="coms-list" id="coms-list">
            <div class="coms-search">
                <input type="search" id="coms-search-input" class="form-control form-control-sm" placeholder="Buscar por titulo... (filtra al escribir)">
            </div>
            <div id="coms-list-items">
                <div class="text-center text-muted small p-4">Cargando...</div>
            </div>
        </div>

        <div class="coms-thread" id="coms-thread">
            <div class="coms-empty" id="coms-thread-empty">
                <div>
                    <i class="bi bi-chat-square-text d-block" style="font-size: 3rem; opacity: .3;"></i>
                    <div class="mt-2">Seleccione una conversacion para ver los mensajes.</div>
                </div>
            </div>

            <div id="coms-thread-content" class="d-none flex-column h-100">
                <div class="coms-thread-header d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <button class="btn btn-link btn-sm d-lg-none p-0 mb-1" id="coms-back-list"><i class="bi bi-arrow-left"></i> Volver</button>
                        <h5 class="mb-1" id="coms-thread-title"></h5>
                        <div class="small text-muted" id="coms-thread-meta"></div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><button class="dropdown-item" data-act="no_leida"><i class="bi bi-bell-slash me-2"></i>Marcar como no leida</button></li>
                            <li><button class="dropdown-item" data-act="archivar"><i class="bi bi-archive me-2"></i>Archivar</button></li>
                            <li><button class="dropdown-item d-none" data-act="desarchivar"><i class="bi bi-arrow-counterclockwise me-2"></i>Mover a activas</button></li>
                        </ul>
                    </div>
                </div>

                <div class="coms-thread-body" id="coms-thread-msgs"></div>

                <div class="coms-editor">
                    <div id="coms-attachments-preview" class="mb-2 d-flex flex-wrap gap-2"></div>
                    <textarea id="coms-editor-text" class="form-control" placeholder="Escriba su mensaje..."></textarea>
                    <div class="editor-toolbar justify-content-between">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <input type="file" id="coms-file-input" multiple class="d-none">
                            <button class="btn btn-sm btn-outline-secondary" id="coms-attach-btn">
                                <i class="bi bi-paperclip"></i> Adjuntar
                            </button>
                            <?php if ($coms_actor === 'ministerio'): ?>
                            <button class="btn btn-sm btn-outline-info" id="coms-plantilla-btn" title="Insertar plantilla de respuesta">
                                <i class="bi bi-file-earmark-text"></i> Plantilla
                            </button>
                            <?php endif; ?>
                            <small class="text-muted">Hasta 25 MB en total</small>
                        </div>
                        <button class="btn btn-primary btn-sm" id="coms-send-btn">
                            <i class="bi bi-send"></i> Enviar
                        </button>
                    </div>
                    <div class="small text-success mt-1 d-none" id="coms-draft-status">
                        <i class="bi bi-cloud-check"></i> Borrador guardado
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: nueva conversacion -->
<div class="modal fade" id="coms-modal-nueva" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Nueva conversacion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Asunto</label>
                    <input type="text" class="form-control" id="coms-new-titulo" maxlength="200" placeholder="Resumen breve del tema">
                </div>
                <div class="mb-3">
                    <label class="form-label">Categoria</label>
                    <select class="form-select" id="coms-new-categoria">
                        <option value="consulta">Consulta</option>
                        <option value="tramite">Tramite</option>
                        <option value="reclamo">Reclamo</option>
                        <?php if ($coms_puede_broadcast): ?>
                        <option value="comunicado">Comunicado</option>
                        <?php endif; ?>
                    </select>
                </div>

                <?php if ($coms_puede_elegir_empresa || $coms_puede_broadcast): ?>
                <div class="mb-3">
                    <label class="form-label">Destinatario</label>
                    <select class="form-select" id="coms-new-destinatario">
                        <?php if ($coms_puede_broadcast): ?>
                        <option value="global">Todas las empresas (comunicado)</option>
                        <?php endif; ?>
                        <?php foreach ($coms_empresas_destino as $emp): ?>
                        <option value="empresa:<?= (int)$emp['id'] ?>"><?= e($emp['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Mensaje</label>
                    <textarea class="form-control" id="coms-new-contenido" rows="5" placeholder="Escriba el primer mensaje..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="coms-new-enviar"><i class="bi bi-send me-1"></i>Enviar</button>
            </div>
        </div>
    </div>
</div>

<?php if ($coms_actor === 'ministerio'): ?>
<!-- Modal: elegir plantilla -->
<div class="modal fade" id="coms-modal-plantilla" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>Plantillas de respuesta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-2 border-bottom">
                    <input type="text" class="form-control form-control-sm" id="coms-plantilla-search" placeholder="Buscar plantilla...">
                </div>
                <div id="coms-plantilla-list" style="max-height:360px;overflow-y:auto;">
                    <div class="text-center text-muted py-4"><i class="bi bi-hourglass-split"></i> Cargando...</div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <a href="plantillas.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear me-1"></i>Gestionar plantillas</a>
                <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const shell = document.getElementById('coms-shell');
    const ACTOR    = shell.dataset.actor;
    const API_BASE = shell.dataset.api;
    const CSRF     = shell.dataset.csrf;

    const $list      = document.getElementById('coms-list-items');
    const $search    = document.getElementById('coms-search-input');
    const $threadEmpty = document.getElementById('coms-thread-empty');
    const $threadCont  = document.getElementById('coms-thread-content');
    const $threadMsgs  = document.getElementById('coms-thread-msgs');
    const $threadTitle = document.getElementById('coms-thread-title');
    const $threadMeta  = document.getElementById('coms-thread-meta');
    const $editorText  = document.getElementById('coms-editor-text');
    const $sendBtn     = document.getElementById('coms-send-btn');
    const $attachBtn   = document.getElementById('coms-attach-btn');
    const $fileInput   = document.getElementById('coms-file-input');
    const $attachPrev  = document.getElementById('coms-attachments-preview');
    const $draftStatus = document.getElementById('coms-draft-status');

    let state = {
        filter: { estado: 'abierta', categoria: '', buscar: '' },
        conversaciones: [],
        currentId: null,
        currentMeta: null,
        attachments: [],   // adjuntos pendientes (subidos al borrador)
        draftMsgId: null,
        draftTimer: null,
    };

    // ============== Helpers ==============
    function fmt(d) {
        if (!d) return '';
        const dt = new Date(d.replace(' ', 'T'));
        const now = new Date();
        const diff = (now - dt) / 1000;
        if (diff < 60)        return 'hace un momento';
        if (diff < 3600)      return 'hace ' + Math.floor(diff/60) + ' min';
        if (diff < 86400)     return 'hace ' + Math.floor(diff/3600) + ' h';
        if (diff < 86400*7)   return 'hace ' + Math.floor(diff/86400) + ' d';
        return dt.toLocaleDateString();
    }
    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    // ============== Sidebar ==============
    document.querySelectorAll('.coms-sidebar .nav-link[data-filter-estado]').forEach(el => {
        el.addEventListener('click', e => {
            document.querySelectorAll('.coms-sidebar .nav-link').forEach(n => n.classList.remove('active'));
            el.classList.add('active');
            state.filter.estado    = el.dataset.filterEstado;
            state.filter.categoria = el.dataset.filterCategoria;
            cargarLista();
        });
    });

    // ============== Lista de conversaciones ==============
    let searchDebounce = null;
    $search.addEventListener('input', () => {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            state.filter.buscar = $search.value.trim();
            cargarLista();
        }, 250);
    });

    async function cargarLista() {
        $list.innerHTML = '<div class="text-center text-muted small p-4">Cargando...</div>';
        const params = new URLSearchParams({
            estado:    state.filter.estado,
            categoria: state.filter.categoria,
            buscar:    state.filter.buscar,
        });
        try {
            const r = await fetch(`${API_BASE}/listar.php?${params}`, { credentials: 'same-origin' });
            const data = await r.json();
            if (!data.ok) throw new Error(data.error || 'Error');
            state.conversaciones = data.conversaciones;
            renderLista();
        } catch (e) {
            $list.innerHTML = `<div class="alert alert-danger m-3">${escapeHtml(e.message)}</div>`;
        }
    }

    function renderLista() {
        if (state.conversaciones.length === 0) {
            $list.innerHTML = '<div class="text-center text-muted small p-4">Sin conversaciones</div>';
            return;
        }
        $list.innerHTML = state.conversaciones.map(c => {
            const unread = c.no_leidos > 0;
            const cls = ['conv-item'];
            if (unread) cls.push('unread');
            if (c.id === state.currentId) cls.push('active');
            const empresa = c.empresa_nombre ? `<span>${escapeHtml(c.empresa_nombre)}</span>` :
                             c.es_comunicado_global ? '<span><i class="bi bi-megaphone"></i> Global</span>' : '';
            return `
                <div class="${cls.join(' ')}" data-id="${c.id}">
                    <div class="conv-dot"></div>
                    <div class="conv-body">
                        <div class="conv-title">${escapeHtml(c.titulo)}</div>
                        <div class="conv-meta">
                            <span class="conv-cat coms-cat-${c.categoria}">${c.categoria}</span>
                            ${empresa ? '<span class="text-muted">&middot;</span>' + empresa : ''}
                            <span class="text-muted ms-auto">${fmt(c.ultimo_mensaje_at || c.created_at)}</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        $list.querySelectorAll('.conv-item').forEach(el => {
            el.addEventListener('click', () => abrirConversacion(parseInt(el.dataset.id, 10)));
        });

        // counter
        const total = state.conversaciones.reduce((s, c) => s + c.no_leidos, 0);
        document.querySelectorAll('[data-counter]').forEach(el => {
            el.textContent = total > 0 ? total : '';
        });
    }

    // ============== Hilo abierto ==============
    async function abrirConversacion(id) {
        state.currentId = id;
        state.attachments = [];
        state.draftMsgId = null;
        $attachPrev.innerHTML = '';
        $editorText.value = '';
        $threadEmpty.classList.add('d-none');
        $threadCont.classList.remove('d-none');
        $threadCont.classList.add('d-flex');
        document.getElementById('coms-thread').setAttribute('data-mobile-state', 'open');
        document.getElementById('coms-list').setAttribute('data-mobile-state', 'thread');

        $threadMsgs.innerHTML = '<div class="text-center text-muted small p-4">Cargando...</div>';

        try {
            const r = await fetch(`${API_BASE}/conversacion.php?id=${id}`, { credentials: 'same-origin' });
            const data = await r.json();
            if (!data.ok) throw new Error(data.error || 'Error');
            state.currentMeta = data.conversacion;
            renderThread(data);
            cargarLista();   // recarga la lista para reflejar conteos actualizados
        } catch (e) {
            $threadMsgs.innerHTML = `<div class="alert alert-danger">${escapeHtml(e.message)}</div>`;
        }
    }

    function renderThread(data) {
        $threadTitle.textContent = data.conversacion.titulo;
        const empresa = data.conversacion.empresa_nombre || (data.conversacion.es_comunicado_global ? 'Comunicado global' : '');
        $threadMeta.textContent = `${data.conversacion.categoria}${empresa ? ' &middot; ' + empresa : ''}`;
        $threadMeta.innerHTML = `<span class="conv-cat coms-cat-${data.conversacion.categoria}">${data.conversacion.categoria}</span>${empresa ? ' &middot; ' + escapeHtml(empresa) : ''}`;

        // Toggle archivar/desarchivar segun estado
        const archivada = data.conversacion.estado === 'archivada';
        document.querySelector('[data-act="archivar"]').classList.toggle('d-none', archivada);
        document.querySelector('[data-act="desarchivar"]').classList.toggle('d-none', !archivada);

        $threadMsgs.innerHTML = data.mensajes.map(m => renderMsg(m)).join('');
        $threadMsgs.scrollTop = $threadMsgs.scrollHeight;
    }

    function renderMsg(m) {
        const out = m.remitente_tipo === ACTOR;
        const adj = (m.adjuntos || []).map(a => `
            <a class="adj" href="${escapeHtml(a.url)}" target="_blank" rel="noopener">
                <i class="bi bi-paperclip"></i> ${escapeHtml(a.nombre)} (${(a.tamano/1024).toFixed(0)} KB)
            </a>
        `).join(' ');
        return `
            <div class="coms-msg ${out ? 'out' : 'in'}">
                <div>
                    <div class="bubble">${escapeHtml(m.contenido)}${adj ? '<div class="mt-2">' + adj + '</div>' : ''}</div>
                    <div class="meta">
                        ${out ? 'Enviado' : (m.remitente_email || m.remitente_tipo)} &middot; ${fmt(m.created_at)}
                    </div>
                </div>
            </div>
        `;
    }

    // ============== Editor ==============
    $editorText.addEventListener('input', programarBorrador);
    function programarBorrador() {
        if (!state.currentId) return;
        clearTimeout(state.draftTimer);
        $draftStatus.classList.add('d-none');
        state.draftTimer = setTimeout(guardarBorrador, 1500);
    }
    async function guardarBorrador() {
        if (!state.currentId) return;
        try {
            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('conversacion_id', state.currentId);
            fd.append('contenido', $editorText.value);
            const r = await fetch(`${API_BASE}/borrador.php`, { method:'POST', body: fd, credentials: 'same-origin' });
            const data = await r.json();
            if (data.ok) {
                state.draftMsgId = data.mensaje_id;
                $draftStatus.classList.remove('d-none');
            }
        } catch (e) { /* silencioso */ }
    }

    $sendBtn.addEventListener('click', enviarMensaje);
    async function enviarMensaje() {
        if (!state.currentId) return;
        const contenido = $editorText.value.trim();
        if (!contenido) { $editorText.focus(); return; }
        $sendBtn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('conversacion_id', state.currentId);
            fd.append('contenido', contenido);
            const r = await fetch(`${API_BASE}/enviar.php`, { method:'POST', body: fd, credentials: 'same-origin' });
            const data = await r.json();
            if (!data.ok) throw new Error(data.error || 'Error');
            $editorText.value = '';
            $attachPrev.innerHTML = '';
            state.attachments = [];
            state.draftMsgId = null;
            $draftStatus.classList.add('d-none');
            await abrirConversacion(state.currentId);
        } catch (e) {
            alert(e.message);
        } finally {
            $sendBtn.disabled = false;
        }
    }

    // ============== Adjuntos ==============
    $attachBtn.addEventListener('click', () => $fileInput.click());
    $fileInput.addEventListener('change', subirAdjuntos);
    async function subirAdjuntos() {
        if (!state.currentId) return;
        if (!state.draftMsgId) {
            // Forzar creacion de borrador para tener mensaje_id donde anclar adjuntos
            await guardarBorrador();
            if (!state.draftMsgId) {
                alert('No se pudo crear borrador para adjuntar.');
                return;
            }
        }
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('mensaje_id', state.draftMsgId);
        for (const f of $fileInput.files) fd.append('archivos[]', f);
        try {
            const r = await fetch(`${API_BASE}/adjuntar.php`, { method:'POST', body: fd, credentials: 'same-origin' });
            const data = await r.json();
            if (!data.ok) throw new Error(data.error || 'Error al adjuntar');
            data.adjuntos.forEach(a => {
                state.attachments.push(a);
                const pill = document.createElement('span');
                pill.className = 'adj-pill';
                pill.innerHTML = `<i class="bi bi-paperclip"></i>${escapeHtml(a.nombre)} (${(a.tamano/1024).toFixed(0)}KB)`;
                $attachPrev.appendChild(pill);
            });
        } catch (e) {
            alert(e.message);
        } finally {
            $fileInput.value = '';
        }
    }

    // ============== Acciones de hilo ==============
    document.querySelectorAll('[data-act]').forEach(el => {
        el.addEventListener('click', async () => {
            if (!state.currentId) return;
            const accion = el.dataset.act;
            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('conversacion_id', state.currentId);
            fd.append('accion', accion);
            try {
                const r = await fetch(`${API_BASE}/marcar.php`, { method:'POST', body: fd, credentials: 'same-origin' });
                const data = await r.json();
                if (!data.ok) throw new Error(data.error || 'Error');
                if (accion === 'archivar' || accion === 'desarchivar') {
                    state.currentId = null;
                    $threadEmpty.classList.remove('d-none');
                    $threadCont.classList.add('d-none');
                    $threadCont.classList.remove('d-flex');
                }
                cargarLista();
            } catch (e) { alert(e.message); }
        });
    });

    // ============== Volver a la lista (mobile) ==============
    document.getElementById('coms-back-list').addEventListener('click', () => {
        document.getElementById('coms-thread').setAttribute('data-mobile-state', '');
        document.getElementById('coms-list').setAttribute('data-mobile-state', '');
    });

    // ============== Nueva conversacion ==============
    const modalNueva = new bootstrap.Modal(document.getElementById('coms-modal-nueva'));
    document.getElementById('btn-nueva-conv').addEventListener('click', () => modalNueva.show());
    document.getElementById('coms-new-enviar').addEventListener('click', async () => {
        const titulo    = document.getElementById('coms-new-titulo').value.trim();
        const categoria = document.getElementById('coms-new-categoria').value;
        const contenido = document.getElementById('coms-new-contenido').value.trim();
        const destEl    = document.getElementById('coms-new-destinatario');
        if (!titulo || !contenido) { alert('Complete asunto y mensaje.'); return; }
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('titulo', titulo);
        fd.append('categoria', categoria);
        fd.append('contenido', contenido);
        if (destEl) fd.append('destinatario', destEl.value);
        try {
            const r = await fetch(`${API_BASE}/enviar.php`, { method:'POST', body: fd, credentials: 'same-origin' });
            const data = await r.json();
            if (!data.ok) throw new Error(data.error || 'Error');
            modalNueva.hide();
            document.getElementById('coms-new-titulo').value = '';
            document.getElementById('coms-new-contenido').value = '';
            await cargarLista();
            abrirConversacion(data.conversacion_id);
        } catch (e) { alert(e.message); }
    });

    // ============== Plantillas (solo ministerio) ==============
    const $plantillaBtn = document.getElementById('coms-plantilla-btn');
    if ($plantillaBtn) {
        const modalPlantilla = new bootstrap.Modal(document.getElementById('coms-modal-plantilla'));
        const $plantillaList = document.getElementById('coms-plantilla-list');
        const $plantillaSearch = document.getElementById('coms-plantilla-search');
        let plantillasData = [];

        async function cargarPlantillas() {
            $plantillaList.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-hourglass-split"></i> Cargando...</div>';
            try {
                const r = await fetch(`${API_BASE}/plantillas.php`, { credentials: 'same-origin' });
                const data = await r.json();
                plantillasData = data.ok ? data.plantillas : [];
            } catch (e) { plantillasData = []; }
            renderPlantillas('');
        }

        function renderPlantillas(buscar) {
            const filtradas = buscar
                ? plantillasData.filter(p => p.titulo.toLowerCase().includes(buscar) || p.contenido.toLowerCase().includes(buscar))
                : plantillasData;
            if (!filtradas.length) {
                $plantillaList.innerHTML = '<div class="text-center text-muted py-4">No hay plantillas disponibles.</div>';
                return;
            }
            $plantillaList.innerHTML = filtradas.map(p => {
                const preview = p.contenido.replace(/\\n/g, ' ').substring(0, 90);
                return `<div class="px-3 py-2 border-bottom coms-plantilla-item" role="button" data-idx="${p.id}" style="cursor:pointer;">
                    <div class="fw-semibold small">${esc(p.titulo)}</div>
                    <div class="text-muted" style="font-size:.75rem;">${esc(preview)}…</div>
                    <span class="badge bg-secondary" style="font-size:.65rem;">${esc(p.categoria)}</span>
                </div>`;
            }).join('');
            $plantillaList.querySelectorAll('.coms-plantilla-item').forEach(el => {
                el.addEventListener('click', () => {
                    const p = plantillasData.find(x => String(x.id) === el.dataset.idx);
                    if (p) {
                        const texto = p.contenido.replace(/\\n/g, '\n');
                        $editorText.value = $editorText.value ? $editorText.value + '\n\n' + texto : texto;
                        $editorText.focus();
                        modalPlantilla.hide();
                    }
                });
            });
        }

        function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

        $plantillaBtn.addEventListener('click', () => {
            cargarPlantillas();
            $plantillaSearch.value = '';
            modalPlantilla.show();
        });
        $plantillaSearch.addEventListener('input', () => renderPlantillas($plantillaSearch.value.toLowerCase()));
    }

    // ============== Init ==============
    cargarLista();
})();
</script>
