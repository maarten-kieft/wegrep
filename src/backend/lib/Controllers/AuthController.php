<?php
declare(strict_types=1);

namespace Wegrep\Controllers;

use Wegrep\Auth\AuthService;
use Wegrep\Auth\Authenticator;
use Wegrep\Http\Request;

final class AuthController
{
    public function __construct(private readonly AuthService $auth, private readonly Authenticator $authenticator)
    {
    }

    /** POST /auth/register {email, firstname, lastname}: create an account, mail a login code */
    public function register(): array
    {
        $in = Request::json();
        $this->auth->register((string) ($in['email'] ?? ''), (string) ($in['firstname'] ?? ''), (string) ($in['lastname'] ?? ''));
        return ['ok' => true];
    }

    /** POST /auth/code {email}: mail a login code */
    public function code(): array
    {
        $this->auth->requestCode((string) (Request::json()['email'] ?? ''));
        return ['ok' => true];
    }

    /** POST /auth/verify {email, code}: swap a code for a token */
    public function verify(): array
    {
        $in = Request::json();
        $user = $this->auth->verify((string) ($in['email'] ?? ''), (string) ($in['code'] ?? ''));
        return ['token' => $this->authenticator->issueToken($user), 'user' => Authenticator::publicUser($user)];
    }

    /** GET /auth/me: who am I (also how the app notices an approval or a removed account) */
    public function me(): array
    {
        return ['user' => Authenticator::publicUser($this->authenticator->user())];
    }
}
