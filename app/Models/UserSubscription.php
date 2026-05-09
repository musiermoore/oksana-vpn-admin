<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'price',
        'renewal_reminder_sent_at',
        'renewal_success_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'renewal_reminder_sent_at' => 'datetime',
            'renewal_success_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
