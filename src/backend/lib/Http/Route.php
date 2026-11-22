<?php
declare(strict_types=1);

namespace Wegrep\Http;

use Wegrep\Auth\Role;

/** One entry of the route table. */
final class Route
{
    /**
     * @param string $method HTTP method
     * @param string $pattern regex for the path, without delimiters; its groups are passed to the handler
     * @param callable $handler
     * @param bool $signedIn whether the caller has to be signed in
     * @param list<Role>|null $roles the roles that may call it; null = any signed-in account, also one without a role yet
     */
    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly mixed $handler,
        public readonly bool $signedIn,
        public readonly ?array $roles,
    ) {
    }
}
