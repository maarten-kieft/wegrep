<?php
declare(strict_types=1);

use Wegrep\Mail\MailTemplate;
use Wegrep\Mail\Mailer;

$loginVars = ['firstname' => 'Piet <b>', 'code' => '012345', 'link' => 'https://sheet.example.nl/login?email=p%40example.com&code=012345'];

test('the login mail has a subject, a plain text and an HTML version with the code', function () use ($loginVars) {
    $mail = (new MailTemplate())->render('login-code', $loginVars);
    assertSame('Je inlogcode voor Wegrep: 012345', $mail['subject']);
    assertTrue(str_contains($mail['text'], 'Je inlogcode is: 012345'));
    assertTrue(str_contains($mail['text'], 'https://sheet.example.nl/login?email=p%40example.com&code=012345'));
    assertTrue(str_contains($mail['html'], '012345'));
    assertTrue(str_contains($mail['html'], '<html lang="nl">'));
});

test('HTML is escaped: names cannot inject markup, and the link keeps working in an attribute', function () use ($loginVars) {
    $html = (new MailTemplate())->render('login-code', $loginVars)['html'];
    assertTrue(!str_contains($html, 'Piet <b>'));
    assertTrue(str_contains($html, 'Piet &lt;b&gt;'));
    assertTrue(str_contains($html, 'href="https://sheet.example.nl/login?email=p%40example.com&amp;code=012345"'));
});

test('without a link there is no button', function () {
    $mail = (new MailTemplate())->render('login-code', ['firstname' => 'Piet', 'code' => '111111', 'link' => null]);
    assertTrue(!str_contains($mail['html'], '<a href'));
    assertTrue(!str_contains($mail['text'], 'http'));
});

test('the registration mail names the person and links to the admin page', function () {
    $mail = (new MailTemplate())->render('new-registration', ['name' => 'Piet Puck', 'email' => 'piet@example.com', 'link' => 'https://sheet.example.nl/admin']);
    assertSame('Nieuwe aanvraag voor Wegrep: Piet Puck', $mail['subject']);
    assertTrue(str_contains($mail['text'], 'piet@example.com'));
    assertTrue(str_contains($mail['html'], 'href="https://sheet.example.nl/admin"'));
});

test('an unknown template name is refused, also a path trick', function () {
    assertThrows(InvalidArgumentException::class, fn() => (new MailTemplate())->render('nope', []));
    assertThrows(InvalidArgumentException::class, fn() => (new MailTemplate())->render('../Mailer', []));
});

test('a mail with HTML goes out as multipart/alternative, text first', function () {
    $sent = null;
    $mailer = new Mailer(['mail_from' => 'wegrep@example.com', 'mail_from_name' => 'Wegrep'], function ($to, $subject, $body, $headers) use (&$sent) {
        $sent = compact('subject', 'body', 'headers');
        return true;
    });
    $mailer->sendTemplate('piet@example.com', 'login-code', ['firstname' => 'Piet', 'code' => '424242', 'link' => null]);
    assertTrue(str_contains($sent['headers'], 'multipart/alternative; boundary='));
    $plain = strpos($sent['body'], 'text/plain');
    $html = strpos($sent['body'], 'text/html');
    assertTrue($plain !== false && $html !== false && $plain < $html);
    assertTrue(str_contains($sent['body'], "Je inlogcode is: 424242\r\n"));
    foreach (explode("\r\n", $sent['body']) as $line) {
        assertTrue(strlen($line) < 998, 'no line may be longer than 998 characters');
    }
});
