<?php
declare(strict_types=1);

namespace Wegrep\Http;

/** One entry of the route table. */
final class Route
{
    /**
     * @param string $method HTTP method
     * @param string $pattern regex for the path, without delimiters; its groups are passed to the handler
     * @param callable $handler
     */
    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly mixed $handler,
        public readonly Access $access,
    ) {
    }
}
