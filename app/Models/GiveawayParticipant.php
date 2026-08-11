<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiveawayParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'giveaway_id',
        'user_id',
        'joined_at',
        'weight_at_draw',
        'eligible_referrals_count_at_draw',
        'snapshot_taken_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'weight_at_draw' => 'integer',
            'eligible_referrals_count_at_draw' => 'integer',
            'snapshot_taken_at' => 'datetime',
        ];
    }

    public function giveaway(): BelongsTo
    {
        return $this->belongsTo(Giveaway::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
