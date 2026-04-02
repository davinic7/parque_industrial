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
                                <label class="form-label">Opciones (separadas por coma)</label>
                                <input type="text" class="form-control" data-name="pregunta_opciones" value="<?= e($post_opc[$idx] ?? '') ?>">
                            </div>

                            <div class="col-12 table-container d-none">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Columnas (separadas por coma)</label>
                                        <input type="text" class="form-control" data-name="pregunta_tabla_cols" value="<?= e($post_cols[$idx] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Filas (separadas por coma)</label>
                                        <input type="text" class="form-control" data-name="pregunta_tabla_rows" value="<?= e($post_rows[$idx] ?? '') ?>">
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

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Guardar formulario</button>
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
                            <label class="form-check-label">Si</label>
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
})();
</script>
JS;
require_once BASEPATH . '/includes/ministerio_layout_footer.php';
