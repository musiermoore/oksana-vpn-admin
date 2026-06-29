<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionCode extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ACTIVATED = 'activated';

    protected $fillable = [
        'buyer_user_id',
        'activated_by_user_id',
        'transaction_id',
        'code',
        'months',
        'days',
        'price',
        'status',
        'activated_at',
        'expires_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'days' => 'integer',
            'price' => 'float',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id')->withTrashed();
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id')->withTrashed();
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isActivated(): bool
    {
        return $this->status === self::STATUS_ACTIVATED;
    }
}
