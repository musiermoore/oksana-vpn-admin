<?php

declare(strict_types=1);

namespace App\Services\ExternalSubscriptions;

final class ExternalSubscriptionNameFilter
{
    public static function isValid(string $pattern): bool
    {
        if (trim($pattern) === '') {
            return true;
        }

        $compiled = self::compile($pattern);

        return $compiled !== null && @preg_match($compiled, '') !== false;
    }

    public static function matches(string $name, string $pattern): bool
    {
        if (trim($pattern) === '') {
            return true;
        }

        $compiled = self::compile($pattern);

        return $compiled !== null && preg_match($compiled, $name) === 1;
    }

    private static function compile(string $pattern): ?string
    {
        foreach (['~', '#', '%', '!', ';', ':', '@', "\x1f"] as $delimiter) {
            if (! str_contains($pattern, $delimiter)) {
                return $delimiter.'(?:'.$pattern.')'.$delimiter.'iu';
            }
        }

        return null;
    }
}
