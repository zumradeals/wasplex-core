<?php

namespace App\Modules\Identity\Application\Services;

use InvalidArgumentException;

final class TotpService
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        if ($bytes < 1) {
            throw new InvalidArgumentException('La taille du secret doit être positive.');
        }

        return $this->base32Encode(random_bytes($bytes));
    }

    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), 30);

        foreach ([-1, 0, 1] as $window) {
            if (hash_equals($this->code($secret, $counter + $window), $code)) {
                return true;
            }
        }

        return false;
    }

    public function uri(string $secret, string $identifier): string
    {
        $label = rawurlencode('Wasplex:'.$identifier);

        return "otpauth://totp/{$label}?secret={$secret}&issuer=Wasplex&digits=6&period=30";
    }

    private function code(string $secret, int $counter): string
    {
        $binaryCounter = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % 1_000_000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $value): string
    {
        $bits = '';

        foreach (str_split($value) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[intval(str_pad($chunk, 5, '0'), 2)];
        }

        return $encoded;
    }

    private function base32Decode(string $value): string
    {
        $bits = '';

        foreach (str_split(strtoupper($value)) as $character) {
            $position = strpos(self::ALPHABET, $character);

            if ($position === false) {
                throw new InvalidArgumentException('Secret TOTP invalide.');
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(intval($chunk, 2));
            }
        }

        return $decoded;
    }
}
