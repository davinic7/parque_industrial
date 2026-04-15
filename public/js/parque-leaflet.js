/**
 * Leaflet: capa satelital (Esri World Imagery) y mapas sin arrastre accidental.
 * Cargar después de leaflet.js
 */
(function (global) {
    'use strict';

    var SAT_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
    var SAT_ATTR = '&copy; <a href="https://www.esri.com/">Esri</a>, Maxar, Earthstar Geographics';

    function addSatelliteLayer(map) {
        return L.tileLayer(SAT_URL, {
            maxZoom: 19,
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
        PANTANILLO_POLYGON: PANTANILLO_POLYGON
    };
})(typeof window !== 'undefined' ? window : this);
