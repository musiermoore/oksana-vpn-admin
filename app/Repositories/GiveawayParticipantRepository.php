<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Giveaway;
use App\Models\GiveawayParticipant;
use Illuminate\Database\Eloquent\Collection;

class GiveawayParticipantRepository
{
    public function firstOrCreate(int $giveawayId, int $userId): GiveawayParticipant
    {
        return GiveawayParticipant::query()->firstOrCreate(
            [
                'giveaway_id' => $giveawayId,
                'user_id' => $userId,
            ],
            [
                'joined_at' => now(),
            ]
        );
    }

    public function forGiveaway(Giveaway $giveaway): Collection
    {
        return GiveawayParticipant::query()
            ->with('user')
            ->where('giveaway_id', $giveaway->id)
            ->orderBy('joined_at')
            ->get();
    }
}
