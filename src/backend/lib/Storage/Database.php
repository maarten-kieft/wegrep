<?php
declare(strict_types=1);

namespace Wegrep\Storage;

use PDO;
use Wegrep\Http\ApiException;

/**
 * The MySQL connection, opened on first use. On connecting it runs the migrations that have not run
 * yet (see Migrator), so there is no manual database setup besides an empty database and a user.
 */
final class Database
{
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
            (new Migrator())->migrate($this->pdo);
        }
        return $this->pdo;
    }
}
