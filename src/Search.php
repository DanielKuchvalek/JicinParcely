<?php

declare(strict_types=1);

namespace JicinParcely;

/**
 * Hledání: parcely podle čísla (přímo v RÚIAN) a adresy, ulice a obce
 * přes geokodér ČÚZK. Výsledky z okresu Jičín jdou první.
 */
final class Search
{
    /** Typ výsledku podle čísla vrstvy RÚIAN, které geokodér posílá v magicKey („12_13931“). */
    private const PLACE_TYPES = [
        0 => ['parcel', 'Parcela'],
        1 => ['address', 'Adresa'],
        2 => ['building', 'Budova'],
        3 => ['building', 'Budova'],
        4 => ['street', 'Ulice'],
        6 => ['settlement', 'Sídelní jednotka'],
        7 => ['cadastral_area', 'Katastrální území'],
        11 => ['municipality_part', 'Část obce'],
        12 => ['municipality', 'Obec'],
        15 => ['district', 'Okres'],
    ];

    /** ORP a POÚ jsou jen další kopie obce. */
    private const SKIPPED_LAYERS = [13, 14];

    /** Plošné výsledky, které se se stejným názvem opakují v několika vrstvách. */
    private const AREA_LAYERS = [6, 7, 11, 12, 15];

    /** Vrstvy, pro které má smysl přiblížit mapu na celý rozsah, ne na bod. */
    private const EXTENT_LAYERS = [4, 6, 7, 11, 12, 15];

    private const MAX_ITEMS = 8;
    private const MAX_PARCELS = 6;

    public function __construct(
        private Ruian $ruian,
        private Geocoder $geocoder,
        private District $district,
    ) {
    }

    /**
     * Rozpozná zápis parcely: „Jičín 1171“, „Hořice st. 25/2“, „1171/3 Jičín“, „k.ú. Jičín p. č. 1171“.
     *
     * @return array{area: string, building: ?bool, number: int, subdivision: ?int}|null
     */
    public static function parseParcelQuery(string $text): ?array
    {
        $text = trim((string)preg_replace('/\s+/u', ' ', $text));
        $text = (string)preg_replace('/^k\.?\s*ú\.?\s+/iu', '', $text);

        $marker = '(?:(?:parc(?:ela|\.)?|p\.)\s*(?:č\.|č|číslo)?\s*)?';
        $number = '(?<st>st\.?\s*)?(?<num>\d+)(?:\s*\/\s*(?<sub>\d+))?';

        if (
            !preg_match("/^(?<area>\D+?)\s+{$marker}{$number}$/iu", $text, $m)
            && !preg_match("/^{$number}\s+(?:k\.?\s*ú\.?\s+)?(?<area>\D+)$/iu", $text, $m)
        ) {
            return null;
        }

        return [
            'area' => trim($m['area']),
            'building' => ($m['st'] ?? '') !== '' ? true : null,
            'number' => (int)$m['num'],
            'subdivision' => ($m['sub'] ?? '') !== '' ? (int)$m['sub'] : null,
        ];
    }

    /**
     * Výsledky geokodéru jako položky našeptávače: bez duplicit, z okresu napřed.
     *
     * @param list<array{text: string, magicKey: string}> $suggestions
     */
    public static function rankPlaces(array $suggestions, District $district, int $limit = self::MAX_ITEMS): array
    {
        $local = [];
        $other = [];
        $seenAreaLabels = [];

        foreach ($suggestions as $suggestion) {
            $layer = (int)strtok($suggestion['magicKey'], '_');
            if (in_array($layer, self::SKIPPED_LAYERS, true)) {
                continue;
            }
            if (in_array($layer, self::AREA_LAYERS, true)) {
                if (isset($seenAreaLabels[$suggestion['text']])) {
                    continue;
                }
                $seenAreaLabels[$suggestion['text']] = true;
            }

            [$type, $typeLabel] = self::PLACE_TYPES[$layer] ?? ['place', 'Místo'];
            $item = [
                'kind' => 'place',
                'type' => $type,
                'type_label' => $typeLabel,
                'label' => $suggestion['text'],
                'key' => $suggestion['magicKey'],
                'local' => $district->isLocal($suggestion['text'], $type),
            ];

            if ($item['local']) {
                $local[] = $item;
            } else {
                $other[] = $item;
            }
        }

        return array_slice(array_merge($local, $other), 0, $limit);
    }

    /**
     * Parcely nalezené podle čísla jako položky našeptávače. Napřed přesně zadané
     * poddělení, pak pozemkové před stavebními.
     *
     * @param list<array> $features GeoJSON features z vrstvy Parcela (stačí properties)
     * @param array{area: string, building: ?bool, number: int, subdivision: ?int} $query
     */
    public static function parcelItems(array $features, array $query, District $district): array
    {
        $rows = [];
        foreach ($features as $feature) {
            $p = $feature['properties'];
            $building = (int)$p['druhcislovanikod'] === 1;
            $subdivision = $p['poddelenicisla'] !== null ? (int)$p['poddelenicisla'] : null;
            $areaName = $district->area((int)$p['katastralniuzemi'])['name'] ?? '';

            $rows[] = [
                'sort' => [$subdivision === $query['subdivision'] ? 0 : 1, $building ? 1 : 0, $subdivision ?? 0, $areaName],
                'item' => [
                    'kind' => 'parcel',
                    'id' => (int)$p['id'],
                    'label' => trim($areaName . ' ' . ($building ? 'st. ' : '') . $p['cisloparcely']),
                    'detail' => implode(', ', array_filter([
                        $building ? 'Stavební parcela' : 'Pozemková parcela',
                        Parcel::LAND_USE[(int)($p['druhpozemkukod'] ?? 0)] ?? null,
                        isset($p['vymeraparcely']) ? self::formatArea((int)round((float)$p['vymeraparcely'])) : null,
                    ])),
                ],
            ];
        }
        usort($rows, static fn(array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return array_column($rows, 'item');
    }

    /** Položky našeptávače pro zadaný text. */
    public function suggest(string $text): array
    {
        $text = trim($text);
        if (!preg_match('/^.{2,100}$/u', $text)) {
            throw new InvalidInput('Zadejte 2 až 100 znaků.');
        }

        $parcels = [];
        $query = self::parseParcelQuery($text);
        if ($query !== null) {
            $areas = $this->district->findAreas($query['area']);
            if ($areas !== []) {
                $features = $this->ruian->parcelsByNumber(
                    array_column($areas, 'code'),
                    $query['number'],
                    $query['subdivision'],
                    $query['building']
                );
                $parcels = array_slice(self::parcelItems($features, $query, $this->district), 0, self::MAX_PARCELS);
            }
        }

        try {
            $places = self::rankPlaces($this->geocoder->suggest($text), $this->district, self::MAX_ITEMS - count($parcels));
        } catch (ServiceUnavailable $e) {
            if ($parcels === []) {
                throw $e;
            }
            $places = [];
        }

        if ($parcels !== []) {
            // Parcely z RÚIAN jsou přesnější než stejné parcely z geokodéru (ten nerozliší „st.“).
            $places = array_values(array_filter($places, static fn(array $p): bool => $p['type'] !== 'parcel'));
        }

        return array_merge($parcels, $places);
    }

    /**
     * Poloha vybraného výsledku geokodéru; u obce, k.ú. nebo ulice i rozsah pro přiblížení.
     *
     * @return array{type: string, lat: float, lng: float, bbox: ?array}
     */
    public function resolve(string $key, string $text): array
    {
        if (!preg_match('/^(\d+)_(\d+)$/', $key, $m) || trim($text) === '') {
            throw new InvalidInput('Neplatný výsledek hledání.');
        }

        $hit = $this->geocoder->find($text, $key);
        if ($hit === null) {
            throw new NotFound('Místo se nepodařilo dohledat. Zkuste ho vybrat znovu.');
        }

        $layer = (int)$m[1];

        return [
            'type' => (self::PLACE_TYPES[$layer] ?? ['place'])[0],
            'lat' => $hit['lat'],
            'lng' => $hit['lng'],
            'bbox' => in_array($layer, self::EXTENT_LAYERS, true) ? $this->ruian->extent($layer, (int)$m[2]) : null,
        ];
    }

    private static function formatArea(int $squareMeters): string
    {
        return number_format($squareMeters, 0, ',', "\u{00A0}") . "\u{00A0}m²";
    }
}
