<?php

declare(strict_types=1);

namespace JicinParcely;

/**
 * Okres Jičín: katastrální území, obce a hranice okresu.
 * Slouží k doplnění názvu k.ú. k parcele a k řazení výsledků hledání.
 */
final class District
{
    public const CODE = 3604;
    public const NAME = 'Jičín';

    private const DIACRITICS = [
        'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ĺ' => 'l',
        'ľ' => 'l', 'ň' => 'n', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ř' => 'r', 'ŕ' => 'r', 'š' => 's',
        'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ü' => 'u', 'ý' => 'y', 'ž' => 'z',
        'Á' => 'a', 'Ä' => 'a', 'Č' => 'c', 'Ď' => 'd', 'É' => 'e', 'Ě' => 'e', 'Í' => 'i', 'Ĺ' => 'l',
        'Ľ' => 'l', 'Ň' => 'n', 'Ó' => 'o', 'Ô' => 'o', 'Ö' => 'o', 'Ř' => 'r', 'Ŕ' => 'r', 'Š' => 's',
        'Ť' => 't', 'Ú' => 'u', 'Ů' => 'u', 'Ü' => 'u', 'Ý' => 'y', 'Ž' => 'z',
    ];

    /** @var array<int, array{code: int, name: string, municipality: string}> */
    private array $areasByCode = [];

    /** @var array<string, true> normalizované názvy k.ú. */
    private array $areaNames = [];

    /** @var array<string, true> normalizované názvy obcí */
    private array $municipalityNames = [];

    /**
     * @param list<array{code: int, name: string, municipality: string}> $areas
     * @param array|null $boundary zjednodušená hranice okresu (GeoJSON geometrie)
     */
    public function __construct(
        public readonly string $name,
        array $areas,
        public readonly ?array $boundary = null,
    ) {
        foreach ($areas as $area) {
            $this->areasByCode[$area['code']] = $area;
            $this->areaNames[self::normalize($area['name'])] = true;
            $this->municipalityNames[self::normalize($area['municipality'])] = true;
        }
    }

    /**
     * Načte okres z RÚIAN (výsledek se drží v cache, viz Ruian).
     */
    public static function fromRuian(Ruian $ruian): self
    {
        $municipalities = $ruian->municipalities(self::CODE);
        $areas = [];
        foreach ($ruian->cadastralAreas(array_keys($municipalities)) as $area) {
            $areas[] = [
                'code' => $area['code'],
                'name' => $area['name'],
                'municipality' => $municipalities[$area['municipality_code']] ?? '',
            ];
        }
        usort($areas, static fn(array $a, array $b): int => strcmp(self::normalize($a['name']), self::normalize($b['name'])));

        return new self(self::NAME, $areas, $ruian->districtBoundary(self::CODE));
    }

    public static function normalize(string $text): string
    {
        $ascii = strtolower(strtr($text, self::DIACRITICS));

        return trim((string)preg_replace('/\s+/', ' ', $ascii));
    }

    /** @return array{code: int, name: string, municipality: string}|null */
    public function area(int $code): ?array
    {
        return $this->areasByCode[$code] ?? null;
    }

    /**
     * Katastrální území podle názvu: přesné shody, jinak ta, jejichž název dotazem začíná.
     *
     * @return list<array{code: int, name: string, municipality: string}>
     */
    public function findAreas(string $query, int $limit = 5): array
    {
        $needle = self::normalize($query);
        if ($needle === '') {
            return [];
        }

        $exact = [];
        $prefix = [];
        foreach ($this->areasByCode as $area) {
            $name = self::normalize($area['name']);
            if ($name === $needle) {
                $exact[] = $area;
            } elseif (str_starts_with($name, $needle)) {
                $prefix[] = $area;
            }
        }

        return array_slice($exact !== [] ? $exact : $prefix, 0, $limit);
    }

    /**
     * Patří výsledek geokodéru ČÚZK do okresu? Rozhoduje podle textu výsledku:
     * „Hořice (Jičín)“ nese okres v závorce, adresa a ulice končí obcí,
     * parcela začíná názvem k.ú.
     */
    public function isLocal(string $text, string $type): bool
    {
        if (preg_match('/\(([^()]+)\)\s*$/u', $text, $m)) {
            return self::normalize($m[1]) === self::normalize($this->name);
        }

        if ($type === 'parcel') {
            $areaName = (string)preg_replace('/\s+(st\.\s*)?\d+(\/\d+)?$/u', '', $text);

            return isset($this->areaNames[self::normalize($areaName)]);
        }

        $parts = explode(',', $text);
        $place = (string)preg_replace('/^\d{3}\s?\d{2}\s+/u', '', trim((string)end($parts)));

        return isset($this->municipalityNames[self::normalize($place)]);
    }
}
