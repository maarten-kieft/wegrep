<?php
declare(strict_types=1);

namespace Wegrep\Auth;

use Wegrep\Http\ApiException;
use Wegrep\Http\Request;

/**
 * Turns the "Authorization: Bearer <jwt>" header into the signed-in user. The token only says
 * who you are and until when; role and existence are read from the database on every request,
 * so a role change or a removed account takes effect immediately.
 *
 * Tokens last 30 days and are renewed (a fresh one in the X-Renewed-Token response header)
 * once they are a day old, so someone who keeps using the app never has to log in again.
 */
final class Authenticator
{
    public const TTL = 30 * 86400;
    private const RENEW_AFTER = 86400;

    private ?array $user = null;
    private ?string $renewed = null;

    /** @var callable(): int */
    private $now;

    /** @param null|callable(): int $now clock, for tests */
    public function __construct(private readonly UserStore $users, private readonly string $secret, ?callable $now = null)
    {
        $this->now = $now ?? static fn(): int => time();
    }

    public function issueToken(array $user): string
    {
        $this->requireSecret();
        $now = ($this->now)();
        return Jwt::encode(['sub' => (int) $user['id'], 'iat' => $now, 'exp' => $now + self::TTL], $this->secret);
    }

    /** The signed-in user, or an ApiException 401. */
    public function requireUser(array $server): array
    {
        $this->requireSecret();
        $token = Request::bearer($server);
        if ($token === null) {
            throw new ApiException('Niet ingelogd', 401);
        }
        $claims = Jwt::decode($token, $this->secret, ($this->now)());
        $user = $this->users->find((int) ($claims['sub'] ?? 0));
        if (!$user) {
            throw new ApiException('Dit account bestaat niet meer', 401);
        }
        if (($this->now)() - (int) ($claims['iat'] ?? 0) > self::RENEW_AFTER) {
            $this->renewed = $this->issueToken($user);
        }
        return $this->user = $user;
    }

    public function user(): array
    {
        return $this->user ?? throw new ApiException('Niet ingelogd', 401);
    }

    public function renewedToken(): ?string
    {
        return $this->renewed;
    }

    /** What the app may know about a user. */
    public static function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'firstname' => $user['firstname'],
            'lastname' => $user['lastname'],
            'role' => $user['role'],
        ];
    }

    private function requireSecret(): void
    {
        if (strlen($this->secret) < 32) {
            throw new ApiException('WEGREP_JWT_SECRET is niet (of te kort, minimaal 32 tekens) ingesteld', 500);
        }
    }
}
