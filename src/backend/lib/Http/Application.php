<?php
declare(strict_types=1);

namespace Wegrep\Http;

/**
 * Handles one API request: finds the route, checks that the caller may use it, calls the controller and
 * turns the answer (or the ApiException) into JSON. public/api/index.php only starts this.
 */
final class Application
{
    private readonly Router $router;

    public function __construct(private readonly Endpoints $endpoints)
    {
        $this->router = new Router();
        (require __DIR__ . '/routes.php')($this->router, $endpoints);
    }

    public function router(): Router
    {
        return $this->router;
    }

    /**
     * The answer of a request.
     *
     * @param array<string, mixed> $server the request's $_SERVER (for the Authorization header)
     * @throws ApiException when the route is unknown or the caller may not use it
     */
    public function handle(string $method, string $path, array $server): mixed
    {
        [$route, $params] = $this->router->match($method, $path);
        $auth = $this->endpoints->authenticator;

        if ($route->access !== Access::Anyone) {
            $auth->requireUser($server);
            if ($route->access === Access::Approved || $route->access === Access::Admin) {
                $auth->requireApproved();
            }
            if ($route->access === Access::Admin) {
                $auth->requireAdmin();
            }
        }
        return ($route->handler)(...$params);
    }

    /** Answer the current request: headers, JSON, status code. */
    public function run(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $path = '/' . trim(preg_replace('#^.*?/api(?=/|$)#', '', $path), '/');

            $result = $this->handle($_SERVER['REQUEST_METHOD'], $path, $_SERVER);
            if ($this->endpoints->authenticator->renewedToken() !== null) {
                header('X-Renewed-Token: ' . $this->endpoints->authenticator->renewedToken());
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (ApiException $e) {
            http_response_code($e->status);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            http_response_code(500);
            error_log((string) $e);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
