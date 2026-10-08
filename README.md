# Parcely okresu Jičín

Webová aplikace pro zobrazení parcel na mapě okresu Jičín, napojená na veřejné služby katastru (ČÚZK). Po kliknutí nebo vyhledání ukáže číslo parcely, druh pozemku, výměru a přesný obrys a připraví výřez z katastrální mapy k tisku. Běží v čistém PHP bez frameworku a bez nutnosti instalovat knihovny.

## Technologie
- **PHP 8.1+** (bez frameworku a externích knihoven; doplněk `curl`)
- **JavaScript** (ES moduly bez build kroku) + **Leaflet.js** (mapa)
- Služby ČÚZK:
  - **RÚIAN** (ArcGIS REST) – údaje o parcele, katastrální území, hranice okresu
  - **geokodér RÚIAN** – hledání adres, ulic a obcí
  - **WMTS katastrální mapy** – rychlé dlaždice katastru v mapě
  - **WMS katastrální mapy** – výřez k tisku a hranice k.ú. při oddálení
  - **ortofoto** a **Základní mapa ČR** – podklady
- Nahlížení do katastru nemovitostí, VDP a Mapy.cz (odkazy)

## Jak aplikace funguje

### 1. Katastrální mapa z dlaždic ČÚZK
- Hranice a čísla parcel se načítají jako hotové dlaždice (WMTS) přímo z ČÚZK, takže se mapa hýbe plynule i v celém okrese.
- Podklad jde přepnout na světlou mapu, ortofoto nebo Základní mapu ČR. Nad ortofotem se katastr kreslí žlutě. Průhlednost katastru jde nastavit.
- ČÚZK poskytuje parcely od přiblížení 17. Při oddálení mapa ukáže aspoň hranice a názvy katastrálních území.

### 2. Detail parcely jedním dotazem do RÚIAN
- Po kliknutí se aplikace zeptá RÚIAN, do které parcely bod padne. Jeden dotaz vrátí číslo parcely (včetně „st.“ u stavebních), druh pozemku, způsob využití, úřední výměru, katastrální území a polygon.
- RÚIAN zná díry v polygonech, takže klik do domu uprostřed zahrady správně vrátí stavební parcelu.
- Obrys parcely lícuje s dlaždicemi katastrální mapy s odchylkou pod 10 cm (ověřeno proti INSPIRE a WMS).
- Odkaz do Nahlížení do KN vede přímo na vybranou parcelu.

### 3. Hledání
- Jedno pole pro parcely i adresy: „Jičín 1171“, „Hořice st. 25/2“, „1171/3 Jičín“, „Husova 2, Jičín“, „Hořice“.
- Parcely se hledají přímo v RÚIAN (u čísla bez „st.“ nabídne pozemkovou i stavební parcelu). Adresy, ulice a obce hledá geokodér ČÚZK. Na velikosti písmen ani diakritice nezáleží.
- Výsledky z okresu Jičín jsou první.

### 4. Výřez k tisku (A4)
- Stránka `vyrez.php` vykreslí list A4 s výřezem katastrální mapy v přesném měřítku 1 : 500 až 1 : 5 000, obrysem parcely, severkou, grafickým měřítkem, přehledkou, legendou a údaji o parcele.
- Měřítko a orientace listu se zvolí automaticky podle velikosti a tvaru parcely, jde je změnit. Volitelně s ortofotem.
- Do PDF se výřez uloží přes tisk v prohlížeči. Měřítko sedí při tisku ve skutečné velikosti (100 %).

### 5. Přehledová mapka
- V rohu mapy je celý okres s obdélníkem právě zobrazeného výřezu. Kliknutím do ní se mapa přesune.

## Rozdělení kódu
- `src/Ruian.php`, `src/Geocoder.php` – dotazy na služby ČÚZK
- `src/Http.php` – HTTP požadavky a souborová cache v dočasném adresáři systému
- `src/Parcel.php` – převod parcely z RÚIAN na data pro mapu (označení, číselníky, obálka, vnitřní bod)
- `src/District.php` – okres Jičín: katastrální území, obce, hranice
- `src/Search.php` – hledání parcel podle čísla a řazení výsledků geokodéru
- `src/PrintSheet.php` – geometrie výřezu k tisku (měřítko, orientace, obálka, obrys, grafické měřítko)
- `src/App.php` – propojení služeb
- `api.php` – JSON API pro mapu, `vyrez.php` – výřez k tisku
- `index.php`, `js/*.js`, `style.css` – mapa v prohlížeči (Leaflet.js)

## Testy
- `php tests/run.php` – testy logiky bez sítě (převod dat, hledání, výpočet výřezu)
- `php tests/live.php` – ověří živé služby ČÚZK (potřebuje internet)

## Co mě při práci překvapilo
1. **Rozdělení dat na ČÚZK** – z každé služby šlo získat jen část. Mezinárodní služba INSPIRE dává přesné obrysy pozemků, ale chybí v ní české názvy druhů pozemků. Nakonec vše potřebné vrací jedním dotazem služba RÚIAN.
2. **Pořadí souřadnic** – služba ČÚZK WFS vyžaduje souřadnice v pořadí zeměpisná šířka a délka (Lat, Lng), REST služby a GeoJSON naopak délka a šířka. Bylo potřeba to v dotazech přesně ohlídat.
3. **Nastavení certifikátů (SSL)** – pro bezproblémové spuštění na jakémkoliv počítači bez složitého nastavování PHP je v požadavcích vypnuta kontrola SSL certifikátů.

## Co by šlo do budoucna vylepšit
1. **Vlastní databáze pozemků** – pravidelné stahování dat katastru do vlastní prostorové databáze (PostgreSQL s PostGIS), aby aplikace nebyla závislá na rychlosti serverů ČÚZK.
2. **Vektorové dlaždice** – umožnily by zvýrazňovat parcely už při pouhém najetí myší.
3. **Vlastníci a listy vlastnictví** – veřejné služby je bez přihlášení nedávají; šlo by je doplnit přes placenou službu WSDP.

## Jak aplikaci spustit

**Předpoklady:** PHP 8.1 nebo novější s povoleným doplňkem `curl`.

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
