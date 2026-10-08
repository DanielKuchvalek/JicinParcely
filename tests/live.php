<?php

declare(strict_types=1);

/**
 * Ověří živé služby ČÚZK (potřebuje internet):  php tests/live.php
 * Hodí se, když ČÚZK změní službu nebo formát dat.
 */

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/lib.php';

use JicinParcely\App;
use JicinParcely\Parcel;
use JicinParcely\PrintSheet;

$app = App::create();

test('klik na Valdštejnovo náměstí vrátí parcelu 1171 v k.ú. Jičín', function () use ($app) {
    $p = $app->parcelAt(50.4367, 15.3513);

    assert_same('1171', $p['label']);
    assert_same('Jičín', $p['cadastral_area']['name']);
    assert_same('ostatní plocha', $p['land_use']);
    assert_same(13077, $p['area_m2']);
});

test('klik do budovy vrátí stavební parcelu, ne pozemek kolem ní', function () use ($app) {
    [$lng, $lat] = Parcel::interiorPoint(fixture('parcel-st-1171.json')['features'][0]['geometry']);

    assert_same('st. 1171', $app->parcelAt($lat, $lng)['label']);
});

test('parcela mimo okres dostane název k.ú. z RÚIAN (Praha, Staroměstské náměstí)', function () use ($app) {
    $p = $app->parcelAt(50.08755, 14.42115);

    assert_true($p['cadastral_area']['name'] !== null, 'název k.ú. chybí');
});

test('hledání „Jičín 1171“ nabídne pozemkovou i stavební parcelu', function () use ($app) {
    $items = $app->suggest('Jičín 1171')['items'];

    assert_same(['Jičín 1171', 'Jičín st. 1171'], array_column(array_slice($items, 0, 2), 'label'));
});

test('hledání „husova 2 jičín“ dá jako první adresu v Jičíně', function () use ($app) {
    $first = $app->suggest('husova 2 jičín')['items'][0];

    assert_same('Husova 2, Valdické Předměstí, 50601 Jičín', $first['label']);
    assert_true($first['local'], 'adresa má být z okresu');
});

test('výběr obce Hořice vrátí rozsah obce kolem nalezeného bodu', function () use ($app) {
    $items = array_values(array_filter(
        $app->suggest('hořice')['items'],
        static fn(array $i): bool => $i['kind'] === 'place' && $i['type'] === 'municipality' && $i['local']
    ));
    $place = $app->resolve($items[0]['key'], $items[0]['label']);
    [$minLng, $minLat, $maxLng, $maxLat] = $place['bbox'];

    assert_true($minLng < $place['lng'] && $place['lng'] < $maxLng, 'délka uvnitř rozsahu');
    assert_true($minLat < $place['lat'] && $place['lat'] < $maxLat, 'šířka uvnitř rozsahu');
});

test('hranice okresu Jičín je k dispozici pro přehledovou mapku', function () use ($app) {
    $district = $app->districtInfo();

    assert_true(in_array($district['boundary']['type'] ?? '', ['Polygon', 'MultiPolygon'], true), 'chybí geometrie');
    assert_near(15.35, ($district['bbox'][0] + $district['bbox'][2]) / 2, 0.3, 'střed okresu');
});

test('obrázky výřezu k tisku se načtou: katastr, ortofoto i přehledka', function () use ($app) {
    $sheet = PrintSheet::compute($app->parcelById(1761441604), null, 'auto', 'ortho');

    foreach (['katastr' => $sheet['kn_url'], 'ortofoto' => $sheet['ortho_url'], 'přehledka' => $sheet['inset']['url']] as $name => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false]);
        $body = curl_exec($ch);
        $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        assert_true(str_starts_with($type, 'image/') && strlen((string)$body) > 1000, "$name: $type, " . strlen((string)$body) . ' B');
    }
});

exit(run_tests());
