<?php
declare(strict_types=1);

use Wegrep\Auth\Authenticator;
use Wegrep\Auth\Role;
use Wegrep\Http\ApiException;
use Wegrep\Http\Application;
use Wegrep\Http\Endpoints;
use Wegrep\Storage\UserStore;

/** The settings the endpoints need, without a database unless $db is given. */
function appConfig(array $db = []): array
{
    return $db + [
        'jwt_secret' => 'test-secret-test-secret-test-secret-123', 'admin_emails' => [], 'app_url' => '', 'mail_from' => 'a@example.com',
        'mail_from_name' => 'W', 'db_host' => 'localhost', 'db_port' => 3306, 'db_name' => 'x', 'db_user' => 'x', 'db_password' => '',
        'upload_enabled' => false, 'api_base' => '', 'customer' => '', 'league_password' => '', 'egrep_version' => '',
    ];
}

function appRoot(): string
{
    return dirname(__DIR__);
}

test('the route table: only these routes are open, and everything under /admin is for administrators', function () {
    $app = new Application(Endpoints::create(appConfig(), appRoot()));
    $open = [];
    foreach ($app->router()->routes() as $route) {
        if (!$route->signedIn) {
            $open[] = $route->method . ' ' . $route->pattern;
        }
        if (str_starts_with($route->pattern, '/admin/')) {
            assertSame([Role::Administrator], $route->roles, $route->pattern);
        }
    }
    sort($open);
    assertSame(['GET /logos', 'GET /meta', 'POST /auth/code', 'POST /auth/register', 'POST /auth/verify'], $open);
});

test('only /auth/me may be used by an account that is not approved yet', function () {
    $app = new Application(Endpoints::create(appConfig(), appRoot()));
    $account = array_filter($app->router()->routes(), fn($r) => $r->signedIn && $r->roles === null);
    assertSame(['/auth/me'], array_values(array_map(fn($r) => $r->pattern, $account)));
});

test('a request without a token is refused, an unknown path is a 404, an open route just works', function () {
    $app = new Application(Endpoints::create(appConfig(), appRoot()));
    assertSame(401, assertThrows(ApiException::class, fn() => $app->handle('GET', '/leagues', []))->status);
    assertSame(401, assertThrows(ApiException::class, fn() => $app->handle('GET', '/admin/users', []))->status);
    assertSame(401, assertThrows(ApiException::class, fn() => $app->handle('GET', '/sessions', ['HTTP_AUTHORIZATION' => 'Bearer nonsense']))->status);
    assertSame(404, assertThrows(ApiException::class, fn() => $app->handle('GET', '/nope', []))->status);
    assertTrue(is_array($app->handle('GET', '/meta', [])['penaltyTypes']));
});

test('access levels are enforced: pending, user and administrator', function () {
    $config = appConfig(testDbConfig());
    $endpoints = Endpoints::create($config, appRoot());
    $app = new Application($endpoints);
    $users = new UserStore(new Wegrep\Storage\Database($config));
    $tokens = new Authenticator($users, $config['jwt_secret']);
    $as = fn(?string $role) => ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokens->issueToken(
        $users->insert(($role ?? 'pending') . '@example.com', 'A', 'B', $role, time())
    )];

    $pending = $as(null);
    $user = $as('user');
    $admin = $as('administrator');

    assertSame('pending@example.com', $app->handle('GET', '/auth/me', $pending)['user']['email']);
    assertSame(403, assertThrows(ApiException::class, fn() => $app->handle('GET', '/sessions', $pending))->status);
    assertSame(403, assertThrows(ApiException::class, fn() => $app->handle('GET', '/admin/users', $pending))->status);

    assertSame([], $app->handle('GET', '/sessions', $user));
    assertSame(403, assertThrows(ApiException::class, fn() => $app->handle('GET', '/admin/users', $user))->status);
    assertSame(403, assertThrows(ApiException::class, fn() => $app->handle('GET', '/admin/sessions', $user))->status);

    assertSame(3, count($app->handle('GET', '/admin/users', $admin)));
});
