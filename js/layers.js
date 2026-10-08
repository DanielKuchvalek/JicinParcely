// Podkladové mapy, katastrální vrstvy ČÚZK a panel pro jejich přepínání.

const CUZK = '© <a href="https://cuzk.gov.cz">ČÚZK</a>';
const OSM = '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

/** Od tohoto přiblížení ČÚZK poskytuje dlaždice katastrální mapy. */
export const CADASTRE_MIN_ZOOM = 17;

export const OSM_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

export const BASE_LABELS = { map: 'Mapa', ortho: 'Ortofoto', topo: 'Základní mapa' };

export function createBaseLayers() {
    return {
        map: L.tileLayer(OSM_URL, {
            maxNativeZoom: 19, maxZoom: 21, className: 'base-muted', attribution: OSM,
        }),
        ortho: L.tileLayer('https://ags.cuzk.gov.cz/arcgis1/rest/services/ORTOFOTO_WM/MapServer/tile/{z}/{y}/{x}', {
            maxNativeZoom: 20, maxZoom: 21, attribution: CUZK,
        }),
        topo: L.tileLayer('https://ags.cuzk.gov.cz/arcgis1/rest/services/ZTM_WM/MapServer/tile/{z}/{y}/{x}', {
            maxNativeZoom: 19, maxZoom: 21, attribution: CUZK,
        }),
    };
}

export function createCadastreLayers() {
    return {
        // Dlaždice katastrální mapy (WMTS) – styl „Yellow“ je určený pro zobrazení nad ortofotem.
        parcels: L.tileLayer('https://services.cuzk.gov.cz/wmts/local-km-wmts-google/rest/WMTS/{style}/KN/{z}/{y}/{x}', {
            style: 'default', minZoom: CADASTRE_MIN_ZOOM, maxZoom: 21, attribution: CUZK,
        }),
        // Hranice a názvy katastrálních území pro menší přiblížení, kde parcely ještě nejsou.
        areas: L.tileLayer.wms('https://services.cuzk.gov.cz/wms/wms.asp', {
            layers: 'prehledka_kat_uz', format: 'image/png', transparent: true, tileSize: 512,
            minZoom: 13, maxZoom: CADASTRE_MIN_ZOOM - 1, attribution: CUZK,
        }),
    };
}

/**
 * Panel vpravo nahoře: podklad, zapnutí katastru a jeho průhlednost.
 */
export const LayerPanel = L.Control.extend({
    options: { position: 'topright' },

    initialize(options) {
        L.setOptions(this, options);
    },

    onAdd(map) {
        const { bases, cadastre } = this.options;
        const el = L.DomUtil.create('div', 'layer-panel');
        el.innerHTML = `
            <fieldset class="segmented">
                <legend class="visually-hidden">Podklad mapy</legend>
                ${Object.entries(BASE_LABELS).map(([key, label]) => `
                    <label><input type="radio" name="base" value="${key}"${map.hasLayer(bases[key]) ? ' checked' : ''}><span>${label}</span></label>
                `).join('')}
            </fieldset>
            <label class="layer-panel__row"><input type="checkbox" name="cadastre" checked> Katastrální mapa</label>
            <label class="layer-panel__row layer-panel__opacity">Průhlednost
                <input type="range" name="opacity" min="0" max="80" step="5" value="0">
            </label>`;

        L.DomEvent.disableClickPropagation(el);
        L.DomEvent.disableScrollPropagation(el);

        el.addEventListener('change', (event) => {
            const input = event.target;
            if (input.name === 'base') {
                Object.entries(bases).forEach(([key, layer]) => {
                    if (key === input.value) {
                        layer.addTo(map).bringToBack();
                    } else {
                        map.removeLayer(layer);
                    }
                });
                cadastre.parcels.options.style = input.value === 'ortho' ? 'Yellow' : 'default';
                cadastre.parcels.redraw();
            }
            if (input.name === 'cadastre') {
                Object.values(cadastre).forEach((layer) => (input.checked ? layer.addTo(map) : map.removeLayer(layer)));
                el.querySelector('[name="opacity"]').disabled = !input.checked;
            }
        });

        el.addEventListener('input', (event) => {
            if (event.target.name === 'opacity') {
                const opacity = 1 - Number(event.target.value) / 100;
                Object.values(cadastre).forEach((layer) => layer.setOpacity(opacity));
            }
        });

        return el;
    },
});
