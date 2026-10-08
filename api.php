<?php

declare(strict_types=1);

/**
 * JSON API pro mapu:
 *   ?action=parcel&lat=..&lng=..    parcela v bodě (výchozí akce)
 *   ?action=parcel&id=..            parcela podle ID
 *   ?action=suggest&q=..            našeptávač: parcely, adresy, ulice, obce
 *   ?action=resolve&key=..&text=..  poloha vybraného výsledku hledání
 *   ?action=district                hranice okresu pro přehledovou mapku
 */

require __DIR__ . '/src/bootstrap.php';

use JicinParcely\App;
use JicinParcely\InvalidInput;
use JicinParcely\NotFound;
use JicinParcely\ServiceUnavailable;

header('Content-Type: application/json; charset=utf-8');

try {
    $app = App::create();

    [$data, $maxAge] = match (string_param('action', 'parcel')) {
        'parcel' => [
            isset($_GET['id'])
                ? $app->parcelById(int_param('id'))
                : $app->parcelAt(float_param('lat'), float_param('lng')),
            3600,
        ],
        'suggest' => [$app->suggest(string_param('q')), 300],
        'resolve' => [$app->resolve(string_param('key'), string_param('text')), 3600],
        'district' => [$app->districtInfo(), 86400],
        default => throw new InvalidInput('Neznámá akce.'),
    };

    header("Cache-Control: public, max-age=$maxAge");
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidInput $e) {
    fail(400, $e->getMessage());
} catch (NotFound $e) {
    fail(404, $e->getMessage());
} catch (ServiceUnavailable $e) {
    error_log('ČÚZK: ' . $e->getMessage());
    fail(502, 'Služba ČÚZK teď neodpovídá. Zkuste to za chvíli znovu.');
} catch (Throwable $e) {
    error_log((string)$e);
    fail(500, 'Na serveru nastala chyba.');
}

function string_param(string $name, string $default = ''): string
{
    $value = $_GET[$name] ?? $default;

    return is_string($value) ? $value : $default;
}

function float_param(string $name): float
{
    $value = filter_var(string_param($name), FILTER_VALIDATE_FLOAT);
    if ($value === false) {
        throw new InvalidInput("Chybí nebo je neplatný parametr $name.");
    }

    return $value;
}

function int_param(string $name): int
{
    $value = filter_var(string_param($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($value === false) {
        throw new InvalidInput("Chybí nebo je neplatný parametr $name.");
    }

    return $value;
}

function fail(int $status, string $message): void
{
    http_response_code($status);
    header('Cache-Control: no-store');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
}
