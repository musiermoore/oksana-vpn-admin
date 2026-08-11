<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiveawayWinner extends Model
{
    use HasFactory;

    public const PRIZE_STATUS_PENDING = 'pending';
    public const PRIZE_STATUS_GRANTED = 'granted';
    public const PRIZE_STATUS_FAILED = 'failed';

    protected $fillable = [
        'giveaway_id',
        'user_id',
        'giveaway_prize_id',
        'prize_slot',
        'duration_months',
        'weight_at_draw',
        'eligible_referrals_count_at_draw',
        'selected_at',
        'prize_status',
        'prize_granted_at',
        'prize_error',
    ];

    protected function casts(): array
    {
        return [
            'prize_slot' => 'integer',
            'duration_months' => 'integer',
            'weight_at_draw' => 'integer',
            'eligible_referrals_count_at_draw' => 'integer',
            'selected_at' => 'datetime',
            'prize_granted_at' => 'datetime',
        ];
    }

    public function giveaway(): BelongsTo
    {
        return $this->belongsTo(Giveaway::class);
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(GiveawayPrize::class, 'giveaway_prize_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
