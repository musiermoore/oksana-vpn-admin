<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionExpiryNotification extends Model
{
    protected $fillable = [
        'user_id',
        'user_subscription_id',
        'threshold_key',
        'threshold_hours',
        'subscription_end_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold_hours' => 'integer',
            'subscription_end_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }
}
