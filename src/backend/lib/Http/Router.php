<?php
declare(strict_types=1);

namespace Wegrep\Http;

/**
 * Minimal router: a list of routes. The first route whose method and pattern match wins; the regex
 * groups of the pattern are passed to its handler. A route also says who may call it (Access), which
 * the Application checks before it calls the handler.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** Routes are closed unless said otherwise: the default is an approved account. */
    public function add(string $method, string $pattern, callable $handler, Access $access = Access::Approved): self
    {
        $this->routes[] = new Route($method, $pattern, $handler, $access);
        return $this;
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * @return array{Route, list<string>} the route and the groups of the path it matched
     * @throws ApiException 404 when no route matches
     */
    public function match(string $method, string $path): array
    {
        foreach ($this->routes as $route) {
            if ($route->method === $method && preg_match('#^' . $route->pattern . '$#', $path, $m)) {
                return [$route, array_slice($m, 1)];
            }
        }
        throw new ApiException('Niet gevonden', 404);
    }

    /** Match and call the handler, without any access check. */
    public function dispatch(string $method, string $path): mixed
    {
        [$route, $params] = $this->match($method, $path);
        return ($route->handler)(...$params);
    }
}
