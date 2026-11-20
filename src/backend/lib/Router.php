<?php
declare(strict_types=1);

namespace Wegrep;

/**
 * Minimal router: a list of [method, path regex, handler]. The first route whose
 * method and pattern match wins; the regex groups are passed to the handler.
 */
final class Router
{
    /** @var list<array{string, string, callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): self
    {
        $this->routes[] = [$method, '#^' . $pattern . '$#', $handler];
        return $this;
    }

    public function dispatch(string $method, string $path): mixed
    {
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if ($routeMethod === $method && preg_match($regex, $path, $m)) {
                return $handler(...array_slice($m, 1));
            }
        }
        throw new ApiException('Niet gevonden', 404);
    }
}
