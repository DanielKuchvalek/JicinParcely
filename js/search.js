// Vyhledávací pole s našeptávačem: parcely, adresy, ulice a obce.

import { api } from './api.js';
import { escapeHtml as e } from './format.js';

const DEBOUNCE_MS = 220;

export function createSearch({ input, list, onPick }) {
    let items = [];
    let active = -1;
    let controller = null;
    let timer = null;
    let loadingQuery = null;
    let pickWhenLoaded = false;

    function open() {
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function close() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    }

    function showStatus(text) {
        items = [];
        list.innerHTML = `<li class="search__status" role="presentation">${e(text)}</li>`;
        open();
    }

    function render() {
        list.classList.remove('is-stale');
        if (items.length === 0) {
            showStatus('Nic jsme nenašli. Zkuste třeba „Jičín 1171“ nebo „Husova 2, Jičín“.');
            return;
        }
        list.innerHTML = items.map((item, i) => `
            <li id="search-option-${i}" class="search__option${item.kind === 'place' && !item.local ? ' is-remote' : ''}"
                role="option" aria-selected="false" data-index="${i}">
                <span class="search__label">${e(item.label)}</span>
                <span class="search__meta">${e(item.kind === 'parcel' ? item.detail : item.type_label)}</span>
            </li>`).join('');
        open();
    }

    function highlight(index) {
        active = index;
        list.querySelectorAll('[role="option"]').forEach((option, i) => {
            option.setAttribute('aria-selected', String(i === active));
            if (i === active) {
                option.scrollIntoView({ block: 'nearest' });
                input.setAttribute('aria-activedescendant', option.id);
            }
        });
    }

    async function load(query) {
        clearTimeout(timer);
        controller?.abort();
        controller = new AbortController();
        loadingQuery = query;
        showStatus('Hledám…');
        try {
            items = (await api.suggest(query, controller.signal)).items;
            active = -1;
            render();
            if (pickWhenLoaded && items.length) {
                pick(0);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                showStatus(error.message);
            }
        } finally {
            if (loadingQuery === query) {
                loadingQuery = null;
                pickWhenLoaded = false;
            }
        }
    }

    function pick(index) {
        const item = items[index];
        if (!item) {
            return;
        }
        input.value = item.label;
        close();
        onPick(item);
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        // Staré výsledky k novému textu nepatří: zůstanou vidět jen zešedlé a Enter je nevybere.
        items = [];
        pickWhenLoaded = false;
        list.classList.add('is-stale');
        const query = input.value.trim();
        if (query.length < 2) {
            controller?.abort();
            loadingQuery = null;
            close();
            return;
        }
        timer = setTimeout(() => load(query), DEBOUNCE_MS);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' && items.length) {
            highlight(Math.min(active + 1, items.length - 1));
            event.preventDefault();
        } else if (event.key === 'ArrowUp' && items.length) {
            highlight(Math.max(active - 1, 0));
            event.preventDefault();
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (items.length) {
                pick(active >= 0 ? active : 0);
                return;
            }
            // Výsledky ještě nedorazily: vybere se první, jakmile přijdou.
            const query = input.value.trim();
            if (query.length >= 2) {
                pickWhenLoaded = true;
                if (loadingQuery !== query) {
                    load(query);
                }
            }
        } else if (event.key === 'Escape') {
            close();
        }
    });

    input.addEventListener('blur', () => setTimeout(close, 120));
    input.addEventListener('focus', () => items.length && render());

    // mousedown místo click, aby výběr proběhl dřív, než pole ztratí fokus
    list.addEventListener('mousedown', (event) => {
        const option = event.target.closest('[data-index]');
        if (option) {
            event.preventDefault();
            pick(Number(option.dataset.index));
        }
    });

    return {
        /** Vyplní pole a rovnou hledá (příklady v prázdném panelu). */
        run(text) {
            input.value = text;
            input.focus();
            load(text);
        },
    };
}
