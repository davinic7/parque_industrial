/**
 * Leaflet: capa satelital (Esri World Imagery) y mapas sin arrastre accidental.
 * Cargar después de leaflet.js
 */
(function (global) {
    'use strict';

    var SAT_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
    var SAT_ATTR = '&copy; <a href="https://www.esri.com/">Esri</a>, Maxar, Earthstar Geographics';

    // Zoom mínimo: muestra el parque completo. Máximo: detalle de parcela.
    var MAP_MIN_ZOOM = 14;
    var MAP_MAX_ZOOM = 19;

    // Bounding box del parque con margen de ~800 m para no cortar el polígono.
    var PARQUE_BOUNDS = L.latLngBounds(
        L.latLng(-28.558, -65.826),   // SW
        L.latLng(-28.514, -65.778)    // NE
    );

    // Restringe zoom y paneo a la zona del parque.
    function constrainMap(map) {
        map.setMinZoom(MAP_MIN_ZOOM);
        map.setMaxZoom(MAP_MAX_ZOOM);
        map.setMaxBounds(PARQUE_BOUNDS);
        map.options.maxBoundsViscosity = 1.0;
    }

    function addSatelliteLayer(map) {
        constrainMap(map);
        return L.tileLayer(SAT_URL, {
            maxZoom: MAP_MAX_ZOOM,
            attribution: SAT_ATTR
        }).addTo(map);
    }

    /** Mapa solo lectura: no arrastrar ni zoom con rueda/dedos (vista previa). */
    function freezeMap(map) {
        if (!map || !map.dragging) return;
        map.dragging.disable();
        map.touchZoom.disable();
        map.doubleClickZoom.disable();
        map.scrollWheelZoom.disable();
        map.boxZoom.disable();
        map.keyboard.disable();
        if (map.tap) map.tap.disable();
    }

    function unfreezeMap(map) {
        if (!map || !map.dragging) return;
        map.dragging.enable();
        map.touchZoom.enable();
        map.doubleClickZoom.enable();
        map.scrollWheelZoom.enable();
        map.boxZoom.enable();
        map.keyboard.enable();
        if (map.tap) map.tap.enable();
    }

    /**
     * Polígono oficial del Parque Industrial El Pantanillo
     * Fuente: OpenStreetMap way/37355935
     * Coordenadas: [lat, lng] (Leaflet)
     */
    var PANTANILLO_POLYGON = [
        [-28.5403037, -65.8143208],
        [-28.5413495, -65.8105384],
        [-28.5442344, -65.8074433],
        [-28.5446768, -65.8032722],
        [-28.5441791, -65.8020229],
        [-28.5461583, -65.8000914],
        [-28.5392846, -65.7939112],
        [-28.5374052, -65.7956748],
        [-28.5355336, -65.7931037],
        [-28.5356673, -65.7928274],
        [-28.5332534, -65.7919537],
        [-28.5282187, -65.7901432],
        [-28.5262409, -65.7874929],
        [-28.5253926, -65.7920703]
    ];

    /**
     * Dibuja el polígono del parque sobre el mapa.
     * @param {L.Map} map
     * @param {object} opts  Opciones opcionales de estilo
     */
    function addParquePolygon(map, opts) {
        var options = Object.assign({
            color:       '#f39c12',   // borde naranja industrial
            weight:      3,
            opacity:     0.9,
            fillColor:   '#f39c12',
            fillOpacity: 0.12,
            dashArray:   null
        }, opts || {});

        return L.polygon(PANTANILLO_POLYGON, options)
            .addTo(map)
            .bindTooltip('Parque Industrial El Pantanillo', {
                permanent: false,
                direction: 'center',
                className: 'leaflet-pi-tooltip'
            });
    }

    global.ParqueLeaflet = {
        addSatelliteLayer:  addSatelliteLayer,
        freezeMap:          freezeMap,
        unfreezeMap:        unfreezeMap,
        addParquePolygon:   addParquePolygon,
        constrainMap:       constrainMap,
        PANTANILLO_POLYGON: PANTANILLO_POLYGON,
        PARQUE_BOUNDS:      PARQUE_BOUNDS,
        MAP_MIN_ZOOM:       MAP_MIN_ZOOM,
        MAP_MAX_ZOOM:       MAP_MAX_ZOOM
    };
})(typeof window !== 'undefined' ? window : this);
