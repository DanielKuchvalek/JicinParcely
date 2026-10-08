// Přehledová mapka v rohu: celý okres, obdélník právě zobrazeného výřezu a vybraná parcela.

const SELECT_COLOR = '#d81b60';

export const MiniMap = L.Control.extend({
    options: { position: 'bottomright' },

    onAdd() {
        const el = L.DomUtil.create('div', 'minimap');
        el.innerHTML = `
            <div class="minimap__map" aria-hidden="true"></div>
            <button type="button" class="minimap__toggle" aria-expanded="true">Skrýt přehled</button>`;
        L.DomEvent.disableClickPropagation(el);
        L.DomEvent.disableScrollPropagation(el);
        this._el = el;

        return el;
    },

    /** Spustí mapku po přidání na hlavní mapu; okres se doplní, až přijde z API. */
    start(main, baseUrl) {
        const mini = L.map(this._el.querySelector('.minimap__map'), {
            attributionControl: false, zoomControl: false, dragging: false, scrollWheelZoom: false,
            doubleClickZoom: false, boxZoom: false, keyboard: false, touchZoom: false,
        }).setView(main.getCenter(), 9);
        L.tileLayer(baseUrl, { className: 'base-muted' }).addTo(mini);

        const view = L.rectangle(main.getBounds(), {
            color: SELECT_COLOR, weight: 1.5, fillOpacity: 0.15, interactive: false,
        }).addTo(mini);
        const here = L.circleMarker(main.getCenter(), {
            radius: 4, color: '#fff', weight: 1.5, fillColor: SELECT_COLOR, fillOpacity: 1, interactive: false,
        }).addTo(mini);
        let home = null;

        const sync = () => {
            const bounds = main.getBounds();
            view.setBounds(bounds);
            here.setLatLng(main.getCenter());
            // Malý obdélník by nebyl vidět – místo něj ukáže tečku.
            const size = mini.latLngToContainerPoint(bounds.getNorthEast()).x - mini.latLngToContainerPoint(bounds.getSouthWest()).x;
            here.setStyle({ opacity: size < 10 ? 1 : 0, fillOpacity: size < 10 ? 1 : 0 });
            // Mimo okres mapka jde za pohledem, v okrese ukazuje celý okres.
            if (home && !home.contains(main.getCenter())) {
                mini.setView(main.getCenter(), mini.getZoom(), { animate: false });
            } else if (home && !mini.getBounds().contains(home)) {
                mini.fitBounds(home, { animate: false, padding: [4, 4] });
            }
        };
        main.on('move', sync);
        sync();

        mini.on('click', (event) => main.setView(event.latlng, Math.max(main.getZoom(), 15)));

        const toggle = this._el.querySelector('.minimap__toggle');
        toggle.addEventListener('click', () => {
            const collapsed = this._el.classList.toggle('is-collapsed');
            toggle.setAttribute('aria-expanded', String(!collapsed));
            toggle.textContent = collapsed ? 'Přehled' : 'Skrýt přehled';
            if (!collapsed) {
                mini.invalidateSize();
                sync();
            }
        });

        return {
            setDistrict(boundary) {
                const outline = L.geoJSON(boundary, {
                    style: { color: '#17232a', weight: 1.2, fillColor: '#17232a', fillOpacity: 0.05 },
                    interactive: false,
                }).addTo(mini);
                home = outline.getBounds();
                mini.fitBounds(home, { animate: false, padding: [4, 4] });
                sync();
            },
        };
    },
});
