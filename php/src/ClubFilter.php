<?php
declare(strict_types=1);

namespace Wegrep;

/**
 * Optional club focus: when enabled, the app only shows the leagues and games of
 * one or more configured clubs (e.g. "Blue Mountain Cougars").
 *
 * Teams are matched by name (substring, case-insensitive). Because hockeydata
 * sometimes returns a short code ("HOO-5") instead of the team name, the filter
 * also looks up the real names from the ijshockey schedule (TeamLogos' schedule
 * index, keyed by game guid). If that schedule isn't available yet the filter
 * fails open (shows everything) rather than hiding the whole app.
 */
final class ClubFilter
{
    /** @var list<string> lowercased club name fragments */
    private array $clubs;
    private bool $enabled;
    /** @var list<int> league ids kept even while filtering (the demo league) */
    private array $demoLeagues;

    public function __construct(array $config)
    {
        $this->clubs = array_values(array_filter(array_map(
            fn($c) => mb_strtolower(trim((string) $c), 'UTF-8'),
            $config['club_filter'] ?? []
        )));
        $this->enabled = (bool) ($config['club_filter_enabled'] ?? false) && $this->clubs !== [];
        $this->demoLeagues = ($config['demo_league_enabled'] ?? false)
            ? array_values(array_map('intval', $config['demo_leagues'] ?? []))
            : [];
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /** True for a league that should stay visible despite the club filter. */
    public function isDemoLeague(int $leagueId): bool
    {
        return in_array($leagueId, $this->demoLeagues, true);
    }

    public function matches(string $teamName): bool
    {
        if ($teamName === '') {
            return false;
        }
        $n = mb_strtolower($teamName, 'UTF-8');
        foreach ($this->clubs as $c) {
            if (str_contains($n, $c)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Keep only the leagues where the club plays. $schedule is TeamLogos'
     * guid => [home, away, league] index, or null when not cached yet.
     *
     * @param list<array{id:int,name:string}> $leagues
     * @param array<string,array{home:string,away:string,league:int}>|null $schedule
     */
    public function filterLeagues(array $leagues, ?array $schedule): array
    {
        if (!$this->enabled || $schedule === null) {
            return $leagues;
        }
        $allowed = [];
        foreach ($this->demoLeagues as $id) {
            $allowed[$id] = true; // always keep the demo league when it is turned on
        }
        foreach ($schedule as $g) {
            if ($this->matches((string) ($g['home'] ?? '')) || $this->matches((string) ($g['away'] ?? ''))) {
                $allowed[(int) ($g['league'] ?? 0)] = true;
            }
        }
        if (!$allowed) {
            return $leagues; // nothing to filter on: fail open
        }
        return array_values(array_filter($leagues, fn($l) => isset($allowed[(int) $l['id']])));
    }

    /**
     * Keep only the games the club plays in, by real name (via guid) then by the
     * name hockeydata returned.
     *
     * @param list<array<string,mixed>> $games
     * @param array<string,array{home:string,away:string,league:int}> $schedule
     */
    public function filterGames(array $games, array $schedule, int $leagueId = 0): array
    {
        if (!$this->enabled || $this->isDemoLeague($leagueId)) {
            return $games; // demo league: show every (dummy) game
        }
        return array_values(array_filter($games, function ($g) use ($schedule) {
            $s = $schedule[(string) ($g['guid'] ?? '')] ?? null;
            if ($s && ($this->matches((string) ($s['home'] ?? '')) || $this->matches((string) ($s['away'] ?? '')))) {
                return true;
            }
            return $this->matches((string) ($g['home'] ?? '')) || $this->matches((string) ($g['away'] ?? ''));
        }));
    }
}
