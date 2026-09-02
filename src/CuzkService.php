<?php

declare(strict_types=1);

namespace Viagem\App;

class CuzkService
{
    private static array $districtMap = [
        '572659' => 'Jičín',
        '659541' => 'Jičín',
        '572926' => 'Hořice v Podkrkonoší',
        '645168' => 'Hořice v Podkrkonoší',
        '645478' => 'Hořice v Podkrkonoší',
        '573434' => 'Sobotka',
        '752096' => 'Sobotka',
        '752185' => 'Sobotka',
        '573264' => 'Ostroměř',
        '715727' => 'Ostroměř',
        '712213' => 'Ostroměř',
    ];

    public function getParcelByCoordinates(float $lat, float $lng): array
    {
        $wmsInfo = $this->getWmsFeatureInfo($lat, $lng);

        $delta = 0.00002;
        $minLat = $lat - $delta;
        $minLng = $lng - $delta;
        $maxLat = $lat + $delta;
        $maxLng = $lng + $delta;

        $params = [
            'SERVICE' => 'WFS',
            'VERSION' => '2.0.0',
            'REQUEST' => 'GetFeature',
            'TYPENAMES' => 'cp:CadastralParcel',
            'SRSNAME' => 'urn:ogc:def:crs:EPSG::4326',
            'BBOX' => "{$minLat},{$minLng},{$maxLat},{$maxLng},urn:ogc:def:crs:EPSG::4326"
        ];

        $url = 'https://services.cuzk.cz/wfs/inspire-cp-wfs.asp?' . http_build_query($params);
        $xmlString = $this->httpGet($url);

        $mapyCzUrl = sprintf(
            'https://mapy.cz/katastralni?x=%.6f&y=%.6f&z=19&source=coor&id=%.6f%%2C%.6f',
            $lng,
            $lat,
            $lng,
            $lat
        );

        $cuzkGeoviewerUrl = sprintf(
            'https://ags.cuzk.cz/geoprohlizec/?wmc=https%%3A%%2F%%2Fgeoportal.cuzk.cz%%2Fwmc%%2Fkn_cz.xml&lat=%.6f&lon=%.6f&zoom=18',
            $lat,
            $lng
        );

        if ($xmlString) {
            $xml = @simplexml_load_string($xmlString);
            if ($xml !== false) {
                $xml->registerXPathNamespace('cp', 'http://inspire.ec.europa.eu/schemas/cp/4.0');
                $xml->registerXPathNamespace('gml', 'http://www.opengis.net/gml/3.2');
                $xml->registerXPathNamespace('xlink', 'http://www.w3.org/1999/xlink');
                
                $parcels = $xml->xpath('//cp:CadastralParcel');

                if (!empty($parcels)) {
                    $selectedParcel = null;
                    $selectedGeometry = null;

                    foreach ($parcels as $p) {
                        $geom = $this->parseGmlGeometry($p);
                        if ($geom && $this->isPointInPolygonWithHoles($lat, $lng, $geom)) {
                            $selectedParcel = $p;
                            $selectedGeometry = $geom;
                            break;
                        }
                    }

                    if ($selectedParcel === null) {
                        $selectedParcel = $parcels[0];
                        $selectedGeometry = $this->parseGmlGeometry($selectedParcel);
                    }

                    $label = (string)($selectedParcel->xpath('.//cp:label')[0] ?? '');
                    $areaValue = (float)($selectedParcel->xpath('.//cp:areaValue')[0] ?? 0);
                    $nationalRef = (string)($selectedParcel->xpath('.//cp:nationalCadastralReference')[0] ?? '');
                    
                    $gmlId = (string)($selectedParcel->attributes('http://www.opengis.net/gml/3.2')['id'] ?? '');
                    $cuzkId = $gmlId ?: ($nationalRef ? "CP.{$nationalRef}" : 'N/A');

                    $cadastralDistrictLabel = $this->resolveDistrictName($selectedParcel, $nationalRef, $wmsInfo);
                    $isBuilding = stripos($label, 'st') !== false || ($wmsInfo['land_use'] ?? '') === 'zastavěná plocha a nádvoří';

                    $nahlizeniUrl = $cuzkGeoviewerUrl;
                    if ($nationalRef && preg_match('/^\d+$/', $nationalRef)) {
                        $nahlizeniUrl = "https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id={$nationalRef}";
                    }

                    return [
                        'success' => true,
                        'id' => 'Parcela č. ' . ($wmsInfo['parcel_num'] ?: ($label ?: $nationalRef)),
                        'parcel_type' => $isBuilding ? 'Stavební parcela' : 'Pozemková parcela',
                        'land_use' => $wmsInfo['land_use'] ?: ($isBuilding ? 'zastavěná plocha a nádvoří' : 'Dle KN'),
                        'area_m2' => $wmsInfo['area'] ?: ($areaValue > 0 ? (string)round($areaValue) : 'Dle KN'),
                        'cuzk_id' => $cuzkId,
                        'cadastral_district' => $cadastralDistrictLabel,
                        'mapy_cz_url' => $mapyCzUrl,
                        'cuzk_url' => $nahlizeniUrl,
                        'geometry' => $selectedGeometry
                    ];
                }
            }
        }

        $fallbackDistrict = !empty($wmsInfo['district_code'])
            ? (self::$districtMap[$wmsInfo['district_code']] ?? $wmsInfo['district_code'])
            : 'Neznámé';

        return [
            'success' => true,
            'id' => 'Zvolený bod v katastrální mapě',
            'parcel_type' => 'Katastr nemovitostí',
            'land_use' => $wmsInfo['land_use'] ?: 'Dle KN',
            'area_m2' => $wmsInfo['area'] ?: 'Dle KN',
            'cuzk_id' => 'N/A',
            'cadastral_district' => $fallbackDistrict,
            'mapy_cz_url' => $mapyCzUrl,
            'cuzk_url' => $cuzkGeoviewerUrl,
            'geometry' => null
        ];
    }

    private function getWmsFeatureInfo(float $lat, float $lng): array
    {
        $d = 0.0005;
        $bbox = ($lat - $d) . ',' . ($lng - $d) . ',' . ($lat + $d) . ',' . ($lng + $d);

        $params = [
            'SERVICE' => 'WMS',
            'VERSION' => '1.3.0',
            'REQUEST' => 'GetFeatureInfo',
            'LAYERS' => 'PARCELY,OBVODY_PARCEL',
            'QUERY_LAYERS' => 'PARCELY,OBVODY_PARCEL',
            'CRS' => 'EPSG:4326',
            'BBOX' => $bbox,
            'WIDTH' => '101',
            'HEIGHT' => '101',
            'I' => '50',
            'J' => '50',
            'INFO_FORMAT' => 'text/html'
        ];

        $url = 'https://services.cuzk.cz/wms/wms.asp?' . http_build_query($params);
        $html = $this->httpGet($url);

        $result = [
            'land_use' => null,
            'area' => null,
            'parcel_num' => null,
            'district_code' => null
        ];

        if ($html) {
            if (preg_match('/Druh pozemku[:\s<>\/tdb]+([^<>\n\r]+)/ui', $html, $m)) {
                $result['land_use'] = trim($m[1]);
            } elseif (preg_match('/DRUH_POZEMKU[^\w]+([^\r\n<]+)/ui', $html, $m)) {
                $result['land_use'] = trim($m[1]);
            }

            if (preg_match('/Výměra[^\d]+(\d+)/ui', $html, $m)) {
                $result['area'] = trim($m[1]);
            }

            if (preg_match('/Kmenové číslo[^\d]+(\d+)/ui', $html, $m)) {
                $result['parcel_num'] = $m[1];
                if (preg_match('/Poddělení čísla[^\d]+(\d+)/ui', $html, $m2) && (int)$m2[1] > 0) {
                    $result['parcel_num'] .= '/' . $m2[1];
                }
            }

            if (preg_match('/Katastrální území[^\d]+(\d{6})/ui', $html, $mCode)) {
                $result['district_code'] = $mCode[1];
            } elseif (preg_match('/KAT_UZEMI[^\d]+(\d{6})/ui', $html, $mCode)) {
                $result['district_code'] = $mCode[1];
            }
        }

        return $result;
    }

    private function resolveDistrictName(\SimpleXMLElement $parcel, string $nationalRef, array $wmsInfo): string
    {
        $code = null;

        if (!empty($wmsInfo['district_code'])) {
            $code = $wmsInfo['district_code'];
        }

        if (!$code) {
            $zoning = $parcel->xpath('.//cp:cadastralZoning');
            if (!empty($zoning)) {
                $href = (string)($zoning[0]->attributes('http://www.w3.org/1999/xlink')['href'] ?? '');
                if (preg_match('/(\d{6})$/', $href, $matchHref)) {
                    $code = $matchHref[1];
                }
            }
        }

        if (!$code && !empty($nationalRef)) {
            if (preg_match('/^(\d{6})/', $nationalRef, $matchRef)) {
                $code = $matchRef[1];
            }
        }

        if ($code) {
            if (isset(self::$districtMap[$code])) {
                return self::$districtMap[$code] . " [{$code}]";
            }

            return $code;
        }

        return 'Neznámé';
    }

    private function isPointInPolygonWithHoles(float $lat, float $lng, array $rings): bool
    {
        if (empty($rings) || !is_array($rings[0])) {
            return false;
        }

        if (!$this->isPointInRing($lat, $lng, $rings[0])) {
            return false;
        }

        $ringsCount = count($rings);
        for ($i = 1; $i < $ringsCount; $i++) {
            if ($this->isPointInRing($lat, $lng, $rings[$i])) {
                return false;
            }
        }

        return true;
    }

    private function isPointInRing(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];

            $intersect = (($yi > $lng) !== ($yj > $lng))
                && ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    private function parseGmlGeometry(\SimpleXMLElement $parcel): ?array
    {
        $posLists = $parcel->xpath('.//gml:posList');
        if (empty($posLists)) {
            return null;
        }

        $allRings = [];

        foreach ($posLists as $posList) {
            $rawText = trim((string)$posList);
            if (empty($rawText)) {
                continue;
            }

            $numbers = preg_split('/\s+/', $rawText);
            $coords = [];
            $count = count($numbers);

            for ($i = 0; $i + 1 < $count; $i += 2) {
                $lat = (float)$numbers[$i];
                $lng = (float)$numbers[$i + 1];
                if ($lat !== 0.0 && $lng !== 0.0) {
                    $coords[] = [$lat, $lng];
                }
            }

            if (count($coords) >= 3) {
                $allRings[] = $coords;
            }
        }

        return !empty($allRings) ? $allRings : null;
    }

    private function httpGet(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode >= 200 && $httpCode < 300 && $response) ? $response : null;
    }
}
