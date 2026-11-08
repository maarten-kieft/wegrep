<?php
declare(strict_types=1);

/**
 * JSON API for the web app. Routes (all under /api):
 *
 *   GET  /meta                    penalty codes, upload on/off, whether a password is needed
 *   GET  /leagues                 list of leagues
 *   GET  /leagues/{id}/games      games of a league (?selection=ALL|TODAY|...)
 *   GET  /games/{id}              game report template + parsed report
 *   POST /games/{id}/preview      build XML + encrypted payload, nothing is sent
 *   GET  /games/{id}/precheck     server status of the game before uploading
 *   POST /games/{id}/upload       send the report (only when upload_enabled)
 */

use Wegrep\ApiException;
use Wegrep\GameReport;
use Wegrep\HdCrypt;
use Wegrep\HockeyDataClient;

$root = dirname(__DIR__, 2);
spl_autoload_register(function (string $class) use ($root) {
    if (str_starts_with($class, 'Wegrep\\')) {
        require $root . '/src/' . substr($class, 7) . '.php';
    }
});

$config = require $root . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = '/' . trim(preg_replace('#^.*?/api(?=/|$)#', '', $path), '/');

    if ($path !== '/meta' && $config['app_password'] !== ''
        && !hash_equals($config['app_password'], $_SERVER['HTTP_X_APP_PASSWORD'] ?? '')) {
        throw new ApiException('Wachtwoord onjuist', 401);
    }

    $client = new HockeyDataClient($config);
    $result = route($method, $path, $client, $config, $root);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (ApiException $e) {
    http_response_code($e->status);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log((string) $e);
    echo json_encode(['error' => $e->getMessage()]);
}

function route(string $method, string $path, HockeyDataClient $client, array $config, string $root): mixed
{
    if ($method === 'GET' && $path === '/meta') {
        return [
            'uploadEnabled' => (bool) $config['upload_enabled'],
            'passwordRequired' => $config['app_password'] !== '',
            'penaltyTypes' => json_decode(file_get_contents($root . '/data/penalty_types.json'), true),
        ];
    }
    if ($method === 'GET' && $path === '/leagues') {
        return $client->leagues();
    }
    if ($method === 'GET' && preg_match('#^/leagues/(\d+)/games$#', $path, $m)) {
        $selection = $_GET['selection'] ?? 'ALL';
        if (!in_array($selection, ['TODAY', 'TODAY_FUTURE', 'ALL', 'TODAY_YESTERDAY', 'TODAY_2DAYSBEFORE'], true)) {
            throw new ApiException('Onbekende selectie');
        }
        return $client->games((int) $m[1], $selection);
    }
    if (preg_match('#^/games/(\d+)(?:/(\w+))?$#', $path, $m)) {
        $gameId = (int) $m[1];
        $action = $m[2] ?? '';

        if ($method === 'GET' && $action === '') {
            $data = $client->emptyGameReport($gameId);
            $xml = $data['XmlData'] ?? '';
            if ($xml === '') {
                throw new ApiException('Geen wedstrijdverslag gevonden', 404);
            }
            return [
                'templateXml' => $xml,
                'report' => GameReport::parse($xml),
                'gameRuleset' => $data['GameRuleset'] ?? null,
                'penaltyRuleset' => $data['PenaltyRuleset'] ?? null,
            ];
        }
        if ($method === 'GET' && $action === 'precheck') {
            $home = (int) ($_GET['homeId'] ?? 0);
            $away = (int) ($_GET['awayId'] ?? 0);
            return $client->precheck($gameId, $home, $away);
        }
        if ($method === 'POST' && in_array($action, ['preview', 'upload'], true)) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input) || empty($input['templateXml']) || !is_array($input['state'] ?? null)) {
                throw new ApiException('templateXml en state zijn verplicht');
            }
            $xml = GameReport::build($input['templateXml'], $input['state']);
            $payload = HdCrypt::encode($xml);

            if ($action === 'preview') {
                return ['xml' => $xml, 'payload' => $payload];
            }
            if (!$config['upload_enabled']) {
                throw new ApiException('Uploaden staat uit (upload_enabled in config.php)', 403);
            }
            $status = (int) ($input['status'] ?? 1);
            if (!in_array($status, [1, 3], true)) {
                throw new ApiException('status moet 1 (live) of 3 (definitief) zijn');
            }
            return $client->upload(
                $gameId,
                $payload,
                $status,
                (string) ($input['username'] ?? ''),
                (string) ($input['blueCode'] ?? ''),
                (string) ($input['redCode'] ?? '')
            );
        }
    }
    throw new ApiException('Niet gevonden', 404);
}
