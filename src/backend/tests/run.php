<?php
declare(strict_types=1);

// Tiny test runner without dependencies: php tests/run.php
// Every tests/*Test.php file calls test('name', fn() => ...) with the assert helpers below.
require dirname(__DIR__) . '/lib/autoload.php';

$GLOBALS['tests'] = [];

final class SkipTest extends RuntimeException
{
}

function skip(string $why): never
{
    throw new SkipTest($why);
}

/**
 * A connection to a throw-away MySQL database for the integration tests, with empty tables.
 * Set WEGREP_TEST_DB_HOST (and _USER, _PASSWORD) to run them, e.g. against the compose db with root.
 * Without it those tests are skipped.
 */
function testDatabase(): Wegrep\Storage\Database
{
    $host = getenv('WEGREP_TEST_DB_HOST') ?: skip('WEGREP_TEST_DB_HOST not set');
    $user = getenv('WEGREP_TEST_DB_USER') ?: 'root';
    $pass = getenv('WEGREP_TEST_DB_PASSWORD') ?: '';
    $name = 'wegrep_test';
    $admin = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $admin->exec("DROP DATABASE IF EXISTS $name");
    $admin->exec("CREATE DATABASE $name CHARACTER SET utf8mb4");
    return new Wegrep\Storage\Database(['db_host' => $host, 'db_port' => 3306, 'db_name' => $name, 'db_user' => $user, 'db_password' => $pass]);
}

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
$skipped = 0;
foreach ($GLOBALS['tests'] as [$name, $fn]) {
    try {
        $fn();
        echo "ok    $name\n";
    } catch (SkipTest $e) {
        $skipped++;
        echo "skip  $name ({$e->getMessage()})\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL  $name\n      " . $e->getMessage() . "\n";
    }
}
echo "\n" . count($GLOBALS['tests']) . " tests, $failed failed, $skipped skipped\n";
exit($failed > 0 ? 1 : 0);
