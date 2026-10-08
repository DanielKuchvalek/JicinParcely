<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Katastrální mapa – okres Jičín</title>
    <meta name="description" content="Parcely okresu Jičín z dat ČÚZK: číslo, druh pozemku, výměra a výřez z katastrální mapy k tisku.">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cpath d='M4.5 10.5 19 4.5l8.5 13-14 10Z' fill='none' stroke='%2317232a' stroke-width='2.6' stroke-linejoin='round'/%3E%3Ccircle cx='19' cy='4.5' r='3.6' fill='%23d81b60'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@500;600&display=swap">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <aside class="sidebar">
        <header class="brand">
            <svg class="brand__mark" viewBox="0 0 32 32" aria-hidden="true">
                <path d="M4.5 10.5 19 4.5l8.5 13-14 10Z"/>
                <circle cx="19" cy="4.5" r="3"/>
            </svg>
            <div>
                <h1>Katastrální mapa</h1>
                <p>Okres Jičín, data ČÚZK</p>
            </div>
        </header>

        <div class="search" role="search">
            <label class="visually-hidden" for="search-input">Hledat parcelu, adresu nebo obec</label>
            <svg class="search__icon" viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5"/><path d="m13 13 4.5 4.5"/></svg>
            <input id="search-input" type="search" placeholder="Parcela, adresa nebo obec" autocomplete="off" spellcheck="false"
                   role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="search-results">
            <ul id="search-results" class="search__results" role="listbox" aria-label="Výsledky hledání" hidden></ul>
        </div>

        <section id="panel" class="panel" aria-live="polite"></section>

        <footer class="sidebar__footer">
            <p>Data ČÚZK: katastrální mapa, RÚIAN, ortofoto. Údaje jsou informativní, úřední výstup vydává katastrální úřad.</p>
            <p>Autor: Daniel Kuchválek</p>
        </footer>
    </aside>

    <main id="map" aria-label="Mapa parcel"></main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script type="module" src="js/app.js"></script>
</body>
</html>
