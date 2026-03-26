<?php
/**
 * Tarjeta de presentación de empresa - Reutilizable
 * Incluir desde index, empresas, etc. Definir $emp (array con id, nombre, rubro, ubicacion, logo, visitas, telefono, contacto_nombre, direccion).
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
$url_perfil = $emp_id ? (defined('PUBLIC_URL') ? PUBLIC_URL : '') . '/empresa.php?id=' . (int)$emp_id : '#';
?>
<div class="col-md-6 col-lg-4">
    <div class="empresa-card">
        <div class="card-img">
            <?php if (!empty($logo) && defined('UPLOADS_URL')): ?>
                <img src="<?= UPLOADS_URL ?>/logos/<?= e($logo) ?>" alt="<?= e($nombre) ?>">
            <?php else: ?>
                <i class="bi bi-building placeholder-icon" style="font-size: 4rem; color: #ccc;"></i>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($rubro !== null && $rubro !== ''): ?>
                <span class="card-rubro"><?= e($rubro) ?></span>
            <?php endif; ?>
            <h5 class="card-title"><?= e($nombre) ?></h5>
            <?php if ($ubicacion !== null && $ubicacion !== ''): ?>
                <p class="card-text mb-1"><i class="bi bi-geo-alt text-primary"></i> <?= e($ubicacion) ?></p>
            <?php endif; ?>
            <?php if ($show_contact && !empty($direccion)): ?>
                <p class="card-text mb-1 small"><i class="bi bi-geo"></i> <?= e($direccion) ?></p>
            <?php endif; ?>
            <?php if ($show_contact && !empty($telefono)): ?>
                <p class="card-text mb-1"><i class="bi bi-telephone text-primary"></i> <?= e($telefono) ?></p>
            <?php endif; ?>
            <?php if ($show_contact && !empty($contacto_nombre)): ?>
                <p class="card-text"><i class="bi bi-person text-primary"></i> <?= e($contacto_nombre) ?></p>
            <?php endif; ?>
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
