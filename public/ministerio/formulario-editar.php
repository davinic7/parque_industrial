<?php
/**
 * Editar Formulario Dinámico - Ministerio
 */
require_once __DIR__ . '/../../config/config.php';

if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;

$db = getDB();
$form_id = (int)($_GET['id'] ?? 0);

if ($form_id <= 0) {
    set_flash('error', 'Formulario no especificado.');
    redirect('formularios-dinamicos.php');
}

$stmt = $db->prepare("SELECT * FROM formularios_dinamicos WHERE id = ?");
$stmt->execute([$form_id]);
$formulario = $stmt->fetch();

if (!$formulario) {
    set_flash('error', 'Formulario no encontrado.');
    redirect('formularios-dinamicos.php');
}

$page_title = 'Editar: ' . $formulario['titulo'];
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
        $error = 'Token de seguridad inválido. Recargue la página.';
    } else {
        $titulo     = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $estado     = $_POST['estado'] ?? $formulario['estado'];
        $estado     = in_array($estado, ['borrador', 'publicado', 'archivado'], true) ? $estado : $formulario['estado'];

        if ($titulo === '') {
            $error = 'Debe ingresar un título.';
        } else {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("UPDATE formularios_dinamicos SET titulo = ?, descripcion = ?, estado = ? WHERE id = ?");
                $stmt->execute([$titulo, $descripcion, $estado, $form_id]);

                // Reemplazar preguntas: eliminar las existentes e insertar las nuevas
                $db->prepare("DELETE FROM formulario_preguntas WHERE formulario_id = ?")->execute([$form_id]);

                $tipos_validos  = array_keys($tipos);
                $tipos_post     = $_POST['pregunta_tipo'] ?? [];
                $labels_post    = $_POST['pregunta_label'] ?? [];
                $req_post       = $_POST['pregunta_requerido'] ?? [];
                $ayuda_post     = $_POST['pregunta_ayuda'] ?? [];
                $opc_post       = $_POST['pregunta_opciones'] ?? [];
                $cols_post      = $_POST['pregunta_tabla_cols'] ?? [];
                $rows_post      = $_POST['pregunta_tabla_rows'] ?? [];
                $min_post       = $_POST['pregunta_min'] ?? [];
                $max_post       = $_POST['pregunta_max'] ?? [];

                foreach ($tipos_post as $i => $tipo) {
                    $tipo  = trim($tipo);
                    $label = trim($labels_post[$i] ?? '');
                    if ($label === '' || !in_array($tipo, $tipos_validos, true)) continue;

                    $requerido = !empty($req_post[$i]) ? 1 : 0;
                    $ayuda     = trim($ayuda_post[$i] ?? '');
                    $opciones  = null;
                    $min_valor = $tipo === 'numero' && $min_post[$i] !== '' ? (float)$min_post[$i] : null;
                    $max_valor = $tipo === 'numero' && $max_post[$i] !== '' ? (float)$max_post[$i] : null;

                    if (in_array($tipo, ['select', 'radio', 'checkbox'], true)) {
                        $items = array_values(array_filter(array_map('trim', explode(',', $opc_post[$i] ?? ''))));
                        if (empty($items)) throw new Exception("Debe ingresar opciones para la pregunta \"$label\".");
                        $opciones = json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
                    } elseif ($tipo === 'tabla') {
                        $cols = array_values(array_filter(array_map('trim', explode(',', $cols_post[$i] ?? ''))));
                        $rows = array_values(array_filter(array_map('trim', explode(',', $rows_post[$i] ?? ''))));
                        if (empty($cols) || empty($rows)) throw new Exception("Debe ingresar columnas y filas para la tabla \"$label\".");
                        $opciones = json_encode(['cols' => $cols, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
                    }

                    $stmt = $db->prepare("
                        INSERT INTO formulario_preguntas
                            (formulario_id, tipo, etiqueta, ayuda, requerido, opciones, min_valor, max_valor, orden)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$form_id, $tipo, $label, $ayuda, $requerido, $opciones, $min_valor, $max_valor, $i + 1]);
                }

                $db->commit();
                log_activity('formulario_dinamico_editado', 'formularios_dinamicos', $form_id);
                set_flash('success', 'Formulario actualizado correctamente.');
                redirect('formularios-dinamicos.php');
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = $e->getMessage() ?: 'Error al actualizar el formulario.';
            }
        }
    }
}

// Cargar preguntas actuales
$stmt = $db->prepare("SELECT * FROM formulario_preguntas WHERE formulario_id = ? ORDER BY orden, id");
$stmt->execute([$form_id]);
$preguntas_actuales = $stmt->fetchAll();

$ministerio_nav = 'formularios_dinamicos';
require_once BASEPATH . '/includes/ministerio_layout_header.php';
?>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="h4 mb-0 fw-semibold">Editar formulario</h2>
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
                            <label class="form-label">Título *</label>
                            <input type="text" name="titulo" class="form-control" required value="<?= e($formulario['titulo']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <?php foreach (['borrador' => 'Borrador', 'publicado' => 'Publicado', 'archivado' => 'Archivado'] as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $formulario['estado'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="3"><?= e($formulario['descripcion'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Preguntas</h5>
                <button type="button" id="btnAddPregunta" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Agregar pregunta</button>
            </div>

            <div id="preguntasContainer">
                <?php foreach ($preguntas_actuales as $idx => $p):
                    $p_opc = $p['opciones'] ? json_decode($p['opciones'], true) : [];
                    $opc_str = '';
                    $cols_str = '';
                    $rows_str = '';
                    if (in_array($p['tipo'], ['select','radio','checkbox'])) {
                        $opc_str = implode(', ', $p_opc['items'] ?? []);
                    } elseif ($p['tipo'] === 'tabla') {
                        $cols_str = implode(', ', $p_opc['cols'] ?? []);
                        $rows_str = implode(', ', $p_opc['rows'] ?? []);
                    }
                ?>
                <div class="card mb-3 pregunta-item">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Tipo</label>
                                <select class="form-select pregunta-tipo" data-name="pregunta_tipo">
                                    <?php foreach ($tipos as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $p['tipo'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Pregunta</label>
                                <input type="text" class="form-control" data-name="pregunta_label" value="<?= e($p['etiqueta']) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Requerido</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" data-name="pregunta_requerido" <?= $p['requerido'] ? 'checked' : '' ?>>
                                    <label class="form-check-label">Sí</label>
                                </div>
                            </div>
                            <div class="col-md-2 text-end">
                                <button type="button" class="btn btn-outline-danger btn-sm btn-remove"><i class="bi bi-trash"></i></button>
                            </div>
                            <div class="col-12 options-container <?= in_array($p['tipo'],['select','radio','checkbox']) ? '' : 'd-none' ?>">
                                <label class="form-label">Opciones (separadas por coma)</label>
                                <input type="text" class="form-control" data-name="pregunta_opciones" value="<?= e($opc_str) ?>">
                            </div>
                            <div class="col-12 table-container <?= $p['tipo'] === 'tabla' ? '' : 'd-none' ?>">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Columnas (separadas por coma)</label>
                                        <input type="text" class="form-control" data-name="pregunta_tabla_cols" value="<?= e($cols_str) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Filas (separadas por coma)</label>
                                        <input type="text" class="form-control" data-name="pregunta_tabla_rows" value="<?= e($rows_str) ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 numero-container <?= $p['tipo'] === 'numero' ? '' : 'd-none' ?>">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label">Valor mínimo</label>
                                        <input type="number" step="any" class="form-control" data-name="pregunta_min" value="<?= $p['min_valor'] ?? '' ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Valor máximo</label>
                                        <input type="number" step="any" class="form-control" data-name="pregunta_max" value="<?= $p['max_valor'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Ayuda (opcional)</label>
                                <input type="text" class="form-control" data-name="pregunta_ayuda" value="<?= e($p['ayuda'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Guardar cambios</button>
                <a href="formularios-dinamicos.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>

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
                            <label class="form-check-label">Sí</label>
                        </div>
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove"><i class="bi bi-trash"></i></button>
                    </div>
                    <div class="col-12 options-container d-none">
                        <label class="form-label">Opciones (separadas por coma)</label>
                        <input type="text" class="form-control" data-name="pregunta_opciones">
                    </div>
                    <div class="col-12 table-container d-none">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Columnas (separadas por coma)</label>
                                <input type="text" class="form-control" data-name="pregunta_tabla_cols">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Filas (separadas por coma)</label>
                                <input type="text" class="form-control" data-name="pregunta_tabla_rows">
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
<script>
(function() {
    const container = document.getElementById('preguntasContainer');
    const template = document.getElementById('preguntaTemplate');
    const btnAdd = document.getElementById('btnAddPregunta');
    if (!container || !template || !btnAdd) return;

    function updateNames() {
        container.querySelectorAll('.pregunta-item').forEach((item, index) => {
            item.querySelectorAll('[data-name]').forEach((input) => {
                input.name = input.getAttribute('data-name') + '[' + index + ']';
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
        item.querySelector('.pregunta-tipo').addEventListener('change', () => toggleExtraFields(item));
        item.querySelector('.btn-remove').addEventListener('click', () => { item.remove(); updateNames(); });
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
})();
</script>
JS;
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
