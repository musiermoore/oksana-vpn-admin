<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

class PublicAppUrl
{
    public static function isHiddenPublicRequest(Request $request): bool
    {
        return in_array($request->getHost(), config('app.public_hosts', []), true);
    }

    public static function toVisibleUrl(string $url, Request $request): string
    {
        if (! self::isHiddenPublicRequest($request)) {
            return $url;
        }

        $url = preg_replace('#^/public(?=/|$)#', '', $url) ?: '/';

        return $url === '' ? '/' : $url;
    }
}
