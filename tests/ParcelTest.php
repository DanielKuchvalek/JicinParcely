<?php

declare(strict_types=1);

use JicinParcely\Parcel;

$jicin = ['code' => 659541, 'name' => 'Jičín', 'municipality' => 'Jičín'];

test('pozemková parcela: číslo, druh pozemku, způsob využití a výměra z RÚIAN', function () use ($jicin) {
    $p = Parcel::fromFeature(fixture('parcel-1171.json')['features'][0], $jicin);

    assert_same(1761441604, $p['id']);
    assert_same('1171', $p['label']);
    assert_same('pozemková', $p['type']);
    assert_same('ostatní plocha', $p['land_use']);
    assert_same('ostatní komunikace', $p['usage']);
    assert_same(13077, $p['area_m2']);
    assert_same('DKM', $p['map_source']);
    assert_same(['code' => 659541, 'name' => 'Jičín'], $p['cadastral_area']);
    assert_same('Jičín', $p['municipality']);
});

test('stavební parcela má předponu „st.“ a žádný způsob využití', function () use ($jicin) {
    $p = Parcel::fromFeature(fixture('parcel-st-1171.json')['features'][0], $jicin);

    assert_same('st. 1171', $p['label']);
    assert_same('stavební', $p['type']);
    assert_same('zastavěná plocha a nádvoří', $p['land_use']);
    assert_same(null, $p['usage']);
    assert_same(204, $p['area_m2']);
});

test('parcela s poddělením čísla se označí lomítkem', function () use ($jicin) {
    $p = Parcel::fromFeature(fixture('parcel-st-25-2.json')['features'][0], $jicin);

    assert_same('st. 25/2', $p['label']);
});

test('neznámé k.ú. nechá kód z RÚIAN a název prázdný', function () {
    $p = Parcel::fromFeature(fixture('parcel-1171.json')['features'][0], null);

    assert_same(['code' => 659541, 'name' => null], $p['cadastral_area']);
    assert_same(null, $p['municipality']);
});

test('neznámý kód druhu pozemku se zobrazí jako kód, ne jako prázdno', function () use ($jicin) {
    $feature = fixture('parcel-1171.json')['features'][0];
    $feature['properties']['druhpozemkukod'] = 99;

    assert_same('kód 99', Parcel::fromFeature($feature, $jicin)['land_use']);
});

test('odkazy vedou přímo na parcelu v Nahlížení do KN a ve VDP', function () use ($jicin) {
    $links = Parcel::fromFeature(fixture('parcel-1171.json')['features'][0], $jicin)['links'];

    assert_same('https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id=1761441604', $links['nahlizeni']);
    assert_same('https://vdp.cuzk.gov.cz/vdp/ruian/parcely/1761441604', $links['vdp']);
});

test('odkaz na Mapy.cz má x = zeměpisná délka a y = šířka', function () use ($jicin) {
    $links = Parcel::fromFeature(fixture('parcel-st-25-2.json')['features'][0], $jicin)['links'];

    assert_true(str_contains($links['mapy_cz'], 'x=15.348444&y=50.436633'), 'odkaz: ' . $links['mapy_cz']);
});

test('obálka parcely se počítá z vnějšího obvodu', function () use ($jicin) {
    $p = Parcel::fromFeature(fixture('parcel-1171.json')['features'][0], $jicin);

    assert_same([15.3505166, 50.4363881, 15.3527592, 50.4372653], $p['bbox']);
});

test('obálka vícedílné parcely pokryje všechny části', function () {
    $geometry = ['type' => 'MultiPolygon', 'coordinates' => [
        [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]],
        [[[2, 2], [3, 2], [3, 3], [2, 3], [2, 2]]],
    ]];

    assert_same([0.0, 0.0, 3.0, 3.0], Parcel::bbox($geometry));
});

test('vnitřní bod je střed obálky, když leží uvnitř parcely', function () {
    $square = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]]];

    assert_same([5.0, 5.0], Parcel::interiorPoint($square));
});

test('vnitřní bod u tvaru U neleží v mezeře mezi rameny', function () {
    $u = ['type' => 'Polygon', 'coordinates' => [[
        [0, 0], [10, 0], [10, 10], [7, 10], [7, 3], [3, 3], [3, 10], [0, 10], [0, 0],
    ]]];

    assert_same([1.5, 5.0], Parcel::interiorPoint($u));
});

test('vnitřní bod se vyhne díře v parcele', function () {
    $withHole = ['type' => 'Polygon', 'coordinates' => [
        [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]],
        [[4, 4], [6, 4], [6, 6], [4, 6], [4, 4]],
    ]];

    assert_same([2.0, 5.0], Parcel::interiorPoint($withHole));
});
