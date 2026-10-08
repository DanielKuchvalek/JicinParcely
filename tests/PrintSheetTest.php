<?php

declare(strict_types=1);

use JicinParcely\PrintSheet;

/** Obálky parcel o známé velikosti se středem v Jičíně (spočteno nezávisle na elipsoidu WGS84). */
const BOX_60x40 = [15.3495777, 50.4365202, 15.3504223, 50.4368798];
const BOX_150x100 = [15.3489443, 50.4362505, 15.3510557, 50.4371495];
const BOX_300x200 = [15.3478886, 50.435801, 15.3521114, 50.437599];
const BOX_700x500 = [15.3450735, 50.4344526, 15.3549265, 50.4389474];
const BOX_2000x1000 = [15.3359243, 50.4322051, 15.3640757, 50.4411949];
const BOX_WIDE_165x40 = [15.3488388, 50.4365202, 15.3511612, 50.4368798];
const BOX_TALL_40x165 = [15.3497185, 50.4359583, 15.3502815, 50.4374417];

function box_parcel(array $bbox): array
{
    [$x1, $y1, $x2, $y2] = $bbox;

    return [
        'bbox' => $bbox,
        'geometry' => ['type' => 'Polygon', 'coordinates' => [[[$x1, $y1], [$x2, $y1], [$x2, $y2], [$x1, $y2], [$x1, $y1]]]],
    ];
}

function query_of(string $url): array
{
    parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

    return $query;
}

test('měřítkové faktory pseudo-Mercatoru se na elipsoidu liší po osách', function () {
    [$kx, $ky] = PrintSheet::mercatorScale(50.4367);

    assert_near(1.566904084, $kx, 1e-8, 'kx');
    assert_near(1.571188126, $ky, 1e-8, 'ky');
});

test('mapové pole 190 × 190 mm v měřítku 1 : 1000 pokryje přesně 190 × 190 m terénu', function () {
    $b = PrintSheet::frameBbox(50.4367, 15.3513, 1000, 190, 190);

    assert_near(1708750.0431, $b[0], 0.01, 'minX');
    assert_near(6522101.6422, $b[1], 0.01, 'minY');
    assert_near(1709047.7549, $b[2], 0.01, 'maxX');
    assert_near(6522400.1680, $b[3], 0.01, 'maxY');
});

test('automatické měřítko je nejpodrobnější, do kterého se parcela vejde i s okrajem', function () {
    $cases = [
        [BOX_60x40, 500],
        [BOX_150x100, 1000],
        // 185 × 100 m: bez okraje by se vešla do 1 : 1000, s okrajem už ne
        [[15.348698, 50.4362505, 15.351302, 50.4371495], 2000],
        [BOX_300x200, 2000],
        [BOX_700x500, 5000],
        [BOX_2000x1000, null],
    ];
    foreach ($cases as [$bbox, $expected]) {
        assert_same($expected, PrintSheet::autoScale($bbox, 190, 190), 'obálka ' . json_encode($bbox));
    }
});

test('široká parcela otočí list na šířku, když se tak vejde v podrobnějším měřítku', function () {
    assert_same(
        ['orientation' => 'landscape', 'scale' => 1000, 'fits' => true],
        PrintSheet::chooseLayout(BOX_WIDE_165x40, null, 'auto')
    );
});

test('při stejném měřítku v obou orientacích zůstává list na výšku', function () {
    assert_same(
        ['orientation' => 'portrait', 'scale' => 2000, 'fits' => true],
        PrintSheet::chooseLayout(BOX_TALL_40x165, null, 'auto')
    );
});

test('zvolené měřítko a orientace mají přednost a výřez hlásí, že se parcela nevejde', function () {
    assert_same(
        ['orientation' => 'portrait', 'scale' => 500, 'fits' => false],
        PrintSheet::chooseLayout(BOX_WIDE_165x40, 500, 'portrait')
    );
});

test('příliš velká parcela dostane nejmenší měřítko a hlášení, že se nevejde', function () {
    assert_same(
        ['orientation' => 'portrait', 'scale' => 5000, 'fits' => false],
        PrintSheet::chooseLayout(BOX_2000x1000, null, 'portrait')
    );
});

test('obrys parcely i s dírou se převede do milimetrů mapového pole', function () {
    $geometry = ['type' => 'Polygon', 'coordinates' => [
        [[0, 0], [0.002, 0], [0.002, 0.002], [0, 0.002], [0, 0]],
        [[0.0005, 0.0005], [0.0015, 0.0005], [0.0015, 0.0015], [0.0005, 0.0015], [0.0005, 0.0005]],
    ]];

    assert_same(
        'M0.00 100.00L100.00 100.00L100.00 0.00L0.00 0.00Z M25.00 75.00L75.00 75.00L75.00 25.00L25.00 25.00Z',
        PrintSheet::svgPath($geometry, [0.0, 0.0, 222.638982, 222.638982], 100, 100)
    );
});

test('grafické měřítko odpovídá měřítku mapy a má rozumnou délku', function () {
    foreach (PrintSheet::SCALES as $scale) {
        $bar = PrintSheet::scaleBar($scale);
        assert_near($bar['total_m'] * 1000 / $scale, $bar['length_mm'], 1e-9, "1 : $scale");
        assert_true($bar['length_mm'] >= 30 && $bar['length_mm'] <= 70, "délka 30–70 mm pro 1 : $scale");
    }
});

test('grafické měřítko v SVG: 1 jednotka = 1 mm a dílky dají dohromady délku měřítka', function () {
    $bar = PrintSheet::scaleBar(1000);
    $svg = PrintSheet::scaleBarSvg($bar);

    preg_match('/viewBox="(\S+) (\S+) (\S+) (\S+)"/', $svg, $viewBox);
    preg_match('/ width="([\d.]+)mm"/', $svg, $width);
    preg_match('/ height="([\d.]+)mm"/', $svg, $height);
    assert_near((float)$viewBox[3], (float)$width[1], 1e-9, 'šířka viewBoxu = šířka v mm');
    assert_near((float)$viewBox[4], (float)$height[1], 1e-9, 'výška viewBoxu = výška v mm');

    preg_match_all('/<rect [^>]*width="([\d.]+)"/', $svg, $segments);
    assert_same($bar['segments'], count($segments[1]), 'počet dílků');
    assert_near($bar['length_mm'], array_sum(array_map('floatval', $segments[1])), 1e-9, 'součet dílků');
});

test('obrázek katastru z WMS má obálku mapového pole a čtvercové pixely do limitu služby', function () {
    $sheet = PrintSheet::compute(box_parcel(BOX_150x100), null, 'portrait', 'none');
    $q = query_of($sheet['kn_url']);
    $bbox = array_map('floatval', explode(',', $q['BBOX']));

    assert_same('KN', $q['LAYERS']);
    assert_same('EPSG:3857', $q['SRS']);
    foreach ($sheet['bbox3857'] as $i => $value) {
        assert_near($value, $bbox[$i], 0.01, "BBOX[$i]");
    }
    $pixelW = ($bbox[2] - $bbox[0]) / (int)$q['WIDTH'];
    $pixelH = ($bbox[3] - $bbox[1]) / (int)$q['HEIGHT'];
    assert_near($pixelW, $pixelH, $pixelW * 0.002, 'čtvercové pixely');
    assert_true((int)$q['WIDTH'] <= 4096 && (int)$q['HEIGHT'] <= 4096, 'limit služby 4096 px');
    assert_same(null, $sheet['ortho_url']);
});

test('s podkladem ortofoto se načte ortofoto se stejnou obálkou', function () {
    $sheet = PrintSheet::compute(box_parcel(BOX_150x100), null, 'portrait', 'ortho');
    $q = query_of((string)$sheet['ortho_url']);
    $bbox = array_map('floatval', explode(',', $q['bbox']));

    assert_true(str_contains((string)$sheet['ortho_url'], '/ORTOFOTO_WM/MapServer/export'), 'služba ortofota');
    foreach ($sheet['bbox3857'] as $i => $value) {
        assert_near($value, $bbox[$i], 0.01, "bbox[$i]");
    }
});

test('přehledka ukazuje obdélník výřezu ve správném poměru a uprostřed', function () {
    $sheet = PrintSheet::compute(box_parcel(BOX_150x100), 1000, 'portrait', 'none');
    $inset = $sheet['inset'];
    $rect = $inset['rect'];

    assert_near(190 * 1000 / $inset['scale'], $rect['w'], 0.01, 'šířka obdélníku');
    assert_near($inset['width_mm'] / 2, $rect['x'] + $rect['w'] / 2, 0.01, 'střed x');
    assert_near($inset['height_mm'] / 2, $rect['y'] + $rect['h'] / 2, 0.01, 'střed y');
});
