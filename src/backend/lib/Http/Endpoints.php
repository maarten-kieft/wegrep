<?php
declare(strict_types=1);

namespace Wegrep\Http;

use Wegrep\Auth\AdminService;
use Wegrep\Auth\Authenticator;
use Wegrep\Auth\AuthService;
use Wegrep\HockeyData\HockeyDataClient;
use Wegrep\Controllers\AdminController;
use Wegrep\Controllers\AuthController;
use Wegrep\Controllers\GameController;
use Wegrep\Controllers\LeagueController;
use Wegrep\Controllers\MetaController;
use Wegrep\Controllers\SessionController;
use Wegrep\Mail\Mailer;
use Wegrep\Sessions\SessionService;
use Wegrep\Storage\Database;
use Wegrep\Storage\UserStore;

/**
 * All the endpoints (controllers), wired up with what they need. This is the one place that knows how the
 * parts fit together. Nothing connects to the database or the network until a request needs it.
 */
final class Endpoints
{
    public function __construct(
        public readonly Authenticator $authenticator,
        public readonly MetaController $meta,
        public readonly AuthController $auth,
        public readonly AdminController $admin,
        public readonly SessionController $sessions,
        public readonly LeagueController $leagues,
        public readonly GameController $games,
    ) {
    }

    public static function create(array $config, string $root): self
    {
        $db = new Database($config);
        $users = new UserStore($db);
        $authenticator = new Authenticator($users, $config['jwt_secret']);
        $sessionService = new SessionService($db);
        $client = new HockeyDataClient($config);

        return new self(
            $authenticator,
            new MetaController($config, $root),
            new AuthController(new AuthService($users, new Mailer($config), $config), $authenticator),
            new AdminController(new AdminService($users), $authenticator, $sessionService),
            new SessionController($sessionService, $authenticator),
            new LeagueController($client, $config),
            new GameController($client, $config),
        );
    }
}
