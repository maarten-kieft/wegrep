<?php
declare(strict_types=1);

use Wegrep\Http\ApiException;
use Wegrep\Mail\Mailer;

test('mailer encodes non-ascii subjects and sets UTF-8 headers', function () {
    $sent = null;
    $mailer = new Mailer(['mail_from' => 'wegrep@example.com', 'mail_from_name' => 'Wegrep'], function ($to, $subject, $body, $headers) use (&$sent) {
        $sent = compact('to', 'subject', 'body', 'headers');
        return true;
    });
    $mailer->send('piet@example.com', 'Één code', "regel 1\nregel 2");
    assertSame('=?UTF-8?B?' . base64_encode('Één code') . '?=', $sent['subject']);
    assertSame("regel 1\r\nregel 2", $sent['body']);
    assertTrue(str_contains($sent['headers'], 'From: Wegrep <wegrep@example.com>'));
    assertTrue(str_contains($sent['headers'], 'charset=UTF-8'));
});

test('mailer refuses header injection in the recipient', function () {
    $mailer = new Mailer(['mail_from' => 'a@example.com', 'mail_from_name' => 'W'], fn() => true);
    assertThrows(ApiException::class, fn() => $mailer->send("x@example.com\r\nBcc: y@example.com", 's', 'b'));
});

test('mailer reports a failing transport', function () {
    $mailer = new Mailer(['mail_from' => 'a@example.com', 'mail_from_name' => 'W'], fn() => false);
    $e = assertThrows(ApiException::class, fn() => $mailer->send('x@example.com', 's', 'b'));
    assertSame(502, $e->status);
});
