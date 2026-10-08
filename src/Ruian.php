<?php

declare(strict_types=1);

namespace JicinParcely;

/**
 * Dotazy do RÚIAN přes ArcGIS REST službu ČÚZK.
 * Parcela přijde i s druhem pozemku, výměrou a polygonem v jednom dotazu.
 */
final class Ruian
{
    private const BASE = 'https://ags.cuzk.gov.cz/arcgis/rest/services/RUIAN/MapServer';

    private const PARCEL = 5;
    private const CADASTRAL_AREA = 7;
    private const MUNICIPALITY = 12;
    private const DISTRICT = 15;

    private const PARCEL_FIELDS = 'id,kmenovecislo,poddelenicisla,cisloparcely,druhcislovanikod,'
        . 'druhpozemkukod,zpusobyvyuzitipozemku,vymeraparcely,katastralniuzemi,zdroj,nespravny';

    private const HOUR = 3600;
    private const WEEK = 604800;

    public function __construct(private Http $http)
    {
    }

    /**
     * Parcela, do které padne bod. RÚIAN zná díry v polygonech, takže klik do domu
     * uprostřed zahrady vrátí stavební parcelu, ne zahradu kolem.
     */
    public function parcelAt(float $lat, float $lng): ?array
    {
        return $this->queryFeatures(self::PARCEL, [
            'geometry' => sprintf('%.8F,%.8F', $lng, $lat),
            'geometryType' => 'esriGeometryPoint',
            'inSR' => 4326,
            'spatialRel' => 'esriSpatialRelIntersects',
            'outFields' => self::PARCEL_FIELDS,
            'returnGeometry' => 'true',
            'outSR' => 4326,
            'geometryPrecision' => 7,
        ])[0] ?? null;
    }

    public function parcelById(int $id): ?array
    {
        return $this->queryFeatures(self::PARCEL, [
            'where' => "id = $id",
            'outFields' => self::PARCEL_FIELDS,
            'returnGeometry' => 'true',
            'outSR' => 4326,
            'geometryPrecision' => 7,
        ], self::HOUR)[0] ?? null;
    }

    /**
     * Parcely podle čísla v daných k.ú. (bez geometrie). Bez poddělení vrátí i všechna poddělení.
     *
     * @param list<int> $areaCodes
     */
    public function parcelsByNumber(array $areaCodes, int $number, ?int $subdivision, ?bool $building): array
    {
        $where = sprintf('katastralniuzemi IN (%s) AND kmenovecislo = %d', implode(',', array_map('intval', $areaCodes)), $number);
        if ($subdivision !== null) {
            $where .= " AND poddelenicisla = $subdivision";
        }
        if ($building !== null) {
            $where .= ' AND druhcislovanikod = ' . ($building ? 1 : 2);
        }

        return $this->queryFeatures(self::PARCEL, [
            'where' => $where,
            'outFields' => self::PARCEL_FIELDS,
            'returnGeometry' => 'false',
            'orderByFields' => 'poddelenicisla',
            'resultRecordCount' => 50,
        ], self::HOUR);
    }

    /** @return array<int, string> kód obce => název */
    public function municipalities(int $districtCode): array
    {
        $features = $this->queryFeatures(self::MUNICIPALITY, [
            'where' => "okres = $districtCode",
            'outFields' => 'kod,nazev',
            'returnGeometry' => 'false',
        ], self::WEEK);

        $names = [];
        foreach ($features as $feature) {
            $names[(int)$feature['properties']['kod']] = (string)$feature['properties']['nazev'];
        }

        return $names;
    }

    /**
     * @param list<int> $municipalityCodes
     * @return list<array{code: int, name: string, municipality_code: int}>
     */
    public function cadastralAreas(array $municipalityCodes): array
    {
        if ($municipalityCodes === []) {
            return [];
        }

        $features = $this->queryFeatures(self::CADASTRAL_AREA, [
            'where' => 'obec IN (' . implode(',', array_map('intval', $municipalityCodes)) . ')',
            'outFields' => 'kod,nazev,obec',
            'returnGeometry' => 'false',
        ], self::WEEK);

        return array_map(static fn(array $f): array => [
            'code' => (int)$f['properties']['kod'],
            'name' => (string)$f['properties']['nazev'],
            'municipality_code' => (int)$f['properties']['obec'],
        ], $features);
    }

    /**
     * Katastrální území podle kódu i mimo okres Jičín.
     *
     * @return array{code: int, name: string, municipality: ?string}|null
     */
    public function cadastralArea(int $code): ?array
    {
        $area = $this->queryFeatures(self::CADASTRAL_AREA, [
            'where' => "kod = $code",
            'outFields' => 'kod,nazev,obec',
            'returnGeometry' => 'false',
        ], self::WEEK)[0]['properties'] ?? null;

        if ($area === null) {
            return null;
        }

        $municipality = $this->queryFeatures(self::MUNICIPALITY, [
            'where' => 'kod = ' . (int)$area['obec'],
            'outFields' => 'nazev',
            'returnGeometry' => 'false',
        ], self::WEEK)[0]['properties']['nazev'] ?? null;

        return ['code' => (int)$area['kod'], 'name' => (string)$area['nazev'], 'municipality' => $municipality];
    }

    /** Zjednodušená hranice okresu (GeoJSON geometrie, přesnost asi 10 m). */
    public function districtBoundary(int $districtCode): ?array
    {
        return $this->queryFeatures(self::DISTRICT, [
            'where' => "kod = $districtCode",
            'outFields' => 'kod',
            'returnGeometry' => 'true',
            'outSR' => 4326,
            'maxAllowableOffset' => 0.0001,
            'geometryPrecision' => 5,
        ], self::WEEK)[0]['geometry'] ?? null;
    }

    /**
     * Obálka prvku [minLng, minLat, maxLng, maxLat] podle objectid, které posílá geokodér.
     */
    public function extent(int $layer, int $objectId): ?array
    {
        $extent = $this->http->getJson(self::BASE . "/$layer/query", [
            'where' => "objectid = $objectId",
            'returnExtentOnly' => 'true',
            'outSR' => 4326,
            'f' => 'json',
        ], self::WEEK)['extent'] ?? null;

        // Bez shody vrací služba "NaN".
        if (!is_array($extent) || !is_numeric($extent['xmin'] ?? null)) {
            return null;
        }

        return [(float)$extent['xmin'], (float)$extent['ymin'], (float)$extent['xmax'], (float)$extent['ymax']];
    }

    private function queryFeatures(int $layer, array $params, int $cacheTtl = 0): array
    {
        return $this->http->getJson(self::BASE . "/$layer/query", $params + ['f' => 'geojson'], $cacheTtl)['features'] ?? [];
    }
}
