<?php
declare(strict_types=1);

namespace Wegrep\Http;

use Wegrep\Auth\Role;

/**
 * Minimal router: a list of routes. The first route whose method and pattern match wins; the regex
 * groups of the pattern are passed to its handler. A route also says who may call it (open, any account,
 * or certain roles), which the Application checks before it calls the handler.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /**
     * A route for the given roles. Without roles every role may call it, but not an account that has no
     * role yet (not approved). So a route is closed unless it is made open() or account() on purpose.
     *
     * @param list<Role> $roles
     */
    public function add(string $method, string $pattern, callable $handler, array $roles = [Role::User, Role::Administrator]): self
    {
        $this->routes[] = new Route($method, $pattern, $handler, true, $roles);
        return $this;
    }

    /** A route anybody can call, also without being signed in. */
    public function open(string $method, string $pattern, callable $handler): self
    {
        $this->routes[] = new Route($method, $pattern, $handler, false, null);
        return $this;
    }

    /** A route for any signed-in account, also one that waits for approval. */
    public function account(string $method, string $pattern, callable $handler): self
    {
        $this->routes[] = new Route($method, $pattern, $handler, true, null);
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
