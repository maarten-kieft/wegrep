<?php
declare(strict_types=1);

use Wegrep\Http\ApiException;
use Wegrep\Http\Router;

test('router passes the regex groups to the handler', function () {
    $router = (new Router())->add('GET', '/games/(\d+)', fn(string $id) => ['id' => $id]);
    assertSame(['id' => '42'], $router->dispatch('GET', '/games/42'));
});

test('router matches on method', function () {
    $router = (new Router())->add('POST', '/x', fn() => 'posted');
    $e = assertThrows(ApiException::class, fn() => $router->dispatch('GET', '/x'));
    assertSame(404, $e->status);
});

test('router answers 404 for an unknown path', function () {
    $e = assertThrows(ApiException::class, fn() => (new Router())->dispatch('GET', '/nope'));
    assertSame(404, $e->status);
});
