<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\GiveawayWinner;

class GiveawayWinnerRepository
{
    public function create(array $attributes): GiveawayWinner
    {
        return GiveawayWinner::query()->create($attributes);
    }

    public function update(GiveawayWinner $winner, array $attributes): GiveawayWinner
    {
        $winner->update($attributes);

        return $winner->refresh();
    }
}
