<?php

declare(strict_types=1);

/**
 * Výřez z katastrální mapy k tisku (A4) pro jednu parcelu:
 *   vyrez.php?id=1761441604&meritko=1000&orientace=na-sirku&podklad=ortofoto
 * Do PDF se uloží přes tisk v prohlížeči („Uložit jako PDF“).
 */

require __DIR__ . '/src/bootstrap.php';

use JicinParcely\App;
use JicinParcely\InvalidInput;
use JicinParcely\NotFound;
use JicinParcely\PrintSheet;
use JicinParcely\ServiceUnavailable;

date_default_timezone_set('Europe/Prague');

const ORIENTATIONS = ['auto' => 'Automaticky', 'na-vysku' => 'Na výšku', 'na-sirku' => 'Na šířku'];
const BACKGROUNDS = ['katastr' => 'Jen katastr', 'ortofoto' => 'S ortofotem'];

function param(string $name, array $allowed, string $default): string
{
    $value = $_GET[$name] ?? $default;

    return is_string($value) && array_key_exists($value, $allowed) ? $value : $default;
}

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function num(float|int $value): string
{
    return number_format($value, 0, ',', "\u{00A0}");
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$scaleParam = filter_input(INPUT_GET, 'meritko', FILTER_VALIDATE_INT);
$scale = in_array($scaleParam, PrintSheet::SCALES, true) ? $scaleParam : null;
$orientation = param('orientace', ORIENTATIONS, 'auto');
$background = param('podklad', BACKGROUNDS, 'katastr');

$parcel = null;
$sheet = null;
$error = null;

try {
    if (!is_int($id)) {
        throw new InvalidInput('Chybí parcela. Vyberte ji v mapě a zvolte „Výřez k tisku“.');
    }
    $parcel = App::create()->parcelById($id);
    $sheet = PrintSheet::compute(
        $parcel,
        $scale,
        ['na-vysku' => 'portrait', 'na-sirku' => 'landscape'][$orientation] ?? 'auto',
        $background === 'ortofoto' ? 'ortho' : 'none'
    );
} catch (InvalidInput | NotFound $e) {
    http_response_code($e instanceof NotFound ? 404 : 400);
    $error = $e->getMessage();
} catch (ServiceUnavailable $e) {
    http_response_code(502);
    error_log('ČÚZK: ' . $e->getMessage());
    $error = 'Služba ČÚZK teď neodpovídá. Zkuste stránku za chvíli načíst znovu.';
}

$area = $parcel['cadastral_area'] ?? null;
$areaText = $area ? trim(($area['name'] ?? '') . ' (' . $area['code'] . ')') : '';
$title = $parcel ? "Výřez KM – parc. {$parcel['label']}, k.ú. " . ($area['name'] ?? $area['code']) : 'Výřez z katastrální mapy';
$autoScale = $parcel ? PrintSheet::chooseLayout($parcel['bbox'], null, $sheet['orientation'] ?? 'auto')['scale'] : null;
$landscape = ($sheet['orientation'] ?? '') === 'landscape';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cpath d='M4.5 10.5 19 4.5l8.5 13-14 10Z' fill='none' stroke='%2317232a' stroke-width='2.6' stroke-linejoin='round'/%3E%3Ccircle cx='19' cy='4.5' r='3.6' fill='%23d81b60'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@500;600&display=swap">
    <link rel="stylesheet" href="vyrez.css">
    <style>@page { size: A4 <?= $landscape ? 'landscape' : 'portrait' ?>; margin: 0; }</style>
</head>
<body>
<?php if ($error !== null): ?>
    <main class="message">
        <h1>Výřez se nepodařilo připravit</h1>
        <p><?= h($error) ?></p>
        <a class="button" href="index.php">Zpět na mapu</a>
    </main>
<?php else: ?>
    <form class="toolbar" method="get" action="vyrez.php">
        <input type="hidden" name="id" value="<?= h($parcel['id']) ?>">
        <label>Měřítko
            <select name="meritko">
                <option value="">Automaticky (1 : <?= num($autoScale) ?>)</option>
                <?php foreach (PrintSheet::SCALES as $option): ?>
                    <option value="<?= $option ?>"<?= $option === $scale ? ' selected' : '' ?>>1 : <?= num($option) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Orientace
            <select name="orientace">
                <?php foreach (ORIENTATIONS as $value => $label): ?>
                    <option value="<?= $value ?>"<?= $value === $orientation ? ' selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Podklad
            <select name="podklad">
                <?php foreach (BACKGROUNDS as $value => $label): ?>
                    <option value="<?= $value ?>"<?= $value === $background ? ' selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <noscript><button type="submit" class="button">Použít</button></noscript>
        <span class="toolbar__spacer"></span>
        <a class="button" href="index.php">Zpět na mapu</a>
        <button type="button" class="button button--primary" data-print>Tisknout nebo uložit PDF</button>
        <p class="toolbar__hint">Aby měřítko sedělo, tiskněte ve skutečné velikosti (100 %), ne „přizpůsobit stránce“.</p>
        <?php if (!$sheet['fits']): ?>
            <p class="toolbar__warning">Parcela se do výřezu v měřítku 1 : <?= num($sheet['scale']) ?> celá nevejde. Zvolte menší měřítko nebo jinou orientaci.</p>
        <?php endif; ?>
    </form>

    <main class="sheet<?= $landscape ? ' sheet--landscape' : '' ?>">
        <header class="sheet__head">
            <div>
                <p class="sheet__kicker">Výřez z katastrální mapy</p>
                <h1>Parcela <?= h($parcel['label']) ?>, k.ú. <?= h($areaText) ?></h1>
            </div>
            <p class="sheet__date">Stav k <?= date('j. n. Y') ?></p>
        </header>

        <figure class="frame" style="width: <?= $sheet['frame']['width_mm'] ?>mm; height: <?= $sheet['frame']['height_mm'] ?>mm">
            <?php if ($sheet['ortho_url'] !== null): ?>
                <img class="frame__layer" src="<?= h($sheet['ortho_url']) ?>" alt="">
            <?php endif; ?>
            <img class="frame__layer" src="<?= h($sheet['kn_url']) ?>" alt="Katastrální mapa v okolí parcely <?= h($parcel['label']) ?>">
            <svg class="frame__overlay" viewBox="0 0 <?= $sheet['frame']['width_mm'] ?> <?= $sheet['frame']['height_mm'] ?>" preserveAspectRatio="none" aria-hidden="true">
                <path class="outline-casing" d="<?= h($sheet['outline']) ?>"/>
                <path class="outline" d="<?= h($sheet['outline']) ?>"/>
            </svg>
            <div class="frame__north" aria-label="Sever">
                <svg viewBox="0 0 20 28" aria-hidden="true"><path d="M10 1 17 22 10 17 3 22Z"/><path class="north-half" d="M10 1 10 17 3 22Z"/></svg>
                <span>S</span>
            </div>
            <div class="frame__scale">
                <?= PrintSheet::scaleBarSvg($sheet['scale_bar']) ?>
                <span>1 : <?= num($sheet['scale']) ?></span>
            </div>
        </figure>

        <section class="sheet__info">
            <dl class="info">
                <div><dt>Parcela</dt><dd><?= h($parcel['label']) ?> (<?= h($parcel['type']) ?>)</dd></div>
                <div><dt>Katastrální území</dt><dd><?= h($areaText) ?></dd></div>
                <?php if ($parcel['municipality']): ?>
                    <div><dt>Obec</dt><dd><?= h($parcel['municipality']) ?></dd></div>
                <?php endif; ?>
                <div><dt>Druh pozemku</dt><dd><?= h($parcel['land_use'] ?? '–') ?></dd></div>
                <?php if ($parcel['usage']): ?>
                    <div><dt>Způsob využití</dt><dd><?= h($parcel['usage']) ?></dd></div>
                <?php endif; ?>
                <div><dt>Výměra</dt><dd><?= $parcel['area_m2'] !== null ? num($parcel['area_m2']) . "\u{00A0}m²" : '–' ?></dd></div>
                <?php if ($parcel['map_source']): ?>
                    <div><dt>Typ mapy</dt><dd><?= h($parcel['map_source']) ?></dd></div>
                <?php endif; ?>
                <div><dt>ID parcely</dt><dd><?= h($parcel['id']) ?></dd></div>
            </dl>

            <div class="sheet__side">
                <figure class="inset" style="width: <?= $sheet['inset']['width_mm'] ?>mm; height: <?= $sheet['inset']['height_mm'] ?>mm">
                    <img src="<?= h($sheet['inset']['url']) ?>" alt="Přehledová mapa okolí">
                    <?php $rect = $sheet['inset']['rect']; ?>
                    <svg viewBox="0 0 <?= $sheet['inset']['width_mm'] ?> <?= $sheet['inset']['height_mm'] ?>" aria-hidden="true">
                        <?php if ($rect['w'] < 3): ?>
                            <circle cx="<?= $rect['x'] + $rect['w'] / 2 ?>" cy="<?= $rect['y'] + $rect['h'] / 2 ?>" r="1.6"/>
                        <?php else: ?>
                            <rect x="<?= $rect['x'] ?>" y="<?= $rect['y'] ?>" width="<?= $rect['w'] ?>" height="<?= $rect['h'] ?>"/>
                        <?php endif; ?>
                    </svg>
                    <figcaption>Přehled 1 : <?= num($sheet['inset']['scale']) ?></figcaption>
                </figure>
                <ul class="legend">
                    <li><svg viewBox="0 0 16 10" aria-hidden="true"><rect class="legend-parcel" x="1.5" y="1.5" width="13" height="7"/></svg>Vybraná parcela</li>
                    <li><svg viewBox="0 0 16 10" aria-hidden="true"><path class="legend-line" d="M1 8 6 2l9 3"/></svg>Hranice a čísla parcel, budovy</li>
                </ul>
            </div>
        </section>

        <footer class="sheet__foot">
            <p>Zdroj: ČÚZK – katastrální mapa (WMS) a RÚIAN<?= $sheet['ortho_url'] !== null ? ', ortofoto' : '' ?>; přehled: Základní mapa ČR.
                Informativní výřez, nenahrazuje úřední výstup z katastru nemovitostí.</p>
        </footer>
    </main>

    <script>
        document.querySelectorAll('.toolbar select').forEach((select) => {
            select.addEventListener('change', () => select.form.submit());
        });

        document.querySelectorAll('.frame img, .inset img').forEach((img) => {
            img.addEventListener('error', () => img.closest('figure').classList.add('is-broken'));
        });

        // Tisk až po načtení obrázků mapy, jinak by v PDF chyběly.
        document.querySelector('[data-print]').addEventListener('click', async () => {
            await Promise.all([...document.images].filter((img) => !img.complete).map((img) => new Promise((resolve) => {
                img.addEventListener('load', resolve, { once: true });
                img.addEventListener('error', resolve, { once: true });
            })));
            window.print();
        });
    </script>
<?php endif; ?>
</body>
</html>
