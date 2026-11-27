<?php
namespace App\Services;
class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $bytes = random_bytes((int) ceil($length * 5 / 8));
        $bits = '';
        foreach (str_split($bytes) as $byte) $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        $secret = '';
        foreach (str_split(substr($bits, 0, $length * 5), 5) as $chunk) $secret .= self::ALPHABET[bindec($chunk)];
        return $secret;
    }

    private function decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
        $bits = '';
        foreach (str_split($secret) as $char) {
            $position = strpos(self::ALPHABET, $char);
            if ($position === false) throw new \InvalidArgumentException('Invalid TOTP secret.');
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $binary = '';
        foreach (str_split($bits, 8) as $chunk) if (strlen($chunk) === 8) $binary .= chr(bindec($chunk));
        return $binary;
    }

    public function codeAt(string $secret, int $timestamp): string
    {
        $counter = intdiv($timestamp, 30);
        $message = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $message, $this->decode($secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $number = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $code, int $timestamp): ?int
    {
        $current = intdiv($timestamp, 30);
        for ($step = $current - 1; $step <= $current + 1; $step++) {
            if (hash_equals($this->codeAt($secret, $step * 30), $code)) return $step;
        }
        return null;
    }
}