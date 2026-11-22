<?php
declare(strict_types=1);

use Wegrep\Auth\AuthService;
use Wegrep\Auth\Authenticator;
use Wegrep\Auth\UserStore;
use Wegrep\Http\ApiException;
use Wegrep\Mail\Mailer;

/** A fresh service on an empty database, a mailbox that records what was sent and a clock we can move. */
function authFixture(array $extraConfig = []): object
{
    $f = new stdClass();
    $f->time = 1_800_000_000;
    $f->mails = [];
    $f->config = $extraConfig + [
        'jwt_secret' => JWT_SECRET, 'admin_emails' => ['boss@example.com'], 'app_url' => 'https://sheet.example.nl',
        'mail_from' => 'wegrep@example.com', 'mail_from_name' => 'Wegrep',
    ];
    $f->users = new UserStore(testDatabase());
    $mailer = new Mailer($f->config, function (string $to, string $subject, string $body) use ($f): bool {
        $f->mails[] = compact('to', 'subject', 'body');
        return true;
    });
    $f->now = fn() => $f->time;
    $f->auth = new AuthService($f->users, $mailer, $f->config, $f->now);
    $f->authenticator = new Authenticator($f->users, JWT_SECRET, $f->now);
    $f->lastCode = function () use ($f): string {
        preg_match('/inlogcode is: (\d{6})/', end($f->mails)['body'], $m);
        return $m[1];
    };
    return $f;
}

test('register mails a 6 digit code and a login link', function () {
    $f = authFixture();
    $f->auth->register('  Piet@Example.com ', 'Piet', 'Puck');
    assertSame(1, count($f->mails));
    assertSame('piet@example.com', $f->mails[0]['to']);
    assertTrue(preg_match('/^\d{6}$/', ($f->lastCode)()) === 1);
    assertTrue(str_contains($f->mails[0]['body'], 'https://sheet.example.nl/login?email=piet%40example.com&code='));
});

test('register refuses a bad address, missing names and an existing account', function () {
    $f = authFixture();
    assertThrows(ApiException::class, fn() => $f->auth->register('nope', 'A', 'B'));
    assertThrows(ApiException::class, fn() => $f->auth->register('a@example.com', '', 'B'));
    $f->auth->register('a@example.com', 'A', 'B');
    $e = assertThrows(ApiException::class, fn() => $f->auth->register('A@example.com', 'A', 'B'));
    assertSame(409, $e->status);
});

test('a code logs you in once', function () {
    $f = authFixture();
    $f->auth->register('a@example.com', 'A', 'B');
    $code = ($f->lastCode)();
    $user = $f->auth->verify('a@example.com', $code);
    assertSame('a@example.com', $user['email']);
    $e = assertThrows(ApiException::class, fn() => $f->auth->verify('a@example.com', $code));
    assertSame(401, $e->status);
});

test('a code expires after 15 minutes', function () {
    $f = authFixture();
    $f->auth->register('a@example.com', 'A', 'B');
    $code = ($f->lastCode)();
    $f->time += AuthService::CODE_TTL;
    assertThrows(ApiException::class, fn() => $f->auth->verify('a@example.com', $code));
});

test('five wrong guesses kill the code, even the right one afterwards', function () {
    $f = authFixture();
    $f->auth->register('a@example.com', 'A', 'B');
    $code = ($f->lastCode)();
    $wrong = $code === '000000' ? '111111' : '000000';
    for ($i = 0; $i < AuthService::MAX_ATTEMPTS; $i++) {
        assertThrows(ApiException::class, fn() => $f->auth->verify('a@example.com', $wrong));
    }
    assertThrows(ApiException::class, fn() => $f->auth->verify('a@example.com', $code));
});

test('a new code replaces the old one and cannot be requested too often', function () {
    $f = authFixture();
    $f->auth->register('a@example.com', 'A', 'B');
    $first = ($f->lastCode)();
    $e = assertThrows(ApiException::class, fn() => $f->auth->requestCode('a@example.com'));
    assertSame(429, $e->status);
    $f->time += AuthService::RESEND_AFTER;
    $f->auth->requestCode('a@example.com');
    $second = ($f->lastCode)();
    if ($first !== $second) {
        assertThrows(ApiException::class, fn() => $f->auth->verify('a@example.com', $first));
    }
    assertSame('a@example.com', $f->auth->verify('a@example.com', $second)['email']);
});

test('an unknown address gets a 404 when asking for a code', function () {
    $f = authFixture();
    $e = assertThrows(ApiException::class, fn() => $f->auth->requestCode('who@example.com'));
    assertSame(404, $e->status);
});

test('an address in the admin list becomes administrator', function () {
    $f = authFixture();
    $f->auth->register('boss@example.com', 'The', 'Boss');
    assertSame('administrator', $f->auth->verify('boss@example.com', ($f->lastCode)())['role']);
    $f->auth->register('piet@example.com', 'Piet', 'Puck');
    assertSame('user', $f->auth->verify('piet@example.com', ($f->lastCode)())['role']);
});

test('a token gets you in, is renewed after a day and dies with the account', function () {
    $f = authFixture();
    $f->auth->register('a@example.com', 'A', 'B');
    $user = $f->auth->verify('a@example.com', ($f->lastCode)());
    $token = $f->authenticator->issueToken($user);
    $server = ['HTTP_AUTHORIZATION' => "Bearer $token"];

    assertSame('a@example.com', $f->authenticator->requireUser($server)['email']);
    assertSame(null, $f->authenticator->renewedToken());

    $f->time += 2 * 86400;
    $f->authenticator->requireUser($server);
    assertTrue($f->authenticator->renewedToken() !== null);

    $f->users->delete((int) $user['id']);
    $e = assertThrows(ApiException::class, fn() => $f->authenticator->requireUser($server));
    assertSame(401, $e->status);
});

test('a token expires after 30 days without use', function () {
    $f = authFixture();
    $f->auth->register('a@example.com', 'A', 'B');
    $token = $f->authenticator->issueToken($f->auth->verify('a@example.com', ($f->lastCode)()));
    $f->time += Authenticator::TTL;
    assertThrows(ApiException::class, fn() => $f->authenticator->requireUser(['HTTP_AUTHORIZATION' => "Bearer $token"]));
});

test('the authenticator refuses to work without a proper secret', function () {
    $f = authFixture();
    $weak = new Authenticator($f->users, 'short', $f->now);
    $e = assertThrows(ApiException::class, fn() => $weak->requireUser(['HTTP_AUTHORIZATION' => 'Bearer x.y.z']));
    assertSame(500, $e->status);
});
