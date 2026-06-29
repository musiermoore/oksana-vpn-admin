<?php

namespace App\Enums;

enum SubscriptionPurchaseType: string
{
    case PERSONAL = 'PERSONAL';
    case GIFT = 'GIFT';

    public function isPersonal(): bool
    {
        return $this === self::PERSONAL;
    }

    public function isGift(): bool
    {
        return $this === self::GIFT;
    }
}
