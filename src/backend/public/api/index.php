<?php
declare(strict_types=1);

/**
 * JSON API for the web app. All requests under /api end up here (see .htaccess);
 * the routes below map them to the controllers in lib/Http/Controllers.
 */

use Wegrep\Auth\AdminService;
use Wegrep\Auth\AuthService;
use Wegrep\Auth\Authenticator;
use Wegrep\Auth\UserStore;
use Wegrep\Db\Database;
use Wegrep\HockeyData\HockeyDataClient;
use Wegrep\Http\ApiException;
use Wegrep\Http\Controllers\AdminController;
use Wegrep\Http\Controllers\AuthController;
use Wegrep\Http\Controllers\GameController;
use Wegrep\Http\Controllers\LeagueController;
use Wegrep\Http\Controllers\MetaController;
use Wegrep\Http\Router;
use Wegrep\Mail\Mailer;

$root = dirname(__DIR__, 2);
require $root . '/lib/autoload.php';

$config = require $root . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = '/' . trim(preg_replace('#^.*?/api(?=/|$)#', '', $path), '/');

    $users = new UserStore(new Database($config));
    $authenticator = new Authenticator($users, $config['jwt_secret']);
    $authService = new AuthService($users, new Mailer($config), $config);

    // Everything needs a signed-in user (a JWT in the Authorization header) except these.
    $public = ['/meta', '/logos', '/auth/register', '/auth/code', '/auth/verify'];
    if (!in_array($path, $public, true)) {
        $authenticator->requireUser($_SERVER);
        if ($authenticator->renewedToken() !== null) {
            header('X-Renewed-Token: ' . $authenticator->renewedToken());
        }
        // A new account can only ask who it is (to find out when it was approved), nothing else.
        if ($path !== '/auth/me') {
            $authenticator->requireApproved();
        }
        if (str_starts_with($path, '/admin/')) {
            $authenticator->requireAdmin();
        }
    }

    $client = new HockeyDataClient($config);
    $meta = new MetaController($config, $root);
    $auth = new AuthController($authService, $authenticator);
    $leagues = new LeagueController($client, $config);
    $games = new GameController($client, $config);
    $admin = new AdminController(new AdminService($users), $authenticator);

    $router = (new Router())
        ->add('GET',  '/meta',                   $meta->meta(...))
        ->add('GET',  '/logos',                  $meta->logos(...))
        ->add('POST', '/auth/register',          $auth->register(...))
        ->add('POST', '/auth/code',              $auth->code(...))
        ->add('POST', '/auth/verify',            $auth->verify(...))
        ->add('GET',  '/auth/me',                $auth->me(...))
        ->add('GET',  '/admin/users',            $admin->users(...))
        ->add('PATCH', '/admin/users/(\d+)',     $admin->setRole(...))
        ->add('GET',  '/leagues',                $leagues->leagues(...))
        ->add('GET',  '/leagues/(\d+)/games',    $leagues->games(...))
        ->add('GET',  '/games/(\d+)',            $games->show(...))
        ->add('GET',  '/games/(\d+)/precheck',   $games->precheck(...))
        ->add('POST', '/games/(\d+)/preview',    $games->preview(...))
        ->add('POST', '/games/(\d+)/upload',     $games->upload(...));

    $result = $router->dispatch($method, $path);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (ApiException $e) {
    http_response_code($e->status);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log((string) $e);
    echo json_encode(['error' => $e->getMessage()]);
}
