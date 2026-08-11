<?php

declare(strict_types=1);

namespace App\Services\Giveaways;

use App\DTOs\Giveaway\GiveawayParticipantWeightData;
use App\Models\Giveaway;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Collection;

class GiveawayEligibilityService
{
    /**
     * @return Collection<int, Referral>
     */
    public function getEligibleReferrals(Giveaway $giveaway, User $participant): Collection
    {
        return Referral::query()
            ->with('referralUser')
            ->where('referrer_id', $participant->id)
            ->where('referral_user_id', '!=', $participant->id)
            ->whereBetween('created_at', [$giveaway->starts_at, $giveaway->ends_at])
            ->orderBy('created_at')
            ->get()
            ->unique('referral_user_id')
            ->filter(fn (Referral $referral) => $referral->referralUser?->hasActiveSubscription($giveaway->ends_at) === true)
            ->values();
    }

    public function calculateWeight(Giveaway $giveaway, User $participant): GiveawayParticipantWeightData
    {
        $eligibleReferrals = $this->getEligibleReferrals($giveaway, $participant);
        $eligibleReferralsCount = $eligibleReferrals->count();

        return new GiveawayParticipantWeightData(
            baseVotes: 1,
            eligibleReferrals: $eligibleReferralsCount,
            totalWeight: 1 + $eligibleReferralsCount,
        );
    }
}
