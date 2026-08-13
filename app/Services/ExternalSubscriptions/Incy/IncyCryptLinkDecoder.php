<?php

declare(strict_types=1);

namespace App\Services\ExternalSubscriptions\Incy;

use RuntimeException;

class IncyCryptLinkDecoder
{
    private const PREFIX = 'incy://crypt1/';

    public function __construct(
        private readonly IncyKeyMaterialProvider $keyMaterialProvider,
    ) {}

    /**
     * @return array{url:string,name:?string}
     */
    public function decode(string $link): array
    {
        $link = trim($link);

        if ($link === '') {
            throw new RuntimeException('INCY link is empty.');
        }

        if (! str_starts_with($link, self::PREFIX)) {
            throw new RuntimeException('Invalid INCY link: expected incy://crypt1/.');
        }

        $payload = rtrim(substr($link, strlen(self::PREFIX)), '/');

        if ($payload === '') {
            throw new RuntimeException('INCY payload is empty.');
        }

        $wire = $this->base64UrlDecode($payload);

        if (strlen($wire) < 29) {
            throw new RuntimeException('INCY payload is too short.');
        }

        $iv = substr($wire, 0, 12);
        $tag = substr($wire, -16);
        $ciphertext = substr($wire, 12, -16);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->deriveKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('INCY crypt1 authentication failed.');
        }

        try {
            $data = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('INCY plaintext contains invalid JSON.', previous: $exception);
        }

        if (! is_array($data) || ! isset($data['url']) || ! is_string($data['url']) || trim($data['url']) === '') {
            throw new RuntimeException('INCY payload does not contain a valid url.');
        }

        return [
            'url' => trim($data['url']),
            'name' => isset($data['n']) && is_string($data['n']) && trim($data['n']) !== ''
                ? trim($data['n'])
                : null,
        ];
    }

    private function deriveKey(): string
    {
        $material = $this->keyMaterialProvider->get();
        $salt = (string) config('incy.keymat.salt', 'incydeepcrypt1v2026.06');
        $key = hash('sha256', $salt.$material['km_a'].$material['km_b'], true);
        $expectedFingerprint = (string) config(
            'incy.keymat.expected_key_fingerprint',
            'b6bf708471cc90043232967660aade86a50b4e57929db2e53c5fa34db624c08c'
        );

        if (! hash_equals($expectedFingerprint, hash('sha256', $key))) {
            throw new RuntimeException('INCY K1 fingerprint mismatch.');
        }

        return $key;
    }

    private function base64UrlDecode(string $value): string
    {
        $normalized = strtr($value, '-_', '+/');
        $normalized .= str_repeat('=', (4 - strlen($normalized) % 4) % 4);
        $decoded = base64_decode($normalized, true);

        if ($decoded === false) {
            throw new RuntimeException('INCY payload is not valid base64url.');
        }

        return $decoded;
    }
}
