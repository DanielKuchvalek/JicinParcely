<?php

declare(strict_types=1);

/**
 * Autoloader tříd z namespace JicinParcely (soubor src/<Třída>.php), bez Composeru.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'JicinParcely\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/' . substr($class, strlen($prefix)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
