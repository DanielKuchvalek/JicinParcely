// Mapa parcel okresu Jičín: klik nebo hledání → parcela z RÚIAN → detail, obrys a výřez k tisku.

import { api } from './api.js';
import { landUseColor, toBounds } from './format.js';
import { CADASTRE_MIN_ZOOM, LayerPanel, OSM_URL, createBaseLayers, createCadastreLayers } from './layers.js';
import { MiniMap } from './minimap.js';
import * as panel from './panel.js';
import { createSearch } from './search.js';

const SELECT_COLOR = '#d81b60';
const EXAMPLES = ['Jičín 1171', 'Husova 2, Jičín', 'Hořice st. 25'];
/** Pod tímto přiblížením klik mapu jen přiblíží – parcela by se netrefila přesně. */
const MIN_SELECT_ZOOM = 15;
const POINT_TYPES = ['address', 'building', 'parcel'];

const panelEl = document.getElementById('panel');

const map = L.map('map', {
    center: [50.4368, 15.3514],
    zoom: CADASTRE_MIN_ZOOM,
    minZoom: 7,
    maxZoom: 21,
    zoomControl: false,
});
map.attributionControl.setPrefix('<a href="https://leafletjs.com">Leaflet</a>');
L.control.zoom({ position: 'topleft', zoomInTitle: 'Přiblížit', zoomOutTitle: 'Oddálit' }).addTo(map);
L.control.scale({ position: 'bottomleft', imperial: false, maxWidth: 140 }).addTo(map);

const bases = createBaseLayers();
bases.map.addTo(map);
const cadastre = createCadastreLayers();
cadastre.areas.addTo(map);
cadastre.parcels.addTo(map);
new LayerPanel({ bases, cadastre }).addTo(map);

const minimap = new MiniMap().addTo(map).start(map, OSM_URL);

// Výběr parcely

const selection = L.layerGroup().addTo(map);
let pulse = null;
let requestId = 0;

function drawSelection(parcel) {
    selection.clearLayers();
    L.geoJSON(parcel.geometry, {
        interactive: false,
        style: { color: '#ffffff', weight: 7, opacity: 0.85, fill: false },
    }).addTo(selection);
    L.geoJSON(parcel.geometry, {
        interactive: false,
        style: { color: SELECT_COLOR, weight: 3, fillColor: landUseColor(parcel.land_use_code), fillOpacity: 0.28 },
    }).addTo(selection);
}

function showPulse(latlng) {
    pulse?.remove();
    pulse = latlng
        ? L.marker(latlng, { interactive: false, keyboard: false, icon: L.divIcon({ className: 'pulse', iconSize: [18, 18] }) }).addTo(map)
        : null;
}

/**
 * Načte parcelu a zobrazí ji. Odpověď na starší klik se zahodí,
 * aby rychlé klikání neukázalo jinou parcelu, než na kterou se kliklo naposledy.
 */
async function selectParcel(load, { fit = false, point = null } = {}) {
    const id = ++requestId;
    panel.renderLoading(panelEl);
    showPulse(point);

    try {
        const parcel = await load();
        if (id !== requestId) {
            return;
        }
        drawSelection(parcel);
        panel.renderParcel(panelEl, parcel);
        if (fit) {
            map.fitBounds(toBounds(parcel.bbox), { padding: [48, 48], maxZoom: 19 });
        }
    } catch (error) {
        if (id !== requestId) {
            return;
        }
        selection.clearLayers();
        panel.renderError(panelEl, error, () => selectParcel(load, { fit, point }));
    } finally {
        if (id === requestId) {
            showPulse(null);
        }
    }
}

map.on('click', (event) => {
    if (map.getZoom() < MIN_SELECT_ZOOM) {
        map.setView(event.latlng, CADASTRE_MIN_ZOOM);
        return;
    }
    selectParcel(() => api.parcelAt(event.latlng.lat, event.latlng.lng), { point: event.latlng });
});

// Hledání

async function goToPlace(item) {
    if (item.kind === 'parcel') {
        selectParcel(() => api.parcelById(item.id), { fit: true });
        return;
    }

    try {
        const place = await api.resolve(item.key, item.label);
        if (POINT_TYPES.includes(place.type)) {
            selectParcel(() => api.parcelAt(place.lat, place.lng), { fit: true });
        } else if (place.bbox) {
            map.fitBounds(toBounds(place.bbox), { padding: [24, 24], maxZoom: CADASTRE_MIN_ZOOM });
        } else {
            map.setView([place.lat, place.lng], 16);
        }
    } catch (error) {
        panel.renderError(panelEl, error, () => goToPlace(item));
    }
}

const search = createSearch({
    input: document.getElementById('search-input'),
    list: document.getElementById('search-results'),
    onPick: goToPlace,
});

panel.renderIntro(panelEl, EXAMPLES, (text) => search.run(text));

// Nápověda k přiblížení: parcely jsou v mapě až od přiblížení 17.

const zoomHint = L.DomUtil.create('div', 'zoom-hint', map.getContainer());
zoomHint.innerHTML = 'Hranice parcel se ukážou po přiblížení. <button type="button">Přiblížit</button>';
L.DomEvent.disableClickPropagation(zoomHint);
zoomHint.querySelector('button').addEventListener('click', () => map.setZoom(CADASTRE_MIN_ZOOM));
const updateZoomHint = () => zoomHint.classList.toggle('is-visible', map.getZoom() < CADASTRE_MIN_ZOOM);
map.on('zoomend', updateZoomHint);
updateZoomHint();

// Hranice okresu: v přehledové mapce a při oddálení i v hlavní mapě.

api.district()
    .then((district) => {
        if (!district.boundary) {
            return;
        }
        minimap.setDistrict(district.boundary);
        const outline = L.geoJSON(district.boundary, {
            interactive: false,
            style: { color: '#17232a', weight: 2, dashArray: '6 6', fill: false },
        });
        const updateOutline = () => (map.getZoom() <= 13 ? outline.addTo(map) : outline.remove());
        map.on('zoomend', updateOutline);
        updateOutline();
    })
    .catch(() => {
        // Bez hranice okresu mapa funguje dál, přehledka jen ukazuje okolí.
    });
