<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katastrální mapa Jičín</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="style.css" />
</head>
<body>
    <aside id="sidebar">
        <header>
            <div class="brand-row">
                <span class="logo-dot"></span>
                <h1>Katastrální mapa parcel</h1>
            </div>
            <p class="subtitle">Okres Jičín (Sobotka, Hořice, Ostroměř, Jičín)</p>
        </header>
        
        <div id="parcel-info">
            <div class="empty-state">
                <p>Klikněte na libovolnou parcelu v mapě pro zobrazení detailních informací z KN.</p>
            </div>
        </div>

        <footer style="margin-top: auto; padding-top: 16px; border-top: 1px solid var(--viagem-border); font-size: 0.8rem; color: var(--viagem-muted);">
            Autor řešení: <strong>Daniel Kuchválek</strong>
        </footer>
    </aside>

    <main id="map"></main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="app.js"></script>
</body>
</html>
