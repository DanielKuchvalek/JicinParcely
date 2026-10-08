// Boční panel: úvod, načítání, detail parcely a chybová hlášení.

import { escapeHtml as e, formatArea, landUseColor } from './format.js';

const EXTERNAL_ICON = '<svg class="icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M6.5 3.5h-3v9h9v-3M9.5 3.5h3v3M12.5 3.5 7 9"/></svg>';

const MAP_SOURCES = {
    DKM: 'DKM – digitální katastrální mapa',
    UKM: 'UKM – účelová katastrální mapa',
};

function setBusy(el, busy) {
    el.setAttribute('aria-busy', String(busy));
}

export function renderIntro(el, examples, onExample) {
    setBusy(el, false);
    el.innerHTML = `
        <div class="intro">
            <h2>Najděte parcelu</h2>
            <p>Klikněte do mapy, nebo nahoře zadejte číslo parcely, adresu či obec.</p>
            <p class="intro__try">Zkuste třeba</p>
            <div class="intro__examples">
                ${examples.map((text) => `<button type="button" class="chip" data-example="${e(text)}">${e(text)}</button>`).join('')}
            </div>
        </div>`;
    el.querySelectorAll('[data-example]').forEach((button) => {
        button.addEventListener('click', () => onExample(button.dataset.example));
    });
}

export function renderLoading(el) {
    setBusy(el, true);
    el.innerHTML = `
        <div class="skeleton" role="status">
            <span class="visually-hidden">Načítám parcelu…</span>
            <span></span><span></span><span></span><span></span>
        </div>`;
}

export function renderParcel(el, parcel) {
    setBusy(el, false);
    const area = parcel.cadastral_area;
    const areaText = area.name ? `${area.name} (${area.code})` : String(area.code);
    const facts = [
        ['Výměra', formatArea(parcel.area_m2)],
        ['Druh pozemku', parcel.land_use],
        ['Způsob využití', parcel.usage],
        ['Obec', parcel.municipality],
        ['Typ mapy', MAP_SOURCES[parcel.map_source] ?? parcel.map_source],
        ['ID parcely', parcel.id],
    ].filter(([, value]) => value != null && value !== '');

    el.innerHTML = `
        <article class="parcel" style="--land: ${landUseColor(parcel.land_use_code)}">
            <header class="parcel__head">
                <p class="parcel__kind">${parcel.type === 'stavební' ? 'Stavební parcela' : 'Pozemková parcela'}</p>
                <h2 class="parcel__number">${e(parcel.label)}</h2>
                <p class="parcel__where">k.ú. ${e(areaText)}</p>
                <button type="button" class="link-button" data-copy>Kopírovat označení</button>
            </header>
            ${parcel.flagged_incorrect ? '<p class="notice notice--warning">RÚIAN u této parcely eviduje nesprávný údaj.</p>' : ''}
            <dl class="facts">
                ${facts.map(([label, value]) => `
                    <div class="facts__row">
                        <dt>${label}</dt>
                        <dd>${label === 'Druh pozemku' ? '<span class="swatch" aria-hidden="true"></span>' : ''}${e(value)}</dd>
                    </div>`).join('')}
            </dl>
            <div class="parcel__actions">
                <a class="button button--primary" href="vyrez.php?id=${encodeURIComponent(parcel.id)}" target="_blank">Výřez k tisku (A4)</a>
                <a class="button" href="${e(parcel.links.nahlizeni)}" target="_blank" rel="noopener">Nahlížení do KN ${EXTERNAL_ICON}</a>
                <a class="button" href="${e(parcel.links.mapy_cz)}" target="_blank" rel="noopener">Mapy.cz ${EXTERNAL_ICON}</a>
            </div>
            <p class="parcel__note">Vlastníka a list vlastnictví ukáže Nahlížení do KN.</p>
        </article>`;

    el.querySelector('[data-copy]').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const text = `parc. č. ${parcel.label}, k.ú. ${areaText}`;
        try {
            await navigator.clipboard.writeText(text);
            button.textContent = 'Zkopírováno';
        } catch {
            button.textContent = text;
        }
    });
}

export function renderError(el, error, onRetry) {
    setBusy(el, false);
    const missing = error.status === 404;
    const canRetry = !missing && (error.status === 0 || error.status >= 500);

    el.innerHTML = `
        <div class="notice ${missing ? 'notice--info' : 'notice--error'}" role="${missing ? 'status' : 'alert'}">
            <p>${e(error.message)}</p>
            ${canRetry ? '<button type="button" class="button" data-retry>Zkusit znovu</button>' : ''}
        </div>`;
    el.querySelector('[data-retry]')?.addEventListener('click', onRetry);
}
