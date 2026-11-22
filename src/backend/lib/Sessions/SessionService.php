<?php
declare(strict_types=1);

namespace Wegrep\Sessions;

use Wegrep\Db\Database;
use Wegrep\Http\ApiException;

/**
 * Saved game report sessions: the database copy of what the app keeps in the browser, so a game can be
 * picked up on another device. A user has at most one session per game and only ever sees their own.
 * The newest save (by the savedAt time the app puts on it) wins.
 */
final class SessionService
{
    private const MAX_STATE_BYTES = 4_000_000;

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array> summaries of this user's sessions, newest first */
    public function list(int $userId): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT game_id, league_id, title, phase, score_home, score_away, saved_at FROM game_sessions WHERE user_id = ? ORDER BY saved_at DESC'
        );
        $stmt->execute([$userId]);
        return array_map(self::summary(...), $stmt->fetchAll());
    }

    /**
     * Everybody's sessions, for the administrators, newest first.
     *
     * @return list<array>
     */
    public function listAll(): array
    {
        $rows = $this->db->pdo()->query(
            'SELECT s.game_id, s.league_id, s.title, s.phase, s.score_home, s.score_away, s.saved_at, u.id AS user_id, u.firstname, u.lastname, u.email
             FROM game_sessions s JOIN users u ON u.id = s.user_id ORDER BY s.saved_at DESC'
        )->fetchAll();
        return array_map(static fn(array $r): array => self::summary($r) + [
            'user' => ['id' => (int) $r['user_id'], 'firstname' => $r['firstname'], 'lastname' => $r['lastname'], 'email' => $r['email']],
        ], $rows);
    }

    /** The full session, or null. */
    public function get(int $userId, int $gameId): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM game_sessions WHERE user_id = ? AND game_id = ?');
        $stmt->execute([$userId, $gameId]);
        $row = $stmt->fetch();
        return $row ? self::summary($row) + ['state' => json_decode($row['state'], true)] : null;
    }

    /**
     * Save a session. Refused with 409 when what is stored is newer (it was saved from another device).
     *
     * @param array{leagueId?: mixed, title?: mixed, phase?: mixed, scoreHome?: mixed, scoreAway?: mixed, savedAt?: mixed, state?: mixed} $in
     */
    public function put(int $userId, int $gameId, array $in): array
    {
        $savedAt = (int) ($in['savedAt'] ?? 0);
        if ($gameId <= 0 || $savedAt <= 0 || !is_array($in['state'] ?? null) || (int) ($in['leagueId'] ?? 0) <= 0) {
            throw new ApiException('leagueId, savedAt en state zijn verplicht');
        }
        $state = json_encode($in['state'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($state) > self::MAX_STATE_BYTES) {
            throw new ApiException('De sessie is te groot om op te slaan', 413);
        }
        if ($this->isTakenOver($userId, $gameId)) {
            throw new ApiException('Deze sessie is overgenomen door een beheerder', 410);
        }
        $pdo = $this->db->pdo();
        $row = [
            $userId, $gameId, (int) $in['leagueId'], mb_substr((string) ($in['title'] ?? ''), 0, 255),
            mb_substr((string) ($in['phase'] ?? ''), 0, 16), max(0, (int) ($in['scoreHome'] ?? 0)), max(0, (int) ($in['scoreAway'] ?? 0)),
            $savedAt, $state,
        ];
        // Lock the stored row while we compare, so two devices saving at once cannot both win.
        $pdo->beginTransaction();
        try {
            $current = $pdo->prepare('SELECT saved_at FROM game_sessions WHERE user_id = ? AND game_id = ? FOR UPDATE');
            $current->execute([$userId, $gameId]);
            $stored = $current->fetchColumn();
            if ($stored !== false && (int) $stored > $savedAt) {
                $pdo->rollBack();
                throw new ApiException('Er is een nieuwere versie van deze sessie opgeslagen', 409);
            }
            $pdo->prepare(
                'INSERT INTO game_sessions (user_id, game_id, league_id, title, phase, score_home, score_away, saved_at, state)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE league_id = VALUES(league_id), title = VALUES(title), phase = VALUES(phase),
                   score_home = VALUES(score_home), score_away = VALUES(score_away), saved_at = VALUES(saved_at), state = VALUES(state)'
            )->execute($row);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'savedAt' => $savedAt];
    }

    /** Whether an administrator took this session over from the user and the user has not acknowledged that yet. */
    public function isTakenOver(int $userId, int $gameId): bool
    {
        $stmt = $this->db->pdo()->prepare('SELECT 1 FROM session_takeovers WHERE user_id = ? AND game_id = ?');
        $stmt->execute([$userId, $gameId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Move a session from its owner to an administrator. The owner loses it (and is told by a 410 the next
     * time one of their devices syncs it); a session the administrator already has for the same game is replaced.
     */
    public function takeOver(int $adminId, int $userId, int $gameId, int $nowMs): array
    {
        if ($adminId === $userId) {
            throw new ApiException('Dit is al je eigen sessie', 400);
        }
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $find = $pdo->prepare('SELECT id FROM game_sessions WHERE user_id = ? AND game_id = ? FOR UPDATE');
            $find->execute([$userId, $gameId]);
            $id = $find->fetchColumn();
            if ($id === false) {
                throw new ApiException('Sessie niet gevonden', 404);
            }
            $pdo->prepare('DELETE FROM game_sessions WHERE user_id = ? AND game_id = ?')->execute([$adminId, $gameId]);
            $pdo->prepare('UPDATE game_sessions SET user_id = ? WHERE id = ?')->execute([$adminId, $id]);
            $pdo->prepare('INSERT INTO session_takeovers (user_id, game_id, taken_at) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE taken_at = VALUES(taken_at)')->execute([$userId, $gameId, $nowMs]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return $this->get($adminId, $gameId);
    }

    /** Delete the user's session. Also how a device acknowledges that the session was taken over. */
    public function delete(int $userId, int $gameId): void
    {
        $pdo = $this->db->pdo();
        $pdo->prepare('DELETE FROM game_sessions WHERE user_id = ? AND game_id = ?')->execute([$userId, $gameId]);
        $pdo->prepare('DELETE FROM session_takeovers WHERE user_id = ? AND game_id = ?')->execute([$userId, $gameId]);
    }

    private static function summary(array $row): array
    {
        return [
            'gameId' => (int) $row['game_id'],
            'leagueId' => (int) $row['league_id'],
            'title' => $row['title'],
            'phase' => $row['phase'],
            'scoreHome' => (int) $row['score_home'],
            'scoreAway' => (int) $row['score_away'],
            'savedAt' => (int) $row['saved_at'],
        ];
    }
}
