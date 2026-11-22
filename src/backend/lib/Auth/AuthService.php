<?php
declare(strict_types=1);

namespace Wegrep\Auth;

use Wegrep\Http\ApiException;
use Wegrep\Mail\Mailer;

/** Registration and passwordless login: a 6 digit code (also in a link) that works once for 15 minutes. */
final class AuthService
{
    public const CODE_TTL = 900;        // 15 minutes
    public const RESEND_AFTER = 60;     // seconds between two code mails to the same person
    public const MAX_ATTEMPTS = 5;      // wrong guesses before the code is dead

    /** @var callable(): int */
    private $now;

    /** @param null|callable(): int $now clock, for tests */
    public function __construct(
        private readonly UserStore $users,
        private readonly Mailer $mailer,
        private readonly array $config,
        ?callable $now = null
    ) {
        $this->now = $now ?? static fn(): int => time();
    }

    /** Create an account and mail the first login code. */
    public function register(string $email, string $firstname, string $lastname): array
    {
        $email = self::normalizeEmail($email);
        $firstname = trim($firstname);
        $lastname = trim($lastname);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            throw new ApiException('Vul een geldig e-mailadres in');
        }
        if ($firstname === '' || $lastname === '' || mb_strlen($firstname) > 100 || mb_strlen($lastname) > 100) {
            throw new ApiException('Vul je voor- en achternaam in');
        }
        if ($this->users->findByEmail($email)) {
            throw new ApiException('Dit e-mailadres heeft al een account. Log in met een code.', 409);
        }
        $user = $this->users->insert($email, $firstname, $lastname, $this->roleForNewUser($email), ($this->now)());
        $this->sendCode($user);
        return $user;
    }

    /** Mail a new login code to an existing user. */
    public function requestCode(string $email): void
    {
        $user = $this->users->findByEmail(self::normalizeEmail($email));
        if (!$user) {
            throw new ApiException('Dit e-mailadres is niet bekend. Maak eerst een account aan.', 404);
        }
        $this->sendCode($user);
    }

    /** Check the code. It works once; five wrong guesses kill it. */
    public function verify(string $email, string $code): array
    {
        $wrong = new ApiException('Code onjuist of verlopen', 401);
        $user = $this->users->findByEmail(self::normalizeEmail($email));
        if (!$user) {
            throw $wrong;
        }
        $now = ($this->now)();
        $open = $this->users->openCode((int) $user['id'], $now);
        if (!$open || (int) $open['attempts'] >= self::MAX_ATTEMPTS) {
            throw $wrong;
        }
        $this->users->countAttempt((int) $open['id']);
        if (!hash_equals($open['code_hash'], $this->hash(preg_replace('/\D/', '', $code)))) {
            throw $wrong;
        }
        $this->users->invalidateCodes((int) $user['id'], $now);
        // An address in WEGREP_ADMIN_EMAIL is always an administrator (also if it registered before it was listed).
        if ($user['role'] !== 'administrator' && $this->isConfiguredAdmin($user['email'])) {
            $this->users->setRole((int) $user['id'], 'administrator');
            $user = $this->users->find((int) $user['id']);
        }
        // First time the code is used the address is proven to be theirs: only now the administrators
        // hear about the new account, so nobody can fill their inbox with made-up addresses.
        if ($user['verified_at'] === null) {
            $this->users->markVerified((int) $user['id'], $now);
            $user = $this->users->find((int) $user['id']);
            if ($user['role'] === null) {
                $this->notifyAdministrators($user);
            }
        }
        return $user;
    }

    private function notifyAdministrators(array $user): void
    {
        $text = "{$user['firstname']} {$user['lastname']} ({$user['email']}) heeft een account aangemaakt voor Wegrep en wacht op goedkeuring.\n";
        if ($this->config['app_url'] !== '') {
            $text .= "\nGoedkeuren kan hier: {$this->config['app_url']}/admin\n";
        }
        foreach ($this->users->administrators() as $admin) {
            try {
                $this->mailer->send($admin['email'], "Nieuwe aanvraag voor Wegrep: {$user['firstname']} {$user['lastname']}", $text);
            } catch (ApiException $e) {
                error_log("Could not notify administrator {$admin['email']}: " . $e->getMessage());
            }
        }
    }

    private function sendCode(array $user): void
    {
        $now = ($this->now)();
        $last = $this->users->lastCodeCreatedAt((int) $user['id']);
        if ($last !== null && $now - $last < self::RESEND_AFTER) {
            throw new ApiException('Wacht even voordat je een nieuwe code aanvraagt', 429);
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->users->replaceCode((int) $user['id'], $this->hash($code), $now, $now + self::CODE_TTL);

        $text = "Hoi {$user['firstname']},\n\nJe inlogcode is: $code\n\n";
        if ($this->config['app_url'] !== '') {
            $link = $this->config['app_url'] . '/login?' . http_build_query(['email' => $user['email'], 'code' => $code]);
            $text .= "Of open deze link om direct in te loggen:\n$link\n\n";
        }
        $text .= "De code is 15 minuten geldig en werkt één keer. Heb je dit niet aangevraagd? Dan kun je deze mail negeren.\n";
        $this->mailer->send($user['email'], 'Je inlogcode voor Wegrep: ' . $code, $text);
    }

    private function roleForNewUser(string $email): ?string
    {
        return $this->isConfiguredAdmin($email) ? 'administrator' : null; // null = waits for approval
    }

    private function isConfiguredAdmin(string $email): bool
    {
        return in_array($email, $this->config['admin_emails'], true);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, $this->config['jwt_secret']);
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
