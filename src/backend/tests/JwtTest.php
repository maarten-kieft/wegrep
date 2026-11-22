<?php
declare(strict_types=1);

use Wegrep\Auth\Jwt;
use Wegrep\Http\ApiException;
use Wegrep\Http\Request;

const JWT_SECRET = 'test-secret-test-secret-test-secret-123';

test('jwt round trip returns the claims', function () {
    $token = Jwt::encode(['sub' => 7, 'exp' => 2000], JWT_SECRET);
    assertSame(['sub' => 7, 'exp' => 2000], Jwt::decode($token, JWT_SECRET, 1000));
});

test('jwt rejects a token that expired', function () {
    $token = Jwt::encode(['sub' => 7, 'exp' => 2000], JWT_SECRET);
    $e = assertThrows(ApiException::class, fn() => Jwt::decode($token, JWT_SECRET, 2000));
    assertSame(401, $e->status);
});

test('jwt rejects a wrong secret and a tampered payload', function () {
    $token = Jwt::encode(['sub' => 7, 'exp' => 2000], JWT_SECRET);
    assertThrows(ApiException::class, fn() => Jwt::decode($token, 'another-secret-another-secret-123456', 1000));

    [$head, , $sig] = explode('.', $token);
    $forged = rtrim(strtr(base64_encode(json_encode(['sub' => 1, 'exp' => 2000])), '+/', '-_'), '=');
    assertThrows(ApiException::class, fn() => Jwt::decode("$head.$forged.$sig", JWT_SECRET, 1000));
});

test('jwt rejects garbage and the alg none trick', function () {
    assertThrows(ApiException::class, fn() => Jwt::decode('abc', JWT_SECRET, 1000));
    $none = rtrim(strtr(base64_encode('{"alg":"none"}'), '+/', '-_'), '=');
    $body = rtrim(strtr(base64_encode('{"sub":1,"exp":2000}'), '+/', '-_'), '=');
    assertThrows(ApiException::class, fn() => Jwt::decode("$none.$body.", JWT_SECRET, 1000));
});

test('bearer token is read from the Authorization header', function () {
    assertSame('abc.def.ghi', Request::bearer(['HTTP_AUTHORIZATION' => 'Bearer abc.def.ghi']));
    assertSame('abc', Request::bearer(['REDIRECT_HTTP_AUTHORIZATION' => 'bearer abc']));
    assertSame(null, Request::bearer([]));
    assertSame(null, Request::bearer(['HTTP_AUTHORIZATION' => 'Basic abc']));
});
