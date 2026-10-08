<?php

declare(strict_types=1);

namespace JicinParcely;

/**
 * Převod parcely z RÚIAN (GeoJSON feature) do tvaru, který vrací api.php.
 */
final class Parcel
{
    /** Druh pozemku – doména atributu druhpozemkukod ve službě RÚIAN. */
    public const LAND_USE = [
        2 => 'orná půda',
        3 => 'chmelnice',
        4 => 'vinice',
        5 => 'zahrada',
        6 => 'ovocný sad',
        7 => 'trvalý travní porost',
        8 => 'trvalý travní porost',
        10 => 'lesní pozemek',
        11 => 'vodní plocha',
        13 => 'zastavěná plocha a nádvoří',
        14 => 'ostatní plocha',
    ];

    /** Způsob využití pozemku – doména atributu zpusobyvyuzitipozemku. */
    public const USAGE = [
        1 => 'skleník, pařeniště',
        2 => 'školka',
        3 => 'plantáž dřevin',
        4 => 'les jiný než hospodářský',
        5 => 'lesní pozemek, na kterém je budova',
        6 => 'rybník',
        7 => 'koryto vodního toku přirozené nebo upravené',
        8 => 'koryto vodního toku umělé',
        9 => 'vodní nádrž přírodní',
        10 => 'vodní nádrž umělá',
        11 => 'zamokřená plocha',
        12 => 'společný dvůr',
        13 => 'zbořeniště',
        14 => 'dráha',
        15 => 'dálnice',
        16 => 'silnice',
        17 => 'ostatní komunikace',
        18 => 'ostatní dopravní plocha',
        19 => 'zeleň',
        20 => 'sportoviště a rekreační plocha',
        21 => 'pohřebiště',
        22 => 'kulturní a osvětová plocha',
        23 => 'manipulační plocha',
        24 => 'dobývací prostor',
        25 => 'skládka',
        26 => 'jiná plocha',
        27 => 'neplodná půda',
        28 => 'vodní plocha, na které je budova',
        29 => 'fotovoltaická elektrárna',
        30 => 'mez, stráň',
    ];

    private const NUMBERING = [1 => 'stavební', 2 => 'pozemková'];

    private const MAP_SOURCE = [1 => 'DKM', 2 => 'UKM'];

    /**
     * @param array $feature GeoJSON feature z vrstvy Parcela (RÚIAN)
     * @param array{name: string, municipality: ?string}|null $area katastrální území, pokud je známé
     */
    public static function fromFeature(array $feature, ?array $area): array
    {
        $a = $feature['properties'];
        $geometry = $feature['geometry'];
        $id = (int)$a['id'];
        $numbering = (int)($a['druhcislovanikod'] ?? 0);
        [$lng, $lat] = self::interiorPoint($geometry);

        return [
            'id' => $id,
            'label' => ($numbering === 1 ? 'st. ' : '') . $a['cisloparcely'],
            'type' => self::NUMBERING[$numbering] ?? null,
            'land_use' => self::codeName(self::LAND_USE, $a['druhpozemkukod'] ?? null),
            'land_use_code' => isset($a['druhpozemkukod']) ? (int)$a['druhpozemkukod'] : null,
            'usage' => self::codeName(self::USAGE, $a['zpusobyvyuzitipozemku'] ?? null),
            'area_m2' => isset($a['vymeraparcely']) ? (int)round((float)$a['vymeraparcely']) : null,
            'cadastral_area' => ['code' => (int)$a['katastralniuzemi'], 'name' => $area['name'] ?? null],
            'municipality' => $area['municipality'] ?? null,
            'map_source' => self::MAP_SOURCE[(int)($a['zdroj'] ?? 0)] ?? null,
            'flagged_incorrect' => !empty($a['nespravny']),
            'point' => ['lat' => $lat, 'lng' => $lng],
            'bbox' => self::bbox($geometry),
            'geometry' => $geometry,
            'links' => [
                'nahlizeni' => "https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id={$id}",
                'vdp' => "https://vdp.cuzk.gov.cz/vdp/ruian/parcely/{$id}",
                'mapy_cz' => sprintf(
                    'https://mapy.cz/katastralni?x=%.6f&y=%.6f&z=19&source=coor&id=%.6f%%2C%.6f',
                    $lng,
                    $lat,
                    $lng,
                    $lat
                ),
            ],
        ];
    }

    /**
     * Obálka [minLng, minLat, maxLng, maxLat] z vnějších obvodů (díry leží uvnitř).
     */
    public static function bbox(array $geometry): array
    {
        $box = [INF, INF, -INF, -INF];
        foreach (self::polygons($geometry) as $rings) {
            foreach ($rings[0] as [$x, $y]) {
                $box = [min($box[0], $x), min($box[1], $y), max($box[2], $x), max($box[3], $y)];
            }
        }

        return array_map('floatval', $box);
    }

    /**
     * Bod [lng, lat], který leží uvnitř parcely (ne v díře ani mimo tvar U).
     * Zkusí střed obálky; když leží mimo, vezme střed nejširšího úseku
     * vodorovné přímky vedené středem obálky.
     */
    public static function interiorPoint(array $geometry): array
    {
        $rings = self::largestPolygon($geometry);
        [$minX, $minY, $maxX, $maxY] = self::bbox(['type' => 'Polygon', 'coordinates' => $rings]);
        $cx = ($minX + $maxX) / 2.0;
        $cy = ($minY + $maxY) / 2.0;

        if (self::containsPoint($rings, $cx, $cy)) {
            return [$cx, $cy];
        }

        $xs = [];
        foreach ($rings as $ring) {
            for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
                [$x1, $y1] = $ring[$j];
                [$x2, $y2] = $ring[$i];
                if (($y1 > $cy) !== ($y2 > $cy)) {
                    $xs[] = $x1 + ($cy - $y1) * ($x2 - $x1) / ($y2 - $y1);
                }
            }
        }
        sort($xs);

        $best = null;
        for ($k = 0; $k + 1 < count($xs); $k += 2) {
            if ($best === null || $xs[$k + 1] - $xs[$k] > $best[1] - $best[0]) {
                $best = [$xs[$k], $xs[$k + 1]];
            }
        }

        return $best !== null
            ? [($best[0] + $best[1]) / 2.0, $cy]
            : array_map('floatval', $rings[0][0]);
    }

    /** Leží bod uvnitř vnějšího obvodu a mimo všechny díry? */
    private static function containsPoint(array $rings, float $x, float $y): bool
    {
        foreach ($rings as $index => $ring) {
            $inRing = self::ringContains($ring, $x, $y);
            if ($index === 0 ? !$inRing : $inRing) {
                return false;
            }
        }

        return true;
    }

    private static function ringContains(array $ring, float $x, float $y): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /** @return list<array> seznam polygonů, každý jako pole prstenců */
    private static function polygons(array $geometry): array
    {
        return $geometry['type'] === 'MultiPolygon' ? $geometry['coordinates'] : [$geometry['coordinates']];
    }

    private static function largestPolygon(array $geometry): array
    {
        $best = null;
        $bestArea = -1.0;
        foreach (self::polygons($geometry) as $rings) {
            $area = abs(self::signedArea($rings[0]));
            if ($area > $bestArea) {
                $best = $rings;
                $bestArea = $area;
            }
        }

        return $best;
    }

    private static function signedArea(array $ring): float
    {
        $sum = 0.0;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            $sum += ($ring[$j][0] * $ring[$i][1]) - ($ring[$i][0] * $ring[$j][1]);
        }

        return $sum / 2.0;
    }

    private static function codeName(array $codes, mixed $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return $codes[(int)$code] ?? 'kód ' . (int)$code;
    }
}
