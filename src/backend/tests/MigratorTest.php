<?php
declare(strict_types=1);

use Wegrep\Storage\Migrator;

test('migration files are numbered in order without gaps or duplicates', function () {
    $versions = array_keys((new Migrator())->migrations());
    assertSame(range(1, count($versions)), $versions);
});

test('every migration file returns a list of SQL statements', function () {
    foreach ((new Migrator())->migrations() as $file) {
        $statements = require $file;
        assertTrue(is_array($statements) && $statements !== [], basename($file));
        foreach ($statements as $sql) {
            assertTrue(is_string($sql) && trim($sql) !== '', basename($file));
        }
    }
});

test('migrating an empty database runs everything once and a second time nothing', function () {
    $pdo = testDatabase()->pdo(); // opening it migrates
    $migrator = new Migrator();
    assertSame(array_keys($migrator->migrations()), array_map('intval', $pdo->query('SELECT version FROM migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN)));
    assertSame([], $migrator->migrate($pdo));
    foreach (['users', 'login_codes', 'game_sessions', 'session_takeovers'] as $table) {
        assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$table'")->fetchColumn(), $table);
    }
});
