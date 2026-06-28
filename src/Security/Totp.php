<?php

declare(strict_types=1);

namespace App\Security;

/**
 * RFC 4226/6238 TOTP helper. Gate A deliberately supports SHA-1/6-digit/30s
 * only because that is the interoperable authenticator-app baseline.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function code(string $secret, ?int $time = null, int $period = 30, int $digits = 6): string
    {
        $step = intdiv($time ?? time(), $period);
        return $this->hotp($secret, $step, $digits);
    }

    public function verify(string $secret, string $code, ?int $lastUsedStep = null, ?int $time = null, int $window = 1, int $period = 30, int $digits = 6): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{' . $digits . '}$/', $code)) {
            return null;
        }

        $nowStep = intdiv($time ?? time(), $period);
        for ($step = $nowStep - $window; $step <= $nowStep + $window; $step++) {
            if ($step < 0 || ($lastUsedStep !== null && $step <= $lastUsedStep)) {
                continue;
            }
            if (hash_equals($this->hotp($secret, $step, $digits), $code)) {
                return $step;
            }
        }
        return null;
    }

    public function provisioningUri(string $issuer, string $account, string $secret): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        return 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    private function hotp(string $secret, int $counter, int $digits): string
    {
        $key = $this->base32Decode($secret);
        $binCounter = pack('N2', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string) ($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        for ($i = 0, $len = strlen($bytes); $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
        $bits = '';
        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $pos = strpos(self::ALPHABET, $secret[$i]);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }
        return $out;
    }
}
