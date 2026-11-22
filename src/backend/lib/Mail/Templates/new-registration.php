<?php
declare(strict_types=1);

// Sent to the administrators when somebody has confirmed their address and waits for approval.
// Variables: name, email, link (the admin page, or null when there is no app_url).
return static fn(array $v): array => [
    'subject' => 'Nieuwe aanvraag voor Wegrep: ' . $v['name'],
    'preheader' => $v['name'] . ' wacht op goedkeuring.',
    'heading' => 'Nieuwe aanvraag',
    'paragraphs' => [
        "{$v['name']} ({$v['email']}) heeft een account aangemaakt voor Wegrep en wacht op goedkeuring.",
    ],
    'code' => null,
    'button' => $v['link'] === null ? null : ['label' => 'Goedkeuren', 'url' => $v['link']],
    'footnotes' => ['Je krijgt deze mail omdat je beheerder bent van Wegrep.'],
];
