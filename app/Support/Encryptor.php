<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Encryptor
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return $plaintext;
        }

        $key = self::key();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength === false) {
            throw new RuntimeException('Unable to determine IV length for encryption.');
        }

        $iv = random_bytes($ivLength);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);
        if ($ciphertext === false) {
            throw new RuntimeException('Failed to encrypt value.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $payload): string
    {
        if ($payload === '') {
            return $payload;
        }

        $key = self::key();
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new RuntimeException('Encrypted payload is not valid base64.');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength === false || strlen($decoded) <= $ivLength + self::TAG_LENGTH) {
            throw new RuntimeException('Encrypted payload is malformed.');
        }

        $iv = substr($decoded, 0, $ivLength);
        $tag = substr($decoded, $ivLength, self::TAG_LENGTH);
        $ciphertext = substr($decoded, $ivLength + self::TAG_LENGTH);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new RuntimeException('Failed to decrypt value.');
        }

        return $plaintext;
    }

    private static function key(): string
    {
        $config = app_config();
        $rawKey = $config['encryption_key'] ?? null;
        if (!is_string($rawKey) || $rawKey === '') {
            throw new RuntimeException('Encryption key is not configured.');
        }

        if (str_starts_with($rawKey, 'base64:')) {
            $decoded = base64_decode(substr($rawKey, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('Invalid base64 encryption key.');
            }
            $rawKey = $decoded;
        }

        if (strlen($rawKey) === 32) {
            return $rawKey;
        }

        return hash('sha256', $rawKey, true);
    }
}
