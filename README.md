# Parcely okresu Jičín

Webová aplikace pro zobrazení parcel na mapě okresu Jičín, napojená na veřejné služby katastru (ČÚZK). Je postavená tak, aby byla rychlá, nesekala se a fungovala v čistém PHP bez nutnosti instalovat složité knihovny.

## Technologie
- **PHP 8.1+** (bez frameworku a externích knihoven; `curl`, `simplexml`)
- **JavaScript** + **Leaflet.js** (mapa)
- Služby ČÚZK: **WMS**, **WFS** a **INSPIRE**
- Mapy.cz a Nahlížení do katastru nemovitostí (odkazy)

## Jak aplikace funguje

### 1. Rychlé načítání mapy přes obrázkové dlaždice (WMS)
- Místo stahování obrovského množství čar a bodů pro celý okres najednou se mapa zobrazuje pomocí předem vygenerovaných obrázků přímo ze serveru ČÚZK.
- Díky tomu se prohlížeč nezasekne ani při zobrazení celého okresu a mapa se hýbe plynule.

### 2. Načtení detailu až po kliknutí na parcelu (WFS a WMS)
- Až když uživatel klikne na konkrétní místo v mapě, aplikace si sáhne pro podrobnosti o dané parcele.
- Získá se číslo parcely, druh pozemku, výměra, katastrální území a modrý obrys pozemku.
- Součástí je výpočet, který správně pozná, na jakou parcelu uživatel kliknul, i když jde o dům stojící uprostřed zahrady.

### 3. Rozdělení kódu na části
- `src/CuzkService.php` – komunikace s katastrem a výpočty
- `api.php` – předává data z PHP do JavaScriptu ve formátu JSON
- `index.php` a `app.js` – zobrazení mapy v prohlížeči (Leaflet.js)

### 4. Odkazy do katastru a na Mapy.cz
- Po kliknutí na parcelu se v bočním panelu zobrazí přímé odkazy na Nahlížení do katastru nemovitostí (obecný odkaz) a na Mapy.cz na přesné místo.

## Co mě při práci překvapilo
1. **Rozdělení dat na ČÚZK** – z jednoho zdroje nešlo získat vše najednou. Mezinárodní služba INSPIRE dává přesné obrysy pozemků, ale chybí v ní české názvy druhů pozemků. Ty se proto dohledávají z národní služby WMS.
2. **Pořadí souřadnic** – služba ČÚZK WFS vyžaduje souřadnice v pořadí zeměpisná šířka a délka (Lat, Lng), což bylo potřeba v dotazech přesně ohlídat.
3. **Nastavení certifikátů (SSL)** – pro bezproblémové spuštění na jakémkoliv počítači bez složitého nastavování PHP je v požadavcích vypnuta kontrola SSL certifikátů.

## Co by šlo do budoucna vylepšit
1. **Vlastní databáze pozemků** – pravidelné stahování dat katastru do vlastní prostorové databáze (PostgreSQL s PostGIS), aby aplikace nebyla závislá na rychlosti serverů ČÚZK.
2. **Vektorové dlaždice** – umožnily by zvýrazňovat parcely už při pouhém najetí myší.
3. **Mezipaměť (cache)** – ukládání jednou načtených parcel do paměti, aby se stejná data nestahovala opakovaně.

## Jak aplikaci spustit

**Předpoklady:** PHP 8.1 nebo novější s povolenými doplňky `curl` a `simplexml`.

1. Otevřete příkazový řádek v kořenovém adresáři projektu, např. v XAMPPu:
   ```
   cd C:\xampp\htdocs\JicinParcely
   ```
2. Spusťte vestavěný PHP server:
   ```
   php -S localhost:8000
   ```
   Pokud systém hlásí, že příkaz `php` nebyl rozpoznán (není v proměnné PATH), zadejte přímou cestu k PHP:
   ```
   C:\xampp\php\php.exe -S localhost:8000
   ```
3. Otevřete v prohlížeči adresu http://localhost:8000

---
Autor: Daniel Kuchválek
