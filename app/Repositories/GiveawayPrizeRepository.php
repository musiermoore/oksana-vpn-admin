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

        $timestamp = now();
        $prizes = collect($rows)
            ->map(fn (array $row): array => [
                'giveaway_id' => $giveaway->id,
                'duration_months' => $row['duration_months'],
                'quantity' => $row['quantity'],
                'title' => $row['title'] ?? null,
                'sort_order' => $row['sort_order'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->all();

        if ($prizes !== []) {
            GiveawayPrize::query()->insert($prizes);
        }
    }
}
