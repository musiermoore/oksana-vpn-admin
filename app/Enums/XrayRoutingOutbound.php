<?php

declare(strict_types=1);

namespace App\Enums;

enum XrayRoutingOutbound: string
{
    case Direct = 'direct';
    case Proxy = 'proxy';
    case Blocked = 'blocked';

    public function tag(): string
    {
        return match ($this) {
            self::Direct => 'direct',
            self::Proxy => 'proxy',
            self::Blocked => 'block',
        };
    }
}
