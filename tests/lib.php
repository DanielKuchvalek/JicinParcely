<?php

declare(strict_types=1);

/**
 * Minimální testovací nástroje bez frameworku (spouští je tests/run.php).
 */

final class AssertionFailed extends \Exception
{
}

/** @var list<array{string, callable}> */
$GLOBALS['tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['tests'][] = [$name, $fn];
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(
            ($message !== '' ? $message . "\n" : '')
            . 'očekáváno: ' . var_export($expected, true) . "\n"
            . 'skutečnost: ' . var_export($actual, true)
        );
    }
}

function assert_near(float $expected, float $actual, float $tolerance, string $message = ''): void
{
    if (abs($expected - $actual) > $tolerance) {
        throw new AssertionFailed(
            ($message !== '' ? $message . "\n" : '')
            . "očekáváno: $expected (± $tolerance)\nskutečnost: $actual"
        );
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new AssertionFailed($message);
    }
}

function assert_throws(string $class, callable $fn): void
{
    try {
        $fn();
    } catch (\Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        throw new AssertionFailed("očekávána výjimka $class, vyhozena " . get_class($e) . ': ' . $e->getMessage());
    }
    throw new AssertionFailed("očekávána výjimka $class, nic nevyhozeno");
}

function fixture(string $name): array
{
    return json_decode((string)file_get_contents(__DIR__ . '/fixtures/' . $name), true, 512, JSON_THROW_ON_ERROR);
}

function run_tests(): int
{
    $failed = 0;
    foreach ($GLOBALS['tests'] as [$name, $fn]) {
        try {
            $fn();
            echo "  ✓ $name\n";
        } catch (\Throwable $e) {
            $failed++;
            echo "  ✗ $name\n    " . str_replace("\n", "\n    ", $e->getMessage()) . "\n";
        }
    }
    $total = count($GLOBALS['tests']);
    echo "\n" . ($total - $failed) . "/$total testů prošlo\n";

    return $failed > 0 ? 1 : 0;
}
