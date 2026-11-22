<?php
declare(strict_types=1);

namespace Wegrep\Auth;

use Wegrep\Http\ApiException;

/** Minimal HS256 JSON Web Token: just enough to sign who someone is and until when. */
final class Jwt
{
    /** @param array<string, mixed> $claims */
    public static function encode(array $claims, string $secret): string
    {
        $head = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::b64(json_encode($claims));
        return $head . '.' . $body . '.' . self::b64(hash_hmac('sha256', $head . '.' . $body, $secret, true));
    }

    /**
     * @return array<string, mixed> the claims
     * @throws ApiException 401 when the token is malformed, tampered with or expired
     */
    public static function decode(string $token, string $secret, int $now): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new ApiException('Ongeldige sessie', 401);
        }
        [$head, $body, $signature] = $parts;
        $header = json_decode(self::unb64($head), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            throw new ApiException('Ongeldige sessie', 401);
        }
        $expected = self::b64(hash_hmac('sha256', $head . '.' . $body, $secret, true));
        if (!hash_equals($expected, $signature)) {
            throw new ApiException('Ongeldige sessie', 401);
        }
        $claims = json_decode(self::unb64($body), true);
        if (!is_array($claims) || !isset($claims['exp']) || (int) $claims['exp'] <= $now) {
            throw new ApiException('Sessie verlopen', 401);
        }
        return $claims;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/'));
    }
}
