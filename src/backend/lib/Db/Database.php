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
    private const MIGRATIONS = [
        1 => [
            // role NULL = registered but not approved yet.
            "CREATE TABLE users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL,
                firstname VARCHAR(100) NOT NULL,
                lastname VARCHAR(100) NOT NULL,
                role ENUM('user', 'administrator') NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY users_email (email)
            ) DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE login_codes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                code_hash CHAR(64) NOT NULL,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                KEY login_codes_user (user_id),
                CONSTRAINT login_codes_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARSET=utf8mb4",
        ],
        // The address is proven by using a login code once; administrators are told about a new
        // account only then. Accounts that existed before count as verified.
        2 => [
            'ALTER TABLE users ADD COLUMN verified_at DATETIME NULL',
            'UPDATE users SET verified_at = created_at',
        ],
        // One saved game report session per user and game. state is the app's own JSON (report, clock,
        // roster edits...); the other columns are what the session list shows without opening it.
        3 => [
            "CREATE TABLE game_sessions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                game_id INT UNSIGNED NOT NULL,
                league_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                phase VARCHAR(16) NOT NULL,
                score_home SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                score_away SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                saved_at BIGINT UNSIGNED NOT NULL,
                state LONGTEXT NOT NULL,
                UNIQUE KEY game_sessions_user_game (user_id, game_id),
                CONSTRAINT game_sessions_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARSET=utf8mb4",
        ],
        // Left behind when an administrator takes a session over: the previous owner's devices still hold a
        // copy and must be told (410) instead of silently uploading it again. Cleared when they acknowledge.
        4 => [
            "CREATE TABLE session_takeovers (
                user_id INT UNSIGNED NOT NULL,
                game_id INT UNSIGNED NOT NULL,
                taken_at BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (user_id, game_id),
                CONSTRAINT session_takeovers_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARSET=utf8mb4",
        ],
    ];

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
