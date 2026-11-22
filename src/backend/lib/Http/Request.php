<?php
declare(strict_types=1);

namespace Wegrep\Http;

final class Request
{
    /** The JSON body as an array (empty when there is none). */
    public static function json(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }

    /** The token of an "Authorization: Bearer ..." header, or null. */
    public static function bearer(array $server): ?string
    {
        $header = $server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        return preg_match('/^Bearer\s+(\S+)$/i', $header, $m) ? $m[1] : null;
    }
}
