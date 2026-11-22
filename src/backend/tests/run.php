<?php
declare(strict_types=1);

// Tiny test runner without dependencies: php tests/run.php
// Every tests/*Test.php file calls test('name', fn() => ...) with the assert helpers below.
require dirname(__DIR__) . '/lib/autoload.php';

$GLOBALS['tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['tests'][] = [$name, $fn];
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            ($message !== '' ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

function assertTrue(mixed $actual, string $message = ''): void
{
    assertSame(true, $actual, $message);
}

function assertThrows(string $class, callable $fn): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        if (!$e instanceof $class) {
            throw new RuntimeException("expected $class, got " . $e::class . ': ' . $e->getMessage());
        }
        return $e;
    }
    throw new RuntimeException("expected $class to be thrown");
}

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    require $file;
}

$failed = 0;
foreach ($GLOBALS['tests'] as [$name, $fn]) {
    try {
        $fn();
        echo "ok    $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL  $name\n      " . $e->getMessage() . "\n";
    }
}
echo "\n" . count($GLOBALS['tests']) . ' tests, ' . $failed . " failed\n";
exit($failed > 0 ? 1 : 0);
