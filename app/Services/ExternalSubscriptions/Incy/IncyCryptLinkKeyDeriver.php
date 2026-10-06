<?php

declare(strict_types=1);

namespace App\Services\ExternalSubscriptions\Incy;

use RuntimeException;

class IncyCryptLinkKeyDeriver
{
    public function __construct(
        private readonly IncyKeyMaterialProvider $keyMaterialProvider,
    ) {}

    public function derive(): string
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
}
