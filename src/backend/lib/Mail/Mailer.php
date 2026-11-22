<?php
declare(strict_types=1);

namespace Wegrep\Mail;

use Wegrep\Http\ApiException;

/**
 * Sends mail through PHP's mail(): locally that lands in Mailpit (docker compose), on the server in
 * whatever sendmail the hosting provides. A mail with an HTML version goes out as multipart/alternative
 * (plain text first, so every client can fall back on it).
 */
final class Mailer
{
    /** @var callable(string, string, string, string): bool */
    private $transport;

    /** @param null|callable(string $to, string $subject, string $body, string $headers): bool $transport for tests */
    public function __construct(
        private readonly array $config,
        ?callable $transport = null,
        private readonly MailTemplate $templates = new MailTemplate(),
    ) {
        $this->transport = $transport ?? static fn(string $to, string $subject, string $body, string $headers): bool
            => mail($to, $subject, $body, $headers);
    }

    /** Send a mail made from a template in Templates/ (see MailTemplate). */
    public function sendTemplate(string $to, string $template, array $vars): void
    {
        $mail = $this->templates->render($template, $vars);
        $this->send($to, $mail['subject'], $mail['text'], $mail['html']);
    }

    public function send(string $to, string $subject, string $text, ?string $html = null): void
    {
        if (preg_match('/[\r\n,;<>]/', $to)) {
            throw new ApiException('Ongeldig e-mailadres');
        }
        $headers = [
            'From: ' . $this->encode($this->config['mail_from_name']) . ' <' . $this->config['mail_from'] . '>',
            'MIME-Version: 1.0',
        ];
        if ($html === null) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $body = $text;
        } else {
            $boundary = 'wegrep-' . bin2hex(random_bytes(12));
            $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";
            $body = "--$boundary\n"
                . "Content-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\n\n$text\n"
                . "--$boundary\n"
                . "Content-Type: text/html; charset=UTF-8\nContent-Transfer-Encoding: 8bit\n\n$html\n"
                . "--$boundary--\n";
        }
        if (!($this->transport)($to, $this->encode($subject), str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body)), implode("\r\n", $headers))) {
            error_log('mail() failed for ' . $to);
            throw new ApiException('De e-mail kon niet worden verstuurd', 502);
        }
    }

    private function encode(string $text): string
    {
        return preg_match('/^[\x20-\x7E]*$/', $text) ? $text : '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
}
