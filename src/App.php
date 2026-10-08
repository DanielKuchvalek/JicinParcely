<?php

declare(strict_types=1);

namespace JicinParcely;

/**
 * Propojení služeb pro api.php a vyrez.php.
 */
final class App
{
    private ?District $district = null;

    public function __construct(
        private Ruian $ruian,
        private Geocoder $geocoder,
    ) {
    }

    public static function create(): self
    {
        $http = Http::create();

        return new self(new Ruian($http), new Geocoder($http));
    }

    public function parcelAt(float $lat, float $lng): array
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new InvalidInput('Neplatné souřadnice.');
        }

        $feature = $this->ruian->parcelAt($lat, $lng)
            ?? throw new NotFound('Na tomto místě není evidovaná parcela. Klikněte dovnitř hranic parcely.');

        return $this->toParcel($feature);
    }

    public function parcelById(int $id): array
    {
        if ($id <= 0) {
            throw new InvalidInput('Neplatné ID parcely.');
        }

        $feature = $this->ruian->parcelById($id)
            ?? throw new NotFound('Parcela s tímto ID v katastru není.');

        return $this->toParcel($feature);
    }

    public function suggest(string $text): array
    {
        return ['items' => $this->search()->suggest($text)];
    }

    public function resolve(string $key, string $text): array
    {
        return $this->search()->resolve($key, $text);
    }

    /** Hranice okresu pro přehledovou mapku. */
    public function districtInfo(): array
    {
        $district = $this->district();

        return [
            'name' => $district->name,
            'bbox' => $district->boundary !== null ? Parcel::bbox($district->boundary) : null,
            'boundary' => $district->boundary,
        ];
    }

    private function district(): District
    {
        return $this->district ??= District::fromRuian($this->ruian);
    }

    private function search(): Search
    {
        return new Search($this->ruian, $this->geocoder, $this->district());
    }

    private function toParcel(array $feature): array
    {
        $code = (int)$feature['properties']['katastralniuzemi'];
        $area = $this->district()->area($code) ?? $this->ruian->cadastralArea($code);

        return Parcel::fromFeature($feature, $area);
    }
}
