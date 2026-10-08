<?php

declare(strict_types=1);

namespace JicinParcely;

/**
 * Geometrie výřezu katastrální mapy k tisku (list A4) v přesném měřítku.
 *
 * Výřez je ve Web Mercatoru (EPSG:3857) jako mapa v aplikaci, sever je nahoře.
 * Mercator ale délky zvětšuje (v Jičíně asi 1,57×) a na elipsoidu v ose x jinak
 * než v ose y, proto se obálka počítá s měřítkovým faktorem pro každou osu zvlášť.
 * 1 mm na papíře pak odpovídá přesně (měřítko / 1000) m v terénu.
 */
final class PrintSheet
{
    public const SCALES = [500, 1000, 2000, 5000];

    /** Mapové pole v mm podle orientace listu A4 (okraje 10 mm). */
    public const FRAMES = ['portrait' => [190.0, 190.0], 'landscape' => [207.0, 172.0]];

    /** Volný okraj kolem parcely jako podíl jejího rozměru (na každé straně). */
    private const PADDING = 0.1;

    /** WMS ČÚZK parametr DPI ignoruje; při ~120 DPI jsou čísla parcel na papíře čitelná. */
    private const KN_DPI = 120;
    private const PHOTO_DPI = 200;
    private const INSET_DPI = 150;
    private const INSET_MM = [62.0, 40.0];
    private const INSET_MIN_SCALE = 25000;

    private const KN_WMS = 'https://services.cuzk.gov.cz/wms/wms.asp';
    private const ORTHO_SERVICE = 'https://ags.cuzk.gov.cz/arcgis1/rest/services/ORTOFOTO_WM/MapServer';
    private const ZTM_SERVICE = 'https://ags.cuzk.gov.cz/arcgis1/rest/services/ZTM_WM/MapServer';

    /** Elipsoid WGS84: velká poloosa a kvadrát excentricity f(2 − f). */
    private const A = 6378137.0;
    private const E2 = 0.0066943799901413;

    /**
     * @param array{bbox: array, geometry: array} $parcel parcela z Parcel::fromFeature()
     * @param int|null $scale zvolené měřítko, null = automaticky
     * @param string $orientation 'portrait' | 'landscape' | 'auto'
     * @param string $background 'none' | 'ortho'
     */
    public static function compute(array $parcel, ?int $scale, string $orientation, string $background): array
    {
        $layout = self::chooseLayout($parcel['bbox'], $scale, $orientation);
        [$width, $height] = self::FRAMES[$layout['orientation']];
        [$minLng, $minLat, $maxLng, $maxLat] = $parcel['bbox'];
        $lat = ($minLat + $maxLat) / 2;
        $lng = ($minLng + $maxLng) / 2;

        $frame = self::frameBbox($lat, $lng, $layout['scale'], $width, $height);

        [$insetWidth, $insetHeight] = self::INSET_MM;
        $insetScale = max(self::INSET_MIN_SCALE, $layout['scale'] * 10);
        $insetBbox = self::frameBbox($lat, $lng, $insetScale, $insetWidth, $insetHeight);
        $rectWidth = $width * $layout['scale'] / $insetScale;
        $rectHeight = $height * $layout['scale'] / $insetScale;

        return $layout + [
            'frame' => ['width_mm' => $width, 'height_mm' => $height],
            'bbox3857' => $frame,
            'kn_url' => self::wmsUrl($frame, self::pixels($frame, $height, self::KN_DPI)),
            'ortho_url' => $background === 'ortho'
                ? self::exportUrl(self::ORTHO_SERVICE, $frame, self::pixels($frame, $height, self::PHOTO_DPI), 'jpg')
                : null,
            'outline' => self::svgPath($parcel['geometry'], $frame, $width, $height),
            'scale_bar' => self::scaleBar($layout['scale']),
            'inset' => [
                'url' => self::exportUrl(self::ZTM_SERVICE, $insetBbox, self::pixels($insetBbox, $insetHeight, self::INSET_DPI), 'png'),
                'scale' => $insetScale,
                'width_mm' => $insetWidth,
                'height_mm' => $insetHeight,
                'rect' => [
                    'x' => ($insetWidth - $rectWidth) / 2,
                    'y' => ($insetHeight - $rectHeight) / 2,
                    'w' => $rectWidth,
                    'h' => $rectHeight,
                ],
            ],
        ];
    }

    /**
     * Orientace a měřítko listu. Při 'auto' vyhraje orientace s podrobnějším měřítkem
     * (při shodě na výšku).
     *
     * @return array{orientation: string, scale: int, fits: bool}
     */
    public static function chooseLayout(array $bbox, ?int $scale, string $orientation): array
    {
        if ($scale !== null && !in_array($scale, self::SCALES, true)) {
            $scale = null;
        }
        $candidates = isset(self::FRAMES[$orientation]) ? [$orientation] : array_keys(self::FRAMES);

        $best = null;
        foreach ($candidates as $candidate) {
            $auto = self::autoScale($bbox, ...self::FRAMES[$candidate]);
            if ($best === null || ($auto ?? PHP_INT_MAX) < ($best['auto'] ?? PHP_INT_MAX)) {
                $best = ['orientation' => $candidate, 'auto' => $auto];
            }
        }

        $chosen = $scale ?? $best['auto'] ?? max(self::SCALES);

        return [
            'orientation' => $best['orientation'],
            'scale' => $chosen,
            'fits' => $best['auto'] !== null && $chosen >= $best['auto'],
        ];
    }

    /**
     * Nejpodrobnější měřítko, ve kterém se parcela i s okrajem vejde do mapového pole;
     * null, když se nevejde ani v nejmenším.
     */
    public static function autoScale(array $bbox, float $widthMm, float $heightMm): ?int
    {
        [$x1, $y1] = self::toMercator($bbox[0], $bbox[1]);
        [$x2, $y2] = self::toMercator($bbox[2], $bbox[3]);
        [$kx, $ky] = self::mercatorScale(($bbox[1] + $bbox[3]) / 2);
        $neededWidth = ($x2 - $x1) / $kx * (1 + 2 * self::PADDING);
        $neededHeight = ($y2 - $y1) / $ky * (1 + 2 * self::PADDING);

        foreach (self::SCALES as $scale) {
            if ($neededWidth <= $widthMm / 1000 * $scale && $neededHeight <= $heightMm / 1000 * $scale) {
                return $scale;
            }
        }

        return null;
    }

    /**
     * Obálka mapového pole v EPSG:3857 [minX, minY, maxX, maxY] se středem v daném bodě.
     */
    public static function frameBbox(float $lat, float $lng, int $scale, float $widthMm, float $heightMm): array
    {
        [$x, $y] = self::toMercator($lng, $lat);
        [$kx, $ky] = self::mercatorScale($lat);
        $halfWidth = $widthMm / 1000 * $scale * $kx / 2;
        $halfHeight = $heightMm / 1000 * $scale * $ky / 2;

        return [$x - $halfWidth, $y - $halfHeight, $x + $halfWidth, $y + $halfHeight];
    }

    /**
     * Měřítkový faktor pseudo-Mercatoru vůči elipsoidu WGS84 [ve směru x, ve směru y].
     */
    public static function mercatorScale(float $lat): array
    {
        $phi = deg2rad($lat);
        $w = 1 - self::E2 * sin($phi) ** 2;

        return [sqrt($w) / cos($phi), $w ** 1.5 / ((1 - self::E2) * cos($phi))];
    }

    /** Souřadnice zeměpisné délky a šířky v EPSG:3857 [x, y] v metrech. */
    public static function toMercator(float $lng, float $lat): array
    {
        return [self::A * deg2rad($lng), self::A * log(tan(M_PI / 4 + deg2rad($lat) / 2))];
    }

    /**
     * Obrys parcely jako SVG path v milimetrech mapového pole (díry jako další podcesty).
     */
    public static function svgPath(array $geometry, array $bbox3857, float $widthMm, float $heightMm): string
    {
        [$minX, $minY, $maxX, $maxY] = $bbox3857;
        $scaleX = $widthMm / ($maxX - $minX);
        $scaleY = $heightMm / ($maxY - $minY);
        $polygons = $geometry['type'] === 'MultiPolygon' ? $geometry['coordinates'] : [$geometry['coordinates']];

        $paths = [];
        foreach ($polygons as $rings) {
            foreach ($rings as $ring) {
                if (count($ring) > 1 && $ring[0] === $ring[count($ring) - 1]) {
                    array_pop($ring);
                }
                $path = '';
                foreach ($ring as $i => [$lng, $lat]) {
                    [$x, $y] = self::toMercator((float)$lng, (float)$lat);
                    $path .= ($i === 0 ? 'M' : 'L') . self::mm(($x - $minX) * $scaleX) . ' ' . self::mm(($maxY - $y) * $scaleY);
                }
                $paths[] = $path . 'Z';
            }
        }

        return implode(' ', $paths);
    }

    /**
     * Grafické měřítko: 50 mm rozdělených na 5 dílků.
     *
     * @return array{total_m: float, segments: int, length_mm: float}
     */
    public static function scaleBar(int $scale): array
    {
        $total = $scale / 20;

        return ['total_m' => (float)$total, 'segments' => 5, 'length_mm' => $total * 1000 / $scale];
    }

    /**
     * Grafické měřítko jako SVG, ve kterém 1 jednotka = 1 mm na papíře.
     */
    public static function scaleBarSvg(array $bar): string
    {
        $step = $bar['length_mm'] / $bar['segments'];
        $left = 3.0; // místo pro popisek „0“
        $width = $left + $bar['length_mm'] + 7.0; // + jednotka „m“
        $height = 9.0;

        $parts = [];
        for ($i = 0; $i < $bar['segments']; $i++) {
            $parts[] = sprintf(
                '<rect x="%s" y="5" width="%s" height="1.6" class="%s"/>',
                self::number($i * $step),
                self::number($step),
                $i % 2 === 1 ? 'bar-light' : 'bar-dark'
            );
        }
        for ($i = 0; $i <= $bar['segments']; $i++) {
            $label = number_format($bar['total_m'] / $bar['segments'] * $i, 0, ',', "\u{00A0}");
            $parts[] = sprintf('<text x="%s" y="3.6">%s</text>', self::number($i * $step), $label);
        }
        $parts[] = sprintf('<text x="%s" y="6.9" class="unit">m</text>', self::number($bar['length_mm'] + 2.2));

        return sprintf(
            '<svg viewBox="%s 0 %s %s" width="%smm" height="%smm" aria-hidden="true">%s</svg>',
            self::number(-$left),
            self::number($width),
            self::number($height),
            self::number($width),
            self::number($height),
            implode('', $parts)
        );
    }

    /** Rozměr obrázku v pixelech pro dané DPI se čtvercovými pixely (ArcGIS jinak obálku posune). */
    private static function pixels(array $bbox, float $heightMm, int $dpi): array
    {
        $height = (int)round($heightMm / 25.4 * $dpi);
        $width = (int)round($height * ($bbox[2] - $bbox[0]) / ($bbox[3] - $bbox[1]));

        return [$width, $height];
    }

    private static function wmsUrl(array $bbox, array $size): string
    {
        return self::KN_WMS . '?' . http_build_query([
            'SERVICE' => 'WMS',
            'VERSION' => '1.1.1',
            'REQUEST' => 'GetMap',
            'LAYERS' => 'KN',
            'STYLES' => '',
            'SRS' => 'EPSG:3857',
            'FORMAT' => 'image/png',
            'TRANSPARENT' => 'true',
            'WIDTH' => $size[0],
            'HEIGHT' => $size[1],
            'BBOX' => self::bboxParam($bbox),
        ]);
    }

    private static function exportUrl(string $service, array $bbox, array $size, string $format): string
    {
        return $service . '/export?' . http_build_query([
            'bbox' => self::bboxParam($bbox),
            'bboxSR' => 3857,
            'imageSR' => 3857,
            'size' => $size[0] . ',' . $size[1],
            'format' => $format,
            'f' => 'image',
        ]);
    }

    private static function bboxParam(array $bbox): string
    {
        return implode(',', array_map(static fn(float $v): string => sprintf('%.3f', $v), $bbox));
    }

    private static function mm(float $value): string
    {
        return sprintf('%.2f', round($value, 2) + 0.0);
    }

    /** Číslo pro SVG atribut bez zbytečných nul („10“, „52.2“). */
    private static function number(float $value): string
    {
        return rtrim(rtrim(sprintf('%.3f', $value), '0'), '.');
    }
}
