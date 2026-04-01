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

    global.ParqueLeaflet = {
        addSatelliteLayer: addSatelliteLayer,
        freezeMap: freezeMap,
        unfreezeMap: unfreezeMap
    };
})(typeof window !== 'undefined' ? window : this);
