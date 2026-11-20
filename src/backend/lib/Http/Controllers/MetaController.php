<?php
declare(strict_types=1);

namespace Wegrep\Http\Controllers;

use Wegrep\Clubs\IjshockeyFeed;

final class MetaController
{
    public function __construct(private readonly array $config, private readonly string $root)
    {
    }

    /** GET /meta: penalty codes, upload on/off, whether a password is needed */
    public function meta(): array
    {
        return [
            'uploadEnabled' => (bool) $this->config['upload_enabled'],
            'passwordRequired' => $this->config['app_password'] !== '',
            'penaltyTypes' => json_decode(file_get_contents($this->root . '/data/penalty_types.json'), true),
        ];
    }

    /** GET /logos: club logos by hockeydata team id and by team name */
    public function logos(): array
    {
        header('Cache-Control: public, max-age=3600');
        $dict = (new IjshockeyFeed($this->config))->dictionary();
        unset($dict['schedule']); // server-only (club filter); not needed by the app
        return $dict;
    }
}
