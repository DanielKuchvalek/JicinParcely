const map = L.map('map', {
    center: [50.4372, 15.3516],
    zoom: 16,
    minZoom: 9,
    maxZoom: 19
});

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    maxNativeZoom: 19,
    attribution: '© OpenStreetMap přispěvatelé'
}).addTo(map);

const cuzkWms = L.tileLayer.wms('https://services.cuzk.cz/wms/wms.asp', {
    layers: 'PARCELY,OBVODY_PARCEL,OBVODY_BUDOV,DEFINICNI_BODY_PARCEL,KN_DEF_BUDOVY',
    format: 'image/png',
    transparent: true,
    opacity: 0.8,
    maxZoom: 19,
    attribution: '© ČÚZK'
}).addTo(map);

let currentMarker = null;
let currentPolygon = null;

map.on('click', async (e) => {
    const { lat, lng } = e.latlng;

    if (currentMarker) {
        map.removeLayer(currentMarker);
        currentMarker = null;
    }
    if (currentPolygon) {
        map.removeLayer(currentPolygon);
        currentPolygon = null;
    }

    currentMarker = L.marker([lat, lng]).addTo(map);

    const infoDiv = document.getElementById('parcel-info');
    infoDiv.innerHTML = '<div class="loader">Načítám detail parcely z ČÚZK...</div>';

    try {
        const response = await fetch(`api.php?lat=${lat}&lng=${lng}`);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.json();

        if (data.geometry && Array.isArray(data.geometry) && data.geometry.length > 0) {
            currentPolygon = L.polygon(data.geometry, {
                color: '#0052cc',
                weight: 3,
                fillColor: '#0052cc',
                fillOpacity: 0.25
            }).addTo(map);
        }

        const landUseHtml = (data.land_use && data.land_use !== 'Dle KN') 
            ? `<div class="info-row">
                    <span>Druh pozemku:</span>
                    <strong>${data.land_use}</strong>
               </div>` 
            : '';

        infoDiv.innerHTML = `
            <div class="card">
                <span class="badge">${data.parcel_type || 'Katastr nemovitostí'}</span>
                <h2>${data.id}</h2>
                
                <div class="info-group">
                    ${landUseHtml}
                    <div class="info-row">
                        <span>Výměra:</span>
                        <strong>${data.area_m2} m²</strong>
                    </div>
                    <div class="info-row">
                        <span>Katastrální území:</span>
                        <strong>${data.cadastral_district}</strong>
                    </div>
                    <div class="info-row">
                        <span>ČÚZK Identifikátor:</span>
                        <code>${data.cuzk_id || 'N/A'}</code>
                    </div>
                    <div class="info-row">
                        <span>GPS:</span>
                        <code>${lat.toFixed(5)}, ${lng.toFixed(5)}</code>
                    </div>
                </div>

                <div class="actions-group">
                    <a href="${data.mapy_cz_url}" target="_blank" rel="noopener noreferrer" class="btn">
                        Zobrazit na Mapy.cz ↗
                    </a>
                    <a href="${data.cuzk_url}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                        Nahlížení do KN (ČÚZK) ↗
                    </a>
                </div>
            </div>
        `;
    } catch (err) {
        infoDiv.innerHTML = `
            <div class="card error-box">
                <p class="error-msg">Nepodařilo se načíst data z ČÚZK.</p>
                <p class="subtitle" style="margin-top: 8px;">Zkuste kliknout přímo do vnitřku hranic parcely.</p>
            </div>
        `;
    }
});