<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PromoCodeBenefit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeRedemption extends Model
{
    protected $fillable = [
        'user_id', 'payment_transaction_id', 'subscription_transaction_id', 'code', 'benefit', 'value',
        'discount_amount', 'new_users_only', 'redeemed_at', 'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'benefit' => PromoCodeBenefit::class,
            'value' => 'float',
            'discount_amount' => 'float',
            'new_users_only' => 'boolean',
            'redeemed_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function bonusDays(): int
    {
        return $this->benefit === PromoCodeBenefit::BonusDays ? (int) $this->value : 0;
    }
}
