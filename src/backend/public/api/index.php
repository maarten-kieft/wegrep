<?php
declare(strict_types=1);

/**
 * JSON API for the web app. All requests under /api end up here (see .htaccess).
 * The routes are in lib/Http/routes.php, the request handling in lib/Http/Application.php.
 */

use Wegrep\Http\Application;
use Wegrep\Http\Endpoints;

$root = dirname(__DIR__, 2);
require $root . '/lib/autoload.php';

$config = require $root . '/config.php';
(new Application(Endpoints::create($config, $root)))->run();
