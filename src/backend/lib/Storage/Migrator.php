<?php
declare(strict_types=1);

namespace Wegrep\Storage;

use PDO;

/**
 * Keeps the database schema up to date. Every file in Migrations/ is one migration, named
 * NNNN_description.php and returning a list of SQL statements. A migration runs once, in number
 * order, on the first request after a deploy; the numbers that ran are kept in the migrations table.
 * Never edit a migration that has been deployed: add a new one.
 */
final class Migrator
{
    public function __construct(private readonly string $directory = __DIR__ . '/Migrations')
    {
    }

    /** @return list<int> the migrations that were run now */
    public function migrate(PDO $pdo): array
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (version INT NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL)');
        $done = (int) $pdo->query('SELECT COALESCE(MAX(version), 0) FROM migrations')->fetchColumn();

        $ran = [];
        foreach ($this->migrations() as $version => $file) {
            if ($version <= $done) {
                continue;
            }
            foreach (require $file as $sql) {
                $pdo->exec($sql);
            }
            $pdo->prepare('INSERT INTO migrations (version, applied_at) VALUES (?, NOW())')->execute([$version]);
            $ran[] = $version;
        }
        return $ran;
    }

    /** @return array<int, string> version => file, in order */
    public function migrations(): array
    {
        $found = [];
        foreach (glob($this->directory . '/*.php') ?: [] as $file) {
            if (preg_match('/^(\d+)_/', basename($file), $m)) {
                $found[(int) $m[1]] = $file;
            }
        }
        ksort($found);
        return $found;
    }
}
