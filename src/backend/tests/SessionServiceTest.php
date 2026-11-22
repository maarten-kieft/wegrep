<?php
declare(strict_types=1);

use Wegrep\Http\ApiException;
use Wegrep\Sessions\SessionService;
use Wegrep\Storage\UserStore;

/** @return array{SessionService, int, int} the service and the ids of two users */
function sessionFixture(): array
{
    $db = testDatabase();
    $users = new UserStore($db);
    $a = $users->insert('a@example.com', 'A', 'A', 'user', 1);
    $b = $users->insert('b@example.com', 'B', 'B', 'user', 1);
    return [new SessionService($db), (int) $a['id'], (int) $b['id']];
}

function sessionBody(int $savedAt, string $phase = 'active', int $home = 1, int $away = 0): array
{
    return [
        'leagueId' => 5, 'title' => 'Cougars - Bulldogs', 'phase' => $phase, 'scoreHome' => $home, 'scoreAway' => $away,
        'savedAt' => $savedAt, 'state' => ['phase' => $phase, 'report' => ['goals' => [['time' => 61]]], 'name' => 'Ünï'],
    ];
}

test('a session is saved and read back with its state', function () {
    [$sessions, $a] = sessionFixture();
    $sessions->put($a, 100, sessionBody(1000));
    $got = $sessions->get($a, 100);
    assertSame('Cougars - Bulldogs', $got['title']);
    assertSame(5, $got['leagueId']);
    assertSame(1000, $got['savedAt']);
    assertSame(['time' => 61], $got['state']['report']['goals'][0]);
    assertSame('Ünï', $got['state']['name']);
    assertSame(null, $sessions->get($a, 101));
});

test('a newer save replaces the stored one, an older one is refused', function () {
    [$sessions, $a] = sessionFixture();
    $sessions->put($a, 100, sessionBody(1000, 'active', 1, 0));
    $sessions->put($a, 100, sessionBody(2000, 'active', 2, 0));
    assertSame(2, $sessions->get($a, 100)['scoreHome']);

    $e = assertThrows(ApiException::class, fn() => $sessions->put($a, 100, sessionBody(1500, 'active', 9, 9)));
    assertSame(409, $e->status);
    assertSame(2, $sessions->get($a, 100)['scoreHome']);

    $sessions->put($a, 100, sessionBody(2000, 'ended', 3, 1)); // same time again: the same device saving twice
    assertSame('ended', $sessions->get($a, 100)['phase']);
});

test('users only see and change their own sessions', function () {
    [$sessions, $a, $b] = sessionFixture();
    $sessions->put($a, 100, sessionBody(1000));
    $sessions->put($b, 100, sessionBody(500, 'ended'));
    $sessions->put($a, 200, sessionBody(3000));

    assertSame([200, 100], array_column($sessions->list($a), 'gameId'), 'newest first');
    assertSame([100], array_column($sessions->list($b), 'gameId'));
    assertSame('ended', $sessions->get($b, 100)['phase']);

    $sessions->delete($b, 200); // not theirs: nothing happens
    assertTrue($sessions->get($a, 200) !== null);
    $sessions->delete($a, 200);
    assertSame(null, $sessions->get($a, 200));
});

test('a session needs a league, a time and a state', function () {
    [$sessions, $a] = sessionFixture();
    assertThrows(ApiException::class, fn() => $sessions->put($a, 100, ['savedAt' => 1, 'state' => []]));
    assertThrows(ApiException::class, fn() => $sessions->put($a, 100, ['leagueId' => 5, 'state' => []]));
    assertThrows(ApiException::class, fn() => $sessions->put($a, 100, ['leagueId' => 5, 'savedAt' => 1]));
});

test('sessions go with the account', function () {
    $db = testDatabase();
    $users = new UserStore($db);
    $u = $users->insert('a@example.com', 'A', 'A', 'user', 1);
    $sessions = new SessionService($db);
    $sessions->put((int) $u['id'], 100, sessionBody(1000));
    $users->delete((int) $u['id']);
    assertSame([], $sessions->list((int) $u['id']));
});

test('administrators can list the sessions of everybody, with who they belong to', function () {
    [$sessions, $a, $b] = sessionFixture();
    $sessions->put($a, 100, sessionBody(1000));
    $sessions->put($b, 200, sessionBody(2000, 'ended'));
    $all = $sessions->listAll();
    assertSame([200, 100], array_column($all, 'gameId'));
    assertSame('b@example.com', $all[0]['user']['email']);
    assertSame($a, $all[1]['user']['id']);
});

test('taking a session over moves it to the administrator and replaces their own for that game', function () {
    [$sessions, $user, $admin] = sessionFixture();
    $sessions->put($user, 100, sessionBody(1000, 'active', 2, 1));
    $sessions->put($admin, 100, sessionBody(500, 'active', 9, 9));
    $sessions->put($user, 300, sessionBody(1000));

    $taken = $sessions->takeOver($admin, $user, 100, 5000);
    assertSame(2, $taken['scoreHome']);
    assertSame(2, $sessions->get($admin, 100)['scoreHome']);
    assertSame(null, $sessions->get($user, 100));
    assertTrue($sessions->get($user, 300) !== null, 'their other games stay theirs');
    assertSame(404, assertThrows(ApiException::class, fn() => $sessions->takeOver($admin, $user, 100, 6000))->status);
    assertSame(400, assertThrows(ApiException::class, fn() => $sessions->takeOver($admin, $admin, 100, 6000))->status);
});

test('the previous owner is told with a 410 until their device acknowledges', function () {
    [$sessions, $user, $admin] = sessionFixture();
    $sessions->put($user, 100, sessionBody(1000));
    $sessions->takeOver($admin, $user, 100, 5000);

    assertTrue($sessions->isTakenOver($user, 100));
    assertSame(410, assertThrows(ApiException::class, fn() => $sessions->put($user, 100, sessionBody(9000)))->status);
    assertSame(null, $sessions->get($user, 100), 'the old copy is not resurrected');

    $sessions->delete($user, 100); // the device acknowledges
    assertTrue(!$sessions->isTakenOver($user, 100));
    $sessions->put($user, 100, sessionBody(9500)); // a fresh start is possible again
    assertSame(9500, $sessions->get($user, 100)['savedAt']);
    assertSame(1000, $sessions->get($admin, 100)['savedAt'], 'the administrator keeps theirs');
});
