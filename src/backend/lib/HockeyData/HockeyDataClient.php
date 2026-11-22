<?php
declare(strict_types=1);

namespace Wegrep\HockeyData;

use Wegrep\Http\ApiException;

/**
 * Client for the hockeydata LOS6 API, mirroring the calls EGREP makes.
 * Base URL: https://los.hockeydata.net/los/data-asp/api
 */
final class HockeyDataClient
{
    public function __construct(private array $config)
    {
    }

    /** @return list<array{id:int,name:string}> */
    public function leagues(): array
    {
        $bytes = $this->get('GetListOfAllLeagues', [
            'customer' => $this->config['customer'],
            'password' => '',
        ]);
        $container = NrbfReader::decode($bytes);
        return array_map(
            fn($l) => ['id' => (int) $l['Id'], 'name' => trim((string) $l['DisplayText'])],
            $container['Liste'] ?? []
        );
    }

    /** selection: TODAY, TODAY_FUTURE, ALL, TODAY_YESTERDAY, TODAY_2DAYSBEFORE */
    public function games(int $leagueId, string $selection = 'ALL', string $password = '', string $specificDate = ''): array
    {
        $body = $this->get('GetAllGamesOfLeagueAsJson', [
            'leagueId' => (string) $leagueId,
            'password' => $password !== '' ? $password : $this->config['league_password'],
            'debugStatusLevel' => '2',
            'selectionOption' => $selection,
            'specificDate' => $specificDate,
        ]);
        if (str_contains($body, '!!ERROR!!')) {
            // e.g. "!!ERROR!!~Permission denied..." when the league password is wrong
            throw new ApiException(explode('~', $body)[1] ?? 'hockeydata error', 403);
        }
        return array_map(fn($g) => [
            'id' => (int) $g['GameId'],
            'guid' => strtolower((string) ($g['GameGuid'] ?? '')),
            'name' => $g['Gamename'] ?? '',
            'division' => $g['DivisionName'] ?? '',
            'status' => (int) ($g['GameStatus'] ?? 0),
            'location' => $g['LocationName'] ?? '',
            'scheduled' => $g['ScheduledDate'] ?? null,
            'home' => $g['HomeTeamname'] ?? '',
            'away' => $g['AwayTeamname'] ?? '',
            'homeRosterPublic' => (bool) ($g['HomeRosterIsPublic'] ?? false),
            'awayRosterPublic' => (bool) ($g['AwayRosterIsPublic'] ?? false),
            'downloadable' => (bool) ($g['IsEgrepDownloadable'] ?? false),
        ], $this->json($body));
    }

    /**
     * The game report template with rosters. The IJS design (Netherlands) uses the
     * "WithDpLineup" variant when shift mode is off, like EGREP does.
     */
    public function emptyGameReport(int $gameId): array
    {
        return $this->json($this->get('GetEmptyGameReportWithDpLineupForEgrep', [
            'theCurrentGameId' => (string) $gameId,
            'homeIsRegular' => 'True',
            'awayIsRegular' => 'True',
        ]));
    }

    /** A report that was uploaded before (status 3 = completed). */
    public function existingGameReport(int $gameId, int $status = 3): ?array
    {
        $body = $this->get('GetGameReportForEgrep', [
            'gameId' => (string) $gameId,
            'password' => '',
            'status' => (string) $status,
        ]);
        return trim($body) === '' ? null : $this->json($body);
    }

    /**
     * Everybody registered with the club of this team who can still be added to the game (EGREP's
     * "Last minute selectie-update"): the extended lists of players and team officials.
     *
     * @return array{players: list<array>, officials: list<array>}
     */
    public function eligible(int $gameId, int $teamId): array
    {
        $form = http_build_query(['gameId' => $gameId, 'teamId' => $teamId]);
        $players = $this->json($this->request('POST', $this->url('ExtendedEligiblePlayers'), $form, 'application/x-www-form-urlencoded'));
        $officials = $this->json($this->request('POST', $this->url('ExtendedEligibleTeamOfficials'), $form, 'application/x-www-form-urlencoded'));

        return [
            'players' => array_map(static fn(array $p): array => [
                'id' => (string) $p['id'],
                'lastname' => (string) ($p['lastname'] ?? ''),
                'firstname' => (string) ($p['firstname'] ?? ''),
                'prefix' => (string) ($p['middlename'] ?? ''),
                'birthdate' => substr((string) ($p['birthdate'] ?? ''), 0, 10),
                'licensenr' => (string) ($p['licenseNr'] ?? ''),
                'position' => (string) ($p['position'] ?? ''),
                'suspended' => (bool) ($p['isSuspended'] ?? false),
            ], $players),
            'officials' => array_map(static fn(array $o): array => [
                'id' => (string) $o['id'],
                'lastname' => (string) ($o['lastname'] ?? ''),
                'firstname' => (string) ($o['firstname'] ?? ''),
                'prefix' => (string) ($o['middlename'] ?? ''),
                'birthdate' => substr((string) ($o['birthdate'] ?? ''), 0, 10),
                'job' => (string) ((json_decode((string) ($o['additionalData'] ?? ''), true) ?: [])['job'] ?? ''),
            ], $officials),
        ];
    }

    public function precheck(int $gameId, int $homeId, int $awayId): array
    {
        return $this->json($this->get('CheckGameOkStatusBeforeUpload', [
            'gameId' => (string) $gameId,
            'homeId' => (string) $homeId,
            'awayId' => (string) $awayId,
        ]));
    }

    /**
     * Uploads the report. status 1 = live update, 3 = final (completed).
     * The body is built exactly like EGREP does it: the values are NOT url-encoded (a capture of a real EGREP
     * upload confirms this), and the fields come in the same order, including errors=null.
     *
     * @return array{Status:string,SmsReturnCode:string}
     */
    public function upload(int $gameId, string $payload, int $status, string $username, string $blueCode = '', string $redCode = ''): array
    {
        $response = $this->request('POST', $this->url('ReceiveUploadedGameReport'), self::uploadBody(
            $gameId, $payload, $status, $username, $this->config['egrep_version'], $blueCode, $redCode
        ), 'application/x-www-form-urlencoded');
        $json = $this->json($response);
        return ['Status' => (string) ($json['Status'] ?? ''), 'SmsReturnCode' => (string) ($json['SmsReturnCode'] ?? '')];
    }

    public static function uploadBody(int $gameId, string $payload, int $status, string $username, string $egrepVersion, string $blueCode = '', string $redCode = ''): string
    {
        return "gameId={$gameId}&serializedData={$payload}&status={$status}&uploadUsername={$username}"
            . "&egrepVersion={$egrepVersion}&blueCode={$blueCode}&redCode={$redCode}&errors=null&jsonEnabled=1";
    }

    private function get(string $method, array $query): string
    {
        return $this->request('GET', $this->url($method) . '?' . http_build_query($query));
    }

    private function url(string $method): string
    {
        return rtrim($this->config['api_base'], '/') . '/' . $method;
    }

    private function json(string $body): array
    {
        $data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $body), true);
        if (!is_array($data)) {
            error_log('hockeydata sent something that is not JSON: ' . substr($body, 0, 1000));
            throw new ApiException('Unexpected response from hockeydata', 502);
        }
        return $data;
    }

    private function request(string $method, string $url, ?string $body = null, ?string $contentType = null): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $contentType ? ["Content-Type: $contentType"] : [],
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $result = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create(['http' => [
                'method' => $method,
                'header' => $contentType ? "Content-Type: $contentType\r\n" : '',
                'content' => $body ?? '',
                'timeout' => 30,
                'ignore_errors' => true,
            ]]);
            $result = @file_get_contents($url, false, $context);
            $code = 0;
            foreach ($http_response_header ?? [] as $h) {
                if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) {
                    $code = (int) $m[1];
                }
            }
            $error = $result === false ? 'request failed' : '';
        }
        if (!empty($this->config['debug_hockeydata'])) {
            error_log("hockeydata >>> $method $url" . ($contentType ? "\nContent-Type: $contentType" : '') . ($body !== null ? "\n\n$body" : ''));
            error_log("hockeydata <<< $code $error\n\n" . substr((string) $result, 0, 5000));
        }
        if ($result === false || $code >= 400 || $code === 0) {
            // What hockeydata actually answered, for the server log (the app only gets the short message).
            error_log("hockeydata $method $url answered $code $error: " . substr((string) $result, 0, 1000));
            throw new ApiException("hockeydata request failed ($code $error)", 502);
        }
        return $result;
    }
}
