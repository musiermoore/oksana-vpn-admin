<?php

declare(strict_types=1);

namespace App\Services\ExternalSubscriptions\Incy;

use RuntimeException;

class IncyCryptLinkEncoder
{
    private const PREFIX = 'incy://crypt1/';

    public function __construct(
        private readonly IncyCryptLinkKeyDeriver $keyDeriver,
    ) {}

    public function encode(string $url, ?string $name = null): string
    {
        $url = trim($url);

        if ($url === '') {
            throw new RuntimeException('INCY URL is empty.');
        }

        $payload = ['url' => $url];
        $name = trim((string) $name);

        if ($name !== '') {
            $payload['n'] = $name;
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'aes-256-gcm',
            $this->keyDeriver->derive(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16,
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Unable to encode INCY crypt1 link.');
        }

        return self::PREFIX.$this->base64UrlEncode($iv.$ciphertext.$tag);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
