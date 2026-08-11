<?php

declare(strict_types=1);

namespace App\Services\Giveaways;

use App\Models\GiveawayWinner;
use App\Models\User;
use App\Services\SubscriptionService;

class GiveawayGrantService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function grant(GiveawayWinner $winner, User $user): void
    {
        $this->subscriptions->grantGiveawayMonths(
            user: $user,
            months: $winner->duration_months,
            meta: [
                'giveaway_id' => $winner->giveaway_id,
                'giveaway_winner_id' => $winner->id,
                'prize_slot' => $winner->prize_slot,
            ],
        );
    }
}
