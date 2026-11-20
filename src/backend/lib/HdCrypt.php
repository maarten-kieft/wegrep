<?php
declare(strict_types=1);

namespace Wegrep;

/**
 * The "[HD]" container EGREP uses for saved and uploaded game reports:
 *
 *   "[HD]" + base64( AES-256-CBC( randomSalt + "<HD>" + base64(gzip(xml)) + "</HD>" ) )
 *
 * Key and IV come from PBKDF2-SHA1(sha256(password), same as salt, 1000 rounds).
 * EGREP's password is MAGIC_COLOR.ToString(); MAGIC_COLOR is a SecureString,
 * so that call returns the type name "System.Security.SecureString".
 */
final class HdCrypt
{
    private const PASSWORD = 'System.Security.SecureString';

    public static function encode(string $xml): string
    {
        $inner = '<HD>' . base64_encode(gzencode($xml)) . '</HD>';
        [$key, $iv, $saltSize] = self::keyMaterial();
        $cipher = openssl_encrypt(random_bytes($saltSize) . $inner, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return '[HD]' . base64_encode($cipher);
    }

    public static function decode(string $text): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', trim($text));
        if (!str_starts_with($text, '[HD]')) {
            return $text; // already plain XML
        }
        [$key, $iv, $saltSize] = self::keyMaterial();
        $plain = openssl_decrypt(base64_decode(substr($text, 4)), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new \RuntimeException('HdCrypt: decryption failed');
        }
        $inner = substr($plain, $saltSize);
        $inner = preg_replace('#^<HD>|</HD>$#', '', $inner);
        $xml = gzdecode(base64_decode($inner));
        if ($xml === false) {
            throw new \RuntimeException('HdCrypt: gzip data invalid');
        }
        return $xml;
    }

    /** @return array{0:string,1:string,2:int} */
    private static function keyMaterial(): array
    {
        $pw = hash('sha256', self::PASSWORD, true);
        $derived = hash_pbkdf2('sha1', $pw, $pw, 1000, 48, true);
        // Salt length: the digits of the first two derived bytes, summed.
        $digits = ord($derived[0]) . ord($derived[1]);
        $saltSize = array_sum(str_split($digits));
        return [substr($derived, 0, 32), substr($derived, 32, 16), $saltSize];
    }
}
