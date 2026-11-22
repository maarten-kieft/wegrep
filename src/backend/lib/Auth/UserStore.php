<?php
declare(strict_types=1);

namespace Wegrep\Auth;

use PDO;
use Wegrep\Db\Database;

/** Users and their one-time login codes. All times are UTC. */
final class UserStore
{
    public function __construct(private readonly Database $db)
    {
    }

    private function pdo(): PDO
    {
        return $this->db->pdo();
    }

    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->one('SELECT * FROM users WHERE email = ?', [$email]);
    }

    /** @return list<array> */
    public function all(): array
    {
        return $this->pdo()->query('SELECT * FROM users ORDER BY created_at DESC, id DESC')->fetchAll();
    }

    /** Administrators, which are the ones who get a mail about new registrations. */
    public function administrators(): array
    {
        return $this->pdo()->query("SELECT * FROM users WHERE role = 'administrator' ORDER BY id")->fetchAll();
    }

    public function insert(string $email, string $firstname, string $lastname, ?string $role, int $now): array
    {
        $this->pdo()->prepare('INSERT INTO users (email, firstname, lastname, role, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$email, $firstname, $lastname, $role, self::time($now)]);
        return $this->find((int) $this->pdo()->lastInsertId());
    }

    public function setRole(int $id, ?string $role): void
    {
        $this->pdo()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
    }

    public function markVerified(int $id, int $now): void
    {
        $this->pdo()->prepare('UPDATE users SET verified_at = ? WHERE id = ?')->execute([self::time($now), $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    /** Replace any open code of this user by a new one. */
    public function replaceCode(int $userId, string $codeHash, int $now, int $expiresAt): void
    {
        $this->invalidateCodes($userId, $now);
        $this->pdo()->prepare('INSERT INTO login_codes (user_id, code_hash, created_at, expires_at) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $codeHash, self::time($now), self::time($expiresAt)]);
    }

    public function lastCodeCreatedAt(int $userId): ?int
    {
        $value = $this->pdo()->prepare('SELECT MAX(created_at) FROM login_codes WHERE user_id = ?');
        $value->execute([$userId]);
        $at = $value->fetchColumn();
        return $at ? strtotime($at . ' UTC') : null;
    }

    /** The code that can still be used: not used yet and not expired. */
    public function openCode(int $userId, int $now): ?array
    {
        return $this->one(
            'SELECT * FROM login_codes WHERE user_id = ? AND used_at IS NULL AND expires_at > ? ORDER BY id DESC LIMIT 1',
            [$userId, self::time($now)]
        );
    }

    public function countAttempt(int $codeId): void
    {
        $this->pdo()->prepare('UPDATE login_codes SET attempts = attempts + 1 WHERE id = ?')->execute([$codeId]);
    }

    public function invalidateCodes(int $userId, int $now): void
    {
        $this->pdo()->prepare('UPDATE login_codes SET used_at = ? WHERE user_id = ? AND used_at IS NULL')
            ->execute([self::time($now), $userId]);
    }

    private function one(string $sql, array $params): ?array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    private static function time(int $t): string
    {
        return gmdate('Y-m-d H:i:s', $t);
    }
}
