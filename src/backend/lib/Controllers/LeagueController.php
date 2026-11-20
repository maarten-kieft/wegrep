<?php
declare(strict_types=1);

namespace Wegrep\Controllers;

use Wegrep\ApiException;
use Wegrep\ClubFilter;
use Wegrep\HockeyDataClient;
use Wegrep\TeamLogos;

final class LeagueController
{
    private const SELECTIONS = ['TODAY', 'TODAY_FUTURE', 'ALL', 'TODAY_YESTERDAY', 'TODAY_2DAYSBEFORE'];

    public function __construct(private readonly HockeyDataClient $client, private readonly array $config)
    {
    }

    /** GET /leagues */
    public function leagues(): array
    {
        $filter = new ClubFilter($this->config);
        // Use the cached schedule only (never block the landing page on a crawl);
        // when it isn't warm yet the filter fails open and shows all leagues.
        $schedule = $filter->enabled() ? ((new TeamLogos($this->config))->cachedDictionary()['schedule'] ?? null) : null;
        return $filter->filterLeagues($this->client->leagues(), $schedule);
    }

    /** GET /leagues/{id}/games?selection=ALL|TODAY|... */
    public function games(string $leagueId): array
    {
        $selection = $_GET['selection'] ?? 'ALL';
        if (!in_array($selection, self::SELECTIONS, true)) {
            throw new ApiException('Onbekende selectie');
        }
        $leagueId = (int) $leagueId;
        $games = $this->client->games($leagueId, $selection);
        $filter = new ClubFilter($this->config);
        if ($filter->enabled() && !$filter->isDemoLeague($leagueId)) {
            $schedule = (new TeamLogos($this->config))->dictionary()['schedule'] ?? [];
            $games = $filter->filterGames($games, $schedule, $leagueId);
        }
        return $games;
    }
}
