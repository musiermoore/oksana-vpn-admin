<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Config;
use App\Models\VlessConfig;

final class WireGuardConfigPublicId
{
    private const XRAY_PREFIX = 'xray-';

    public static function encode(Config|VlessConfig $config): int|string
    {
        if ($config instanceof Config) {
            return (int) $config->getKey();
        }

        return self::XRAY_PREFIX.(int) $config->getKey();
    }

    /**
     * @return array{source: 'config'|'xray', id: int}|null
     */
    public static function decode(int|string $value): ?array
    {
        if (is_int($value) || ctype_digit((string) $value)) {
            $id = (int) $value;

            return $id > 0
                ? ['source' => 'config', 'id' => $id]
                : null;
        }

        $normalized = trim((string) $value);

        if (! str_starts_with($normalized, self::XRAY_PREFIX)) {
            return null;
        }

        $id = (int) substr($normalized, strlen(self::XRAY_PREFIX));

        return $id > 0
            ? ['source' => 'xray', 'id' => $id]
            : null;
    }
}
