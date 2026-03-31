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
<div class="col-md-6 col-lg-4">
    <div class="empresa-card h-100 d-flex flex-column">
        <div class="card-img">
            <?php if (!empty($logo) && defined('UPLOADS_URL')): ?>
                <img src="<?= e(uploads_resolve_url($logo, 'logos')) ?>" alt="<?= e($nombre) ?>">
            <?php else: ?>
                <i class="bi bi-building placeholder-icon" style="font-size: 4rem; color: #ccc;" aria-hidden="true"></i>
            <?php endif; ?>
        </div>
        <div class="card-body flex-grow-1">
            <span class="card-rubro<?= $rubro_ok ? '' : ' rubro-faltante' ?>"><?= $rubro_ok ? e($rubro) : 'Sin rubro' ?></span>
            <h5 class="card-title mt-2"><?= e($nombre) ?></h5>
            <ul class="list-unstyled small empresa-card-meta mb-0">
                <li class="mb-1"><i class="bi bi-geo-alt text-primary me-1"></i><?= ($ubicacion !== null && $ubicacion !== '') ? e($ubicacion) : '<span class="text-muted">Sin ubicación</span>' ?></li>
                <?php if ($show_contact): ?>
                <li class="mb-1"><i class="bi bi-signpost text-primary me-1"></i><?= !empty($direccion) ? e($direccion) : '<span class="text-muted">—</span>' ?></li>
                <li class="mb-1"><i class="bi bi-telephone text-primary me-1"></i><?= !empty($telefono) ? e($telefono) : '<span class="text-muted">—</span>' ?></li>
                <li class="mb-1"><i class="bi bi-person text-primary me-1"></i><?= !empty($contacto_nombre) ? e($contacto_nombre) : '<span class="text-muted">—</span>' ?></li>
                <li class="mb-0"><i class="bi bi-envelope text-primary me-1"></i><?= !empty($email_contacto) ? '<a href="mailto:' . e($email_contacto) . '">' . e($email_contacto) . '</a>' : '<span class="text-muted">—</span>' ?></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
            <a href="<?= e($url_perfil) ?>" class="btn btn-sm btn-outline-primary">Ver perfil</a>
            <?php if ($show_visitas && $visitas !== null): ?>
                <small class="text-muted"><i class="bi bi-eye"></i> <?= function_exists('format_number') ? format_number($visitas) : (int)$visitas ?></small>
            <?php endif; ?>
            <?php if ($show_tel_button && !empty($telefono)): ?>
                <a href="tel:<?= e($telefono) ?>" class="btn btn-sm btn-outline-success" title="Llamar"><i class="bi bi-telephone"></i></a>
            <?php endif; ?>
        </div>
    </div>
</div>
