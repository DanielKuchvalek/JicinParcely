Zobrazení parcel okresu Jičín - Daniel Kuchválek

Webová aplikace pro zobrazení parcel na mapě okresu Jičín. Je postavená tak, aby byla rychlá, nesekala se a fungovala v čistém PHP bez nutnosti instalovat složité knihovny.

---

Architektonická rozhodnutí a jak aplikace funguje

1. Rychlé načítání mapy přes obrázkové dlaždice (WMS):
   - Místo stahování obrovského množství čar a bodů pro celý okres najednou se mapa zobrazuje pomocí předem vygenerovaných obrázků přímo ze serveru ČÚZK.
   - Díky tomu se prohlížeč nezaseká ani při zobrazení celého okresu a mapa se hýbe naprosto plynule.

2. Načtení detailu až po kliknutí na parcelu (WFS a WMS):
   - Až když uživatel klikne na konkrétní místo v mapě, aplikace si sáhne pro podrobnosti o dané parcele.
   - Získá se číslo parcely, druh pozemku, výměra, katastrální území a modrý obrys pozemku.
   - Součástí je výpočet, který správně pozná, na jakou parcelu uživatel kliknul, i když jde o dům stojící uprostřed zahrady.

3. Rozdělení kódu na části:
   - src/CuzkService.php – stará se o komunikaci s katastrem a výpočty.
   - api.php – předává data z PHP do JavaScriptu ve formátu JSON.
   - index.php a app.js – zobrazují mapu v prohlížeči (pomocí knihovny Leaflet.js).

4. Odkazy do katastru a na Mapy.cz:
   - Po kliknutí na parcelu se v bočním panelu zobrazí přímé odkazy na Nahlížení do katastru nemovitostí(obecný odkaz) a na Mapy.cz na přesné místo.

---

Co mě při práci překvapilo

1. Rozdělení dat na ČÚZK:
   - Z jednoho zdroje nešlo získat vše najednou. Mezinárodní služba INSPIRE dává přesné obrysy pozemků, ale chybí v ní české názvy druhů pozemků. Ty se proto dohledávají z národní služby WMS.
2. Pořadí souřadnic:
   - Služba ČÚZK WFS vyžaduje souřadnice v pořadí zeměpisná šířka a délka (Lat, Lng), což bylo potřeba v dotazech přesně ohlídat.
3. Nastavení certifikátů (SSL):
   - Pro bezproblémové spuštění na jakémkoliv počítači bez nutnosti složitého nastavování PHP je v požadavcích vypnuta kontrola SSL certifikátů.

---

Co by šlo do budoucna vylepšit

1. Vlastní databáze pozemků:
   - Pravidelné stahování dat katastru do vlastní prostorové databáze (PostgreSQL s PostGIS), aby aplikace nebyla závislá na rychlosti serverů ČÚZK.
2. Vektorové dlaždice:
   - Umožnily by zvýrazňovat parcely už při pouhém najetí myší.
3. Mezipaměť (Cache):
   - Ukládání jednou načtených parcel do paměti, aby se stejná data nestahovala opakovaně.

---

Jak aplikaci spustit

Předpoklady:
- Nainstalované PHP verze 8.1 nebo novější s povolenými doplňky curl a simplexml.

Postup:
1. Otevřete příkazový řádek v kořenovém adresáři projektu.
   Například v mém lokálním prostředí přes XAMPP:
   cd C:\xampp\htdocs\JicinParcely

2. Spusťte vestavěný PHP server příkazem:
   php -S localhost:8000
   (Příkazový řádek nechte otevřený, server běží na pozadí.)

   Pokud systém hlásí chybu, že příkaz 'php' nebyl rozpoznán (není přidaný v systémových proměnných PATH), zadejte přímou cestu k PHP (např. v XAMPPu):
   C:\xampp\php\php.exe -S localhost:8000

3. Otevřete webový prohlížeč a přejděte na adresu:
   http://localhost:8000
