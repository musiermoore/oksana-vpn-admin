<?php

declare(strict_types=1);

namespace App\Enums;

enum PromoCodeBenefit: string
{
    case BalanceDeposit = 'balance_deposit';
    case FixedDiscount = 'fixed_discount';
    case PercentageDiscount = 'percentage_discount';
    case BonusDays = 'bonus_days';
    case BalanceCashback = 'balance_cashback';

    public function label(): string
    {
        return match ($this) {
            self::BalanceDeposit => 'Пополнение для оплаты, ₽',
            self::FixedDiscount => 'Скидка, ₽',
            self::PercentageDiscount => 'Скидка, %',
            self::BonusDays => 'Бонусные дни подписки',
            self::BalanceCashback => 'Возврат на баланс после оплаты, ₽',
        };
    }

    public function discount(float $price, float $value): float
    {
        return match ($this) {
            self::FixedDiscount => min($price, $value),
            self::PercentageDiscount => round($price * $value / 100, 2),
            default => 0.0,
        };
    }
}
