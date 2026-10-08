<?php

declare(strict_types=1);

namespace JicinParcely;

/**
 * Geokodér ČÚZK nad daty RÚIAN: adresy, ulice, obce, k.ú. i parcely.
 * Nezáleží mu na velikosti písmen ani na diakritice.
 */
final class Geocoder
{
    private const BASE = 'https://ags.cuzk.gov.cz/arcgis/rest/services/RUIAN/Vyhledavaci_sluzba_nad_daty_RUIAN/MapServer/exts/GeocodeSOE';

    public function __construct(private Http $http)
    {
    }

    /** @return list<array{text: string, magicKey: string}> */
    public function suggest(string $text, int $max = 15): array
    {
        $data = $this->http->getJson(self::BASE . '/suggest', [
            'text' => $text,
            'maxSuggestions' => $max,
            'f' => 'json',
        ], 3600);

        return array_values(array_filter(
            $data['suggestions'] ?? [],
            static fn(mixed $s): bool => is_array($s) && is_string($s['text'] ?? null) && is_string($s['magicKey'] ?? null)
        ));
    }

    /** @return array{lat: float, lng: float}|null */
    public function find(string $text, string $magicKey): ?array
    {
        $data = $this->http->getJson(self::BASE . '/findAddressCandidates', [
            'SingleLine' => $text,
            'magicKey' => $magicKey,
            'outSR' => 4326,
            'maxLocations' => 1,
            'f' => 'json',
        ], 86400);

        $location = $data['candidates'][0]['location'] ?? null;

        return isset($location['x'], $location['y'])
            ? ['lat' => (float)$location['y'], 'lng' => (float)$location['x']]
            : null;
    }
}
