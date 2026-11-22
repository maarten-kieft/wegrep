<?php
declare(strict_types=1);

namespace Wegrep\Mail;

/**
 * Turns a template from Templates/ into the subject, a plain text body and an HTML body. A template
 * only says what the mail contains (heading, paragraphs, a code, a button, footnotes); the HTML look
 * is in Templates/layout.php and the plain text version is made here from the same content.
 */
final class MailTemplate
{
    public function __construct(private readonly string $directory = __DIR__ . '/Templates')
    {
    }

    /**
     * @param array<string, mixed> $vars what the template needs
     * @return array{subject: string, text: string, html: string}
     */
    public function render(string $name, array $vars): array
    {
        $file = $this->directory . '/' . $name . '.php';
        if (!preg_match('/^[a-z0-9-]+$/', $name) || !is_file($file)) {
            throw new \InvalidArgumentException("Unknown mail template: $name");
        }
        $c = (require $file)($vars);

        return ['subject' => $c['subject'], 'text' => $this->text($c), 'html' => $this->html($c)];
    }

    private function text(array $c): string
    {
        $parts = [$c['heading'], ...$c['paragraphs']];
        if (!empty($c['code'])) {
            $parts[] = $c['code']['label'] . ': ' . $c['code']['value'];
        }
        if (!empty($c['button'])) {
            $parts[] = $c['button']['label'] . ":\n" . $c['button']['url'];
        }
        array_push($parts, ...$c['footnotes']);
        return implode("\n\n", $parts) . "\n";
    }

    private function html(array $c): string
    {
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ob_start();
        try {
            require $this->directory . '/layout.php';
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
