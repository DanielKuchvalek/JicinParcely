<?php

declare(strict_types=1);

use JicinParcely\District;

function sample_district(): District
{
    return new District('Jičín', [
        ['code' => 659541, 'name' => 'Jičín', 'municipality' => 'Jičín'],
        ['code' => 659592, 'name' => 'Jičíněves', 'municipality' => 'Jičíněves'],
        ['code' => 645168, 'name' => 'Hořice v Podkrkonoší', 'municipality' => 'Hořice'],
        ['code' => 666521, 'name' => 'Kbelnice u Jičína', 'municipality' => 'Kbelnice'],
        ['code' => 752096, 'name' => 'Sobotka', 'municipality' => 'Sobotka'],
    ]);
}

test('normalizace odstraní českou diakritiku a velká písmena', function () {
    assert_same('escrzyaieuutdn ou', District::normalize('  ĚŠČŘŽÝÁÍÉÚŮŤĎŇ   óú '));
});

test('přesná shoda názvu k.ú. má přednost před začátkem jiného názvu', function () {
    $codes = array_column(sample_district()->findAreas('jicin'), 'code');

    assert_same([659541], $codes);
});

test('k.ú. se najde i podle začátku názvu bez diakritiky', function () {
    $codes = array_column(sample_district()->findAreas('horice'), 'code');

    assert_same([645168], $codes);
});

test('neznámé k.ú. nevrátí nic', function () {
    assert_same([], sample_district()->findAreas('Praha'));
});

test('k.ú. se dohledá podle kódu', function () {
    $district = sample_district();

    assert_same('Hořice v Podkrkonoší', $district->area(645168)['name'] ?? null);
    assert_same(null, $district->area(123456));
});

test('adresa patří do okresu podle obce za PSČ', function () {
    $district = sample_district();

    assert_true($district->isLocal('Husova 2, Valdické Předměstí, 50601 Jičín', 'address'), 'Jičín je v okrese');
    assert_true(!$district->isLocal('Husova 1151/2, 74101 Nový Jičín', 'address'), 'Nový Jičín není v okrese');
});

test('ulice patří do okresu podle obce za čárkou', function () {
    $district = sample_district();

    assert_true($district->isLocal('Valdštejnovo náměstí, Jičín', 'street'), 'Jičín je v okrese');
    assert_true(!$district->isLocal('Nádražní, Nový Jičín', 'street'), 'Nový Jičín není v okrese');
});

test('obec patří do okresu podle okresu v závorce', function () {
    $district = sample_district();

    assert_true($district->isLocal('Hořice (Jičín)', 'municipality'), 'Hořice (Jičín) jsou v okrese');
    assert_true(!$district->isLocal('Hořice (Pelhřimov)', 'municipality'), 'Hořice (Pelhřimov) nejsou v okrese');
});

test('parcela z geokodéru patří do okresu podle názvu k.ú.', function () {
    $district = sample_district();

    assert_true($district->isLocal('Jičín 1171', 'parcel'), 'k.ú. Jičín je v okrese');
    assert_true(!$district->isLocal('Nový Jičín-Horní Předměstí 1171', 'parcel'), 'k.ú. Nový Jičín-Horní Předměstí není v okrese');
});
