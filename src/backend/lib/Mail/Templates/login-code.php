<?php
declare(strict_types=1);

// The mail with the one-time login code. Variables: firstname, code, link (or null when there is no app_url).
return static fn(array $v): array => [
    'subject' => 'Je inlogcode voor Wegrep: ' . $v['code'],
    'preheader' => 'Je inlogcode is ' . $v['code'] . '. Hij is 15 minuten geldig.',
    'heading' => 'Inloggen bij Wegrep',
    'paragraphs' => ["Hoi {$v['firstname']},", 'Vul deze code in om in te loggen:'],
    'code' => ['label' => 'Je inlogcode is', 'value' => $v['code']],
    'button' => $v['link'] === null ? null : ['label' => 'Of log direct in', 'url' => $v['link']],
    'footnotes' => [
        'De code is 15 minuten geldig en werkt één keer.',
        'Heb je dit niet aangevraagd? Dan kun je deze mail negeren.',
    ],
];
