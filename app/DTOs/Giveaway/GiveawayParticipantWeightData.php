<?php

declare(strict_types=1);

namespace App\DTOs\Giveaway;

use App\DTOs\Data;

class GiveawayParticipantWeightData extends Data
{
    public function __construct(
        public int $baseVotes,
        public int $eligibleReferrals,
        public int $totalWeight,
    ) {}
}
