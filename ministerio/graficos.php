<?php
/**
 * Gráficos y Datos - Ministerio
 */
require_once __DIR__ . '/../config/config.php';
if (!$auth->requireRole(['ministerio', 'admin'], PUBLIC_URL . '/login.php')) exit;
$page_title = 'Gráficos y Datos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - Ministerio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= PUBLIC_URL ?>/css/styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header"><span class="text-white fw-bold">Ministerio</span></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="empresas.php"><i class="bi bi-buildings"></i> Empresas</a>
            <a href="graficos.php" class="active"><i class="bi bi-graph-up"></i> Gráficos</a>
            <a href="<?= PUBLIC_URL ?>/logout.php"><i class="bi bi-box-arrow-left"></i> Salir</a>
        </nav>
    </aside>
    
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Gráficos y Análisis</h1>
            <button class="btn btn-success"><i class="bi bi-download me-1"></i>Exportar PDF</button>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Desde</label>
                        <input type="date" class="form-control" value="2025-01-01">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" class="form-control" value="2025-12-31">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ubicación</label>
                        <select class="form-select"><option value="">Todas</option><option>PI El Pantanillo</option></select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card"><div class="card-header bg-white"><h5 class="mb-0">Empresas por Rubro</h5></div>
                <div class="card-body"><canvas id="chartRubros" height="250"></canvas></div></div>
            </div>
            <div class="col-lg-6">
                <div class="card"><div class="card-header bg-white"><h5 class="mb-0">Empleados por Género</h5></div>
                <div class="card-body"><canvas id="chartEmpleados" height="250"></canvas></div></div>
            </div>
            <div class="col-lg-8">
                <div class="card"><div class="card-header bg-white"><h5 class="mb-0">Evolución Mensual</h5></div>
                <div class="card-body"><canvas id="chartEvolucion" height="200"></canvas></div></div>
            </div>
            <div class="col-lg-4">
                <div class="card"><div class="card-header bg-white"><h5 class="mb-0">Mapa de Calor</h5></div>
                <div class="card-body p-0"><div id="heatMap" style="height:300px;"></div></div></div>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        new Chart(document.getElementById('chartRubros'), {
            type: 'doughnut',
            data: { labels: ['Textil','Construcción','Metalúrgica','Alimentos','Otros'], datasets: [{ data: [14,14,5,5,40], backgroundColor: ['#3498db','#e74c3c','#95a5a6','#27ae60','#bdc3c7'] }] },
            options: { plugins: { legend: { position: 'right' } } }
        });
        new Chart(document.getElementById('chartEmpleados'), {
            type: 'bar',
            data: { labels: ['Textil','Construcción','Metalúrgica','Alimentos'], datasets: [{ label: 'Masculino', data: [180,220,85,60], backgroundColor: '#3498db' },{ label: 'Femenino', data: [150,30,15,70], backgroundColor: '#e91e63' }] },
            options: { scales: { x: { stacked: true }, y: { stacked: true } } }
        });
        new Chart(document.getElementById('chartEvolucion'), {
            type: 'line',
            data: { labels: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'], datasets: [{ label: 'Empresas', data: [65,67,68,70,71,72,73,74,75,76,77,78], borderColor: '#1a5276', tension: 0.4 }] }
        });
        const map = L.map('heatMap').setView([-28.4696, -65.7795], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        [[-28.468,-65.781,50],[-28.470,-65.778,80],[-28.471,-65.780,30]].forEach(p => L.circle([p[0],p[1]], {radius:p[2], color:'#e74c3c', fillOpacity:0.5}).addTo(map));
    </script>
</body>
</html>
