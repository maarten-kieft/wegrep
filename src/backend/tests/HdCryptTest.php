<?php
declare(strict_types=1);

use Wegrep\GameReport\HdCrypt;

test('HdCrypt wraps the xml in an [HD] container that decodes again', function () {
    $xml = '<?xml version="1.0"?><game><goal scorer="7">Ünïcode</goal></game>';
    $payload = HdCrypt::encode($xml);
    assertTrue(str_starts_with($payload, '[HD]'));
    assertSame($xml, HdCrypt::decode($payload));
});
