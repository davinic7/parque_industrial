<?php
/**
 * Parque Industrial El Pantanillo - Ubicación y Sectores
 */
require_once __DIR__ . '/../config/config.php';

$page_title = 'El Parque Industrial';

try {
    $db = getDB();
    $stmt = $db->query("SELECT rubro, COUNT(*) as total, color FROM empresas WHERE rubro IS NOT NULL AND rubro != '' AND estado = 'activa' GROUP BY rubro, color ORDER BY total DESC");
    $sectores = $stmt->fetchAll();
    $total_activas = $db->query("SELECT COUNT(*) FROM empresas WHERE estado = 'activa'")->fetchColumn();
} catch (Exception $e) {
    $sectores = [];
    $total_activas = 0;
}

require_once BASEPATH . '/includes/header.php';
?>

<style>
.parque-header { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; padding: 50px 0; }
.parque-header h1 { font-size: 2rem; margin-bottom: 8px; }
.map-embed-wrapper { border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
.map-embed-wrapper iframe { width: 100%; height: 480px; border: 0; display: block; }
.sector-badge { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); margin-bottom: 10px; }
.sector-dot { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; }
.sector-name { flex: 1; font-size: 0.95rem; font-weight: 500; }
.sector-count { font-size: 0.9rem; color: #666; font-weight: 600; }
.info-card { background: #fff; border-radius: 12px; padding: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); height: 100%; }
.info-card h5 { font-size: 1rem; color: var(--primary); font-weight: 600; border-bottom: 2px solid var(--gray-200); padding-bottom: 10px; margin-bottom: 15px; }
</style>

<div class="parque-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="bi bi-building-fill me-2"></i>Parque Industrial El Pantanillo</h1>
                <p class="mb-0 opacity-75">RN38, San Fernando del Valle de Catamarca</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="d-inline-block text-center me-4">
                    <div style="font-size:2.5rem;font-weight:700;line-height:1"><?= (int)$total_activas ?></div>
                    <div class="small">Empresas activas</div>
                </div>
                <div class="d-inline-block text-center">
                    <div style="font-size:2.5rem;font-weight:700;line-height:1"><?= count($sectores) ?></div>
                    <div class="small">Sectores</div>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="row g-4">

            <!-- Mapa -->
            <div class="col-lg-8">
                <div class="map-embed-wrapper">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d8581.102999803312!2d-65.80320054234065!3d-28.53373098685408!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9424297ed9062de9%3A0xab676b250c7a9379!2sParque%20Industrial%20El%20Pantanillo!5e0!3m2!1ses-419!2sar!4v1774649365290!5m2!1ses-419!2sar"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>

            <!-- Sidebar info -->
            <div class="col-lg-4 d-flex flex-column gap-4">

                <!-- Info del parque -->
                <div class="info-card">
                    <h5><i class="bi bi-geo-alt me-2"></i>Ubicación</h5>
                    <p class="mb-1"><strong>Dirección:</strong> RN 38, El Pantanillo</p>
                    <p class="mb-1"><strong>Localidad:</strong> San Fernando del Valle de Catamarca</p>
                    <p class="mb-0"><strong>Provincia:</strong> Catamarca, Argentina</p>
                </div>

                <!-- Sectores industriales -->
                <div class="info-card">
                    <h5><i class="bi bi-grid me-2"></i>Sectores industriales</h5>
                    <?php if (!empty($sectores)): ?>
                        <?php foreach ($sectores as $s): ?>
                        <div class="sector-badge">
                            <div class="sector-dot" style="background: <?= e($s['color'] ?? '#3498db') ?>;"></div>
                            <span class="sector-name"><?= e($s['rubro']) ?></span>
                            <span class="sector-count"><?= (int)$s['total'] ?> emp.</span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">Sin datos disponibles.</p>
                    <?php endif; ?>
                    <div class="mt-3">
                        <a href="<?= PUBLIC_URL ?>/empresas.php" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-building me-1"></i>Ver todas las empresas
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<?php require_once BASEPATH . '/includes/footer.php'; ?>
