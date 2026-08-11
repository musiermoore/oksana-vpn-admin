<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Giveaway;
use App\Models\GiveawayPrize;

class GiveawayPrizeRepository
{
    public function replaceForGiveaway(Giveaway $giveaway, array $rows): void
    {
        $giveaway->prizes()->delete();

        foreach ($rows as $row) {
            GiveawayPrize::query()->create([
                'giveaway_id' => $giveaway->id,
                'duration_months' => $row['duration_months'],
                'quantity' => $row['quantity'],
                'title' => $row['title'] ?? null,
                'sort_order' => $row['sort_order'],
            ]);
        }
    }
}
