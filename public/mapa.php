<?php
/**
 * Mapa Interactivo - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../config/config.php';

$page_title = 'Mapa del Parque';

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, nombre, rubro, ubicacion, direccion, telefono, contacto_nombre, latitud, longitud FROM empresas ORDER BY nombre");
    $empresas = $stmt->fetchAll();
    
    // Estadísticas
    $total = count($empresas);
    $rubros_unicos = count(array_unique(array_column($empresas, 'rubro')));
    
    // Asignar coordenadas aproximadas a empresas sin coordenadas
    $base_lat = -28.4696;
    $base_lng = -65.7795;
    $offset = 0;
    
    foreach ($empresas as &$emp) {
        if (empty($emp['latitud']) || empty($emp['longitud'])) {
            // Generar coordenadas en espiral alrededor del centro
            $angle = $offset * 0.5;
            $radius = 0.002 + ($offset * 0.0003);
            $emp['latitud'] = $base_lat + ($radius * cos($angle));
            $emp['longitud'] = $base_lng + ($radius * sin($angle));
            $offset++;
        }
    }
    unset($emp);
    
} catch (Exception $e) {
    $empresas = [];
    $total = 0;
    $rubros_unicos = 0;
}

require_once BASEPATH . '/includes/header.php';
?>

<style>
.map-page { display: flex; flex-wrap: wrap; min-height: calc(100vh - 76px); }
.map-panel-left { width: 320px; background: #fff; box-shadow: 2px 0 10px rgba(0,0,0,0.1); z-index: 10; display: flex; flex-direction: column; max-height: calc(100vh - 76px); }
.map-panel-right { flex: 1; position: relative; }
#mapFull { width: 100%; height: 100%; min-height: 500px; }
.panel-header { background: var(--primary); color: #fff; padding: 20px; }
.panel-header h4 { margin: 0; font-size: 1.1rem; }
.panel-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
.panel-stat { background: rgba(255,255,255,0.15); padding: 10px; border-radius: 8px; text-align: center; }
.panel-stat .value { font-size: 1.5rem; font-weight: 700; }
.panel-stat .label { font-size: 0.75rem; opacity: 0.9; }
.filter-section { padding: 15px; border-bottom: 1px solid #eee; }
.filter-section h6 { font-size: 0.85rem; color: var(--gray-600); margin-bottom: 10px; }
.empresa-list { flex: 1; overflow-y: auto; }
.empresa-list-item { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: 0.2s; }
.empresa-list-item:hover { background: var(--gray-100); }
.empresa-list-item.active { background: #e3f2fd; border-left: 3px solid var(--primary); }
.empresa-list-item .nombre { font-weight: 600; font-size: 0.9rem; color: var(--gray-900); }
.empresa-list-item .rubro { font-size: 0.75rem; color: var(--gray-600); }
.empresa-list-item .ubicacion { font-size: 0.7rem; color: var(--gray-500); margin-top: 3px; }
@media (max-width: 991px) {
    .map-panel-left { width: 100%; max-height: 350px; }
    .map-page { flex-direction: column; }
    #mapFull { min-height: 450px; }
}
</style>

<div class="map-page">
    <div class="map-panel-left">
        <div class="panel-header">
            <h4><i class="bi bi-geo-alt me-2"></i>Parque Industrial de Catamarca</h4>
            <div class="panel-stats">
                <div class="panel-stat">
                    <div class="value"><?= $total ?></div>
                    <div class="label">Empresas</div>
                </div>
                <div class="panel-stat">
                    <div class="value"><?= $rubros_unicos ?></div>
                    <div class="label">Sectores</div>
                </div>
            </div>
        </div>
        
        <div class="filter-section">
            <h6><i class="bi bi-funnel me-1"></i>FILTROS</h6>
            <input type="text" id="searchEmpresa" class="form-control form-control-sm mb-2" placeholder="Buscar empresa...">
            <select id="filterRubro" class="form-select form-select-sm">
                <option value="">Todos los rubros</option>
                <?php 
                $rubros_lista = array_unique(array_filter(array_column($empresas, 'rubro')));
                sort($rubros_lista);
                foreach ($rubros_lista as $rubro): ?>
                <option value="<?= e($rubro) ?>"><?= e($rubro) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-section py-2">
            <h6 class="mb-0"><i class="bi bi-building me-1"></i>EMPRESAS (<span id="countVisible"><?= $total ?></span>)</h6>
        </div>
        
        <div class="empresa-list" id="empresaList">
            <?php foreach ($empresas as $emp): ?>
            <div class="empresa-list-item" 
                 data-id="<?= $emp['id'] ?>" 
                 data-lat="<?= $emp['latitud'] ?>" 
                 data-lng="<?= $emp['longitud'] ?>"
                 data-rubro="<?= e($emp['rubro'] ?? '') ?>"
                 data-nombre="<?= e(strtolower($emp['nombre'])) ?>">
                <div class="nombre"><?= e($emp['nombre']) ?></div>
                <div class="rubro"><?= e($emp['rubro'] ?? 'Sin rubro') ?></div>
                <div class="ubicacion"><i class="bi bi-geo-alt"></i> <?= e($emp['ubicacion'] ?? '-') ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="map-panel-right">
        <div id="mapFull"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const empresas = <?= json_encode($empresas) ?>;
    
    const coloresRubro = {
        'TEXTIL': '#3498db',
        'CONSTRUCCION': '#e74c3c',
        'CONSTRUCCIÓN': '#e74c3c',
        'METALURGICA': '#95a5a6',
        'ALIMENTOS': '#27ae60',
        'TRANSPORTE': '#f39c12',
        'RECICLADO': '#2ecc71',
        'HORMIGON': '#7f8c8d',
        'ELECTRODOMESTICOS': '#9b59b6',
        'CALZADOS': '#e67e22',
        'MEDICAMENTOS': '#1abc9c'
    };
    
    const map = L.map('mapFull').setView([-28.4696, -65.7795], 14);
    
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles © Esri'
    }).addTo(map);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        opacity: 0.4
    }).addTo(map);
    
    const markers = {};
    
    empresas.forEach(emp => {
        if (emp.latitud && emp.longitud) {
            const color = coloresRubro[emp.rubro] || '#f39c12';
            
            const icon = L.divIcon({
                className: 'custom-marker',
                html: `<div style="background: ${color}; width: 20px; height: 20px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });
            
            const marker = L.marker([emp.latitud, emp.longitud], { icon: icon })
                .addTo(map)
                .bindPopup(`
                    <div style="min-width: 200px;">
                        <h6 style="margin: 0 0 8px; color: #1a5276;">${emp.nombre}</h6>
                        <p style="margin: 4px 0; font-size: 0.85rem;"><strong>Rubro:</strong> ${emp.rubro || 'N/A'}</p>
                        <p style="margin: 4px 0; font-size: 0.85rem;"><strong>Ubicación:</strong> ${emp.ubicacion || 'N/A'}</p>
                        ${emp.telefono ? `<p style="margin: 4px 0; font-size: 0.85rem;"><strong>Tel:</strong> ${emp.telefono}</p>` : ''}
                        <a href="empresa.php?id=${emp.id}" class="btn btn-sm btn-primary mt-2" style="font-size: 0.75rem;">Ver perfil</a>
                    </div>
                `);
            
            markers[emp.id] = marker;
        }
    });
    
    // Click en lista
    document.querySelectorAll('.empresa-list-item').forEach(item => {
        item.addEventListener('click', function() {
            const lat = parseFloat(this.dataset.lat);
            const lng = parseFloat(this.dataset.lng);
            const id = this.dataset.id;
            
            if (lat && lng) {
                map.setView([lat, lng], 17);
                if (markers[id]) markers[id].openPopup();
            }
            
            document.querySelectorAll('.empresa-list-item').forEach(i => i.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Filtros
    function filtrar() {
        const busqueda = document.getElementById('searchEmpresa').value.toLowerCase();
        const rubro = document.getElementById('filterRubro').value;
        let visible = 0;
        
        document.querySelectorAll('.empresa-list-item').forEach(item => {
            const nombre = item.dataset.nombre;
            const itemRubro = item.dataset.rubro;
            const match = nombre.includes(busqueda) && (!rubro || itemRubro === rubro);
            item.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        
        document.getElementById('countVisible').textContent = visible;
    }
    
    document.getElementById('searchEmpresa').addEventListener('input', filtrar);
    document.getElementById('filterRubro').addEventListener('change', filtrar);
});
</script>

<?php require_once BASEPATH . '/includes/footer.php'; ?>
