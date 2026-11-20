<?php
declare(strict_types=1);

namespace Wegrep\Http\Controllers;

use Wegrep\Http\ApiException;
use Wegrep\GameReport\GameReport;
use Wegrep\GameReport\HdCrypt;
use Wegrep\HockeyData\HockeyDataClient;

final class GameController
{
    public function __construct(private readonly HockeyDataClient $client, private readonly array $config)
    {
    }

    /** GET /games/{id}: game report template + parsed report */
    public function show(string $gameId): array
    {
        $data = $this->client->emptyGameReport((int) $gameId);
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

    /** GET /games/{id}/precheck?homeId=&awayId=: server status of the game before uploading */
    public function precheck(string $gameId): array
    {
        $home = (int) ($_GET['homeId'] ?? 0);
        $away = (int) ($_GET['awayId'] ?? 0);
        return $this->client->precheck((int) $gameId, $home, $away);
    }

    /** POST /games/{id}/preview: build XML + encrypted payload, nothing is sent */
    public function preview(string $gameId): array
    {
        [$xml, $payload] = $this->buildReport($this->input());
        return ['xml' => $xml, 'payload' => $payload];
    }

    /** POST /games/{id}/upload: send the report (only when upload_enabled) */
    public function upload(string $gameId): array
    {
        $input = $this->input();
        [, $payload] = $this->buildReport($input);
        if (!$this->config['upload_enabled']) {
            throw new ApiException('Uploaden staat uit (upload_enabled in config.php)', 403);
        }
        $status = (int) ($input['status'] ?? 1);
        if (!in_array($status, [1, 3], true)) {
            throw new ApiException('status moet 1 (live) of 3 (definitief) zijn');
        }
        return $this->client->upload(
            (int) $gameId,
            $payload,
            $status,
            (string) ($input['username'] ?? ''),
            (string) ($input['blueCode'] ?? ''),
            (string) ($input['redCode'] ?? '')
        );
    }

    private function input(): array
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['templateXml']) || !is_array($input['state'] ?? null)) {
            throw new ApiException('templateXml en state zijn verplicht');
        }
        return $input;
    }

    /** @return array{string, string} the report XML and the [HD] payload */
    private function buildReport(array $input): array
    {
        $xml = GameReport::build($input['templateXml'], $input['state']);
        return [$xml, HdCrypt::encode($xml)];
    }
}
