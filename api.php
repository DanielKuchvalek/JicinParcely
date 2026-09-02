<?php

declare(strict_types=1);

require_once __DIR__ . '/src/CuzkService.php';

use Viagem\App\CuzkService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);

if ($lat === false || $lng === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Neplatné nebo chybějící souřadnice']);
    exit;
}

$service = new CuzkService();
$result = $service->getParcelByCoordinates((float)$lat, (float)$lng);

echo json_encode($result);