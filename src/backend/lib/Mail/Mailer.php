<?php
declare(strict_types=1);

namespace Wegrep\Mail;

use Wegrep\Http\ApiException;

/**
 * Plain text mail through PHP's mail(). Locally that lands in Mailpit (docker compose),
 * on the server in whatever sendmail the hosting provides.
 */
final class Mailer
{
    /** @var callable(string, string, string, string): bool */
    private $transport;

    /** @param null|callable(string $to, string $subject, string $body, string $headers): bool $transport for tests */
    public function __construct(private readonly array $config, ?callable $transport = null)
    {
        $this->transport = $transport ?? static fn(string $to, string $subject, string $body, string $headers): bool
            => mail($to, $subject, $body, $headers);
    }

    public function send(string $to, string $subject, string $body): void
    {
        if (preg_match('/[\r\n,;<>]/', $to)) {
            throw new ApiException('Ongeldig e-mailadres');
        }
        $headers = implode("\r\n", [
            'From: ' . $this->encode($this->config['mail_from_name']) . ' <' . $this->config['mail_from'] . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ]);
        if (!($this->transport)($to, $this->encode($subject), str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body)), $headers)) {
            error_log('mail() failed for ' . $to);
            throw new ApiException('De e-mail kon niet worden verstuurd', 502);
        }
    }

    private function encode(string $text): string
    {
        return preg_match('/^[\x20-\x7E]*$/', $text) ? $text : '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
}
