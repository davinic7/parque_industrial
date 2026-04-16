<?php
/**
 * Crear Formulario Dinamico - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$page_title = 'Nuevo Formulario';
$db = getDB();
$error = '';

$tipos = [
    'texto'     => 'Texto corto',
    'textarea'  => 'Párrafo',
    'numero'    => 'Número',
    'fecha'     => 'Fecha',
    'select'    => 'Lista desplegable',
    'radio'     => 'Opción única',
    'checkbox'  => 'Opción múltiple',
    'tabla'     => 'Tabla',
    'archivo'   => 'Archivo / Imagen',
    'direccion' => 'Dirección',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad invalido. Recargue la pagina.';
    } else {
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $estado = $_POST['estado'] ?? 'borrador';
        $estado = in_array($estado, ['borrador', 'publicado', 'archivado'], true) ? $estado : 'borrador';

        if ($titulo === '') {
            $error = 'Debe ingresar un titulo.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO formularios_dinamicos (titulo, descripcion, estado, creado_por) VALUES (?, ?, ?, ?)");
                $stmt->execute([$titulo, $descripcion, $estado, $_SESSION['user_id']]);
                $formulario_id = (int)$db->lastInsertId();

                $tipos_validos = array_keys($tipos);
                $tipos_post = $_POST['pregunta_tipo'] ?? [];
                $labels_post = $_POST['pregunta_label'] ?? [];
                $req_post = $_POST['pregunta_requerido'] ?? [];
                $ayuda_post = $_POST['pregunta_ayuda'] ?? [];
                $opc_post = $_POST['pregunta_opciones'] ?? [];
                $cols_post = $_POST['pregunta_tabla_cols'] ?? [];
                $rows_post = $_POST['pregunta_tabla_rows'] ?? [];
                $min_post = $_POST['pregunta_min'] ?? [];
                $max_post = $_POST['pregunta_max'] ?? [];

                foreach ($tipos_post as $i => $tipo) {
                    $tipo = trim($tipo);
                    $label = trim($labels_post[$i] ?? '');
                    if ($label === '' || !in_array($tipo, $tipos_validos, true)) continue;

                    $requerido = !empty($req_post[$i]) ? 1 : 0;
                    $ayuda = trim($ayuda_post[$i] ?? '');
                    $opciones = null;
                    $min_valor = $tipo === 'numero' && ($min_post[$i] ?? '') !== '' ? (float)$min_post[$i] : null;
                    $max_valor = $tipo === 'numero' && ($max_post[$i] ?? '') !== '' ? (float)$max_post[$i] : null;

                    if (in_array($tipo, ['select', 'radio', 'checkbox'], true)) {
                        $raw = str_replace("\r\n", "\n", $opc_post[$i] ?? '');
                        $items = array_values(array_filter(array_map('trim', explode("\n", $raw))));
                        if (empty($items)) throw new Exception("Debe ingresar opciones para la pregunta \"$label\".");
                        $opciones = json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
                    } elseif ($tipo === 'tabla') {
                        $rawC = str_replace("\r\n", "\n", $cols_post[$i] ?? '');
                        $rawR = str_replace("\r\n", "\n", $rows_post[$i] ?? '');
                        $cols = array_values(array_filter(array_map('trim', explode("\n", $rawC))));
                        $rows = array_values(array_filter(array_map('trim', explode("\n", $rawR))));
                        if (empty($cols) || empty($rows)) throw new Exception("Debe ingresar columnas y filas para la tabla \"$label\".");
                        $opciones = json_encode(['cols' => $cols, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
                    }

                    $stmt = $db->prepare("
                        INSERT INTO formulario_preguntas
                        (formulario_id, tipo, etiqueta, ayuda, requerido, opciones, min_valor, max_valor, orden)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$formulario_id, $tipo, $label, $ayuda, $requerido, $opciones, $min_valor, $max_valor, $i + 1]);
                }

                log_activity('formulario_dinamico_creado', 'formularios_dinamicos', $formulario_id);
                set_flash('success', 'Formulario creado correctamente.');
                redirect('formularios-dinamicos.php');
            } catch (Exception $e) {
                $error = $e->getMessage() ?: 'Error al crear el formulario.';
            }
        }
    }
}

$ministerio_nav = 'formularios_dinamicos';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="h4 mb-0 fw-semibold">Nuevo formulario dinámico</h2>
            <a href="formularios-dinamicos.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Volver</a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Titulo</label>
                            <input type="text" name="titulo" class="form-control" value="<?= e($_POST['titulo'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <?php
                                $estado_actual = $_POST['estado'] ?? 'borrador';
                                foreach (['borrador' => 'Borrador', 'publicado' => 'Publicado', 'archivado' => 'Archivado'] as $key => $label):
                                ?>
                                <option value="<?= $key ?>" <?= $estado_actual === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripcion</label>
                            <textarea name="descripcion" class="form-control" rows="3"><?= e($_POST['descripcion'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Preguntas</h5>
                <button type="button" id="btnAddPregunta" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Agregar pregunta</button>
            </div>

            <div id="preguntasContainer">
                <?php
                $post_tipos = $_POST['pregunta_tipo'] ?? ['texto'];
                $post_labels = $_POST['pregunta_label'] ?? [''];
                $post_req = $_POST['pregunta_requerido'] ?? [];
                $post_ayuda = $_POST['pregunta_ayuda'] ?? [''];
                $post_opc = $_POST['pregunta_opciones'] ?? [''];
                $post_cols = $_POST['pregunta_tabla_cols'] ?? [''];
                $post_rows = $_POST['pregunta_tabla_rows'] ?? [''];
                foreach ($post_tipos as $idx => $tipo_val):
                ?>
                <div class="card mb-3 pregunta-item">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Tipo</label>
                                <select class="form-select pregunta-tipo" data-name="pregunta_tipo">
                                    <?php foreach ($tipos as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= ($tipo_val === $key) ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Pregunta</label>
                                <input type="text" class="form-control" data-name="pregunta_label" value="<?= e($post_labels[$idx] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Requerido</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" data-name="pregunta_requerido" <?= !empty($post_req[$idx]) ? 'checked' : '' ?>>
                                    <label class="form-check-label">Si</label>
                                </div>
                            </div>
                            <div class="col-md-2 text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm btn-remove"><i class="bi bi-trash"></i></button>
                            </div>

                            <div class="col-12 options-container d-none">
                                <label class="form-label">Opciones <small class="text-muted">(una por línea)</small></label>
                                <textarea class="form-control" data-name="pregunta_opciones" rows="4" placeholder="Opción 1&#10;Opción 2&#10;Opción 3"><?= e($post_opc[$idx] ?? '') ?></textarea>
                            </div>

                            <div class="col-12 table-container d-none">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Columnas <small class="text-muted">(una por línea)</small></label>
                                        <textarea class="form-control" data-name="pregunta_tabla_cols" rows="4" placeholder="Columna A&#10;Columna B"><?= e($post_cols[$idx] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Filas <small class="text-muted">(una por línea)</small></label>
                                        <textarea class="form-control" data-name="pregunta_tabla_rows" rows="4" placeholder="Fila 1&#10;Fila 2"><?= e($post_rows[$idx] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 numero-container d-none">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label">Valor mínimo</label>
                                        <input type="number" step="any" class="form-control" data-name="pregunta_min" value="<?= e($_POST['pregunta_min'][$idx] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Valor máximo</label>
                                        <input type="number" step="any" class="form-control" data-name="pregunta_max" value="<?= e($_POST['pregunta_max'][$idx] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Ayuda (opcional)</label>
                                <input type="text" class="form-control" data-name="pregunta_ayuda" value="<?= e($post_ayuda[$idx] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex gap-2 mt-3 flex-wrap">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Guardar formulario</button>
                <button type="button" id="btnPreview" class="btn btn-outline-info"><i class="bi bi-eye me-2"></i>Vista previa</button>
                <a href="formularios-dinamicos.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>

<!-- Modal Vista Previa -->
<div class="modal fade" id="modalPreview" tabindex="-1" aria-labelledby="modalPreviewLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPreviewLabel"><i class="bi bi-eye me-2"></i>Vista previa del formulario</h5>
                <div class="ms-auto d-flex gap-2 align-items-center me-2">
                    <button type="button" id="btnPrintForm" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Imprimir</button>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewBody">
                <!-- generado por JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Zona de impresión (oculta en pantalla) -->
<div id="printArea" class="d-none"></div>

    <template id="preguntaTemplate">
        <div class="card mb-3 pregunta-item">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select class="form-select pregunta-tipo" data-name="pregunta_tipo">
                            <?php foreach ($tipos as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Pregunta</label>
                        <input type="text" class="form-control" data-name="pregunta_label">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Requerido</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" data-name="pregunta_requerido">
                            <label class="form-check-label">Si</label>
                        </div>
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove"><i class="bi bi-trash"></i></button>
                    </div>

                    <div class="col-12 options-container d-none">
                        <label class="form-label">Opciones <small class="text-muted">(una por línea)</small></label>
                        <textarea class="form-control" data-name="pregunta_opciones" rows="4" placeholder="Opción 1&#10;Opción 2&#10;Opción 3"></textarea>
                    </div>

                    <div class="col-12 table-container d-none">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Columnas <small class="text-muted">(una por línea)</small></label>
                                <textarea class="form-control" data-name="pregunta_tabla_cols" rows="4" placeholder="Columna A&#10;Columna B"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Filas <small class="text-muted">(una por línea)</small></label>
                                <textarea class="form-control" data-name="pregunta_tabla_rows" rows="4" placeholder="Fila 1&#10;Fila 2"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 numero-container d-none">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label">Valor mínimo</label>
                                <input type="number" step="any" class="form-control" data-name="pregunta_min">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Valor máximo</label>
                                <input type="number" step="any" class="form-control" data-name="pregunta_max">
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Ayuda (opcional)</label>
                        <input type="text" class="form-control" data-name="pregunta_ayuda">
                    </div>
                </div>
            </div>
        </div>
    </template>

<?php
$extra_scripts = <<<'JS'
<style>
@media print {
    body > *:not(#printArea) { display: none !important; }
    #printArea {
        display: block !important;
        font-family: Arial, sans-serif;
        padding: 20px;
    }
    #printArea .pv-field { margin-bottom: 18px; }
    #printArea .pv-label { font-weight: 600; font-size: 0.95rem; margin-bottom: 4px; }
    #printArea .pv-input { border: 1px solid #999; border-radius: 4px; padding: 6px 10px; min-height: 32px; width: 100%; box-sizing: border-box; }
    #printArea .pv-textarea { min-height: 64px; }
    #printArea table { border-collapse: collapse; width: 100%; }
    #printArea table th, #printArea table td { border: 1px solid #aaa; padding: 6px 10px; }
    #printArea .pv-required { color: #c0392b; }
    #printArea .pv-help { font-size: 0.8rem; color: #666; margin-top: 2px; }
    #printArea .pv-header { margin-bottom: 24px; border-bottom: 2px solid #333; padding-bottom: 10px; }
    #printArea .pv-header h2 { margin: 0 0 6px; font-size: 1.4rem; }
    #printArea .pv-header p { margin: 0; color: #555; font-size: 0.9rem; }
}
</style>
<script>
(function() {
    const container = document.getElementById('preguntasContainer');
    const template = document.getElementById('preguntaTemplate');
    const btnAdd = document.getElementById('btnAddPregunta');
    if (!container || !template || !btnAdd) return;

    function updateNames() {
        container.querySelectorAll('.pregunta-item').forEach((item, index) => {
            item.querySelectorAll('[data-name]').forEach((input) => {
                const base = input.getAttribute('data-name');
                input.name = base + '[' + index + ']';
            });
        });
    }

    function toggleExtraFields(item) {
        const tipo = item.querySelector('.pregunta-tipo').value;
        item.querySelector('.options-container').classList.toggle('d-none', !['select','radio','checkbox'].includes(tipo));
        item.querySelector('.table-container').classList.toggle('d-none', tipo !== 'tabla');
        item.querySelector('.numero-container').classList.toggle('d-none', tipo !== 'numero');
    }

    function bindItem(item) {
        const tipo = item.querySelector('.pregunta-tipo');
        const remove = item.querySelector('.btn-remove');
        tipo.addEventListener('change', () => toggleExtraFields(item));
        remove.addEventListener('click', () => {
            item.remove();
            updateNames();
        });
        toggleExtraFields(item);
    }

    btnAdd.addEventListener('click', () => {
        const clone = template.content.firstElementChild.cloneNode(true);
        container.appendChild(clone);
        bindItem(clone);
        updateNames();
    });

    container.querySelectorAll('.pregunta-item').forEach(bindItem);
    updateNames();

    /* ---- Vista previa ---- */
    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function buildFieldHTML(tipo, label, ayuda, requerido, opciones, cols, rows, min, max) {
        const req = requerido ? ' <span class="text-danger">*</span>' : '';
        const reqAttr = requerido ? ' required' : '';
        let control = '';

        if (tipo === 'texto') {
            control = `<input type="text" class="form-control"${reqAttr} placeholder="${esc(label)}">`;
        } else if (tipo === 'textarea') {
            control = `<textarea class="form-control" rows="3"${reqAttr}></textarea>`;
        } else if (tipo === 'numero') {
            const minA = min !== '' ? ` min="${esc(min)}"` : '';
            const maxA = max !== '' ? ` max="${esc(max)}"` : '';
            control = `<input type="number" class="form-control"${reqAttr}${minA}${maxA}>`;
        } else if (tipo === 'fecha') {
            control = `<input type="date" class="form-control"${reqAttr}>`;
        } else if (tipo === 'select') {
            const items = opciones.split('\n').map(o => o.trim()).filter(Boolean);
            const opts = items.map(o => `<option value="${esc(o)}">${esc(o)}</option>`).join('');
            control = `<select class="form-select"${reqAttr}><option value="">Seleccionar…</option>${opts}</select>`;
        } else if (tipo === 'radio') {
            const items = opciones.split('\n').map(o => o.trim()).filter(Boolean);
            control = items.map((o, i) => `
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="prev_${label.replace(/\s/g,'_')}" id="r${i}_${label.replace(/\s/g,'_')}">
                    <label class="form-check-label" for="r${i}_${label.replace(/\s/g,'_')}">${esc(o)}</label>
                </div>`).join('');
        } else if (tipo === 'checkbox') {
            const items = opciones.split('\n').map(o => o.trim()).filter(Boolean);
            control = items.map((o, i) => `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="c${i}_${label.replace(/\s/g,'_')}">
                    <label class="form-check-label" for="c${i}_${label.replace(/\s/g,'_')}">${esc(o)}</label>
                </div>`).join('');
        } else if (tipo === 'tabla') {
            const colArr = cols.split('\n').map(c => c.trim()).filter(Boolean);
            const rowArr = rows.split('\n').map(r => r.trim()).filter(Boolean);
            if (colArr.length && rowArr.length) {
                const thead = `<tr><th></th>${colArr.map(c=>`<th>${esc(c)}</th>`).join('')}</tr>`;
                const tbody = rowArr.map(r => `<tr><td>${esc(r)}</td>${colArr.map(()=>'<td><input type="text" class="form-control form-control-sm"></td>').join('')}</tr>`).join('');
                control = `<div class="table-responsive"><table class="table table-bordered table-sm"><thead class="table-light">${thead}</thead><tbody>${tbody}</tbody></table></div>`;
            } else {
                control = '<p class="text-muted small">Tabla sin columnas/filas definidas</p>';
            }
        } else if (tipo === 'archivo') {
            control = `<input type="file" class="form-control"${reqAttr}>`;
        } else if (tipo === 'direccion') {
            control = `
                <div class="row g-2">
                    <div class="col-md-6"><input type="text" class="form-control" placeholder="Calle y número"></div>
                    <div class="col-md-3"><input type="text" class="form-control" placeholder="Ciudad"></div>
                    <div class="col-md-3"><input type="text" class="form-control" placeholder="Provincia"></div>
                </div>`;
        }

        const ayudaHTML = ayuda ? `<div class="form-text">${esc(ayuda)}</div>` : '';
        return `
            <div class="mb-4">
                <label class="form-label fw-semibold">${esc(label)}${req}</label>
                ${control}
                ${ayudaHTML}
            </div>`;
    }

    function buildPreview() {
        const titulo = document.querySelector('[name="titulo"]').value.trim() || '(Sin título)';
        const descripcion = document.querySelector('[name="descripcion"]').value.trim();
        const items = container.querySelectorAll('.pregunta-item');

        let fieldsHTML = '';
        if (items.length === 0) {
            fieldsHTML = '<p class="text-muted">No hay preguntas agregadas.</p>';
        } else {
            items.forEach(item => {
                const tipo = item.querySelector('.pregunta-tipo').value;
                const label = item.querySelector('[data-name="pregunta_label"]').value.trim() || '(Sin etiqueta)';
                const requerido = item.querySelector('[data-name="pregunta_requerido"]').checked;
                const ayuda = item.querySelector('[data-name="pregunta_ayuda"]').value.trim();
                const opciones = item.querySelector('[data-name="pregunta_opciones"]')?.value || '';
                const cols = item.querySelector('[data-name="pregunta_tabla_cols"]')?.value || '';
                const rows = item.querySelector('[data-name="pregunta_tabla_rows"]')?.value || '';
                const min = item.querySelector('[data-name="pregunta_min"]')?.value || '';
                const max = item.querySelector('[data-name="pregunta_max"]')?.value || '';
                fieldsHTML += buildFieldHTML(tipo, label, ayuda, requerido, opciones, cols, rows, min, max);
            });
        }

        return `
            <div class="mb-4 pb-3 border-bottom">
                <h4 class="fw-bold">${esc(titulo)}</h4>
                ${descripcion ? `<p class="text-muted mb-0">${esc(descripcion)}</p>` : ''}
            </div>
            ${fieldsHTML}`;
    }

    document.getElementById('btnPreview').addEventListener('click', () => {
        const html = buildPreview();
        document.getElementById('previewBody').innerHTML = html;
        document.getElementById('printArea').innerHTML =
            `<div class="pv-header"><h2>${document.querySelector('[name="titulo"]').value.trim() || '(Sin título)'}</h2>` +
            (document.querySelector('[name="descripcion"]').value.trim() ? `<p>${document.querySelector('[name="descripcion"]').value.trim()}</p>` : '') +
            `</div>` + html;
        new bootstrap.Modal(document.getElementById('modalPreview')).show();
    });

    document.getElementById('btnPrintForm').addEventListener('click', () => {
        window.print();
    });
})();
</script>
JS;
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
