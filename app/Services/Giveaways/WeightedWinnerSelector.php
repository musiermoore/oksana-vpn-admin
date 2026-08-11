<?php

declare(strict_types=1);

namespace App\Services\Giveaways;

final class WeightedWinnerSelector
{
    /**
     * @param array<int, array{participant_id:int,user_id:int,weight:int,eligible_referrals_count:int}> $pool
     * @return array{participant_id:int,user_id:int,weight:int,eligible_referrals_count:int}|null
     */
    public function pick(array $pool): ?array
    {
        if ($pool === []) {
            return null;
        }

        $totalWeight = array_sum(array_column($pool, 'weight'));

        if ($totalWeight <= 0) {
            return null;
        }

        $point = random_int(1, $totalWeight);
        $running = 0;

        foreach ($pool as $candidate) {
            $running += $candidate['weight'];

            if ($point <= $running) {
                return $candidate;
            }
        }

        return null;
    }
}
