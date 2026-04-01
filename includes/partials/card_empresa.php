<?php
/**
 * Tarjeta de presentación de empresa - Reutilizable
 * Incluir desde index, empresas, etc. Definir $emp (id, nombre, rubro, ubicacion, logo, visitas, telefono, contacto_nombre, direccion, email_contacto).
 * Opcional: $card_options = ['show_visitas' => true, 'show_contact' => true, 'show_tel_button' => true]
 */
if (!isset($emp) || !is_array($emp)) return;
$opt = isset($card_options) && is_array($card_options) ? $card_options : [];
$show_visitas = $opt['show_visitas'] ?? true;
$show_contact = $opt['show_contact'] ?? true;
$show_tel_button = $opt['show_tel_button'] ?? true;
$emp_id = $emp['id'] ?? null;
$nombre = $emp['nombre'] ?? 'Empresa';
$rubro = $emp['rubro'] ?? null;
$ubicacion = $emp['ubicacion'] ?? null;
$logo = $emp['logo'] ?? null;
$visitas = $emp['visitas'] ?? null;
$telefono = $emp['telefono'] ?? null;
$contacto_nombre = $emp['contacto_nombre'] ?? null;
$direccion = $emp['direccion'] ?? null;
$email_contacto = $emp['email_contacto'] ?? null;
$url_perfil = $emp_id ? (defined('PUBLIC_URL') ? PUBLIC_URL : '') . '/empresa.php?id=' . (int)$emp_id : '#';
$rubro_ok = ($rubro !== null && $rubro !== '');
?>
<div class="col-12 col-sm-10 col-md-6 col-lg-4 mx-auto mx-md-0">
    <div class="empresa-card h-100 d-flex flex-column">
        <div class="card-img">
            <?php if (!empty($logo) && defined('UPLOADS_URL')): ?>
                <img src="<?= e(uploads_resolve_url($logo, 'logos')) ?>" alt="<?= e($nombre) ?>">
            <?php else: ?>
                <i class="bi bi-building placeholder-icon" aria-hidden="true"></i>
            <?php endif; ?>
        </div>
        <div class="card-body flex-grow-1 d-flex flex-column">
            <span class="card-rubro<?= $rubro_ok ? '' : ' rubro-faltante' ?>"><?= $rubro_ok ? e($rubro) : 'Sin rubro' ?></span>
            <h5 class="card-title mt-2 mb-1"><?= e($nombre) ?></h5>
            <ul class="list-unstyled small empresa-card-meta mb-0 flex-grow-1">
                <li class="mb-1"><i class="bi bi-geo-alt text-primary me-1"></i><?= ($ubicacion !== null && $ubicacion !== '') ? e($ubicacion) : '<span class="text-muted">Sin ubicación</span>' ?></li>
                <?php if ($show_contact): ?>
                <li class="mb-1"><i class="bi bi-signpost text-primary me-1"></i><?= !empty($direccion) ? e($direccion) : '<span class="text-muted">—</span>' ?></li>
                <li class="mb-1"><i class="bi bi-telephone text-primary me-1"></i><?= !empty($telefono) ? e($telefono) : '<span class="text-muted">—</span>' ?></li>
                <li class="mb-1"><i class="bi bi-person text-primary me-1"></i><?= !empty($contacto_nombre) ? e($contacto_nombre) : '<span class="text-muted">—</span>' ?></li>
                <li class="mb-0"><i class="bi bi-envelope text-primary me-1"></i><?= !empty($email_contacto) ? '<a href="mailto:' . e($email_contacto) . '">' . e($email_contacto) . '</a>' : '<span class="text-muted">—</span>' ?></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="card-footer empresa-card-footer border-0">
            <a href="<?= e($url_perfil) ?>" class="btn btn-primary w-100 py-2">
                <i class="bi bi-building me-1"></i>Ver perfil
            </a>
            <?php if (($show_tel_button && !empty($telefono)) || ($show_visitas && $visitas !== null)): ?>
            <div class="d-flex align-items-center justify-content-between gap-2 mt-2 pt-2 empresa-card-footer-meta">
                <?php if ($show_tel_button && !empty($telefono)): ?>
                <a href="tel:<?= e(preg_replace('/\s+/', '', $telefono)) ?>" class="btn btn-outline-secondary btn-sm flex-shrink-0">
                    <i class="bi bi-telephone-fill me-1"></i>Llamar
                </a>
                <?php else: ?>
                <span class="d-none d-sm-block flex-grow-1"></span>
                <?php endif; ?>
                <?php if ($show_visitas && $visitas !== null): ?>
                <span class="empresa-card-visitas text-muted small ms-auto"><i class="bi bi-eye-fill me-1"></i><?= function_exists('format_number') ? format_number($visitas) : (int)$visitas ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
