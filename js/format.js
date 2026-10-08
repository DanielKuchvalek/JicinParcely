// Formátování údajů pro zobrazení.

const integer = new Intl.NumberFormat('cs-CZ');
const hectares = new Intl.NumberFormat('cs-CZ', { maximumFractionDigits: 2 });

/** Barva druhu pozemku – stejná ve výplni vybrané parcely i ve vzorku v panelu. */
const LAND_USE_COLORS = {
    2: '#c9a227', // orná půda
    3: '#7c9a3a', // chmelnice
    4: '#8e3b5f', // vinice
    5: '#8bbd5a', // zahrada
    6: '#5f9e3c', // ovocný sad
    7: '#6fb86f', // trvalý travní porost
    8: '#6fb86f',
    10: '#2f6b3a', // lesní pozemek
    11: '#3a8fd1', // vodní plocha
    13: '#b5523b', // zastavěná plocha a nádvoří
    14: '#9a9488', // ostatní plocha
};

export function landUseColor(code) {
    return LAND_USE_COLORS[code] ?? '#9a9488';
}

export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[ch]);
}

/** 13 077 m² (1,31 ha) – hektary jen u větších parcel. */
export function formatArea(squareMeters) {
    if (squareMeters == null) {
        return null;
    }
    const text = `${integer.format(squareMeters)} m²`;

    return squareMeters >= 10000 ? `${text} (${hectares.format(squareMeters / 10000)} ha)` : text;
}

/** [minLng, minLat, maxLng, maxLat] → Leaflet LatLngBounds */
export function toBounds(bbox) {
    return L.latLngBounds([bbox[1], bbox[0]], [bbox[3], bbox[2]]);
}
