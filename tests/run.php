<?php

declare(strict_types=1);

/**
 * Spustí všechny testy z tests/*Test.php:  php tests/run.php
 * Testy nesahají na síť; živé služby ČÚZK ověřuje tests/live.php.
 */

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/lib.php';

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    require $file;
}

exit(run_tests());
