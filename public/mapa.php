<?php
/**
 * Mapa Interactivo - Parque Industrial de Catamarca
 */
require_once __DIR__ . '/../config/config.php';

$page_title = 'Mapa del Parque';

try {
    $db = getDB();
    $stmt = $db->query("SELECT id, nombre, rubro, ubicacion, direccion, telefono, contacto_nombre, latitud, longitud, logo, sitio_web, facebook, instagram, linkedin FROM empresas ORDER BY nombre");
    $empresas = $stmt->fetchAll();
    // Resolver URL del logo para cada empresa
    foreach ($empresas as &$emp) {
        $emp['logo_url'] = !empty($emp['logo']) ? uploads_resolve_url($emp['logo'], 'logos') : '';
    }
    unset($emp);
    
    // Estadísticas
    $total = count($empresas);
    $rubros_unicos = count(array_unique(array_column($empresas, 'rubro')));
    
    // Solo ubicaciones reales: no se inventan coordenadas; las empresas sin lat/long no tendrán marcador
    $sin_coords = 0;
    foreach ($empresas as $e) {
        if (empty($e['latitud']) || empty($e['longitud'])) {
            $sin_coords++;
        }
    }
} catch (Exception $e) {
    $empresas = [];
    $total = 0;
    $rubros_unicos = 0;
    $sin_coords = 0;
}

$body_class = 'page-mapa';
require_once BASEPATH . '/includes/header.php';
?>

<style>
/* ── Contenedor marco ── */
.map-wrapper {
    padding: 18px;
    background: #f0f4f8;
    min-height: calc(100vh - 70px);
    box-sizing: border-box;
}
.map-frame {
    display: flex;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(26,82,118,0.18), 0 2px 8px rgba(0,0,0,0.10);
    border: 2px solid rgba(26,82,118,0.13);
    height: calc(100vh - 110px);
    min-height: 520px;
    background: #fff;
}

/* ── Panel izquierdo ── */
.map-panel-left {
    width: 300px;
    min-width: 300px;
    background: #fff;
    display: flex;
    flex-direction: column;
    height: 100%;
    border-right: 1px solid #e5e7eb;
}
.panel-header {
    background: linear-gradient(135deg, #1a5276 0%, #0e3a52 100%);
    color: #fff;
    padding: 16px 16px 14px;
    flex-shrink: 0;
}
.panel-header h4 { margin: 0; font-size: 0.95rem; font-weight: 700; letter-spacing: .01em; }
.panel-header .subtitle { font-size: 0.72rem; opacity: .75; margin-top: 2px; }
.panel-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }
.panel-stat { background: rgba(255,255,255,0.13); padding: 8px 6px; border-radius: 8px; text-align: center; }
.panel-stat .value { font-size: 1.5rem; font-weight: 800; line-height: 1; }
.panel-stat .label { font-size: 0.68rem; opacity: .85; margin-top: 2px; }

/* ── Filtros ── */
.filter-section { padding: 11px 14px; border-bottom: 1px solid #f0f0f0; flex-shrink: 0; }
.filter-section h6 { font-size: 0.72rem; color: #6b7280; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; margin-bottom: 8px; }

/* ── Lista empresas ── */
.empresa-list { flex: 1; overflow-y: auto; }
.empresa-list-item { padding: 9px 14px; border-bottom: 1px solid #f5f5f5; cursor: pointer; transition: background 0.12s; display: flex; align-items: center; gap: 9px; }
.empresa-list-item:hover { background: #f0f7ff; }
.empresa-list-item.active { background: #dbeafe; border-left: 3px solid #1a5276; }
.empresa-list-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; border: 1.5px solid rgba(0,0,0,.12); }
.empresa-list-info { min-width: 0; }
.empresa-list-item .nombre { font-weight: 600; font-size: 0.83rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.empresa-list-item .rubro  { font-size: 0.71rem; color: #64748b; }
.empresa-list-item .ubicacion { font-size: 0.68rem; color: #94a3b8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Leyenda ── */
.legend-section { padding: 10px 14px; border-top: 1px solid #f0f0f0; flex-shrink: 0; background: #fafafa; }
.legend-section h6 { font-size: 0.68rem; color: #6b7280; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; margin-bottom: 7px; }
.legend-items { display: flex; flex-wrap: wrap; gap: 5px; }
.legend-item { display: flex; align-items: center; gap: 4px; font-size: 0.68rem; color: #374151; }
.legend-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }

/* ── Mapa ── */
.map-panel-right { flex: 1; position: relative; height: 100%; }
#mapFull { width: 100%; height: 100%; }

/* ── Responsive ── */
@media (max-width: 991px) {
    .map-wrapper { padding: 10px; }
    .map-frame { flex-direction: column; height: auto; border-radius: 12px; }
    .map-panel-left { width: 100%; min-width: unset; height: auto; max-height: 320px; border-right: none; border-bottom: 1px solid #e5e7eb; }
    #mapFull { min-height: 420px; }
}
</style>

<div class="map-wrapper">
<div class="map-frame">
    <div class="map-panel-left">
        <div class="panel-header">
            <h4><i class="bi bi-geo-alt-fill me-2"></i>Parque Industrial de Catamarca</h4>
            <div class="subtitle">PI El Pantanillo · Catamarca, Argentina</div>
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

        <?php if (!empty($sin_coords)): ?>
        <div class="alert alert-warning border-0 rounded-0 small mb-0 py-2 px-3" role="status" style="font-size:.75rem;">
            <?= (int) $sin_coords ?> empresa(s) sin coordenadas registradas.
        </div>
        <?php endif; ?>

        <div class="filter-section">
            <h6><i class="bi bi-funnel me-1"></i>Filtros</h6>
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
            <h6 class="mb-0"><i class="bi bi-building me-1"></i>Empresas &nbsp;<span class="badge bg-primary rounded-pill" style="font-size:.68rem;" id="countVisible"><?= $total ?></span></h6>
        </div>

        <div class="empresa-list" id="empresaList">
            <?php foreach ($empresas as $emp):
                $tiene_coords = !empty($emp['latitud']) && !empty($emp['longitud']);
                $rubro_key = strtoupper($emp['rubro'] ?? '');
            ?>
            <div class="empresa-list-item <?= $tiene_coords ? '' : 'sin-mapa' ?>"
                 data-id="<?= $emp['id'] ?>"
                 data-lat="<?= $emp['latitud'] ?? '' ?>"
                 data-lng="<?= $emp['longitud'] ?? '' ?>"
                 data-rubro="<?= e($emp['rubro'] ?? '') ?>"
                 data-nombre="<?= e(strtolower($emp['nombre'])) ?>">
                <div class="empresa-list-dot" data-rubro-dot="<?= e($rubro_key) ?>"></div>
                <div class="empresa-list-info">
                    <div class="nombre"><?= e($emp['nombre']) ?></div>
                    <div class="rubro"><?= e($emp['rubro'] ?? 'Sin rubro') ?></div>
                    <div class="ubicacion"><i class="bi bi-geo-alt"></i> <?= e($emp['ubicacion'] ?? '-') ?><?= !$tiene_coords ? ' · <em>sin pin</em>' : '' ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="legend-section" id="legendSection"></div>
    </div>

    <div class="map-panel-right">
        <div id="mapFull"></div>
    </div>
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
    
    // Centro: Parque Industrial El Pantanillo
    const map = L.map('mapFull').setView([-28.5337, -65.8010], 15);
    
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
            
            const logoHtml = emp.logo_url
                ? `<img src="${emp.logo_url}" alt="${emp.nombre}" style="width:52px;height:52px;object-fit:contain;border-radius:8px;border:1px solid #e5e7eb;background:#f9fafb;padding:3px;">`
                : `<div style="width:52px;height:52px;border-radius:8px;border:1px solid #e5e7eb;background:#f0f4f8;display:flex;align-items:center;justify-content:center;"><i class="bi bi-building" style="font-size:1.4rem;color:#94a3b8;"></i></div>`;

            const redesHtml = (() => {
                const links = [];
                if (emp.facebook)  links.push(`<a href="${emp.facebook}" target="_blank" rel="noopener" title="Facebook" style="color:#1877f2;font-size:1.15rem;"><i class="bi bi-facebook"></i></a>`);
                if (emp.instagram) links.push(`<a href="${emp.instagram}" target="_blank" rel="noopener" title="Instagram" style="color:#e1306c;font-size:1.15rem;"><i class="bi bi-instagram"></i></a>`);
                if (emp.linkedin)  links.push(`<a href="${emp.linkedin}" target="_blank" rel="noopener" title="LinkedIn" style="color:#0a66c2;font-size:1.15rem;"><i class="bi bi-linkedin"></i></a>`);
                if (emp.sitio_web) links.push(`<a href="${emp.sitio_web}" target="_blank" rel="noopener" title="Sitio web" style="color:#6b7280;font-size:1.15rem;"><i class="bi bi-globe2"></i></a>`);
                return links.length ? `<div style="display:flex;gap:10px;margin-top:8px;padding-top:8px;border-top:1px solid #f0f0f0;">${links.join('')}</div>` : '';
            })();

            const marker = L.marker([emp.latitud, emp.longitud], { icon: icon })
                .addTo(map)
                .bindPopup(`
                    <div style="min-width:230px;max-width:270px;font-family:inherit;">
                        <div style="display:flex;gap:12px;align-items:center;margin-bottom:10px;">
                            ${logoHtml}
                            <div style="min-width:0;">
                                <div style="font-weight:700;font-size:.95rem;color:#1a5276;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${emp.nombre}</div>
                                <div style="font-size:.75rem;color:#64748b;margin-top:2px;">${emp.rubro || ''}</div>
                            </div>
                        </div>
                        <div style="font-size:.82rem;color:#374151;display:flex;flex-direction:column;gap:4px;">
                            ${emp.ubicacion ? `<div><i class="bi bi-geo-alt" style="color:#6b7280;margin-right:4px;"></i>${emp.ubicacion}</div>` : ''}
                            ${emp.telefono  ? `<div><i class="bi bi-telephone" style="color:#6b7280;margin-right:4px;"></i>${emp.telefono}</div>` : ''}
                        </div>
                        ${redesHtml}
                        <a href="empresa.php?id=${emp.id}" style="display:block;margin-top:10px;padding:6px 0;background:#1a5276;color:#fff;text-align:center;border-radius:6px;font-size:.8rem;font-weight:600;text-decoration:none;">Ver perfil</a>
                    </div>
                `, { maxWidth: 290 });
            
            markers[emp.id] = marker;
        }
    });
    
    // Colorear dots de la lista y construir leyenda
    const rubrosSeen = {};
    document.querySelectorAll('.empresa-list-dot').forEach(dot => {
        const rk = dot.dataset.rubroDot;
        const c = coloresRubro[rk] || '#f39c12';
        dot.style.background = c;
        if (rk && !rubrosSeen[rk]) rubrosSeen[rk] = c;
    });
    const legendEl = document.getElementById('legendSection');
    const rubroKeys = Object.keys(rubrosSeen);
    if (rubroKeys.length) {
        legendEl.innerHTML = '<h6><i class="bi bi-circle-half me-1"></i>Leyenda</h6><div class="legend-items">' +
            rubroKeys.map(r => `<div class="legend-item"><div class="legend-dot" style="background:${rubrosSeen[r]}"></div>${r.charAt(0)+r.slice(1).toLowerCase()}</div>`).join('') +
            '</div>';
    }

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
            if (lat && lng) this.classList.add('active');
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
