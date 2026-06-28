<?php

namespace App\Models;

use App\Enums\ReferralRewardStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = [
        'referrer_id',
        'referral_user_id',
        'qualifying_transaction_id',
        'invitee_bonus_days',
        'referrer_reward_percent',
        'reward_status',
        'reward_scheduled_at',
        'rewarded_at',
    ];

    protected function casts(): array
    {
        return [
            'reward_status' => ReferralRewardStatus::class,
            'reward_scheduled_at' => 'datetime',
            'rewarded_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id')
            ->withTrashed();
    }

    public function referralUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referral_user_id')
            ->withTrashed();
    }

    public function qualifyingTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'qualifying_transaction_id');
    }
}
