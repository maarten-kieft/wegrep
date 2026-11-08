<?php
declare(strict_types=1);

namespace Wegrep;

/**
 * Club logos for the game sheet.
 *
 * hockeydata (the league data EGREP uses) does NOT carry team logos. The Dutch
 * federation site ijshockey.nl does: it keeps a club logo per team, keyed by the
 * same hockeydata team id and team name. This builds one season-wide dictionary
 *
 *     hockeydata teamId -> logo url
 *     team name         -> logo url
 *     game guid         -> {home, away} logo url
 *
 * The game-guid map is what makes the game list reliable: hockeydata sometimes
 * returns a short code ("GRO-2") instead of the real team name, but ijshockey's
 * schedule feed always carries the real logos keyed by the same game guid.
 *
 * by reading ijshockey.nl's public competition feeds, and caches it on disk for a
 * day. It is best-effort: if ijshockey.nl is unreachable the dictionary is simply
 * empty and the app shows the lettered badges instead. No secrets, no auth.
 */
final class TeamLogos
{
    public function __construct(private array $config)
    {
    }

    /**
     * @return array{season:string, byId:array<int,string>, byName:array<string,string>, byGuid:array<string,array{home:string,away:string}>, count:int}
     */
    public function dictionary(): array
    {
        if (!($this->config['logos_enabled'] ?? true)) {
            return $this->empty();
        }
        [$pathSeason] = $this->season();

        $cached = $this->readCache($pathSeason);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $dict = $this->build();
        } catch (\Throwable $e) {
            error_log('TeamLogos build failed: ' . $e->getMessage());
            $dict = $this->empty();
        }
        $this->writeCache($pathSeason, $dict);
        return $dict;
    }

    /** The logo url for a team, by hockeydata id first, then by name. */
    public static function pick(array $dict, string $name, ?int $teamId = null): ?string
    {
        if ($teamId !== null && isset($dict['byId'][$teamId])) {
            return $dict['byId'][$teamId];
        }
        $key = self::normalize($name);
        return $dict['byName'][$key] ?? null;
    }

    // --- building -------------------------------------------------------------

    private function build(): array
    {
        [$pathSeason, $paramSeason] = $this->season();
        $base = rtrim((string) ($this->config['logos_source'] ?? 'https://www.ijshockey.nl'), '/');

        // 1. division slugs on the season overview page
        $index = $this->fetch($base . '/competities/' . $pathSeason);
        preg_match_all('#/competities/' . preg_quote($pathSeason, '#') . '/([a-z0-9-]+)#i', $index, $m);
        $slugs = array_values(array_unique($m[1] ?? []));
        if (!$slugs) {
            return $this->empty();
        }

        // 2. each division page embeds its competition id as "compId":N
        $pages = $this->fetchMany(array_map(fn($s) => $base . '/competities/' . $pathSeason . '/' . $s, $slugs));
        $compIds = [];
        foreach ($pages as $html) {
            if (preg_match_all('#"compId":(\d+)#', (string) $html, $cm)) {
                foreach ($cm[1] as $id) {
                    $compIds[(int) $id] = true;
                }
            }
        }
        $compIds = array_keys($compIds);
        if (!$compIds) {
            return $this->empty();
        }

        // 3. the teams feed of each competition: {teamId, naam, logo}
        $urls = array_map(
            fn($c) => $base . '/index.php/actions/my-module/competities/teams?onderdeel=' . $c
                . '&seizoen=' . rawurlencode($paramSeason),
            $compIds
        );
        $byId = [];
        $byName = [];
        foreach ($this->fetchMany($urls) as $body) {
            $data = json_decode((string) $body, true);
            foreach ($data['teams'] ?? [] as $t) {
                $logo = (string) ($t['logo'] ?? '');
                $name = (string) ($t['naam'] ?? '');
                $id = (int) ($t['teamId'] ?? 0);
                if ($logo === '') {
                    continue;
                }
                if ($id > 0) {
                    $byId[$id] = $logo;
                }
                if ($name !== '') {
                    $byName[self::normalize($name)] = $logo;
                }
            }
        }

        // 4. the schedule feed of each competition: games keyed by guid, with logos.
        // This is the reliable join for the game list (hockeydata sometimes shows a
        // short code like "GRO-2" instead of the team name, but the guid is stable).
        $schedUrls = array_map(
            fn($c) => $base . '/index.php/actions/my-module/wedstrijden/overzicht?onderdeel=' . $c
                . '&seizoen=' . rawurlencode($paramSeason) . '&tab=all',
            $compIds
        );
        $byGuid = [];
        foreach ($this->fetchMany($schedUrls) as $body) {
            $data = json_decode((string) $body, true);
            foreach ($data['wedstrijden'] ?? [] as $w) {
                $guid = strtolower((string) ($w['gameGuid'] ?? ''));
                if ($guid === '') {
                    continue;
                }
                $home = (string) ($w['thuis']['logo'] ?? '');
                $away = (string) ($w['uit']['logo'] ?? '');
                if ($home !== '' || $away !== '') {
                    $byGuid[$guid] = ['home' => $home, 'away' => $away];
                }
            }
        }

        return [
            'season' => $pathSeason,
            'byId' => $byId,
            'byName' => $byName,
            'byGuid' => $byGuid,
            'count' => count($byId),
        ];
    }

    // --- season ---------------------------------------------------------------

    /** @return array{0:string,1:string} [pathSeason "2026-2027", paramSeason "2026/27"] */
    private function season(): array
    {
        $cfg = trim((string) ($this->config['season'] ?? ''));
        if (preg_match('#^(\d{4})-(\d{4})$#', $cfg, $m)) {
            return [$cfg, $m[1] . '/' . substr($m[2], 2)];
        }
        // Dutch ice hockey season runs Aug..Apr; before July it is still last year's.
        $y = (int) date('Y');
        $start = (int) date('n') >= 7 ? $y : $y - 1;
        $end = $start + 1;
        return [$start . '-' . $end, $start . '/' . substr((string) $end, 2)];
    }

    // --- cache ----------------------------------------------------------------

    private function cacheFile(string $pathSeason): string
    {
        $dir = ($this->config['logos_cache_dir'] ?? '') ?: (\dirname(__DIR__) . '/data/logo_cache');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            $dir = sys_get_temp_dir();
        }
        return $dir . '/logos_' . $pathSeason . '.json';
    }

    private function readCache(string $pathSeason): ?array
    {
        $ttl = (int) ($this->config['logos_cache_ttl'] ?? 86400);
        $file = $this->cacheFile($pathSeason);
        if (!is_file($file) || (time() - filemtime($file)) > $ttl) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data) || !isset($data['byId'], $data['byName'])) {
            return null;
        }
        $data['byGuid'] ??= [];
        return $data;
    }

    private function writeCache(string $pathSeason, array $dict): void
    {
        @file_put_contents($this->cacheFile($pathSeason), json_encode($dict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    // --- helpers --------------------------------------------------------------

    private function empty(): array
    {
        [$pathSeason] = $this->season();
        return ['season' => $pathSeason, 'byId' => [], 'byName' => [], 'byGuid' => [], 'count' => 0];
    }

    private static function normalize(string $name): string
    {
        $name = \mb_strtolower(trim($name), 'UTF-8');
        return (string) preg_replace('/\s+/u', ' ', $name);
    }

    private function fetch(string $url): string
    {
        return $this->fetchMany([$url])[0] ?? '';
    }

    /**
     * Fetch several URLs in parallel (curl_multi) and return the bodies in order.
     * Failures become '' rather than throwing, so one bad feed never hides the rest.
     *
     * @param list<string> $urls
     * @return array<int,string>
     */
    private function fetchMany(array $urls): array
    {
        if (!$urls) {
            return [];
        }
        $timeout = (int) ($this->config['logos_timeout'] ?? 12);
        $ua = 'wegrep/1.0 (+ice hockey game sheet)';

        if (!function_exists('curl_multi_init')) {
            $out = [];
            foreach ($urls as $i => $url) {
                $ctx = stream_context_create(['http' => [
                    'method' => 'GET', 'timeout' => $timeout, 'ignore_errors' => true,
                    'header' => "User-Agent: $ua\r\n",
                ]]);
                $out[$i] = (string) @file_get_contents($url, false, $ctx);
            }
            return $out;
        }

        $mh = curl_multi_init();
        $handles = [];
        foreach ($urls as $i => $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_USERAGENT => $ua,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$i] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        $out = [];
        foreach ($handles as $i => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $out[$i] = ($code >= 200 && $code < 400) ? (string) $body : '';
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        return $out;
    }
}
