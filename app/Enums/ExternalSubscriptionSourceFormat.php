<?php

declare(strict_types=1);

namespace App\Enums;

enum ExternalSubscriptionSourceFormat: string
{
    case Direct = 'direct';
    case Incy = 'incy';

    public function isDirect(): bool
    {
        return $this === self::Direct;
    }

    public function isIncy(): bool
    {
        return $this === self::Incy;
    }

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct',
            self::Incy => 'INCY',
        };
    }
}
