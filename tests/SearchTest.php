<?php

declare(strict_types=1);

use JicinParcely\Search;

test('zápis parcely se rozpozná v běžných tvarech', function () {
    $cases = [
        'Jičín 1171' => ['area' => 'Jičín', 'building' => null, 'number' => 1171, 'subdivision' => null],
        'Hořice st. 25/2' => ['area' => 'Hořice', 'building' => true, 'number' => 25, 'subdivision' => 2],
        'jičín st.1171' => ['area' => 'jičín', 'building' => true, 'number' => 1171, 'subdivision' => null],
        '1171/3 Jičín' => ['area' => 'Jičín', 'building' => null, 'number' => 1171, 'subdivision' => 3],
        'st. 25 Sobotka' => ['area' => 'Sobotka', 'building' => true, 'number' => 25, 'subdivision' => null],
        'k.ú. Jičín p. č. 1171' => ['area' => 'Jičín', 'building' => null, 'number' => 1171, 'subdivision' => null],
        'Kbelnice u Jičína 45 / 1' => ['area' => 'Kbelnice u Jičína', 'building' => null, 'number' => 45, 'subdivision' => 1],
    ];
    foreach ($cases as $input => $expected) {
        assert_same($expected, Search::parseParcelQuery($input), "vstup: $input");
    }
});

test('adresa, samotné číslo ani prázdný text nejsou zápis parcely', function () {
    foreach (['Husova 2, Jičín', '1171', '', 'Valdštejnovo náměstí'] as $input) {
        assert_same(null, Search::parseParcelQuery($input), "vstup: $input");
    }
});

test('výsledky z okresu jdou první, jinak zůstává pořadí geokodéru', function () {
    $items = Search::rankPlaces([
        ['text' => 'Jičínská 117, Volanov, 54101 Trutnov', 'magicKey' => '1_100'],
        ['text' => 'č.p. 117, 50731 Jičíněves', 'magicKey' => '1_101'],
        ['text' => 'Robousy 117, 50601 Jičín', 'magicKey' => '1_102'],
        ['text' => 'č.p. 117, 74231 Starý Jičín', 'magicKey' => '1_103'],
    ], sample_district());

    assert_same(['1_101', '1_102', '1_100', '1_103'], array_column($items, 'key'));
    assert_same([true, true, false, false], array_column($items, 'local'));
});

test('duplicity obce (ORP, POÚ, část obce se stejným názvem) se vynechají', function () {
    $items = Search::rankPlaces([
        ['text' => 'Hořice (Královéhradecký kraj)', 'magicKey' => '14_1723'],
        ['text' => 'Hořice (Královéhradecký kraj)', 'magicKey' => '13_2438'],
        ['text' => 'Hořice (Jičín)', 'magicKey' => '12_13931'],
        ['text' => 'Hořice na Šumavě (Český Krumlov)', 'magicKey' => '12_5000'],
        ['text' => 'Hořice (Jičín)', 'magicKey' => '11_11003'],
    ], sample_district());

    assert_same(['12_13931', '12_5000'], array_column($items, 'key'));
    assert_same(['municipality', 'municipality'], array_column($items, 'type'));
});

test('typ výsledku se určí podle vrstvy RÚIAN v klíči geokodéru', function () {
    $items = Search::rankPlaces([
        ['text' => 'Valdštejnovo náměstí, Jičín', 'magicKey' => '4_96669'],
        ['text' => 'Kbelnice u Jičína (Jičín)', 'magicKey' => '7_77'],
        ['text' => 'Husova 2, Valdické Předměstí, 50601 Jičín', 'magicKey' => '1_1946280'],
    ], sample_district());

    assert_same(['Ulice', 'Katastrální území', 'Adresa'], array_column($items, 'type_label'));
});

test('počet výsledků je omezený', function () {
    $suggestions = [];
    for ($i = 1; $i <= 6; $i++) {
        $suggestions[] = ['text' => "Husova $i, 50601 Jičín", 'magicKey' => "1_$i"];
    }

    assert_same(3, count(Search::rankPlaces($suggestions, sample_district(), 3)));
});

test('parcely podle čísla: pozemková před stavební, s k.ú. a výměrou', function () {
    $features = [
        fixture('parcel-st-1171.json')['features'][0],
        fixture('parcel-1171.json')['features'][0],
    ];
    $query = ['area' => 'Jičín', 'building' => null, 'number' => 1171, 'subdivision' => null];

    $items = Search::parcelItems($features, $query, sample_district());

    assert_same(['Jičín 1171', 'Jičín st. 1171'], array_column($items, 'label'));
    assert_same([1761441604, 1754330604], array_column($items, 'id'));
    assert_true(str_contains($items[0]['detail'], "13\u{00A0}077\u{00A0}m²"), 'detail: ' . $items[0]['detail']);
});

test('parcela s přesně zadaným poddělením jde před ostatní', function () {
    $whole = fixture('parcel-1171.json')['features'][0];
    $sub = fixture('parcel-st-25-2.json')['features'][0];
    $query = ['area' => 'Jičín', 'building' => null, 'number' => 25, 'subdivision' => 2];

    $items = Search::parcelItems([$whole, $sub], $query, sample_district());

    assert_same(['Jičín st. 25/2', 'Jičín 1171'], array_column($items, 'label'));
});
