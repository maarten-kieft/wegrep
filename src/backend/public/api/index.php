<?php
declare(strict_types=1);

/**
 * JSON API for the web app. All requests under /api end up here (see .htaccess);
 * the routes below map them to the controllers in lib/Controllers.
 */

use Wegrep\ApiException;
use Wegrep\Controllers\GameController;
use Wegrep\Controllers\LeagueController;
use Wegrep\Controllers\MetaController;
use Wegrep\HockeyDataClient;
use Wegrep\Router;

$root = dirname(__DIR__, 2);
require $root . '/lib/ApiException.php';
require $root . '/lib/ClubFilter.php';
require $root . '/lib/GameReport.php';
require $root . '/lib/HdCrypt.php';
require $root . '/lib/HockeyDataClient.php';
require $root . '/lib/NrbfReader.php';
require $root . '/lib/Router.php';
require $root . '/lib/TeamLogos.php';
require $root . '/lib/Controllers/GameController.php';
require $root . '/lib/Controllers/LeagueController.php';
require $root . '/lib/Controllers/MetaController.php';

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
    $meta = new MetaController($config, $root);
    $leagues = new LeagueController($client, $config);
    $games = new GameController($client, $config);

    $router = (new Router())
        ->add('GET',  '/meta',                   $meta->meta(...))
        ->add('GET',  '/logos',                  $meta->logos(...))
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
