<?php
declare(strict_types=1);

namespace Wegrep\Db;

use PDO;
use Wegrep\Http\ApiException;

/**
 * The MySQL connection, opened on first use. The schema is kept up to date here too:
 * add a new entry to MIGRATIONS (never edit an old one) and it runs once, on the next
 * request after a deploy. No manual database setup besides an empty database and user.
 */
final class Database
{
    /** @var array<int, list<string>> version => statements */
    private const MIGRATIONS = [];

    private ?PDO $pdo = null;

    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->config['db_host'],
                $this->config['db_port'],
                $this->config['db_name']
            );
            try {
                $this->pdo = new PDO($dsn, $this->config['db_user'], $this->config['db_password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (\PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                throw new ApiException('De database is niet bereikbaar', 503);
            }
            $this->migrate($this->pdo);
        }
        return $this->pdo;
    }

    /** Run the migrations that have not run yet. */
    public function migrate(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (version INT NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL)');
        $done = (int) $pdo->query('SELECT COALESCE(MAX(version), 0) FROM migrations')->fetchColumn();
        foreach (self::MIGRATIONS as $version => $statements) {
            if ($version <= $done) {
                continue;
            }
            foreach ($statements as $sql) {
                $pdo->exec($sql);
            }
            $pdo->prepare('INSERT INTO migrations (version, applied_at) VALUES (?, NOW())')->execute([$version]);
        }
    }
}
