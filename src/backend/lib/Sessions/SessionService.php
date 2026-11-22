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

    public function delete(int $userId, int $gameId): void
    {
        $this->db->pdo()->prepare('DELETE FROM game_sessions WHERE user_id = ? AND game_id = ?')->execute([$userId, $gameId]);
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
