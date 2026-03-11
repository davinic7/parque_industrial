<?php
/**
 * Perfil Público de Empresa - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../config/config.php';

$empresa_id = intval($_GET['id'] ?? 0);

if (!$empresa_id) {
    set_flash('error', 'Empresa no encontrada');
    redirect(PUBLIC_URL . '/empresas.php');
}

try {
    $db = getDB();
    
    // Obtener datos de la empresa
    $stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();
    
    if (!$empresa) {
        set_flash('error', 'Empresa no encontrada');
        redirect(PUBLIC_URL . '/empresas.php');
    }
    
    // Incrementar visitas
    $db->prepare("UPDATE empresas SET visitas = visitas + 1 WHERE id = ?")->execute([$empresa_id]);
    
    // Registrar visita para mapa de calor
    $stmt = $db->prepare("INSERT INTO visitas_empresa (empresa_id, ip, user_agent) VALUES (?, ?, ?)");
    $stmt->execute([$empresa_id, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
    
    // Obtener datos adicionales si existen
    $stmt = $db->prepare("SELECT * FROM datos_empresa WHERE empresa_id = ? ORDER BY periodo DESC LIMIT 1");
    $stmt->execute([$empresa_id]);
    $datos = $stmt->fetch();
    
    // Obtener publicaciones de la empresa
    $stmt = $db->prepare("SELECT * FROM publicaciones WHERE empresa_id = ? AND estado = 'aprobado' ORDER BY created_at DESC LIMIT 3");
    $stmt->execute([$empresa_id]);
    $publicaciones = $stmt->fetchAll();
    
} catch (Exception $e) {
    set_flash('error', 'Error al cargar la empresa');
    redirect(PUBLIC_URL . '/empresas.php');
}

$page_title = $empresa['nombre'];

require_once BASEPATH . '/includes/header.php';
?>

<style>
.empresa-header { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; padding: 40px 0; }
.empresa-logo { width: 120px; height: 120px; background: #fff; border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 5px 20px rgba(0,0,0,0.2); }
.empresa-logo img { max-width: 100px; max-height: 100px; }
.info-card { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); height: 100%; }
.info-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid #eee; }
.info-item:last-child { border-bottom: none; }
.info-item i { color: var(--primary); font-size: 1.2rem; width: 24px; }
.info-item .label { font-size: 0.85rem; color: #666; }
.info-item .value { font-weight: 500; }
.stat-mini { text-align: center; padding: 15px; background: var(--gray-100); border-radius: 8px; }
.stat-mini .number { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
.stat-mini .label { font-size: 0.75rem; color: #666; }
</style>

<!-- Header de Empresa -->
<div class="empresa-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="empresa-logo">
                    <?php if (!empty($empresa['logo'])): ?>
                        <img src="<?= UPLOADS_URL ?>/logos/<?= e($empresa['logo']) ?>" alt="<?= e($empresa['nombre']) ?>">
                    <?php else: ?>
                        <i class="bi bi-building fs-1 text-primary"></i>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col">
                <span class="badge bg-light text-primary mb-2"><?= e($empresa['rubro'] ?? 'Sin rubro') ?></span>
                <h1 class="h2 mb-1"><?= e($empresa['nombre']) ?></h1>
                <?php if (!empty($empresa['razon_social'])): ?>
                <p class="opacity-75 mb-2"><?= e($empresa['razon_social']) ?></p>
                <?php endif; ?>
                <p class="mb-0"><i class="bi bi-geo-alt me-1"></i><?= e($empresa['ubicacion'] ?? 'Sin ubicación') ?></p>
            </div>
            <div class="col-auto">
                <a href="<?= PUBLIC_URL ?>/empresas.php" class="btn btn-outline-light">
                    <i class="bi bi-arrow-left me-1"></i>Volver
                </a>
            </div>
        </div>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="row g-4">
            <!-- Información principal -->
            <div class="col-lg-8">
                <!-- Descripción -->
                <div class="info-card mb-4">
                    <h4 class="mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Sobre la Empresa</h4>
                    <?php if (!empty($empresa['descripcion'])): ?>
                        <p><?= nl2br(e($empresa['descripcion'])) ?></p>
                    <?php else: ?>
                        <p class="text-muted">Esta empresa aún no ha agregado una descripción.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Datos de producción si existen -->
                <?php if ($datos): ?>
                <div class="info-card mb-4">
                    <h4 class="mb-3"><i class="bi bi-graph-up text-primary me-2"></i>Datos de Producción</h4>
                    <div class="row g-3">
                        <?php if ($datos['dotacion_total']): ?>
                        <div class="col-md-3">
                            <div class="stat-mini">
                                <div class="number"><?= number_format($datos['dotacion_total']) ?></div>
                                <div class="label">Empleados</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($datos['capacidad_instalada']): ?>
                        <div class="col-md-3">
                            <div class="stat-mini">
                                <div class="number"><?= e($datos['porcentaje_capacidad_uso'] ?? '-') ?>%</div>
                                <div class="label">Capacidad Uso</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($datos['exporta']): ?>
                        <div class="col-md-3">
                            <div class="stat-mini">
                                <div class="number"><i class="bi bi-check-circle text-success"></i></div>
                                <div class="label">Exporta</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($datos['consumo_energia']): ?>
                        <div class="col-md-3">
                            <div class="stat-mini">
                                <div class="number"><?= number_format($datos['consumo_energia']) ?></div>
                                <div class="label">kWh/mes</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Ubicación en mapa -->
                <div class="info-card">
                    <h4 class="mb-3"><i class="bi bi-geo-alt text-primary me-2"></i>Ubicación</h4>
                    <div id="empresaMap" style="height: 300px; border-radius: 8px;"></div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Contacto -->
                <div class="info-card mb-4">
                    <h4 class="mb-3"><i class="bi bi-telephone text-primary me-2"></i>Contacto</h4>
                    
                    <?php if (!empty($empresa['contacto_nombre'])): ?>
                    <div class="info-item">
                        <i class="bi bi-person"></i>
                        <div>
                            <div class="label">Contacto</div>
                            <div class="value"><?= e($empresa['contacto_nombre']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($empresa['telefono'])): ?>
                    <div class="info-item">
                        <i class="bi bi-telephone"></i>
                        <div>
                            <div class="label">Teléfono</div>
                            <div class="value">
                                <a href="tel:<?= $empresa['telefono'] ?>"><?= e($empresa['telefono']) ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($empresa['email_contacto'])): ?>
                    <div class="info-item">
                        <i class="bi bi-envelope"></i>
                        <div>
                            <div class="label">Email</div>
                            <div class="value">
                                <a href="mailto:<?= e($empresa['email_contacto']) ?>"><?= e($empresa['email_contacto']) ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($empresa['direccion'])): ?>
                    <div class="info-item">
                        <i class="bi bi-geo"></i>
                        <div>
                            <div class="label">Dirección</div>
                            <div class="value"><?= e($empresa['direccion']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($empresa['sitio_web'])): ?>
                    <div class="info-item">
                        <i class="bi bi-globe"></i>
                        <div>
                            <div class="label">Sitio Web</div>
                            <div class="value">
                                <a href="<?= e($empresa['sitio_web']) ?>" target="_blank"><?= e($empresa['sitio_web']) ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Datos adicionales -->
                <div class="info-card mb-4">
                    <h4 class="mb-3"><i class="bi bi-card-list text-primary me-2"></i>Información</h4>
                    
                    <?php if (!empty($empresa['cuit'])): ?>
                    <div class="info-item">
                        <i class="bi bi-upc"></i>
                        <div>
                            <div class="label">CUIT</div>
                            <div class="value"><?= e($empresa['cuit']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <i class="bi bi-building"></i>
                        <div>
                            <div class="label">Rubro</div>
                            <div class="value"><?= e($empresa['rubro'] ?? 'No especificado') ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <i class="bi bi-eye"></i>
                        <div>
                            <div class="label">Visitas al perfil</div>
                            <div class="value"><?= number_format($empresa['visitas'] ?? 0) ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Redes sociales -->
                <?php if (!empty($empresa['facebook']) || !empty($empresa['instagram']) || !empty($empresa['linkedin'])): ?>
                <div class="info-card">
                    <h4 class="mb-3"><i class="bi bi-share text-primary me-2"></i>Redes Sociales</h4>
                    <div class="d-flex gap-3">
                        <?php if (!empty($empresa['facebook'])): ?>
                        <a href="<?= e($empresa['facebook']) ?>" target="_blank" class="btn btn-outline-primary">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($empresa['instagram'])): ?>
                        <a href="<?= e($empresa['instagram']) ?>" target="_blank" class="btn btn-outline-danger">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($empresa['linkedin'])): ?>
                        <a href="<?= e($empresa['linkedin']) ?>" target="_blank" class="btn btn-outline-primary">
                            <i class="bi bi-linkedin"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Coordenadas de la empresa o del parque por defecto
    const lat = <?= $empresa['latitud'] ?? -28.4696 ?>;
    const lng = <?= $empresa['longitud'] ?? -65.7795 ?>;
    
    const map = L.map('empresaMap').setView([lat, lng], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);
    
    L.marker([lat, lng])
        .addTo(map)
        .bindPopup('<strong><?= e($empresa['nombre']) ?></strong><br><?= e($empresa['ubicacion'] ?? '') ?>')
        .openPopup();
});
</script>

<?php require_once BASEPATH . '/includes/footer.php'; ?>
